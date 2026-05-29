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

try {

switch ($action) {

    // ── Listar con paginación y filtros ───────────────────────────────────
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

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM cobranza_padrones $wStr");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $sel = $pdo->prepare("SELECT * FROM cobranza_padrones $wStr ORDER BY sistema, codigo LIMIT $limit OFFSET $offset");
        $sel->execute($params);
        $rows = $sel->fetchAll();

        echo json_encode(['data' => $rows, 'total' => $total, 'limit' => $limit]);
        break;

    // ── Listar todos (sin paginación, para uso en procesar.php) ──────────
    case 'listar_todos':
        $rows = $pdo->query("SELECT sistema, codigo, observacion FROM cobranza_padrones")->fetchAll();
        echo json_encode(['data' => $rows]);
        break;

    // ── Guardar (insert o update por sistema+codigo) ──────────────────────
    case 'guardar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'POST requerido']); break;
        }
        $body = json_decode(file_get_contents('php://input'), true);

        $id      = (int)($body['id']          ?? 0);
        $sistema = (int)($body['sistema']      ?? 1);
        $codigo  = substr(trim($body['codigo'] ?? ''), 0, 50);
        $obs     = substr(trim($body['observacion'] ?? ''), 0, 255);

        if (!$codigo || !$obs) {
            echo json_encode(['error' => 'Código y observación son requeridos']); break;
        }
        if (!in_array($sistema, [1, 2])) {
            echo json_encode(['error' => 'Sistema inválido']); break;
        }

        if ($id) {
            $stmt = $pdo->prepare("UPDATE cobranza_padrones SET sistema=?, codigo=?, observacion=? WHERE id=?");
            $stmt->execute([$sistema, $codigo, $obs, $id]);
            echo json_encode(['msg' => 'Actualizado correctamente']);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO cobranza_padrones (sistema, codigo, observacion)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE observacion = VALUES(observacion)
            ");
            $stmt->execute([$sistema, $codigo, $obs]);
            echo json_encode(['msg' => 'Guardado correctamente', 'id' => (int)$pdo->lastInsertId()]);
        }
        break;

    // ── Eliminar ──────────────────────────────────────────────────────────
    case 'eliminar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'POST requerido']); break;
        }
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); break; }

        $pdo->prepare("DELETE FROM cobranza_padrones WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    // ── Importar masivo ───────────────────────────────────────────────────
    case 'importar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'POST requerido']); break;
        }
        $body      = json_decode(file_get_contents('php://input'), true);
        $registros = $body['registros'] ?? [];
        if (empty($registros)) {
            echo json_encode(['error' => 'Sin registros']); break;
        }

        $stmt = $pdo->prepare("
            INSERT INTO cobranza_padrones (sistema, codigo, observacion)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE observacion = VALUES(observacion)
        ");
        $count = 0;
        foreach ($registros as $r) {
            $sistema = in_array((int)($r['sistema'] ?? 1), [1, 2]) ? (int)$r['sistema'] : 1;
            $codigo  = substr(trim($r['codigo'] ?? ''), 0, 50);
            $obs     = substr(trim($r['observacion'] ?? ''), 0, 255);
            if (!$codigo || !$obs) continue;
            $stmt->execute([$sistema, $codigo, $obs]);
            $count++;
        }
        echo json_encode(['insertados' => $count]);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}

} catch (PDOException $e) {
    // Tabla no existe u otro error SQL — devolver JSON limpio
    if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Table") !== false) {
        echo json_encode(['error' => 'Las tablas de cobranza no existen. Corré cobranza_install.php primero.', 'data' => [], 'total' => 0, 'limit' => 50]);
    } else {
        echo json_encode(['error' => $e->getMessage(), 'data' => [], 'total' => 0, 'limit' => 50]);
    }
}
