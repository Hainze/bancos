<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/auth/check_api.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

try {
    $pdo = getDB();
} catch (Exception $e) {
    echo json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]);
    exit;
}

$empty = [
    'deuda'           => 0,
    'favor'           => 0,
    'neto'            => 0,
    'total_proveedores' => 0,
    'sistemas'        => [],
    'prioridades'     => [],
    'rangos'          => [],
    'lotes'           => [],
];

try {

switch ($action) {

    case 'dashboard':
        $ultimo = $pdo->query("SELECT id FROM prov_lotes ORDER BY created_at DESC LIMIT 1")->fetch();
        if (!$ultimo) { echo json_encode($empty); break; }
        $lote_id = $ultimo['id'];

        // Totales generales
        $tot = $pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN saldo < 0 THEN ABS(saldo) ELSE 0 END), 0) AS total_deuda,
                COALESCE(SUM(CASE WHEN saldo > 0 THEN saldo ELSE 0 END), 0)       AS total_favor,
                COUNT(CASE WHEN saldo != 0 THEN 1 END)                             AS total_prov
            FROM prov_cartera WHERE lote_id = ?
        ");
        $tot->execute([$lote_id]);
        $totRow = $tot->fetch();

        // Sistema 1 vs 2
        $sis = $pdo->prepare("
            SELECT sistema,
                COALESCE(SUM(CASE WHEN saldo < 0 THEN ABS(saldo) ELSE 0 END), 0) AS deuda,
                COALESCE(SUM(CASE WHEN saldo > 0 THEN saldo ELSE 0 END), 0)       AS favor,
                COUNT(CASE WHEN saldo != 0 THEN 1 END)                             AS cant
            FROM prov_cartera WHERE lote_id = ?
            GROUP BY sistema
        ");
        $sis->execute([$lote_id]);
        $sisRows  = $sis->fetchAll();
        $sistemas = [
            'sis1_deuda' => 0, 'sis1_favor' => 0, 'sis1_cant' => 0,
            'sis2_deuda' => 0, 'sis2_favor' => 0, 'sis2_cant' => 0,
        ];
        foreach ($sisRows as $s) {
            $k = 'sis' . $s['sistema'] . '_';
            $sistemas[$k . 'deuda'] = (float)$s['deuda'];
            $sistemas[$k . 'favor'] = (float)$s['favor'];
            $sistemas[$k . 'cant']  = (int)$s['cant'];
        }

        // Distribución por prioridad
        $pri = $pdo->prepare("
            SELECT prioridad, COUNT(*) AS cant,
                COALESCE(SUM(ABS(saldo)), 0) AS monto
            FROM prov_cartera WHERE lote_id = ?
            GROUP BY prioridad
        ");
        $pri->execute([$lote_id]);
        $prioridades = [];
        foreach ($pri->fetchAll() as $p) {
            $prioridades[$p['prioridad']] = ['cant' => (int)$p['cant'], 'monto' => (float)$p['monto']];
        }

        // Rangos de saldo (por valor absoluto, saldo != 0)
        $rangoDefs = [
            ['label' => '$0 – $50K',     'min' => 0.01,   'max' => 50000],
            ['label' => '$50K – $100K',  'min' => 50000,  'max' => 100000],
            ['label' => '$100K – $500K', 'min' => 100000, 'max' => 500000],
            ['label' => '$500K – $1M',   'min' => 500000, 'max' => 1000000],
            ['label' => 'Más de $1M',    'min' => 1000000,'max' => 999999999],
        ];
        $rangos = [];
        foreach ($rangoDefs as $rd) {
            $q = $pdo->prepare("
                SELECT COUNT(*) AS cant, COALESCE(SUM(ABS(saldo)), 0) AS monto
                FROM prov_cartera
                WHERE lote_id = ? AND ABS(saldo) >= ? AND ABS(saldo) < ?
            ");
            $q->execute([$lote_id, $rd['min'], $rd['max']]);
            $row = $q->fetch();
            $rangos[] = ['label' => $rd['label'], 'cant' => (int)$row['cant'], 'monto' => (float)$row['monto']];
        }

        // Últimos lotes
        $lotes = $pdo->query("
            SELECT id, archivo_nombre, total_proveedores, total_deuda, total_favor, created_at
            FROM prov_lotes ORDER BY created_at DESC LIMIT 10
        ")->fetchAll();

        echo json_encode([
            'deuda'             => (float)$totRow['total_deuda'],
            'favor'             => (float)$totRow['total_favor'],
            'neto'              => (float)$totRow['total_favor'] - (float)$totRow['total_deuda'],
            'total_proveedores' => (int)$totRow['total_prov'],
            'sistemas'          => $sistemas,
            'prioridades'       => $prioridades,
            'rangos'            => $rangos,
            'lotes'             => $lotes,
        ]);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}

} catch (PDOException $e) {
    echo json_encode($empty + ['error' => $e->getMessage()]);
}
