<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/auth/check_api.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

try { $pdo = getDB(); } catch (Exception $e) {
    echo json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]); exit;
}

switch ($action) {

    // ── Resumen consolidado ────────────────────────────────────────────────
    case 'resumen':
        $desde = $_GET['desde'] ?? null;
        $hasta = $_GET['hasta'] ?? null;

        $result = [];

        // ════════════════════════════════════════════════════════════════════
        // 1. GESTIÓN BANCARIA
        // ════════════════════════════════════════════════════════════════════
        try {
            $wBancos  = ($desde && $hasta) ? 'WHERE fecha BETWEEN ? AND ?' : '';
            $pBancos  = ($desde && $hasta) ? [$desde, $hasta] : [];

            $totBancos = $pdo->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN tipo='ingreso' THEN importe ELSE 0 END), 0) AS ingresos,
                    COALESCE(SUM(CASE WHEN tipo='gasto'   THEN importe ELSE 0 END), 0) AS gastos,
                    COALESCE(SUM(CASE WHEN tipo='ingreso' THEN importe ELSE -importe END), 0) AS balance,
                    COUNT(*) AS cant_movimientos
                FROM movimientos $wBancos
            ");
            $totBancos->execute($pBancos);
            $tb = $totBancos->fetch();

            // Evolución mensual bancos
            $mensualBancos = $pdo->prepare("
                SELECT
                    DATE_FORMAT(fecha, '%Y-%m') AS mes,
                    SUM(CASE WHEN tipo='ingreso' THEN importe ELSE 0 END) AS ingresos,
                    SUM(CASE WHEN tipo='gasto'   THEN importe ELSE 0 END) AS gastos
                FROM movimientos $wBancos
                GROUP BY mes ORDER BY mes
            ");
            $mensualBancos->execute($pBancos);
            $evBancos = $mensualBancos->fetchAll();

            // Top categorías de gastos
            $topCats = $pdo->prepare("
                SELECT categoria_nombre AS nombre,
                       SUM(importe) AS total
                FROM movimientos
                WHERE tipo='gasto' AND categoria_nombre IS NOT NULL
                " . ($wBancos ? str_replace('WHERE','AND',$wBancos) : '') . "
                GROUP BY categoria_nombre
                ORDER BY total DESC
                LIMIT 6
            ");
            $topCats->execute($pBancos);
            $categorias = $topCats->fetchAll();

            $result['bancos'] = [
                'ingresos'         => (float)$tb['ingresos'],
                'gastos'           => (float)$tb['gastos'],
                'balance'          => (float)$tb['balance'],
                'cant_movimientos' => (int)$tb['cant_movimientos'],
                'evolucion'        => $evBancos,
                'top_categorias'   => $categorias,
            ];
        } catch (PDOException $e) {
            $result['bancos'] = ['error' => $e->getMessage()];
        }

        // ════════════════════════════════════════════════════════════════════
        // 2. FISERV
        // ════════════════════════════════════════════════════════════════════
        try {
            $wFiserv  = ($desde && $hasta) ? 'WHERE l.fecha_pago BETWEEN ? AND ?' : '';
            $pFiserv  = ($desde && $hasta) ? [$desde, $hasta] : [];

            $totFiserv = $pdo->prepare("
                SELECT
                    COALESCE(SUM(l.ventas_contado), 0)   AS ventas,
                    COALESCE(SUM(l.total_descuentos), 0) AS descuentos,
                    COALESCE(SUM(l.acreditado), 0)       AS acreditado,
                    COUNT(*) AS cant_liquidaciones
                FROM fiserv_liquidaciones l $wFiserv
            ");
            $totFiserv->execute($pFiserv);
            $tf = $totFiserv->fetch();

            $mensualFiserv = $pdo->prepare("
                SELECT
                    DATE_FORMAT(l.fecha_pago, '%Y-%m') AS mes,
                    SUM(l.ventas_contado) AS ventas,
                    SUM(l.acreditado)     AS acreditado,
                    SUM(l.total_descuentos) AS descuentos
                FROM fiserv_liquidaciones l $wFiserv
                GROUP BY mes ORDER BY mes
            ");
            $mensualFiserv->execute($pFiserv);
            $evFiserv = $mensualFiserv->fetchAll();

            $result['fiserv'] = [
                'ventas'            => (float)$tf['ventas'],
                'descuentos'        => (float)$tf['descuentos'],
                'acreditado'        => (float)$tf['acreditado'],
                'cant_liquidaciones'=> (int)$tf['cant_liquidaciones'],
                'evolucion'         => $evFiserv,
            ];
        } catch (PDOException $e) {
            $result['fiserv'] = ['error' => $e->getMessage()];
        }

        // ════════════════════════════════════════════════════════════════════
        // 3. COBRANZA (siempre el último lote — es una foto, no un período)
        // ════════════════════════════════════════════════════════════════════
        try {
            $ultimoLote = $pdo->query(
                "SELECT id, archivo_nombre, created_at FROM cobranza_lotes ORDER BY created_at DESC LIMIT 1"
            )->fetch();

            if ($ultimoLote) {
                $lid = $ultimoLote['id'];

                // Totales aging
                $aging = $pdo->prepare("
                    SELECT
                        SUM(d30)      AS d30,
                        SUM(d60)      AS d60,
                        SUM(d90)      AS d90,
                        SUM(d120)     AS d120,
                        SUM(d120plus) AS d120plus,
                        SUM(CASE WHEN total > 0 THEN total ELSE 0 END) AS total_positivo,
                        COUNT(CASE WHEN total > 0 THEN 1 END)          AS cant_deudores
                    FROM cobranza_cartera WHERE lote_id = ?
                ");
                $aging->execute([$lid]);
                $ag = $aging->fetch();

                // Sistema 1 vs 2
                $sis = $pdo->prepare("
                    SELECT sistema,
                           COUNT(*) AS cant,
                           SUM(CASE WHEN total > 0 THEN total ELSE 0 END) AS monto
                    FROM cobranza_cartera
                    WHERE lote_id = ? AND total > 0
                    GROUP BY sistema
                ");
                $sis->execute([$lid]);
                $sisRows = $sis->fetchAll();
                $s1 = ['cant' => 0, 'monto' => 0];
                $s2 = ['cant' => 0, 'monto' => 0];
                foreach ($sisRows as $s) {
                    if ($s['sistema'] == 1) { $s1['cant'] = (int)$s['cant']; $s1['monto'] = (float)$s['monto']; }
                    else                   { $s2['cant'] = (int)$s['cant']; $s2['monto'] = (float)$s['monto']; }
                }

                // Prioridades (calculadas igual que en procesar.php)
                $filas = $pdo->prepare("
                    SELECT d30, d60, d90, d120, d120plus, total
                    FROM cobranza_cartera WHERE lote_id = ?
                ");
                $filas->execute([$lid]);
                $prioridades = ['URGENCIA' => 0, 'LLAMAR' => 0, 'COBRAR AL FINAL' => 0, 'SIN ACCIÓN' => 0];
                foreach ($filas->fetchAll() as $f) {
                    $d120p = (float)$f['d120plus'];
                    $d120  = (float)$f['d120'];
                    $d90   = (float)$f['d90'];
                    $tot   = (float)$f['total'];
                    if ($tot <= 0)                                              $pri = 'SIN ACCIÓN';
                    elseif ($d120p >= 50000 || $tot >= 1000000)                $pri = 'URGENCIA';
                    elseif ($d120p > 0 && $tot >= 100000)                      $pri = 'URGENCIA';
                    elseif ($d120 >= 10000 || $d90 >= 10000 || $tot >= 100000) $pri = 'LLAMAR';
                    elseif ($d120 > 0 || $d90 > 0)                             $pri = 'LLAMAR';
                    else                                                        $pri = 'COBRAR AL FINAL';
                    $prioridades[$pri]++;
                }

                $result['cobranza'] = [
                    'lote_nombre'  => $ultimoLote['archivo_nombre'],
                    'lote_fecha'   => $ultimoLote['created_at'],
                    'total_deuda'  => (float)$ag['total_positivo'],
                    'cant_deudores'=> (int)$ag['cant_deudores'],
                    'aging' => [
                        'd30'      => (float)$ag['d30'],
                        'd60'      => (float)$ag['d60'],
                        'd90'      => (float)$ag['d90'],
                        'd120'     => (float)$ag['d120'],
                        'd120plus' => (float)$ag['d120plus'],
                    ],
                    'sistema1'   => $s1,
                    'sistema2'   => $s2,
                    'prioridades'=> $prioridades,
                ];
            } else {
                $result['cobranza'] = null;
            }
        } catch (PDOException $e) {
            $result['cobranza'] = ['error' => $e->getMessage()];
        }

        echo json_encode($result);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}
