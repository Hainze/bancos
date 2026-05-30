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
$pdo->exec("CREATE TABLE IF NOT EXISTS portal_iva_datos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id   INT NOT NULL,
    archivo_id   INT DEFAULT NULL,
    cuit         VARCHAR(20)  DEFAULT '',
    mes          TINYINT      NOT NULL,
    anio         SMALLINT     NOT NULL,
    vRI_iva105   DECIMAL(15,2) DEFAULT 0,
    vRI_neto105  DECIMAL(15,2) DEFAULT 0,
    vRI_iva21    DECIMAL(15,2) DEFAULT 0,
    vRI_neto21   DECIMAL(15,2) DEFAULT 0,
    vCF_iva105   DECIMAL(15,2) DEFAULT 0,
    vCF_neto105  DECIMAL(15,2) DEFAULT 0,
    vCF_iva21    DECIMAL(15,2) DEFAULT 0,
    vCF_neto21   DECIMAL(15,2) DEFAULT 0,
    c_iva105     DECIMAL(15,2) DEFAULT 0,
    c_neto105    DECIMAL(15,2) DEFAULT 0,
    c_iva21      DECIMAL(15,2) DEFAULT 0,
    c_neto21     DECIMAL(15,2) DEFAULT 0,
    c_iva27      DECIMAL(15,2) DEFAULT 0,
    c_neto27     DECIMAL(15,2) DEFAULT 0,
    archivo_nombre VARCHAR(255) DEFAULT '',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cliente_periodo (cliente_id, mes, anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

switch ($action) {

    case 'guardar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        if (!$clienteId) { echo json_encode(['error' => 'Sin cliente seleccionado']); break; }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) { echo json_encode(['error' => 'Body inválido']); break; }

        $mes  = (int)($body['mes']  ?? 0);
        $anio = (int)($body['anio'] ?? 0);
        if (!$mes || !$anio) { echo json_encode(['error' => 'Período no detectado en el PDF']); break; }

        // Guardar PDF
        $archivoId = null;
        if (!empty($body['pdf_base64'])) {
            $pdfContent = base64_decode($body['pdf_base64']);
            $nombre     = substr($body['archivo_nombre'] ?? 'portal_iva.pdf', 0, 255);

            $old = $pdo->prepare("SELECT id FROM imp_archivos WHERE cliente_id=? AND tipo='portal' AND mes=? AND anio=?");
            $old->execute([$clienteId, $mes, $anio]);
            $oldRow = $old->fetch();
            if ($oldRow) {
                $pdo->prepare("UPDATE imp_archivos SET nombre_original=?,contenido=?,tamano=?,created_at=NOW() WHERE id=?")
                    ->execute([$nombre, $pdfContent, strlen($pdfContent), $oldRow['id']]);
                $archivoId = $oldRow['id'];
            } else {
                $pdo->prepare("INSERT INTO imp_archivos (cliente_id,tipo,mes,anio,nombre_original,contenido,mime_type,tamano) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$clienteId,'portal',$mes,$anio,$nombre,$pdfContent,'application/pdf',strlen($pdfContent)]);
                $archivoId = $pdo->lastInsertId();
            }
        }

        $pdo->prepare("
            INSERT INTO portal_iva_datos
                (cliente_id,archivo_id,cuit,mes,anio,
                 vRI_iva105,vRI_neto105,vRI_iva21,vRI_neto21,
                 vCF_iva105,vCF_neto105,vCF_iva21,vCF_neto21,
                 c_iva105,c_neto105,c_iva21,c_neto21,c_iva27,c_neto27,
                 archivo_nombre)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                archivo_id=VALUES(archivo_id), cuit=VALUES(cuit),
                vRI_iva105=VALUES(vRI_iva105), vRI_neto105=VALUES(vRI_neto105),
                vRI_iva21=VALUES(vRI_iva21),   vRI_neto21=VALUES(vRI_neto21),
                vCF_iva105=VALUES(vCF_iva105), vCF_neto105=VALUES(vCF_neto105),
                vCF_iva21=VALUES(vCF_iva21),   vCF_neto21=VALUES(vCF_neto21),
                c_iva105=VALUES(c_iva105),     c_neto105=VALUES(c_neto105),
                c_iva21=VALUES(c_iva21),       c_neto21=VALUES(c_neto21),
                c_iva27=VALUES(c_iva27),       c_neto27=VALUES(c_neto27),
                archivo_nombre=VALUES(archivo_nombre), updated_at=CURRENT_TIMESTAMP
        ")->execute([
            $clienteId, $archivoId,
            substr($body['cuit'] ?? '', 0, 20),
            $mes, $anio,
            (float)($body['vRI_iva105']  ?? 0), (float)($body['vRI_neto105'] ?? 0),
            (float)($body['vRI_iva21']   ?? 0), (float)($body['vRI_neto21']  ?? 0),
            (float)($body['vCF_iva105']  ?? 0), (float)($body['vCF_neto105'] ?? 0),
            (float)($body['vCF_iva21']   ?? 0), (float)($body['vCF_neto21']  ?? 0),
            (float)($body['c_iva105']    ?? 0), (float)($body['c_neto105']   ?? 0),
            (float)($body['c_iva21']     ?? 0), (float)($body['c_neto21']    ?? 0),
            (float)($body['c_iva27']     ?? 0), (float)($body['c_neto27']    ?? 0),
            substr($body['archivo_nombre'] ?? '', 0, 255),
        ]);

        echo json_encode(['success' => true, 'msg' => "Guardado: {$mes}/{$anio}"]);
        break;

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
        $sel  = $pdo->prepare("SELECT * FROM portal_iva_datos $wStr ORDER BY anio, mes");
        $sel->execute($params);
        echo json_encode(['data' => $sel->fetchAll()]);
        break;

    case 'eliminar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); break; }

        $row = $pdo->prepare("SELECT archivo_id FROM portal_iva_datos WHERE id=? AND cliente_id=?");
        $row->execute([$id, $clienteId]);
        $dat = $row->fetch();
        if ($dat && $dat['archivo_id'])
            $pdo->prepare("DELETE FROM imp_archivos WHERE id=?")->execute([$dat['archivo_id']]);
        $pdo->prepare("DELETE FROM portal_iva_datos WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}
