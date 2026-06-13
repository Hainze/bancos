<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/auth/check_api.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

try { $pdo = getDB(); } catch (Exception $e) {
    echo json_encode(['error' => 'DB: ' . $e->getMessage()]); exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS pasos_guias (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    titulo     VARCHAR(200) NOT NULL,
    icono      VARCHAR(20)  DEFAULT '📋',
    color      VARCHAR(20)  DEFAULT 'blue',
    pasos      JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

switch ($action) {

    case 'listar':
        $rows = $pdo->query("SELECT * FROM pasos_guias ORDER BY titulo ASC")->fetchAll();
        echo json_encode(['data' => $rows]);
        break;

    case 'guardar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        $body   = json_decode(file_get_contents('php://input'), true);
        $id     = (int)($body['id']     ?? 0);
        $titulo = trim($body['titulo']  ?? '');
        $icono  = trim($body['icono']   ?? '📋');
        $color  = trim($body['color']   ?? 'blue');
        $pasos  = $body['pasos']        ?? [];

        if (!$titulo) { echo json_encode(['error' => 'El título es requerido']); break; }
        if (!is_array($pasos)) $pasos = [];

        $pasosJson = json_encode(array_values($pasos), JSON_UNESCAPED_UNICODE);

        if ($id) {
            $pdo->prepare("UPDATE pasos_guias SET titulo=?, icono=?, color=?, pasos=?, updated_at=NOW() WHERE id=?")
                ->execute([$titulo, $icono, $color, $pasosJson, $id]);
            echo json_encode(['success' => true, 'id' => $id]);
        } else {
            $pdo->prepare("INSERT INTO pasos_guias (titulo, icono, color, pasos) VALUES (?,?,?,?)")
                ->execute([$titulo, $icono, $color, $pasosJson]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        }
        break;

    case 'eliminar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); break; }
        $pdo->prepare("DELETE FROM pasos_guias WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}
