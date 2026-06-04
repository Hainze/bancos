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

$pdo->exec("CREATE TABLE IF NOT EXISTS prov_padrones (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sistema     TINYINT NOT NULL,
    codigo      VARCHAR(50) NOT NULL,
    observacion VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sistema_codigo (sistema, codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

try {

switch ($action) {

    case 'listar':
        $sistema = $_GET['sistema'] ?? '';
        $q       = trim($_GET['q'] ?? '');
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $limit   = 50;
        $offset  = ($page - 1) * $limit;

        $where  = [];
        $params = [];
        if ($sistema !== '') { $where[] = 'sistema = ?'; $params[] = (int)$sistema; }
        if ($q !== '')       { $where[] = '(codigo LIKE ? OR observacion LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        $wStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM prov_padrones $wStr");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $sel = $pdo->prepare("SELECT * FROM prov_padrones $wStr ORDER BY sistema, codigo LIMIT $limit OFFSET $offset");
        $sel->execute($params);
        echo json_encode(['data' => $sel->fetchAll(), 'total' => $total, 'limit' => $limit]);
        break;

    case 'listar_todos':
        $rows = $pdo->query("SELECT sistema, codigo, observacion FROM prov_padrones")->fetchAll();
        echo json_encode(['data' => $rows]);
        break;

    case 'guardar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        $body = json_decode(file_get_contents('php://input'), true);

        $id      = (int)($body['id'] ?? 0);
        $sistema = (int)($body['sistema'] ?? 1);
        $codigo  = substr(trim($body['codigo'] ?? ''), 0, 50);
        $obs     = substr(trim($body['observacion'] ?? ''), 0, 255);

        if (!$codigo || !$obs) { echo json_encode(['error' => 'Código y observación son requeridos']); break; }
        if (!in_array($sistema, [1, 2])) { echo json_encode(['error' => 'Sistema inválido']); break; }

        if ($id) {
            $pdo->prepare("UPDATE prov_padrones SET sistema=?, codigo=?, observacion=? WHERE id=?")
                ->execute([$sistema, $codigo, $obs, $id]);
            echo json_encode(['msg' => 'Actualizado correctamente']);
        } else {
            $pdo->prepare("INSERT INTO prov_padrones (sistema, codigo, observacion) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE observacion = VALUES(observacion)")
                ->execute([$sistema, $codigo, $obs]);
            echo json_encode(['msg' => 'Guardado correctamente', 'id' => (int)$pdo->lastInsertId()]);
        }
        break;

    case 'eliminar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); break; }
        $pdo->prepare("DELETE FROM prov_padrones WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    case 'importar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        $body      = json_decode(file_get_contents('php://input'), true);
        $registros = $body['registros'] ?? [];
        if (empty($registros)) { echo json_encode(['error' => 'Sin registros']); break; }

        $stmt = $pdo->prepare("INSERT INTO prov_padrones (sistema, codigo, observacion) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE observacion = VALUES(observacion)");
        $count = 0;
        foreach ($registros as $r) {
            $sis = in_array((int)($r['sistema'] ?? 1), [1, 2]) ? (int)$r['sistema'] : 1;
            $cod = substr(trim($r['codigo'] ?? ''), 0, 50);
            $obs = substr(trim($r['observacion'] ?? ''), 0, 255);
            if (!$cod || !$obs) continue;
            $stmt->execute([$sis, $cod, $obs]);
            $count++;
        }
        echo json_encode(['insertados' => $count]);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage(), 'data' => [], 'total' => 0, 'limit' => 50]);
}
