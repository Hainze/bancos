<?php
require_once dirname(__DIR__, 2) . '/auth/check_api.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo    = getDB();

$pdo->exec("CREATE TABLE IF NOT EXISTS soc_sociedades (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(150) NOT NULL,
    tipo            VARCHAR(10)  DEFAULT 'SRL',
    mes_inicio      TINYINT      DEFAULT 1,
    tiene_auditoria TINYINT(1)   DEFAULT 0,
    tiene_pj        TINYINT(1)   DEFAULT 0,
    tiene_uif       TINYINT(1)   DEFAULT 0,
    notas           TEXT         DEFAULT NULL,
    activa          TINYINT(1)   DEFAULT 1,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS soc_checklist (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sociedad_id INT     NOT NULL,
    periodo     VARCHAR(20) NOT NULL,
    item_key    VARCHAR(50) NOT NULL,
    completado  TINYINT(1)  DEFAULT 0,
    fecha_comp  DATE        DEFAULT NULL,
    updated_at  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_item (sociedad_id, periodo, item_key),
    INDEX idx_soc (sociedad_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Items fijos para conteo en el servidor
$ITEMS_DATOS = [
    'ventas','compras','sueldos','cargas','iibb','retenciones',
    'bcra','nuestra_parte','facilidades','sigsa','proveedores','clientes','cheques',
];
$ITEMS_PRES_BASE  = ['ganancias','balance','pub','notas_doc','memoria','acta','cert_literal'];
$ITEMS_PRES_COND  = ['auditoria' => 'tiene_auditoria', 'persona_jur' => 'tiene_pj', 'uif' => 'tiene_uif'];

function calcPeriodo(int $mesInicio): string {
    $m = (int)date('n');
    $y = (int)date('Y');
    if ($mesInicio === 1) return (string)$y;
    return $m >= $mesInicio ? "$y-" . ($y+1) : ($y-1) . "-$y";
}

function totalItems(array $soc, array $base, array $cond): int {
    $n = 13 + count($base); // datos + pres base
    foreach ($cond as $key => $flag) {
        if ($soc[$flag]) $n++;
    }
    return $n;
}

try {
    switch ($action) {

        // ── SOCIEDADES ─────────────────────────────────────────
        case 'listar_sociedades':
            $socs = $pdo->query("SELECT * FROM soc_sociedades WHERE activa=1 ORDER BY nombre")->fetchAll();
            foreach ($socs as &$s) {
                $periodo = calcPeriodo((int)$s['mes_inicio']);
                $s['periodo_actual'] = $periodo;
                $s['total_items']    = totalItems($s, $ITEMS_PRES_BASE, $ITEMS_PRES_COND);
                $st = $pdo->prepare("SELECT COUNT(*) FROM soc_checklist WHERE sociedad_id=? AND periodo=? AND completado=1");
                $st->execute([$s['id'], $periodo]);
                $s['completados'] = (int)$st->fetchColumn();
            }
            echo json_encode(['data' => $socs]);
            break;

        case 'guardar_sociedad':
            $d      = json_decode(file_get_contents('php://input'), true);
            $id     = (int)($d['id']  ?? 0);
            $nombre = trim($d['nombre'] ?? '');
            if (!$nombre) { echo json_encode(['error' => 'Nombre requerido']); break; }
            $tipo    = $d['tipo']       ?? 'SRL';
            $mes     = max(1, min(12, (int)($d['mes_inicio'] ?? 1)));
            $aud     = (int)!empty($d['tiene_auditoria']);
            $pj      = (int)!empty($d['tiene_pj']);
            $uif     = (int)!empty($d['tiene_uif']);
            $notas   = trim($d['notas'] ?? '') ?: null;
            if ($id) {
                $pdo->prepare("UPDATE soc_sociedades SET nombre=?,tipo=?,mes_inicio=?,tiene_auditoria=?,tiene_pj=?,tiene_uif=?,notas=? WHERE id=?")
                    ->execute([$nombre,$tipo,$mes,$aud,$pj,$uif,$notas,$id]);
            } else {
                $pdo->prepare("INSERT INTO soc_sociedades (nombre,tipo,mes_inicio,tiene_auditoria,tiene_pj,tiene_uif,notas) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$nombre,$tipo,$mes,$aud,$pj,$uif,$notas]);
                $id = (int)$pdo->lastInsertId();
            }
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'eliminar_sociedad':
            $d = json_decode(file_get_contents('php://input'), true);
            $pdo->prepare("UPDATE soc_sociedades SET activa=0 WHERE id=?")->execute([(int)($d['id'] ?? 0)]);
            echo json_encode(['success' => true]);
            break;

        // ── CHECKLIST ──────────────────────────────────────────
        case 'get_checklist':
            $soc_id  = (int)($_GET['sociedad_id'] ?? 0);
            $periodo = trim($_GET['periodo'] ?? '');
            if (!$soc_id || !$periodo) { echo json_encode(['error' => 'Parámetros requeridos']); break; }
            $soc = $pdo->prepare("SELECT * FROM soc_sociedades WHERE id=? AND activa=1");
            $soc->execute([$soc_id]);
            $soc = $soc->fetch();
            if (!$soc) { echo json_encode(['error' => 'Sociedad no encontrada']); break; }
            $rows = $pdo->prepare("SELECT item_key, completado, fecha_comp FROM soc_checklist WHERE sociedad_id=? AND periodo=?");
            $rows->execute([$soc_id, $periodo]);
            $map = [];
            foreach ($rows->fetchAll() as $r) {
                $map[$r['item_key']] = ['completado' => (bool)$r['completado'], 'fecha' => $r['fecha_comp']];
            }
            echo json_encode(['sociedad' => $soc, 'periodo' => $periodo, 'checklist' => $map]);
            break;

        case 'toggle_item':
            $d          = json_decode(file_get_contents('php://input'), true);
            $soc_id     = (int)($d['sociedad_id'] ?? 0);
            $periodo    = trim($d['periodo']  ?? '');
            $item_key   = trim($d['item_key'] ?? '');
            $completado = (int)!empty($d['completado']);
            if (!$soc_id || !$periodo || !$item_key) { echo json_encode(['error' => 'Parámetros requeridos']); break; }
            $fecha = $completado ? date('Y-m-d') : null;
            $pdo->prepare("INSERT INTO soc_checklist (sociedad_id, periodo, item_key, completado, fecha_comp)
                VALUES (?,?,?,?,?)
                ON DUPLICATE KEY UPDATE completado=VALUES(completado), fecha_comp=VALUES(fecha_comp), updated_at=NOW()")
                ->execute([$soc_id, $periodo, $item_key, $completado, $fecha]);
            echo json_encode(['success' => true, 'completado' => $completado, 'fecha' => $fecha]);
            break;

        default:
            echo json_encode(['error' => 'Acción no válida: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
