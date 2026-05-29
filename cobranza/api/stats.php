<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/auth/check_api.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$pdo    = getDB();

switch ($action) {

    case 'dashboard':
        // Último lote cargado
        $ultimo = $pdo->query("SELECT id FROM cobranza_lotes ORDER BY created_at DESC LIMIT 1")->fetch();
        if (!$ultimo) {
            echo json_encode([
                'aging'          => [],
                'sistemas'       => [],
                'rangos'         => [],
                'lotes'          => [],
                'total_deuda'    => 0,
                'total_clientes' => 0,
            ]);
            break;
        }
        $lote_id = $ultimo['id'];

        // Aging — totales por columna (solo positivos cuentan para urgencia, pero el total real incluye negativos)
        $aging = $pdo->prepare("
            SELECT
                SUM(CASE WHEN d30   > 0 THEN 1 ELSE 0 END) AS d30_cant,
                SUM(CASE WHEN d60   > 0 THEN 1 ELSE 0 END) AS d60_cant,
                SUM(CASE WHEN d90   > 0 THEN 1 ELSE 0 END) AS d90_cant,
                SUM(CASE WHEN d120  > 0 THEN 1 ELSE 0 END) AS d120_cant,
                SUM(CASE WHEN d120plus > 0 THEN 1 ELSE 0 END) AS d120plus_cant,
                SUM(d30)      AS d30_monto,
                SUM(d60)      AS d60_monto,
                SUM(d90)      AS d90_monto,
                SUM(d120)     AS d120_monto,
                SUM(d120plus) AS d120plus_monto
            FROM cobranza_cartera
            WHERE lote_id = ?
        ");
        $aging->execute([$lote_id]);
        $ag = $aging->fetch();

        $agingOut = [
            'd30'      => ['cant' => (int)$ag['d30_cant'],      'monto' => (float)$ag['d30_monto']],
            'd60'      => ['cant' => (int)$ag['d60_cant'],      'monto' => (float)$ag['d60_monto']],
            'd90'      => ['cant' => (int)$ag['d90_cant'],      'monto' => (float)$ag['d90_monto']],
            'd120'     => ['cant' => (int)$ag['d120_cant'],     'monto' => (float)$ag['d120_monto']],
            'd120plus' => ['cant' => (int)$ag['d120plus_cant'], 'monto' => (float)$ag['d120plus_monto']],
        ];

        // Sistema 1 vs 2 (solo clientes con total > 0)
        $sis = $pdo->prepare("
            SELECT
                sistema,
                COUNT(*) AS cant,
                SUM(total) AS monto
            FROM cobranza_cartera
            WHERE lote_id = ? AND total > 0
            GROUP BY sistema
        ");
        $sis->execute([$lote_id]);
        $sisRows   = $sis->fetchAll();
        $sistemas  = ['sis1_cant' => 0, 'sis1_monto' => 0, 'sis2_cant' => 0, 'sis2_monto' => 0];
        foreach ($sisRows as $s) {
            if ($s['sistema'] == 1) {
                $sistemas['sis1_cant']  = (int)$s['cant'];
                $sistemas['sis1_monto'] = (float)$s['monto'];
            } else {
                $sistemas['sis2_cant']  = (int)$s['cant'];
                $sistemas['sis2_monto'] = (float)$s['monto'];
            }
        }

        // Rangos de deuda
        $rangoDefs = [
            ['label' => '$0 – $100K',         'min' => 0.01,   'max' => 100000],
            ['label' => '$100K – $500K',       'min' => 100000, 'max' => 500000],
            ['label' => '$500K – $1M',         'min' => 500000, 'max' => 1000000],
            ['label' => '$1M – $5M',           'min' => 1000000,'max' => 5000000],
            ['label' => 'Más de $5M',          'min' => 5000000,'max' => 999999999],
        ];
        $rangos = [];
        foreach ($rangoDefs as $rd) {
            $q = $pdo->prepare("
                SELECT COUNT(*) AS cant, COALESCE(SUM(total),0) AS monto
                FROM cobranza_cartera
                WHERE lote_id = ? AND total >= ? AND total < ?
            ");
            $q->execute([$lote_id, $rd['min'], $rd['max']]);
            $row = $q->fetch();
            $rangos[] = [
                'label' => $rd['label'],
                'cant'  => (int)$row['cant'],
                'monto' => (float)$row['monto'],
            ];
        }

        // Totales generales
        $tot = $pdo->prepare("
            SELECT COUNT(*) AS cant, COALESCE(SUM(total),0) AS monto
            FROM cobranza_cartera
            WHERE lote_id = ? AND total > 0
        ");
        $tot->execute([$lote_id]);
        $totRow = $tot->fetch();

        // Últimos lotes
        $lotes = $pdo->query("
            SELECT id, archivo_nombre, total_clientes, total_importe, created_at
            FROM cobranza_lotes
            ORDER BY created_at DESC
            LIMIT 10
        ")->fetchAll();

        echo json_encode([
            'aging'          => $agingOut,
            'sistemas'       => $sistemas,
            'rangos'         => $rangos,
            'lotes'          => $lotes,
            'total_deuda'    => (float)$totRow['monto'],
            'total_clientes' => (int)$totRow['cant'],
        ]);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}
