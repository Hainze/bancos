<?php
/**
 * bna/api/procesar_excel.php
 * Parsea extractos del Banco Nación Argentina.
 *
 * Formatos soportados:
 *  - Formato A (HomeBank): Fecha | Descripción | Debe | Haber  [| Saldo]
 *  - Formato B (simple):   Fecha | Descripción | Importe (firmado, igual que Banco Provincia)
 *
 * Auto-detecta el formato buscando columnas "debe"/"haber"/"débito"/"crédito" en el header.
 */
require_once dirname(__DIR__, 2) . '/auth/check_api.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']); exit;
}
if (!isset($_FILES['excel']) || $_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No se recibió archivo válido']); exit;
}

$ext = strtolower(pathinfo($_FILES['excel']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx','xls','csv'])) {
    echo json_encode(['error' => 'Solo se permiten .xlsx, .xls o .csv']); exit;
}

$tmpPath = sys_get_temp_dir() . '/' . uniqid('bna_') . '.' . $ext;
move_uploaded_file($_FILES['excel']['tmp_name'], $tmpPath);

try {
    $pdo = getDB();

    // ── Auto-create tables ───────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS bna_lotes (
        id                   INT AUTO_INCREMENT PRIMARY KEY,
        codigo               VARCHAR(50) NOT NULL UNIQUE,
        archivo_nombre       VARCHAR(200),
        total_filas          INT DEFAULT 0,
        filas_clasificadas   INT DEFAULT 0,
        filas_sin_clasificar INT DEFAULT 0,
        created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS bna_movimientos (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        fecha            DATE NOT NULL,
        descripcion      TEXT NOT NULL,
        importe          DECIMAL(15,2) NOT NULL,
        tipo             VARCHAR(10) NOT NULL,
        categoria_id     INT DEFAULT NULL,
        categoria_nombre VARCHAR(100) DEFAULT NULL,
        codigo           VARCHAR(20) DEFAULT NULL,
        cuit             VARCHAR(20) DEFAULT NULL,
        dni              VARCHAR(20) DEFAULT NULL,
        numero_cuenta    VARCHAR(50) DEFAULT NULL,
        nombre_cliente   VARCHAR(150) DEFAULT NULL,
        lote_importacion VARCHAR(50) DEFAULT NULL,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_fecha (fecha),
        INDEX idx_tipo (tipo),
        INDEX idx_lote (lote_importacion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Cargar categorías y palabras clave (compartidas) ──
    $cats = $pdo->query("SELECT c.id, c.nombre, c.codigo, c.tipo, p.palabra
                         FROM palabras_clave p
                         JOIN categorias c ON p.categoria_id = c.id
                         WHERE c.activo=1 AND p.activo=1
                         ORDER BY c.id")->fetchAll();
    $keywordMap = [];
    foreach ($cats as $cat) {
        $keywordMap[] = [
            'id'     => $cat['id'],
            'nombre' => $cat['nombre'],
            'codigo' => $cat['codigo'],
            'tipo'   => $cat['tipo'],
            'palabra'=> mb_strtolower(trim($cat['palabra'])),
        ];
    }

    // ── Cargar clientes ───────────────────────────────────
    $clientes  = $pdo->query("SELECT * FROM clientes WHERE activo=1")->fetchAll();
    $cuitMap   = []; $cuentaMap = []; $dniMap = [];
    foreach ($clientes as $cl) {
        if ($cl['cuit'])          $cuitMap[trim($cl['cuit'])]            = $cl;
        if ($cl['numero_cuenta']) $cuentaMap[trim($cl['numero_cuenta'])] = $cl;
        if ($cl['dni'])           $dniMap[trim($cl['dni'])]              = $cl;
    }

    // ── Leer Excel ────────────────────────────────────────
    $rawRows = parseExcelBNA($tmpPath, $ext);
    if (empty($rawRows)) {
        echo json_encode(['error' => 'El archivo no tiene datos o no pudo leerse']); exit;
    }

    // ── Detectar formato en la primera fila (header) ──────
    $header   = array_map('mb_strtolower', array_map('trim', $rawRows[0] ?? []));
    $colFecha = -1; $colDesc = -1; $colDebe = -1; $colHaber = -1; $colImp = -1;

    foreach ($header as $i => $h) {
        $h = normalizeStr($h);
        if (preg_match('/^fecha/', $h))                         $colFecha = $i;
        elseif (preg_match('/^desc|concepto|detalle/', $h))     $colDesc  = $i;
        elseif (preg_match('/debe|debito|debitos|cargo/', $h))  $colDebe  = $i;
        elseif (preg_match('/haber|credito|creditos|abono/', $h)) $colHaber = $i;
        elseif (preg_match('/importe|monto/', $h))              $colImp   = $i;
    }

    // Defaults si no se encontraron encabezados
    $formatoDH = ($colDebe >= 0 && $colHaber >= 0); // formato Debe/Haber
    if ($colFecha < 0) $colFecha = 0;
    if ($colDesc  < 0) $colDesc  = 1;
    if (!$formatoDH && $colImp < 0) $colImp = 2;
    if ($formatoDH) {
        if ($colDebe  < 0) $colDebe  = 2;
        if ($colHaber < 0) $colHaber = 3;
    }

    // ── Procesar filas ────────────────────────────────────
    $loteCode  = 'BNA-' . date('Ymd-His');
    $processed = [];
    $clasificadas = 0;
    $dataRows  = array_slice($rawRows, 1); // saltar header

    foreach ($dataRows as $row) {
        $fechaRaw   = $row[$colFecha] ?? '';
        $descripcion= trim($row[$colDesc] ?? '');

        if ($formatoDH) {
            $debe  = parseBNANum($row[$colDebe]  ?? '');
            $haber = parseBNANum($row[$colHaber] ?? '');
            if ($debe == 0 && $haber == 0 && !$descripcion) continue;
            // Debe = salida de dinero (gasto), Haber = entrada (ingreso)
            $importe = $haber > 0 ? $haber : -$debe;
        } else {
            $importe = parseBNANum($row[$colImp] ?? 0);
        }

        if (empty($descripcion) && $importe == 0) continue;

        $fecha = parseDateBNA($fechaRaw);
        $tipo  = $importe >= 0 ? 'ingreso' : 'gasto';

        // Clasificar por palabra clave
        $cat_id = null; $cat_nom = null; $cat_cod = null;
        $descLower = mb_strtolower($descripcion);
        foreach ($keywordMap as $kw) {
            if ($kw['tipo'] !== 'ambos' && $kw['tipo'] !== $tipo) continue;
            if (strpos($descLower, $kw['palabra']) !== false) {
                $cat_id = $kw['id']; $cat_nom = $kw['nombre']; $cat_cod = $kw['codigo'];
                break;
            }
        }
        if ($cat_id) $clasificadas++;

        // Extraer datos de cliente (solo ingresos)
        $cuit = null; $dni = null; $cuenta = null; $nombre = null;
        if ($tipo === 'ingreso') {
            if (preg_match('/(?:cuit[:\s]+)?[(\s]?(\d{11})[)\s]?/i', $descripcion, $m)) $cuit = $m[1];
            if (!$cuit && preg_match('/dni[:\s]+(\d{7,8})/i', $descripcion, $m)) $dni = $m[1];
            if (preg_match('/(?:C[:\.]|ORI:[^-]+-[^-]+-[^-]+-[^-]+-?)(\d{8,})/i', $descripcion, $m)) $cuenta = $m[1];
            if (!$cuenta && preg_match('/BIP.+?-([A-Z0-9]+)$/i', $descripcion, $m) && strlen($m[1]) >= 8) $cuenta = $m[1];
            if (preg_match('/(?:TRANSF(?:ERENCIA)?\s+DE\s+)([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s,\.]+?)(?:\s*\(|\s+FAC|\s+\d)/i', $descripcion, $m)) $nombre = trim($m[1], ' ,');
            elseif (preg_match('/(?:CUIT\s+\d{11}\s+)([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s]+)$/i', $descripcion, $m)) $nombre = trim($m[1]);
            elseif (preg_match('/DNI\s+\d{7,8}\s+([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s]+)$/i', $descripcion, $m)) $nombre = trim($m[1]);

            $clienteMatch = null;
            if ($cuit && isset($cuitMap[$cuit]))     $clienteMatch = $cuitMap[$cuit];
            if (!$clienteMatch && $dni && isset($dniMap[$dni])) $clienteMatch = $dniMap[$dni];
            if (!$clienteMatch && $cuenta && isset($cuentaMap[$cuenta])) $clienteMatch = $cuentaMap[$cuenta];
            if ($clienteMatch) {
                if (!$nombre) $nombre = $clienteMatch['nombre'];
                if (!$cuit)   $cuit   = $clienteMatch['cuit'];
                if (!$dni)    $dni    = $clienteMatch['dni'];
                if (!$cuenta) $cuenta = $clienteMatch['numero_cuenta'];
            }
        }

        $processed[] = [
            'fecha'        => $fecha,
            'descripcion'  => $descripcion,
            'importe'      => $importe,
            'tipo'         => $tipo,
            'categoria_id' => $cat_id,
            'categoria'    => $cat_nom,
            'codigo'       => $cat_cod,
            'cuit'         => $cuit,
            'dni'          => $dni,
            'numero_cuenta'=> $cuenta,
            'nombre'       => $nombre,
            'lote'         => $loteCode,
        ];
    }

    if (empty($processed)) {
        echo json_encode(['error' => 'No se encontraron filas de datos válidas. Verificá el formato del archivo.']); exit;
    }

    // ── Guardar en bna_movimientos ────────────────────────
    $stmt = $pdo->prepare("INSERT INTO bna_movimientos
        (fecha, descripcion, importe, tipo, categoria_id, categoria_nombre, codigo, cuit, dni, numero_cuenta, nombre_cliente, lote_importacion)
        VALUES (:fecha, :desc, :imp, :tipo, :cat_id, :cat_nom, :cod, :cuit, :dni, :cuenta, :nombre, :lote)");

    foreach ($processed as $p) {
        $stmt->execute([
            ':fecha'  => $p['fecha'] ?: date('Y-m-d'),
            ':desc'   => $p['descripcion'],
            ':imp'    => $p['importe'],
            ':tipo'   => $p['tipo'],
            ':cat_id' => $p['categoria_id'],
            ':cat_nom'=> $p['categoria'],
            ':cod'    => $p['codigo'],
            ':cuit'   => $p['cuit'],
            ':dni'    => $p['dni'],
            ':cuenta' => $p['numero_cuenta'],
            ':nombre' => $p['nombre'],
            ':lote'   => $p['lote'],
        ]);
    }

    $sinClasificar = count($processed) - $clasificadas;
    $pdo->prepare("INSERT INTO bna_lotes (codigo, archivo_nombre, total_filas, filas_clasificadas, filas_sin_clasificar) VALUES (?,?,?,?,?)")
        ->execute([$loteCode, $_FILES['excel']['name'], count($processed), $clasificadas, $sinClasificar]);

    @unlink($tmpPath);

    echo json_encode([
        'success'        => true,
        'lote'           => $loteCode,
        'total'          => count($processed),
        'clasificadas'   => $clasificadas,
        'sin_clasificar' => $sinClasificar,
        'formato'        => $formatoDH ? 'Debe/Haber (BNA)' : 'Importe firmado',
        'rows'           => $processed,
    ]);

} catch (Exception $e) {
    @unlink($tmpPath);
    echo json_encode(['error' => $e->getMessage()]);
}

// ── HELPERS ──────────────────────────────────────────────

function parseExcelBNA($path, $ext) {
    $rows = [];
    if ($ext === 'csv') {
        // Intentar con punto y coma, luego con coma
        if (($h = fopen($path, 'r')) !== false) {
            $first = fgets($h); rewind($h);
            $delim = (substr_count($first, ';') >= substr_count($first, ',')) ? ';' : ',';
            while (($row = fgetcsv($h, 0, $delim)) !== false) {
                $rows[] = $row;
            }
            fclose($h);
        }
    } else {
        $rows = readXlsxBNA($path);
    }
    return $rows;
}

function readXlsxBNA($path) {
    $rows = [];
    $zip  = new ZipArchive();
    if ($zip->open($path) !== true) return $rows;

    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $xml = simplexml_load_string($ssXml);
        if ($xml) {
            foreach ($xml->si as $si) {
                if (isset($si->t)) { $sharedStrings[] = (string)$si->t; }
                else { $str=''; foreach ($si->r as $r) $str .= (string)$r->t; $sharedStrings[] = $str; }
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$sheetXml) return $rows;

    $xml = simplexml_load_string($sheetXml);
    if (!$xml) return $rows;

    foreach ($xml->sheetData->row as $row) {
        $rowData = []; $lastCol = -1;
        foreach ($row->c as $cell) {
            $ref = (string)$cell['r'];
            preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
            $colIdx = colLetterBNA($m[1]);
            while (count($rowData) < $colIdx) $rowData[] = '';
            $type = (string)$cell['t'];
            $val  = (string)$cell->v;
            if ($type === 's') { $val = $sharedStrings[(int)$val] ?? ''; }
            elseif (isset($cell->v) && $colIdx === 0 && is_numeric($val) && (float)$val > 40000) {
                $val = excelDateBNA((float)$val);
            }
            $rowData[] = $val;
        }
        $filtered = array_filter($rowData, fn($v) => $v !== '' && $v !== null);
        if (!empty($filtered)) $rows[] = $rowData;
    }
    return $rows;
}

function colLetterBNA($letters) {
    $idx = 0;
    foreach (str_split(strtoupper($letters)) as $ch) { $idx = $idx * 26 + (ord($ch) - 64); }
    return $idx - 1;
}

function excelDateBNA($serial) { return date('Y-m-d', (int)(($serial - 25569) * 86400)); }

function parseDateBNA($val) {
    if (empty($val)) return null;
    $val = trim($val);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) return $val;
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $val, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $val, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    $ts = strtotime($val);
    return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
}

function parseBNANum($val) {
    if (is_numeric($val)) return (float)$val;
    $val = str_replace(['$','  ',"\xc2\xa0"], ['','',''], trim($val));
    if (preg_match('/^-?[\d\.]+,\d{1,2}$/', $val)) {
        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);
    } else {
        $val = str_replace([',', ' '], ['', ''], $val);
    }
    return (float)$val;
}

function normalizeStr($s) {
    return iconv('UTF-8', 'ASCII//TRANSLIT', mb_strtolower($s)) ?: mb_strtolower($s);
}
?>
