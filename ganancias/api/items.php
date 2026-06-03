<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/auth/check_api.php';

header('Content-Type: application/json; charset=utf-8');

$action    = $_GET['action'] ?? '';
$clienteId = (int)($_SESSION['fact_cliente_id'] ?? 0);

try { $pdo = getDB(); } catch (Exception $e) {
    echo json_encode(['error' => 'DB: ' . $e->getMessage()]); exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS ganancias_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id   INT NOT NULL,
    anio         SMALLINT NOT NULL,
    categoria    VARCHAR(50) NOT NULL,
    descripcion  VARCHAR(255) DEFAULT '',
    campo2       VARCHAR(255) DEFAULT '',
    campo3       VARCHAR(255) DEFAULT '',
    valor_origen DECIMAL(15,2) DEFAULT 0,
    amort_acum   DECIMAL(15,2) DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cli_anio (cliente_id, anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

switch ($action) {

    case 'listar':
        if (!$clienteId) { echo json_encode(['error' => 'Sin cliente']); break; }
        $anio = (int)($_GET['anio'] ?? date('Y'));
        $st = $pdo->prepare("SELECT * FROM ganancias_items WHERE cliente_id=? AND anio=? ORDER BY categoria, id");
        $st->execute([$clienteId, $anio]);
        echo json_encode(['data' => $st->fetchAll()]);
        break;

    case 'guardar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        if (!$clienteId) { echo json_encode(['error' => 'Sin cliente']); break; }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) { echo json_encode(['error' => 'Body inválido']); break; }

        $id          = (int)($body['id'] ?? 0);
        $anio        = (int)($body['anio'] ?? date('Y'));
        $categoria   = substr($body['categoria'] ?? '', 0, 50);
        $descripcion = substr($body['descripcion'] ?? '', 0, 255);
        $campo2      = substr($body['campo2'] ?? '', 0, 255);
        $campo3      = substr($body['campo3'] ?? '', 0, 255);
        $valorOrigen = (float)($body['valor_origen'] ?? 0);
        $amortAcum   = (float)($body['amort_acum'] ?? 0);

        if (!$anio || !$categoria) { echo json_encode(['error' => 'Datos incompletos']); break; }

        if ($id) {
            $pdo->prepare("UPDATE ganancias_items SET descripcion=?,campo2=?,campo3=?,valor_origen=?,amort_acum=?,updated_at=NOW() WHERE id=? AND cliente_id=?")
                ->execute([$descripcion, $campo2, $campo3, $valorOrigen, $amortAcum, $id, $clienteId]);
            echo json_encode(['success' => true, 'id' => $id]);
        } else {
            $pdo->prepare("INSERT INTO ganancias_items (cliente_id,anio,categoria,descripcion,campo2,campo3,valor_origen,amort_acum) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$clienteId, $anio, $categoria, $descripcion, $campo2, $campo3, $valorOrigen, $amortAcum]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        }
        break;

    case 'eliminar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); break; }
        $pdo->prepare("DELETE FROM ganancias_items WHERE id=? AND cliente_id=?")->execute([$id, $clienteId]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}
