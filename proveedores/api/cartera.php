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

// Auto-crear tablas
$pdo->exec("CREATE TABLE IF NOT EXISTS prov_lotes (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    archivo_nombre    VARCHAR(255) NOT NULL,
    total_proveedores INT NOT NULL DEFAULT 0,
    total_deuda       DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_favor       DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS prov_cartera (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    lote_id    INT NOT NULL,
    sistema    TINYINT NOT NULL DEFAULT 1,
    codigo     VARCHAR(50) DEFAULT '',
    nombre     VARCHAR(255) NOT NULL,
    saldo      DECIMAL(15,2) NOT NULL DEFAULT 0,
    prioridad  VARCHAR(20) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lote (lote_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

try {

switch ($action) {

    case 'guardar_lote':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'POST requerido']); break;
        }
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body || empty($body['filas'])) {
            echo json_encode(['error' => 'Datos inválidos']); break;
        }

        $filas   = $body['filas'];
        $archivo = substr($body['archivo_nombre'] ?? 'Sin nombre', 0, 255);

        $totalProveedores = 0;
        $totalDeuda       = 0.0;
        $totalFavor       = 0.0;
        foreach ($filas as $f) {
            $s = (float)($f['saldo'] ?? 0);
            if ($s != 0) $totalProveedores++;
            if ($s < 0)  $totalDeuda  += abs($s);
            if ($s > 0)  $totalFavor  += $s;
        }

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare("INSERT INTO prov_lotes (archivo_nombre, total_proveedores, total_deuda, total_favor) VALUES (?, ?, ?, ?)");
            $ins->execute([$archivo, $totalProveedores, $totalDeuda, $totalFavor]);
            $loteId = $pdo->lastInsertId();

            $insRow = $pdo->prepare("INSERT INTO prov_cartera (lote_id, sistema, codigo, nombre, saldo, prioridad) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($filas as $f) {
                $insRow->execute([
                    $loteId,
                    (int)($f['sistema']   ?? 1),
                    substr((string)($f['codigo'] ?? ''), 0, 50),
                    substr((string)($f['nombre'] ?? ''), 0, 255),
                    (float)($f['saldo']   ?? 0),
                    substr((string)($f['prioridad'] ?? ''), 0, 20),
                ]);
            }
            $pdo->commit();
            echo json_encode(['lote_id' => (int)$loteId, 'total' => count($filas)]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'eliminar_lote':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'POST requerido']); break;
        }
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); break; }
        $pdo->prepare("DELETE FROM prov_lotes WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    case 'eliminar_todo':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        $pdo->exec("DELETE FROM prov_lotes");
        $pdo->exec("DELETE FROM prov_padrones");
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
