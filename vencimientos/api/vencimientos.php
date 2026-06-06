<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/auth/check_api.php';

header('Content-Type: application/json; charset=utf-8');

$action    = $_GET['action'] ?? '';
$clienteId = (int)($_SESSION['fact_cliente_id'] ?? 0);

try { $pdo = getDB(); } catch (Exception $e) {
    echo json_encode(['error' => 'DB: ' . $e->getMessage()]); exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS venc_cuentas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id  INT NOT NULL,
    tipo        VARCHAR(10) NOT NULL DEFAULT 'servicio',
    categoria   VARCHAR(50) NOT NULL,
    nombre      VARCHAR(200) NOT NULL,
    descripcion VARCHAR(500) DEFAULT '',
    url_web     VARCHAR(500) DEFAULT '',
    usuario     VARCHAR(200) DEFAULT '',
    clave       VARCHAR(800) DEFAULT '',
    notas       TEXT,
    activo      TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cliente (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS venc_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    cuenta_id   INT NOT NULL,
    cliente_id  INT NOT NULL,
    descripcion VARCHAR(200) DEFAULT '',
    fecha_venc  DATE NOT NULL,
    importe     DECIMAL(15,2) DEFAULT 0,
    estado      VARCHAR(10) NOT NULL DEFAULT 'pendiente',
    fecha_pago  DATE NULL,
    nro_comp    VARCHAR(100) DEFAULT '',
    notas       VARCHAR(500) DEFAULT '',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cliente (cliente_id),
    INDEX idx_fecha (fecha_venc),
    INDEX idx_estado (estado),
    INDEX idx_cuenta (cuenta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

switch ($action) {

    // ── Stats para el dashboard ───────────────────────────────────────────────
    case 'stats':
        if (!$clienteId) { echo json_encode(['vencidos'=>0,'semana'=>0,'mes'=>0,'pagados_mes'=>0]); break; }
        $hoy = date('Y-m-d');
        $en7 = date('Y-m-d', strtotime('+7 days'));
        $en30= date('Y-m-d', strtotime('+30 days'));
        $ini = date('Y-m-01');
        $fin = date('Y-m-t');

        $q = function($sql, $p) use ($pdo) {
            $st = $pdo->prepare($sql); $st->execute($p); return (int)$st->fetchColumn();
        };

        echo json_encode([
            'vencidos'    => $q("SELECT COUNT(*) FROM venc_items WHERE cliente_id=? AND estado='pendiente' AND fecha_venc < ?", [$clienteId, $hoy]),
            'semana'      => $q("SELECT COUNT(*) FROM venc_items WHERE cliente_id=? AND estado='pendiente' AND fecha_venc BETWEEN ? AND ?", [$clienteId, $hoy, $en7]),
            'mes'         => $q("SELECT COUNT(*) FROM venc_items WHERE cliente_id=? AND estado='pendiente' AND fecha_venc BETWEEN ? AND ?", [$clienteId, $hoy, $en30]),
            'pagados_mes' => $q("SELECT COUNT(*) FROM venc_items WHERE cliente_id=? AND estado='pagado' AND fecha_pago BETWEEN ? AND ?", [$clienteId, $ini, $fin]),
        ]);
        break;

    // ── Listar vencimientos ───────────────────────────────────────────────────
    case 'listar':
        if (!$clienteId) { echo json_encode(['data' => []]); break; }

        $estado  = $_GET['estado']   ?? '';   // pendiente | pagado | todos
        $dias    = (int)($_GET['dias'] ?? 0); // 0 = todos, 7, 30, 90
        $cuentaF = (int)($_GET['cuenta_id'] ?? 0);

        $where  = ['i.cliente_id = ?'];
        $params = [$clienteId];

        if ($estado === 'pendiente') {
            $where[] = "i.estado = 'pendiente'";
        } elseif ($estado === 'pagado') {
            $where[] = "i.estado = 'pagado'";
        }

        if ($dias > 0) {
            $where[]  = 'i.fecha_venc <= ?';
            $params[] = date('Y-m-d', strtotime("+$dias days"));
        }

        if ($cuentaF) {
            $where[]  = 'i.cuenta_id = ?';
            $params[] = $cuentaF;
        }

        $wStr = 'WHERE ' . implode(' AND ', $where);

        $st = $pdo->prepare("
            SELECT i.*, c.tipo, c.categoria, c.nombre AS cuenta_nombre, c.url_web
            FROM venc_items i
            JOIN venc_cuentas c ON c.id = i.cuenta_id
            $wStr
            ORDER BY
                CASE WHEN i.estado = 'pendiente' AND i.fecha_venc < CURDATE() THEN 0 ELSE 1 END,
                i.fecha_venc ASC
            LIMIT 500
        ");
        $st->execute($params);
        echo json_encode(['data' => $st->fetchAll()]);
        break;

    // ── Guardar (crear o editar) ──────────────────────────────────────────────
    case 'guardar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        if (!$clienteId) { echo json_encode(['error' => 'Sin cliente']); break; }

        $body = json_decode(file_get_contents('php://input'), true);

        $id          = (int)($body['id']        ?? 0);
        $cuentaId    = (int)($body['cuenta_id'] ?? 0);
        $descripcion = substr($body['descripcion'] ?? '', 0, 200);
        $fechaVenc   = $body['fecha_venc'] ?? '';
        $importe     = (float)($body['importe'] ?? 0);
        $notas       = substr($body['notas'] ?? '', 0, 500);

        if (!$cuentaId || !$fechaVenc) { echo json_encode(['error' => 'Cuenta y fecha son requeridos']); break; }

        // Verificar que la cuenta pertenece al cliente
        $chk = $pdo->prepare("SELECT id FROM venc_cuentas WHERE id=? AND cliente_id=?");
        $chk->execute([$cuentaId, $clienteId]);
        if (!$chk->fetch()) { echo json_encode(['error' => 'Cuenta no encontrada']); break; }

        if ($id) {
            $pdo->prepare("UPDATE venc_items SET cuenta_id=?,descripcion=?,fecha_venc=?,importe=?,notas=? WHERE id=? AND cliente_id=?")
                ->execute([$cuentaId,$descripcion,$fechaVenc,$importe,$notas,$id,$clienteId]);
            echo json_encode(['success' => true, 'id' => $id]);
        } else {
            $pdo->prepare("INSERT INTO venc_items (cliente_id,cuenta_id,descripcion,fecha_venc,importe,notas) VALUES (?,?,?,?,?,?)")
                ->execute([$clienteId,$cuentaId,$descripcion,$fechaVenc,$importe,$notas]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        }
        break;

    // ── Marcar como pagado ────────────────────────────────────────────────────
    case 'pagar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        $body     = json_decode(file_get_contents('php://input'), true);
        $id       = (int)($body['id']       ?? 0);
        $fechaPago= $body['fecha_pago']     ?? date('Y-m-d');
        $nroComp  = substr($body['nro_comp'] ?? '', 0, 100);

        if (!$id) { echo json_encode(['error' => 'ID requerido']); break; }
        $pdo->prepare("UPDATE venc_items SET estado='pagado', fecha_pago=?, nro_comp=?, updated_at=NOW() WHERE id=? AND cliente_id=?")
            ->execute([$fechaPago, $nroComp, $id, $clienteId]);
        echo json_encode(['success' => true]);
        break;

    // ── Desmarcar pago (volver a pendiente) ───────────────────────────────────
    case 'desmarcar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); break; }
        $pdo->prepare("UPDATE venc_items SET estado='pendiente', fecha_pago=NULL, nro_comp='', updated_at=NOW() WHERE id=? AND cliente_id=?")
            ->execute([$id, $clienteId]);
        echo json_encode(['success' => true]);
        break;

    // ── Eliminar ──────────────────────────────────────────────────────────────
    case 'eliminar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); break; }
        $pdo->prepare("DELETE FROM venc_items WHERE id=? AND cliente_id=?")->execute([$id, $clienteId]);
        echo json_encode(['success' => true]);
        break;

    // ── Generar vencimientos periódicos (mensual) ─────────────────────────────
    case 'generar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        if (!$clienteId) { echo json_encode(['error' => 'Sin cliente']); break; }

        $body     = json_decode(file_get_contents('php://input'), true);
        $cuentaId = (int)($body['cuenta_id']    ?? 0);
        $anio     = (int)($body['anio']         ?? date('Y'));
        $dia      = (int)($body['dia']          ?? 10);
        $importe  = (float)($body['importe']    ?? 0);
        $meses    = $body['meses']              ?? range(1, 12); // array de meses

        if (!$cuentaId) { echo json_encode(['error' => 'Cuenta requerida']); break; }

        $chk = $pdo->prepare("SELECT id FROM venc_cuentas WHERE id=? AND cliente_id=?");
        $chk->execute([$cuentaId, $clienteId]);
        if (!$chk->fetch()) { echo json_encode(['error' => 'Cuenta no encontrada']); break; }

        $mesesNombres = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                         'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        $ins = $pdo->prepare("INSERT INTO venc_items (cliente_id,cuenta_id,descripcion,fecha_venc,importe) VALUES (?,?,?,?,?)");
        $creados = 0;
        foreach ($meses as $m) {
            $m = (int)$m;
            if ($m < 1 || $m > 12) continue;
            $diasMes = cal_days_in_month(CAL_GREGORIAN, $m, $anio);
            $diaReal = min($dia, $diasMes);
            $fecha   = sprintf('%04d-%02d-%02d', $anio, $m, $diaReal);
            $desc    = $mesesNombres[$m] . ' ' . $anio;
            $ins->execute([$clienteId, $cuentaId, $desc, $fecha, $importe]);
            $creados++;
        }
        echo json_encode(['success' => true, 'creados' => $creados]);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}
