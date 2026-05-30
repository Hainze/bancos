<?php
require_once dirname(__DIR__) . '/auth/check_api.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$db = getDB();

// Auto-create tables on first use
static $tablesReady = false;
if (!$tablesReady) {
    $db->exec("CREATE TABLE IF NOT EXISTS fact_clientes (
        id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(200) NOT NULL,
        cuit VARCHAR(20) NOT NULL DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS fact_compras (
        id INT AUTO_INCREMENT PRIMARY KEY, cliente_id INT NOT NULL,
        tipo VARCHAR(50) NOT NULL, punto_venta INT NOT NULL DEFAULT 0, numero INT NOT NULL DEFAULT 0,
        fecha DATE NOT NULL, mes_contable TINYINT NOT NULL, anio_contable SMALLINT NOT NULL,
        proveedor_nombre VARCHAR(200) NOT NULL DEFAULT '', proveedor_cuit VARCHAR(20) NOT NULL DEFAULT '',
        no_gravado DECIMAL(14,2) NOT NULL DEFAULT 0, perc_iibb DECIMAL(14,2) NOT NULL DEFAULT 0,
        perc_iva DECIMAL(14,2) NOT NULL DEFAULT 0, imp_interno DECIMAL(14,2) NOT NULL DEFAULT 0,
        imp_interno_gasoil DECIMAL(14,2) NOT NULL DEFAULT 0, total DECIMAL(14,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_c (cliente_id), INDEX idx_p (cliente_id, anio_contable, mes_contable)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS fact_compras_renglones (
        id INT AUTO_INCREMENT PRIMARY KEY, compra_id INT NOT NULL,
        alicuota DECIMAL(5,2) NOT NULL DEFAULT 21.00, neto DECIMAL(14,2) NOT NULL DEFAULT 0,
        iva DECIMAL(14,2) NOT NULL DEFAULT 0, INDEX idx_cr (compra_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS fact_ventas (
        id INT AUTO_INCREMENT PRIMARY KEY, cliente_id INT NOT NULL,
        tipo VARCHAR(50) NOT NULL, punto_venta INT NOT NULL DEFAULT 0, numero INT NOT NULL DEFAULT 0,
        fecha DATE NOT NULL, mes_contable TINYINT NOT NULL, anio_contable SMALLINT NOT NULL,
        destinatario_nombre VARCHAR(200) NOT NULL DEFAULT '', destinatario_cuit VARCHAR(20) NOT NULL DEFAULT '',
        no_gravado DECIMAL(14,2) NOT NULL DEFAULT 0, retencion DECIMAL(14,2) NOT NULL DEFAULT 0,
        total DECIMAL(14,2) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_v (cliente_id), INDEX idx_vp (cliente_id, anio_contable, mes_contable)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS fact_ventas_renglones (
        id INT AUTO_INCREMENT PRIMARY KEY, venta_id INT NOT NULL,
        alicuota DECIMAL(5,2) NOT NULL DEFAULT 21.00, neto DECIMAL(14,2) NOT NULL DEFAULT 0,
        iva DECIMAL(14,2) NOT NULL DEFAULT 0, INDEX idx_vr (venta_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $tablesReady = true;
}

function isNC(string $tipo): bool {
    return stripos($tipo, 'nota de cr') !== false;
}

function getSign(string $tipo): int {
    return isNC($tipo) ? -1 : 1;
}

function parseRenglones(array $row): array {
    $renglones = [];
    $alics = $row['alic_list'] !== null ? explode('|', $row['alic_list']) : [];
    $netos = $row['neto_list'] !== null ? explode('|', $row['neto_list']) : [];
    $ivas  = $row['iva_list']  !== null ? explode('|', $row['iva_list'])  : [];
    foreach ($alics as $i => $a) {
        if ($a === '' || $a === null) continue;
        $renglones[] = [
            'alicuota' => (float)$a,
            'neto'     => (float)($netos[$i] ?? 0),
            'iva'      => (float)($ivas[$i]  ?? 0),
        ];
    }
    return $renglones;
}

switch ($action) {

    // ── CLIENTES ─────────────────────────────────────────────────────────────
    case 'clientes_listar':
        $rows = $db->query("SELECT * FROM fact_clientes ORDER BY nombre")->fetchAll();
        echo json_encode(['data' => $rows]);
        break;

    case 'clientes_guardar':
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $nombre = trim($body['nombre'] ?? '');
        $cuit   = trim($body['cuit']   ?? '');
        $id     = (int)($body['id']    ?? 0);
        if ($nombre === '') { echo json_encode(['error' => 'El nombre es requerido']); break; }
        if ($id > 0) {
            $db->prepare("UPDATE fact_clientes SET nombre=?, cuit=? WHERE id=?")->execute([$nombre, $cuit, $id]);
        } else {
            $db->prepare("INSERT INTO fact_clientes (nombre, cuit) VALUES (?,?)")->execute([$nombre, $cuit]);
            $id = (int)$db->lastInsertId();
        }
        echo json_encode(['success' => true, 'id' => $id, 'nombre' => $nombre, 'cuit' => $cuit]);
        break;

    case 'clientes_eliminar':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); break; }
        // Delete renglones → compras/ventas → cliente
        $db->exec("DELETE r FROM fact_compras_renglones r INNER JOIN fact_compras c ON c.id=r.compra_id WHERE c.cliente_id=$id");
        $db->exec("DELETE r FROM fact_ventas_renglones r INNER JOIN fact_ventas v ON v.id=r.venta_id WHERE v.cliente_id=$id");
        $db->prepare("DELETE FROM fact_compras WHERE cliente_id=?")->execute([$id]);
        $db->prepare("DELETE FROM fact_ventas  WHERE cliente_id=?")->execute([$id]);
        $db->prepare("DELETE FROM fact_clientes WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    case 'set_cliente':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); break; }
        $row = $db->prepare("SELECT id, nombre, cuit FROM fact_clientes WHERE id=?");
        $row->execute([$id]);
        $cliente = $row->fetch();
        if (!$cliente) { echo json_encode(['error' => 'Cliente no encontrado']); break; }
        $_SESSION['fact_cliente_id']     = $cliente['id'];
        $_SESSION['fact_cliente_nombre'] = $cliente['nombre'];
        echo json_encode(['success' => true, 'cliente' => $cliente]);
        break;

    // ── COMPRAS ──────────────────────────────────────────────────────────────
    case 'compras_listar':
        $cid  = (int)($_GET['cliente_id'] ?? 0);
        $mes  = (int)($_GET['mes']        ?? 0);
        $anio = (int)($_GET['anio']       ?? 0);
        if (!$cid) { echo json_encode(['data' => []]); break; }
        $where  = 'c.cliente_id = ?';
        $params = [$cid];
        if ($mes && $anio) { $where .= ' AND c.mes_contable=? AND c.anio_contable=?'; $params[] = $mes; $params[] = $anio; }
        elseif ($anio)     { $where .= ' AND c.anio_contable=?'; $params[] = $anio; }
        $sql = "SELECT c.*,
                GROUP_CONCAT(r.alicuota ORDER BY r.id SEPARATOR '|') AS alic_list,
                GROUP_CONCAT(r.neto     ORDER BY r.id SEPARATOR '|') AS neto_list,
                GROUP_CONCAT(r.iva      ORDER BY r.id SEPARATOR '|') AS iva_list
                FROM fact_compras c
                LEFT JOIN fact_compras_renglones r ON r.compra_id = c.id
                WHERE $where
                GROUP BY c.id
                ORDER BY c.anio_contable DESC, c.mes_contable DESC, c.fecha DESC, c.id DESC";
        $st = $db->prepare($sql); $st->execute($params);
        $rows = $st->fetchAll();
        foreach ($rows as &$row) {
            $row['renglones'] = parseRenglones($row);
            unset($row['alic_list'], $row['neto_list'], $row['iva_list']);
        }
        echo json_encode(['data' => $rows]);
        break;

    case 'compras_guardar':
        $b  = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int)($b['id'] ?? 0);
        $cid      = (int)($b['cliente_id']         ?? 0);
        $tipo     = trim($b['tipo']                 ?? '');
        $pv       = (int)($b['punto_venta']         ?? 0);
        $num      = (int)($b['numero']              ?? 0);
        $fecha    = trim($b['fecha']                ?? '');
        $mes      = (int)($b['mes_contable']        ?? 0);
        $anio     = (int)($b['anio_contable']       ?? 0);
        $pNombre  = trim($b['proveedor_nombre']     ?? '');
        $pCuit    = trim($b['proveedor_cuit']       ?? '');
        $noGrav   = round((float)($b['no_gravado']         ?? 0), 2);
        $percIIBB = round((float)($b['perc_iibb']          ?? 0), 2);
        $percIva  = round((float)($b['perc_iva']           ?? 0), 2);
        $impInt   = round((float)($b['imp_interno']        ?? 0), 2);
        $impGas   = round((float)($b['imp_interno_gasoil'] ?? 0), 2);
        $total    = round((float)($b['total']               ?? 0), 2);
        $renglones= $b['renglones'] ?? [];

        if (!$cid || !$tipo || !$fecha || !$mes || !$anio) {
            echo json_encode(['error' => 'Faltan campos requeridos']); break;
        }
        if ($id > 0) {
            $db->prepare("UPDATE fact_compras SET cliente_id=?,tipo=?,punto_venta=?,numero=?,fecha=?,mes_contable=?,anio_contable=?,proveedor_nombre=?,proveedor_cuit=?,no_gravado=?,perc_iibb=?,perc_iva=?,imp_interno=?,imp_interno_gasoil=?,total=? WHERE id=?")
               ->execute([$cid,$tipo,$pv,$num,$fecha,$mes,$anio,$pNombre,$pCuit,$noGrav,$percIIBB,$percIva,$impInt,$impGas,$total,$id]);
            $db->prepare("DELETE FROM fact_compras_renglones WHERE compra_id=?")->execute([$id]);
        } else {
            $db->prepare("INSERT INTO fact_compras (cliente_id,tipo,punto_venta,numero,fecha,mes_contable,anio_contable,proveedor_nombre,proveedor_cuit,no_gravado,perc_iibb,perc_iva,imp_interno,imp_interno_gasoil,total) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$cid,$tipo,$pv,$num,$fecha,$mes,$anio,$pNombre,$pCuit,$noGrav,$percIIBB,$percIva,$impInt,$impGas,$total]);
            $id = (int)$db->lastInsertId();
        }
        $st2 = $db->prepare("INSERT INTO fact_compras_renglones (compra_id,alicuota,neto,iva) VALUES (?,?,?,?)");
        foreach ($renglones as $r) {
            if ((float)($r['neto'] ?? 0) == 0 && (float)($r['iva'] ?? 0) == 0) continue;
            $st2->execute([$id, round((float)($r['alicuota']??21),2), round((float)($r['neto']??0),2), round((float)($r['iva']??0),2)]);
        }
        echo json_encode(['success' => true, 'id' => $id]);
        break;

    case 'compras_eliminar':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); break; }
        $db->prepare("DELETE FROM fact_compras_renglones WHERE compra_id=?")->execute([$id]);
        $db->prepare("DELETE FROM fact_compras WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    // ── VENTAS ───────────────────────────────────────────────────────────────
    case 'ventas_listar':
        $cid  = (int)($_GET['cliente_id'] ?? 0);
        $mes  = (int)($_GET['mes']        ?? 0);
        $anio = (int)($_GET['anio']       ?? 0);
        if (!$cid) { echo json_encode(['data' => []]); break; }
        $where  = 'v.cliente_id = ?';
        $params = [$cid];
        if ($mes && $anio) { $where .= ' AND v.mes_contable=? AND v.anio_contable=?'; $params[] = $mes; $params[] = $anio; }
        elseif ($anio)     { $where .= ' AND v.anio_contable=?'; $params[] = $anio; }
        $sql = "SELECT v.*,
                GROUP_CONCAT(r.alicuota ORDER BY r.id SEPARATOR '|') AS alic_list,
                GROUP_CONCAT(r.neto     ORDER BY r.id SEPARATOR '|') AS neto_list,
                GROUP_CONCAT(r.iva      ORDER BY r.id SEPARATOR '|') AS iva_list
                FROM fact_ventas v
                LEFT JOIN fact_ventas_renglones r ON r.venta_id = v.id
                WHERE $where
                GROUP BY v.id
                ORDER BY v.anio_contable DESC, v.mes_contable DESC, v.fecha DESC, v.id DESC";
        $st = $db->prepare($sql); $st->execute($params);
        $rows = $st->fetchAll();
        foreach ($rows as &$row) {
            $row['renglones'] = parseRenglones($row);
            unset($row['alic_list'], $row['neto_list'], $row['iva_list']);
        }
        echo json_encode(['data' => $rows]);
        break;

    case 'ventas_guardar':
        $b  = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int)($b['id'] ?? 0);
        $cid     = (int)($b['cliente_id']          ?? 0);
        $tipo    = trim($b['tipo']                  ?? '');
        $pv      = (int)($b['punto_venta']          ?? 0);
        $num     = (int)($b['numero']               ?? 0);
        $fecha   = trim($b['fecha']                 ?? '');
        $mes     = (int)($b['mes_contable']         ?? 0);
        $anio    = (int)($b['anio_contable']        ?? 0);
        $dNombre = trim($b['destinatario_nombre']   ?? '');
        $dCuit   = trim($b['destinatario_cuit']     ?? '');
        $noGrav  = round((float)($b['no_gravado']   ?? 0), 2);
        $ret     = round((float)($b['retencion']    ?? 0), 2);
        $total   = round((float)($b['total']        ?? 0), 2);
        $renglones = $b['renglones'] ?? [];

        if (!$cid || !$tipo || !$fecha || !$mes || !$anio) {
            echo json_encode(['error' => 'Faltan campos requeridos']); break;
        }
        if ($id > 0) {
            $db->prepare("UPDATE fact_ventas SET cliente_id=?,tipo=?,punto_venta=?,numero=?,fecha=?,mes_contable=?,anio_contable=?,destinatario_nombre=?,destinatario_cuit=?,no_gravado=?,retencion=?,total=? WHERE id=?")
               ->execute([$cid,$tipo,$pv,$num,$fecha,$mes,$anio,$dNombre,$dCuit,$noGrav,$ret,$total,$id]);
            $db->prepare("DELETE FROM fact_ventas_renglones WHERE venta_id=?")->execute([$id]);
        } else {
            $db->prepare("INSERT INTO fact_ventas (cliente_id,tipo,punto_venta,numero,fecha,mes_contable,anio_contable,destinatario_nombre,destinatario_cuit,no_gravado,retencion,total) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$cid,$tipo,$pv,$num,$fecha,$mes,$anio,$dNombre,$dCuit,$noGrav,$ret,$total]);
            $id = (int)$db->lastInsertId();
        }
        $st2 = $db->prepare("INSERT INTO fact_ventas_renglones (venta_id,alicuota,neto,iva) VALUES (?,?,?,?)");
        foreach ($renglones as $r) {
            if ((float)($r['neto'] ?? 0) == 0 && (float)($r['iva'] ?? 0) == 0) continue;
            $st2->execute([$id, round((float)($r['alicuota']??21),2), round((float)($r['neto']??0),2), round((float)($r['iva']??0),2)]);
        }
        echo json_encode(['success' => true, 'id' => $id]);
        break;

    case 'ventas_eliminar':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); break; }
        $db->prepare("DELETE FROM fact_ventas_renglones WHERE venta_id=?")->execute([$id]);
        $db->prepare("DELETE FROM fact_ventas WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    // ── INFORMES ─────────────────────────────────────────────────────────────
    case 'informe_mensual':
        $cid  = (int)($_GET['cliente_id'] ?? 0);
        $mes  = (int)($_GET['mes']        ?? 0);
        $anio = (int)($_GET['anio']       ?? 0);
        $tipo = $_GET['tipo'] ?? 'compras';
        if (!$cid || !$mes || !$anio) { echo json_encode(['error' => 'Parámetros incompletos']); break; }

        if ($tipo === 'compras') {
            $sql = "SELECT c.*,
                    GROUP_CONCAT(r.alicuota ORDER BY r.id SEPARATOR '|') AS alic_list,
                    GROUP_CONCAT(r.neto     ORDER BY r.id SEPARATOR '|') AS neto_list,
                    GROUP_CONCAT(r.iva      ORDER BY r.id SEPARATOR '|') AS iva_list
                    FROM fact_compras c
                    LEFT JOIN fact_compras_renglones r ON r.compra_id = c.id
                    WHERE c.cliente_id=? AND c.mes_contable=? AND c.anio_contable=?
                    GROUP BY c.id ORDER BY c.fecha, c.id";
            $st = $db->prepare($sql); $st->execute([$cid, $mes, $anio]);
        } else {
            $sql = "SELECT v.*,
                    GROUP_CONCAT(r.alicuota ORDER BY r.id SEPARATOR '|') AS alic_list,
                    GROUP_CONCAT(r.neto     ORDER BY r.id SEPARATOR '|') AS neto_list,
                    GROUP_CONCAT(r.iva      ORDER BY r.id SEPARATOR '|') AS iva_list
                    FROM fact_ventas v
                    LEFT JOIN fact_ventas_renglones r ON r.venta_id = v.id
                    WHERE v.cliente_id=? AND v.mes_contable=? AND v.anio_contable=?
                    GROUP BY v.id ORDER BY v.fecha, v.id";
            $st = $db->prepare($sql); $st->execute([$cid, $mes, $anio]);
        }
        $rows = $st->fetchAll();

        $docs = [];
        $totNeto  = 0; $totIva   = 0; $totNoGrav = 0;
        $totPIIBB = 0; $totPIva  = 0; $totImpInt = 0; $totImpGas = 0;
        $totRet   = 0; $totTotal = 0;

        foreach ($rows as $row) {
            $sign      = getSign($row['tipo']);
            $renglones = parseRenglones($row);
            $rNeto = 0; $rIva = 0;
            $signedRenglones = [];
            foreach ($renglones as $r) {
                $n = round((float)$r['neto'] * $sign, 2);
                $v = round((float)$r['iva']  * $sign, 2);
                $rNeto += $n; $rIva += $v;
                $signedRenglones[] = ['alicuota' => $r['alicuota'], 'neto' => $n, 'iva' => $v];
            }
            $noGrav  = round((float)($row['no_gravado']         ?? 0) * $sign, 2);
            $pIIBB   = round((float)($row['perc_iibb']          ?? 0) * $sign, 2);
            $pIva    = round((float)($row['perc_iva']           ?? 0) * $sign, 2);
            $iInt    = round((float)($row['imp_interno']        ?? 0) * $sign, 2);
            $iGas    = round((float)($row['imp_interno_gasoil'] ?? 0) * $sign, 2);
            $ret     = round((float)($row['retencion']          ?? 0) * $sign, 2);
            $tot     = round((float)($row['total']              ?? 0) * $sign, 2);

            $totNeto  += $rNeto; $totIva   += $rIva;   $totNoGrav += $noGrav;
            $totPIIBB += $pIIBB; $totPIva  += $pIva;   $totImpInt += $iInt;
            $totImpGas+= $iGas;  $totRet   += $ret;    $totTotal  += $tot;

            $nombre = $tipo === 'compras' ? ($row['proveedor_nombre'] ?? '') : ($row['destinatario_nombre'] ?? '');
            $cuit_p = $tipo === 'compras' ? ($row['proveedor_cuit']   ?? '') : ($row['destinatario_cuit']   ?? '');

            $docs[] = [
                'id'                 => $row['id'],
                'tipo'               => $row['tipo'],
                'punto_venta'        => $row['punto_venta'],
                'numero'             => $row['numero'],
                'fecha'              => $row['fecha'],
                'nombre'             => $nombre,
                'cuit'               => $cuit_p,
                'renglones'          => $signedRenglones,
                'no_gravado'         => $noGrav,
                'perc_iibb'          => $pIIBB,
                'perc_iva'           => $pIva,
                'imp_interno'        => $iInt,
                'imp_interno_gasoil' => $iGas,
                'retencion'          => $ret,
                'total'              => $tot,
                'sign'               => $sign,
            ];
        }
        echo json_encode([
            'docs'    => $docs,
            'totales' => [
                'neto'               => round($totNeto,   2),
                'iva'                => round($totIva,    2),
                'no_gravado'         => round($totNoGrav, 2),
                'perc_iibb'          => round($totPIIBB,  2),
                'perc_iva'           => round($totPIva,   2),
                'imp_interno'        => round($totImpInt, 2),
                'imp_interno_gasoil' => round($totImpGas, 2),
                'retencion'          => round($totRet,    2),
                'total'              => round($totTotal,  2),
            ],
        ]);
        break;

    case 'informe_rango':
        $cid       = (int)($_GET['cliente_id'] ?? 0);
        $mesDesde  = (int)($_GET['mes_desde']  ?? 0);
        $anioDesde = (int)($_GET['anio_desde'] ?? 0);
        $mesHasta  = (int)($_GET['mes_hasta']  ?? 0);
        $anioHasta = (int)($_GET['anio_hasta'] ?? 0);
        if (!$cid || !$mesDesde || !$anioDesde || !$mesHasta || !$anioHasta) {
            echo json_encode(['error' => 'Parámetros incompletos']); break;
        }
        $pdDesde = $anioDesde * 100 + $mesDesde;
        $pdHasta = $anioHasta * 100 + $mesHasta;

        // Build period map
        $meses = [];
        for ($a = $anioDesde; $a <= $anioHasta; $a++) {
            $mS = ($a === $anioDesde) ? $mesDesde : 1;
            $mE = ($a === $anioHasta) ? $mesHasta : 12;
            for ($m = $mS; $m <= $mE; $m++) {
                $key = sprintf('%04d-%02d', $a, $m);
                $meses[$key] = [
                    'mes' => $m, 'anio' => $a,
                    'compras' => ['neto'=>0,'iva'=>0,'no_gravado'=>0,'perc_iibb'=>0,'perc_iva'=>0,'imp_interno'=>0,'imp_interno_gasoil'=>0,'total'=>0],
                    'ventas'  => ['neto'=>0,'iva'=>0,'no_gravado'=>0,'retencion'=>0,'total'=>0],
                ];
            }
        }

        // Compras
        $stC = $db->prepare(
            "SELECT c.tipo, c.mes_contable, c.anio_contable,
             c.no_gravado, c.perc_iibb, c.perc_iva, c.imp_interno, c.imp_interno_gasoil, c.total,
             GROUP_CONCAT(r.neto ORDER BY r.id SEPARATOR '|') AS neto_list,
             GROUP_CONCAT(r.iva  ORDER BY r.id SEPARATOR '|') AS iva_list
             FROM fact_compras c
             LEFT JOIN fact_compras_renglones r ON r.compra_id = c.id
             WHERE c.cliente_id=? AND (c.anio_contable*100+c.mes_contable) BETWEEN ? AND ?
             GROUP BY c.id"
        );
        $stC->execute([$cid, $pdDesde, $pdHasta]);
        foreach ($stC->fetchAll() as $row) {
            $key  = sprintf('%04d-%02d', $row['anio_contable'], $row['mes_contable']);
            if (!isset($meses[$key])) continue;
            $sign = getSign($row['tipo']);
            $netos = $row['neto_list'] ? explode('|', $row['neto_list']) : [];
            $ivas  = $row['iva_list']  ? explode('|', $row['iva_list'])  : [];
            $rN = 0; $rI = 0;
            foreach ($netos as $i => $n) { if ($n !== '') { $rN += (float)$n * $sign; $rI += (float)($ivas[$i]??0) * $sign; } }
            $meses[$key]['compras']['neto']               += $rN;
            $meses[$key]['compras']['iva']                += $rI;
            $meses[$key]['compras']['no_gravado']         += (float)$row['no_gravado']         * $sign;
            $meses[$key]['compras']['perc_iibb']          += (float)$row['perc_iibb']          * $sign;
            $meses[$key]['compras']['perc_iva']           += (float)$row['perc_iva']           * $sign;
            $meses[$key]['compras']['imp_interno']        += (float)$row['imp_interno']        * $sign;
            $meses[$key]['compras']['imp_interno_gasoil'] += (float)$row['imp_interno_gasoil'] * $sign;
            $meses[$key]['compras']['total']              += (float)$row['total']              * $sign;
        }

        // Ventas
        $stV = $db->prepare(
            "SELECT v.tipo, v.mes_contable, v.anio_contable,
             v.no_gravado, v.retencion, v.total,
             GROUP_CONCAT(r.neto ORDER BY r.id SEPARATOR '|') AS neto_list,
             GROUP_CONCAT(r.iva  ORDER BY r.id SEPARATOR '|') AS iva_list
             FROM fact_ventas v
             LEFT JOIN fact_ventas_renglones r ON r.venta_id = v.id
             WHERE v.cliente_id=? AND (v.anio_contable*100+v.mes_contable) BETWEEN ? AND ?
             GROUP BY v.id"
        );
        $stV->execute([$cid, $pdDesde, $pdHasta]);
        foreach ($stV->fetchAll() as $row) {
            $key  = sprintf('%04d-%02d', $row['anio_contable'], $row['mes_contable']);
            if (!isset($meses[$key])) continue;
            $sign = getSign($row['tipo']);
            $netos = $row['neto_list'] ? explode('|', $row['neto_list']) : [];
            $ivas  = $row['iva_list']  ? explode('|', $row['iva_list'])  : [];
            $rN = 0; $rI = 0;
            foreach ($netos as $i => $n) { if ($n !== '') { $rN += (float)$n * $sign; $rI += (float)($ivas[$i]??0) * $sign; } }
            $meses[$key]['ventas']['neto']      += $rN;
            $meses[$key]['ventas']['iva']       += $rI;
            $meses[$key]['ventas']['no_gravado']+= (float)$row['no_gravado'] * $sign;
            $meses[$key]['ventas']['retencion'] += (float)$row['retencion']  * $sign;
            $meses[$key]['ventas']['total']     += (float)$row['total']      * $sign;
        }

        // Round all
        foreach ($meses as &$m) {
            foreach ($m['compras'] as &$v) $v = round($v, 2);
            foreach ($m['ventas']  as &$v) $v = round($v, 2);
        }
        unset($m); // Break reference to prevent last element from being overwritten in next loop

        // Grand totals
        $totC = ['neto'=>0,'iva'=>0,'no_gravado'=>0,'perc_iibb'=>0,'perc_iva'=>0,'imp_interno'=>0,'imp_interno_gasoil'=>0,'total'=>0];
        $totV = ['neto'=>0,'iva'=>0,'no_gravado'=>0,'retencion'=>0,'total'=>0];
        foreach ($meses as $m) {
            foreach ($totC as $k => &$v) $v += $m['compras'][$k] ?? 0;
            foreach ($totV as $k => &$v) $v += $m['ventas'][$k]  ?? 0;
        }
        foreach ($totC as &$v) $v = round($v, 2);
        foreach ($totV as &$v) $v = round($v, 2);

        echo json_encode([
            'meses'       => array_values($meses),
            'tot_compras' => $totC,
            'tot_ventas'  => $totV,
            'saldo_iva'   => round($totV['iva'] - $totC['iva'], 2),
        ]);
        break;

    case 'eliminar_todo':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST requerido']); break; }
        $pdo->exec("DELETE FROM fact_compras_renglones");
        $pdo->exec("DELETE FROM fact_ventas_renglones");
        $pdo->exec("DELETE FROM fact_compras");
        $pdo->exec("DELETE FROM fact_ventas");
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Acción no válida: ' . htmlspecialchars($action)]);
}
