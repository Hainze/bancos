<?php
require_once dirname(__DIR__) . '/auth/check_api.php';

$action = $_GET['action'] ?? '';
$db = getDB();

// Auto-create table
$db->exec("CREATE TABLE IF NOT EXISTS imp_archivos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id      INT            NOT NULL,
    tipo            VARCHAR(20)    NOT NULL COMMENT 'iibb | 931 | portal',
    mes             TINYINT        NOT NULL,
    anio            SMALLINT       NOT NULL,
    nombre_original VARCHAR(250)   NOT NULL,
    contenido       MEDIUMBLOB     NOT NULL,
    mime_type       VARCHAR(100)   NOT NULL DEFAULT 'application/pdf',
    tamano          INT            NOT NULL DEFAULT 0,
    created_at      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX  idx_cliente (cliente_id),
    INDEX  idx_periodo (cliente_id, anio, mes),
    UNIQUE KEY uk_tipo_periodo (cliente_id, tipo, mes, anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── File download — raw binary response, must go before JSON header ──────
if ($action === 'archivos_descargar') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { http_response_code(404); exit; }
    $st = $db->prepare("SELECT nombre_original, contenido, mime_type FROM imp_archivos WHERE id=?");
    $st->execute([$id]);
    $file = $st->fetch();
    if (!$file) { http_response_code(404); exit; }
    header('Content-Type: ' . ($file['mime_type'] ?: 'application/pdf'));
    header('Content-Disposition: inline; filename="' . rawurlencode($file['nombre_original']) . '"');
    header('Content-Length: ' . strlen($file['contenido']));
    echo $file['contenido'];
    exit;
}

header('Content-Type: application/json');

switch ($action) {

    // ── Listar archivos ───────────────────────────────────────────────────
    case 'archivos_listar':
        $cid  = (int)($_GET['cliente_id'] ?? 0);
        $mes  = (int)($_GET['mes']        ?? 0);
        $anio = (int)($_GET['anio']       ?? 0);
        $tipo = trim($_GET['tipo']        ?? '');
        if (!$cid) { echo json_encode(['data' => []]); break; }

        $where  = 'cliente_id = ?';
        $params = [$cid];
        if ($anio) { $where .= ' AND anio=?'; $params[] = $anio; }
        if ($mes)  { $where .= ' AND mes=?';  $params[] = $mes;  }
        if ($tipo) { $where .= ' AND tipo=?'; $params[] = $tipo; }

        $st = $db->prepare(
            "SELECT id, cliente_id, tipo, mes, anio, nombre_original, mime_type, tamano, created_at
             FROM imp_archivos WHERE $where ORDER BY anio DESC, mes DESC, tipo"
        );
        $st->execute($params);
        echo json_encode(['data' => $st->fetchAll()]);
        break;

    // ── Guardar archivo (multipart/form-data) ─────────────────────────────
    case 'archivos_guardar':
        $cid  = (int)($_POST['cliente_id'] ?? 0);
        $tipo = trim($_POST['tipo']        ?? '');
        $mes  = (int)($_POST['mes']        ?? 0);
        $anio = (int)($_POST['anio']       ?? 0);

        if (!$cid || !$tipo || !$mes || !$anio) {
            echo json_encode(['error' => 'Faltan campos requeridos']); break;
        }
        if (!in_array($tipo, ['iibb','931','portal'])) {
            echo json_encode(['error' => 'Tipo de archivo inválido']); break;
        }

        $file = $_FILES['archivo'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $msg = match ($file['error'] ?? -1) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido',
                UPLOAD_ERR_NO_FILE  => 'No se seleccionó ningún archivo',
                default             => 'Error al subir el archivo',
            };
            echo json_encode(['error' => $msg]); break;
        }

        $contenido = file_get_contents($file['tmp_name']);
        $nombre    = basename($file['name']);
        $mime      = $file['type'] ?: 'application/pdf';
        $tamano    = $file['size'];

        // Check if replacing existing
        $exists = $db->prepare("SELECT id FROM imp_archivos WHERE cliente_id=? AND tipo=? AND mes=? AND anio=?");
        $exists->execute([$cid, $tipo, $mes, $anio]);
        $reemplazado = $exists->fetch() !== false;

        $st = $db->prepare(
            "INSERT INTO imp_archivos (cliente_id, tipo, mes, anio, nombre_original, contenido, mime_type, tamano)
             VALUES (?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               nombre_original=VALUES(nombre_original), contenido=VALUES(contenido),
               mime_type=VALUES(mime_type), tamano=VALUES(tamano), created_at=NOW()"
        );
        $st->execute([$cid, $tipo, $mes, $anio, $nombre, $contenido, $mime, $tamano]);
        $id = (int)($db->lastInsertId() ?: $db->query("SELECT id FROM imp_archivos WHERE cliente_id=$cid AND tipo='$tipo' AND mes=$mes AND anio=$anio")->fetchColumn());

        echo json_encode(['success' => true, 'id' => $id, 'reemplazado' => $reemplazado]);
        break;

    // ── Eliminar archivo ─────────────────────────────────────────────────
    case 'archivos_eliminar':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); break; }
        $db->prepare("DELETE FROM imp_archivos WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    // ── Resumen de períodos disponibles ──────────────────────────────────
    case 'periodos_listar':
        $cid = (int)($_GET['cliente_id'] ?? 0);
        if (!$cid) { echo json_encode(['data' => []]); break; }
        $st = $db->prepare(
            "SELECT anio, mes, GROUP_CONCAT(tipo ORDER BY tipo SEPARATOR ',') AS tipos
             FROM imp_archivos WHERE cliente_id=?
             GROUP BY anio, mes ORDER BY anio DESC, mes DESC"
        );
        $st->execute([$cid]);
        echo json_encode(['data' => $st->fetchAll()]);
        break;

    case 'eliminar_todo':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST requerido']); break; }
        $pdo->exec("DELETE FROM imp_archivos");
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Acción no válida']);
}
