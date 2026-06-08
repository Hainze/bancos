<?php
require_once dirname(__DIR__, 2) . '/auth/check_api.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo    = getDB();

$pdo->exec("CREATE TABLE IF NOT EXISTS echeqs_lotes (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    archivo_nombre   VARCHAR(255) NOT NULL,
    total_echeqs     INT DEFAULT 0,
    total_monto      DECIMAL(18,2) DEFAULT 0,
    cant_vencidos    INT DEFAULT 0,
    cant_endosar_ya  INT DEFAULT 0,
    cant_proximos    INT DEFAULT 0,
    cant_pendientes  INT DEFAULT 0,
    cant_endosados   INT DEFAULT 0,
    cant_depositados INT DEFAULT 0,
    cant_rechazados  INT DEFAULT 0,
    cant_sin_fecha   INT DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

try {
    switch ($action) {

        case 'guardar_lote':
            $d = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("INSERT INTO echeqs_lotes
                (archivo_nombre, total_echeqs, total_monto, cant_vencidos, cant_endosar_ya,
                 cant_proximos, cant_pendientes, cant_endosados, cant_depositados, cant_rechazados, cant_sin_fecha)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $d['archivo_nombre'] ?? '',
                (int)($d['total_echeqs']    ?? 0),
                (float)($d['total_monto']   ?? 0),
                (int)($d['cant_vencidos']   ?? 0),
                (int)($d['cant_endosar_ya'] ?? 0),
                (int)($d['cant_proximos']   ?? 0),
                (int)($d['cant_pendientes'] ?? 0),
                (int)($d['cant_endosados']  ?? 0),
                (int)($d['cant_depositados']?? 0),
                (int)($d['cant_rechazados'] ?? 0),
                (int)($d['cant_sin_fecha']  ?? 0),
            ]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
            break;

        case 'dashboard':
            $ultimo = $pdo->query("SELECT * FROM echeqs_lotes ORDER BY created_at DESC LIMIT 1")->fetch();
            $lotes  = $pdo->query("SELECT * FROM echeqs_lotes ORDER BY created_at DESC LIMIT 15")->fetchAll();
            echo json_encode(['ultimo' => $ultimo ?: null, 'lotes' => $lotes]);
            break;

        case 'eliminar_lote':
            $d = json_decode(file_get_contents('php://input'), true);
            $pdo->prepare("DELETE FROM echeqs_lotes WHERE id=?")->execute([(int)($d['id'] ?? 0)]);
            echo json_encode(['success' => true]);
            break;

        case 'eliminar_todo':
            $pdo->exec("DELETE FROM echeqs_lotes");
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['error' => 'Acción no válida: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
