<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/auth/check_api.php';

header('Content-Type: application/json; charset=utf-8');

$action    = $_GET['action'] ?? '';
$clienteId = (int)($_SESSION['fact_cliente_id'] ?? 0);

try { $pdo = getDB(); } catch (Exception $e) {
    echo json_encode(['error' => 'DB: ' . $e->getMessage()]); exit;
}

// Crear tabla si no existe
$pdo->exec("CREATE TABLE IF NOT EXISTS f931_datos (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id     INT NOT NULL,
    archivo_id     INT DEFAULT NULL,
    cuit           VARCHAR(20) DEFAULT '',
    mes            TINYINT NOT NULL,
    anio           SMALLINT NOT NULL,
    rem9           DECIMAL(15,2) DEFAULT 0,
    retenciones    DECIMAL(15,2) DEFAULT 0,
    c351           DECIMAL(15,2) DEFAULT 0,
    c301           DECIMAL(15,2) DEFAULT 0,
    c360           DECIMAL(15,2) DEFAULT 0,
    c352           DECIMAL(15,2) DEFAULT 0,
    c935           DECIMAL(15,2) DEFAULT 0,
    c302           DECIMAL(15,2) DEFAULT 0,
    c270           DECIMAL(15,2) DEFAULT 0,
    c312           DECIMAL(15,2) DEFAULT 0,
    c028           DECIMAL(15,2) DEFAULT 0,
    archivo_nombre VARCHAR(255) DEFAULT '',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cliente_periodo (cliente_id, mes, anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

switch ($action) {

    // ── Guardar PDF + datos extraídos ─────────────────────────────────────
    case 'guardar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        if (!$clienteId) { echo json_encode(['error' => 'Sin cliente seleccionado. Seleccioná un cliente primero.']); break; }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) { echo json_encode(['error' => 'Body inválido']); break; }

        $mes  = (int)($body['mes']  ?? 0);
        $anio = (int)($body['anio'] ?? 0);
        if (!$mes || !$anio) { echo json_encode(['error' => 'Período no detectado en el PDF']); break; }

        // Guardar PDF en imp_archivos
        $archivoId = null;
        if (!empty($body['pdf_base64'])) {
            $pdfContent = base64_decode($body['pdf_base64']);
            $nombre     = substr($body['archivo_nombre'] ?? 'f931.pdf', 0, 255);

            // Si ya existe un archivo 931 para este cliente/mes/año, reemplazarlo
            $old = $pdo->prepare("SELECT id FROM imp_archivos WHERE cliente_id=? AND tipo='931' AND mes=? AND anio=?");
            $old->execute([$clienteId, $mes, $anio]);
            $oldRow = $old->fetch();
            if ($oldRow) {
                $upd = $pdo->prepare("UPDATE imp_archivos SET nombre_original=?,contenido=?,tamano=?,created_at=NOW() WHERE id=?");
                $upd->execute([$nombre, $pdfContent, strlen($pdfContent), $oldRow['id']]);
                $archivoId = $oldRow['id'];
            } else {
                $ins = $pdo->prepare("INSERT INTO imp_archivos (cliente_id,tipo,mes,anio,nombre_original,contenido,mime_type,tamano) VALUES (?,?,?,?,?,?,?,?)");
                $ins->execute([$clienteId,'931',$mes,$anio,$nombre,$pdfContent,'application/pdf',strlen($pdfContent)]);
                $archivoId = $pdo->lastInsertId();
            }
        }

        // Guardar / actualizar datos extraídos
        $stmt = $pdo->prepare("
            INSERT INTO f931_datos
                (cliente_id, archivo_id, cuit, mes, anio, rem9, retenciones,
                 c351, c301, c360, c352, c935, c302, c270, c312, c028, archivo_nombre)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                archivo_id=VALUES(archivo_id), cuit=VALUES(cuit),
                rem9=VALUES(rem9), retenciones=VALUES(retenciones),
                c351=VALUES(c351), c301=VALUES(c301), c360=VALUES(c360),
                c352=VALUES(c352), c935=VALUES(c935), c302=VALUES(c302),
                c270=VALUES(c270), c312=VALUES(c312), c028=VALUES(c028),
                archivo_nombre=VALUES(archivo_nombre), updated_at=CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            $clienteId, $archivoId,
            substr($body['cuit'] ?? '', 0, 20),
            $mes, $anio,
            (float)($body['rem9']        ?? 0),
            (float)($body['retenciones'] ?? 0),
            (float)($body['c351'] ?? 0), (float)($body['c301'] ?? 0),
            (float)($body['c360'] ?? 0), (float)($body['c352'] ?? 0),
            (float)($body['c935'] ?? 0), (float)($body['c302'] ?? 0),
            (float)($body['c270'] ?? 0), (float)($body['c312'] ?? 0),
            (float)($body['c028'] ?? 0),
            substr($body['archivo_nombre'] ?? '', 0, 255),
        ]);

        echo json_encode(['success' => true, 'msg' => "Guardado: {$mes}/{$anio}"]);
        break;

    // ── Listar datos guardados (con filtro de período) ────────────────────
    case 'listar':
        if (!$clienteId) { echo json_encode(['error' => 'Sin cliente']); break; }

        $where  = ['cliente_id = ?'];
        $params = [$clienteId];

        $anioD = (int)($_GET['anio_desde'] ?? 0);
        $mesD  = (int)($_GET['mes_desde']  ?? 0);
        $anioH = (int)($_GET['anio_hasta'] ?? 0);
        $mesH  = (int)($_GET['mes_hasta']  ?? 0);

        if ($anioD && $mesD) {
            $where[]  = '(anio > ? OR (anio = ? AND mes >= ?))';
            $params[] = $anioD; $params[] = $anioD; $params[] = $mesD;
        }
        if ($anioH && $mesH) {
            $where[]  = '(anio < ? OR (anio = ? AND mes <= ?))';
            $params[] = $anioH; $params[] = $anioH; $params[] = $mesH;
        }

        $wStr = 'WHERE ' . implode(' AND ', $where);
        $sel  = $pdo->prepare("SELECT * FROM f931_datos $wStr ORDER BY anio, mes");
        $sel->execute($params);
        echo json_encode(['data' => $sel->fetchAll()]);
        break;

    // ── Eliminar un período guardado ──────────────────────────────────────
    case 'eliminar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); break; }

        $row = $pdo->prepare("SELECT archivo_id FROM f931_datos WHERE id=? AND cliente_id=?");
        $row->execute([$id, $clienteId]);
        $dat = $row->fetch();
        if ($dat && $dat['archivo_id']) {
            $pdo->prepare("DELETE FROM imp_archivos WHERE id=?")->execute([$dat['archivo_id']]);
        }
        $pdo->prepare("DELETE FROM f931_datos WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}
