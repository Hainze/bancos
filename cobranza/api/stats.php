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

$pdo->exec("CREATE TABLE IF NOT EXISTS cobranza_lotes (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    archivo_nombre VARCHAR(255) NOT NULL,
    total_clientes INT DEFAULT 0,
    total_importe  DECIMAL(15,2) DEFAULT 0,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS cobranza_cartera (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    lote_id  INT NOT NULL,
    sistema  TINYINT(1) NOT NULL,
    codigo   VARCHAR(50) NOT NULL,
    nombre   VARCHAR(255) NOT NULL,
    d30      DECIMAL(15,2) DEFAULT 0,
    d60      DECIMAL(15,2) DEFAULT 0,
    d90      DECIMAL(15,2) DEFAULT 0,
    d120     DECIMAL(15,2) DEFAULT 0,
    d120plus DECIMAL(15,2) DEFAULT 0,
    total    DECIMAL(15,2) DEFAULT 0,
    INDEX idx_lote (lote_id),
    INDEX idx_sistema_codigo (sistema, codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS cobranza_padrones (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sistema     TINYINT(1) NOT NULL,
    codigo      VARCHAR(50) NOT NULL,
    observacion VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sistema_codigo (sistema, codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

try {

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
        $sistemas  = ['sis1_cant' => 0, 'sis1_monto' => 0, 'sis2_cant' => 0, 'sis2_monto' => 0, 'sis3_cant' => 0, 'sis3_monto' => 0];
        foreach ($sisRows as $s) {
            if ($s['sistema'] == 1) {
                $sistemas['sis1_cant']  = (int)$s['cant'];
                $sistemas['sis1_monto'] = (float)$s['monto'];
            } elseif ($s['sistema'] == 2) {
                $sistemas['sis2_cant']  = (int)$s['cant'];
                $sistemas['sis2_monto'] = (float)$s['monto'];
            } else {
                $sistemas['sis3_cant']  = (int)$s['cant'];
                $sistemas['sis3_monto'] = (float)$s['monto'];
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

} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo json_encode([
            'error'          => 'Tablas no creadas. Corré cobranza_install.php primero.',
            'aging'          => [], 'sistemas' => [], 'rangos' => [],
            'lotes'          => [], 'total_deuda' => 0, 'total_clientes' => 0,
        ]);
    } else {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
