<?php
/**
 * credicoop/api/procesar_excel.php
 * Parsea extractos del Banco Credicoop.
 * Detecta automáticamente formato Debe/Haber o Importe único.
 */
require_once dirname(__DIR__, 2) . '/auth/check_api.php';
header('Content-Type: application/json');

// ── Acumula info de debug en cada paso ─────────────────────────────────────────
$debug = [];

function dbg(&$debug, $key, $val) { $debug[$key] = $val; }

// ── Validaciones iniciales ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido', 'debug' => $debug]); exit;
}

$uploadErr  = $_FILES['excel']['error']    ?? -1;
$uploadName = $_FILES['excel']['name']     ?? '';
$uploadSize = $_FILES['excel']['size']     ?? 0;
$uploadTmp  = $_FILES['excel']['tmp_name'] ?? '';

dbg($debug, '1_upload', [
    'error_code' => $uploadErr,
    'nombre'     => $uploadName,
    'size_bytes' => $uploadSize,
    'tmp_name'   => $uploadTmp,
]);

if ($uploadErr !== UPLOAD_ERR_OK) {
    $errMsg = [
        UPLOAD_ERR_INI_SIZE   => 'Archivo supera upload_max_filesize en php.ini',
        UPLOAD_ERR_FORM_SIZE  => 'Archivo supera MAX_FILE_SIZE del formulario',
        UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente',
        UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor',
        UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en disco',
        UPLOAD_ERR_EXTENSION  => 'Una extensión PHP bloqueó la subida',
    ][$uploadErr] ?? "Código de error desconocido: $uploadErr";
    echo json_encode(['error' => "Error en la subida: $errMsg", 'debug' => $debug]); exit;
}

$ext = strtolower(pathinfo($uploadName, PATHINFO_EXTENSION));
dbg($debug, '2_extension', $ext);

if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
    echo json_encode(['error' => "Extensión '$ext' no permitida. Usar .xlsx .xls o .csv", 'debug' => $debug]); exit;
}

// ── Mover a temp ───────────────────────────────────────────────────────────────
$tmpDir  = sys_get_temp_dir();
$tmpPath = $tmpDir . DIRECTORY_SEPARATOR . uniqid('coop_') . '.' . $ext;
$moved   = move_uploaded_file($uploadTmp, $tmpPath);

dbg($debug, '3_tmp', [
    'sys_temp_dir' => $tmpDir,
    'tmp_path'     => $tmpPath,
    'move_ok'      => $moved,
    'file_exists'  => file_exists($tmpPath),
    'file_size'    => $moved ? filesize($tmpPath) : 0,
]);

if (!$moved || !file_exists($tmpPath)) {
    echo json_encode(['error' => 'No se pudo guardar el archivo temporalmente.', 'debug' => $debug]); exit;
}

// ── Detectar si es realmente un ZIP (xlsx) o binario xls ──────────────────────
$magic4 = '';
if ($fh = fopen($tmpPath, 'rb')) { $magic4 = bin2hex(fread($fh, 4)); fclose($fh); }
$isZip  = ($magic4 === '504b0304');  // PK\x03\x04
$isBiff = (substr($magic4, 0, 4) === 'd0cf');  // OLE2 magic: D0CF11E0

dbg($debug, '4_magic', [
    'hex_4bytes' => $magic4,
    'es_zip_xlsx' => $isZip,
    'es_ole2_xls' => $isBiff,
]);

if ($ext === 'xls' && $isBiff) {
    @unlink($tmpPath);
    echo json_encode([
        'error' => 'El archivo es .xls en formato OLE2/BIFF (Excel 97-2003). ' .
                   'Este servidor no tiene soporte para leer ese formato binario. ' .
                   'Por favor guardá el archivo como .xlsx desde Excel y volvé a subirlo.',
        'debug' => $debug,
    ]); exit;
}

try {
    $pdo = getDB();

    $pdo->exec("CREATE TABLE IF NOT EXISTS coop_lotes (
        id INT AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(50) NOT NULL UNIQUE,
        archivo_nombre VARCHAR(200), total_filas INT DEFAULT 0,
        filas_clasificadas INT DEFAULT 0, filas_sin_clasificar INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS coop_movimientos (
        id INT AUTO_INCREMENT PRIMARY KEY, fecha DATE NOT NULL,
        descripcion TEXT NOT NULL, importe DECIMAL(15,2) NOT NULL,
        tipo VARCHAR(10) NOT NULL, categoria_id INT DEFAULT NULL,
        categoria_nombre VARCHAR(100) DEFAULT NULL, codigo VARCHAR(20) DEFAULT NULL,
        cuit VARCHAR(20) DEFAULT NULL, dni VARCHAR(20) DEFAULT NULL,
        numero_cuenta VARCHAR(50) DEFAULT NULL, nombre_cliente VARCHAR(150) DEFAULT NULL,
        lote_importacion VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_fecha (fecha), INDEX idx_tipo (tipo), INDEX idx_lote (lote_importacion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $cats = $pdo->query("SELECT c.id,c.nombre,c.codigo,c.tipo,p.palabra FROM palabras_clave p JOIN categorias c ON p.categoria_id=c.id WHERE c.activo=1 AND p.activo=1 ORDER BY c.id")->fetchAll();
    $keywordMap = [];
    foreach ($cats as $cat) $keywordMap[] = ['id'=>$cat['id'],'nombre'=>$cat['nombre'],'codigo'=>$cat['codigo'],'tipo'=>$cat['tipo'],'palabra'=>mb_strtolower(trim($cat['palabra']))];

    $clientes = $pdo->query("SELECT * FROM clientes WHERE activo=1")->fetchAll();
    $cuitMap = []; $cuentaMap = []; $dniMap = [];
    foreach ($clientes as $cl) {
        if ($cl['cuit'])          $cuitMap[trim($cl['cuit'])]            = $cl;
        if ($cl['numero_cuenta']) $cuentaMap[trim($cl['numero_cuenta'])] = $cl;
        if ($cl['dni'])           $dniMap[trim($cl['dni'])]              = $cl;
    }

    // ── Parsear el archivo ─────────────────────────────────────────────────────
    $rawRows = parseExcelCOOP($tmpPath, $ext, $debug);

    dbg($debug, '6_parse_result', [
        'total_rawRows'   => count($rawRows),
        'header_raw'      => $rawRows[0] ?? [],
        'muestra_fila2'   => $rawRows[1] ?? [],
        'muestra_fila3'   => $rawRows[2] ?? [],
    ]);

    if (empty($rawRows)) {
        echo json_encode(['error' => 'El archivo no tiene datos o no pudo leerse.', 'debug' => $debug]); exit;
    }

    // ── Detección de columnas ──────────────────────────────────────────────────
    $header = array_map('mb_strtolower', array_map('trim', $rawRows[0] ?? []));
    $colFecha = -1; $colDesc = -1; $colDebe = -1; $colHaber = -1; $colImp = -1;

    foreach ($header as $i => $h) {
        $hn = normalizeStrCOOP($h);
        if      (preg_match('/^fecha/', $hn))                                $colFecha = $i;
        elseif  (preg_match('/^desc|concepto|detalle|movimiento/', $hn))     $colDesc  = $i;
        elseif  (preg_match('/debe|debito|debitos|cargo|extraccion/', $hn))  $colDebe  = $i;
        elseif  (preg_match('/haber|credito|creditos|abono|deposito/', $hn)) $colHaber = $i;
        elseif  (preg_match('/importe|monto/', $hn))                         $colImp   = $i;
    }

    $formatoDH = ($colDebe >= 0 && $colHaber >= 0);
    if ($colFecha < 0) $colFecha = 0;
    if ($colDesc  < 0) $colDesc  = 1;
    if (!$formatoDH && $colImp < 0) $colImp = 2;
    if ($formatoDH) { if ($colDebe < 0) $colDebe = 2; if ($colHaber < 0) $colHaber = 3; }

    dbg($debug, '7_columnas', [
        'header_normalizado' => $header,
        'colFecha'  => $colFecha,
        'colDesc'   => $colDesc,
        'colDebe'   => $colDebe,
        'colHaber'  => $colHaber,
        'colImp'    => $colImp,
        'formatoDH' => $formatoDH,
    ]);

    // ── Procesar filas ─────────────────────────────────────────────────────────
    $loteCode  = 'COOP-' . date('Ymd-His');
    $processed = [];
    $clasificadas = 0;
    $skipped = 0;

    foreach (array_slice($rawRows, 1) as $rowIdx => $row) {
        $fechaRaw    = $row[$colFecha] ?? '';
        $descripcion = trim($row[$colDesc] ?? '');

        if ($formatoDH) {
            $debe  = parseNumCOOP($row[$colDebe]  ?? '');
            $haber = parseNumCOOP($row[$colHaber] ?? '');
            if ($debe == 0 && $haber == 0 && !$descripcion) { $skipped++; continue; }
            $importe = $haber > 0 ? $haber : -$debe;
        } else {
            $importe = parseNumCOOP($row[$colImp] ?? 0);
        }
        if (empty($descripcion) && $importe == 0) { $skipped++; continue; }

        $fecha = parseDateCOOP($fechaRaw);
        $tipo  = $importe >= 0 ? 'ingreso' : 'gasto';

        $cat_id = null; $cat_nom = null; $cat_cod = null;
        $descLower = mb_strtolower($descripcion);
        foreach ($keywordMap as $kw) {
            if ($kw['tipo'] !== 'ambos' && $kw['tipo'] !== $tipo) continue;
            if (strpos($descLower, $kw['palabra']) !== false) {
                $cat_id = $kw['id']; $cat_nom = $kw['nombre']; $cat_cod = $kw['codigo']; break;
            }
        }
        if ($cat_id) $clasificadas++;

        $cuit = null; $dni = null; $cuenta = null; $nombre = null;
        if ($tipo === 'ingreso') {
            if (preg_match('/(?:cuit[:\s]+)?[(\s]?(\d{11})[)\s]?/i', $descripcion, $m)) $cuit = $m[1];
            if (!$cuit && preg_match('/dni[:\s]+(\d{7,8})/i', $descripcion, $m)) $dni = $m[1];
            if (preg_match('/(?:C[:\.]|ORI:[^-]+-[^-]+-[^-]+-[^-]+-?)(\d{8,})/i', $descripcion, $m)) $cuenta = $m[1];
            if (preg_match('/(?:TRANSF(?:ERENCIA)?\s+DE\s+)([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s,\.]+?)(?:\s*\(|\s+FAC|\s+\d)/i', $descripcion, $m)) $nombre = trim($m[1], ' ,');
            elseif (preg_match('/(?:CUIT\s+\d{11}\s+)([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s]+)$/i', $descripcion, $m)) $nombre = trim($m[1]);
            elseif (preg_match('/DNI\s+\d{7,8}\s+([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s]+)$/i', $descripcion, $m)) $nombre = trim($m[1]);
            $cm = null;
            if ($cuit && isset($cuitMap[$cuit])) $cm = $cuitMap[$cuit];
            if (!$cm && $dni && isset($dniMap[$dni])) $cm = $dniMap[$dni];
            if (!$cm && $cuenta && isset($cuentaMap[$cuenta])) $cm = $cuentaMap[$cuenta];
            if ($cm) { if (!$nombre) $nombre = $cm['nombre']; if (!$cuit) $cuit = $cm['cuit']; if (!$dni) $dni = $cm['dni']; if (!$cuenta) $cuenta = $cm['numero_cuenta']; }
        }

        $processed[] = [
            'fecha' => $fecha, 'descripcion' => $descripcion, 'importe' => $importe, 'tipo' => $tipo,
            'categoria_id' => $cat_id, 'categoria' => $cat_nom, 'codigo' => $cat_cod,
            'cuit' => $cuit, 'dni' => $dni, 'numero_cuenta' => $cuenta, 'nombre' => $nombre, 'lote' => $loteCode,
        ];
    }

    dbg($debug, '8_filas', [
        'procesadas' => count($processed),
        'saltadas'   => $skipped,
        'clasificadas' => $clasificadas,
    ]);

    if (empty($processed)) {
        echo json_encode(['error' => 'No se encontraron filas válidas. Verificá el formato.', 'debug' => $debug]); exit;
    }

    // ── Guardar en BD ──────────────────────────────────────────────────────────
    $stmt = $pdo->prepare("INSERT INTO coop_movimientos (fecha,descripcion,importe,tipo,categoria_id,categoria_nombre,codigo,cuit,dni,numero_cuenta,nombre_cliente,lote_importacion) VALUES (:fecha,:desc,:imp,:tipo,:cat_id,:cat_nom,:cod,:cuit,:dni,:cuenta,:nombre,:lote)");
    foreach ($processed as $p) {
        $stmt->execute([':fecha'=>$p['fecha']?:date('Y-m-d'),':desc'=>$p['descripcion'],':imp'=>$p['importe'],':tipo'=>$p['tipo'],':cat_id'=>$p['categoria_id'],':cat_nom'=>$p['categoria'],':cod'=>$p['codigo'],':cuit'=>$p['cuit'],':dni'=>$p['dni'],':cuenta'=>$p['numero_cuenta'],':nombre'=>$p['nombre'],':lote'=>$p['lote']]);
    }

    $sinClas = count($processed) - $clasificadas;
    $pdo->prepare("INSERT INTO coop_lotes (codigo,archivo_nombre,total_filas,filas_clasificadas,filas_sin_clasificar) VALUES (?,?,?,?,?)")
        ->execute([$loteCode, $uploadName, count($processed), $clasificadas, $sinClas]);

    @unlink($tmpPath);

    echo json_encode([
        'success'       => true,
        'lote'          => $loteCode,
        'total'         => count($processed),
        'clasificadas'  => $clasificadas,
        'sin_clasificar'=> $sinClas,
        'formato'       => $formatoDH ? 'Debe/Haber' : 'Importe firmado',
        'rows'          => $processed,
        'debug'         => $debug,
    ]);

} catch (Exception $e) {
    @unlink($tmpPath ?? '');
    echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'debug' => $debug]);
}

// ── Funciones de parseo ────────────────────────────────────────────────────────

function parseExcelCOOP($path, $ext, &$debug) {
    $rows = [];

    if ($ext === 'csv') {
        dbg($debug, '5a_csv', 'leyendo CSV');
        if (($h = fopen($path, 'r')) !== false) {
            $f = fgets($h); rewind($h);
            $d = (substr_count($f, ';') >= substr_count($f, ',')) ? ';' : ',';
            dbg($debug, '5b_csv_delim', $d);
            while (($r = fgetcsv($h, 0, $d)) !== false) $rows[] = $r;
            fclose($h);
        }
        return $rows;
    }

    // xlsx / xls
    return readXlsxCOOP($path, $debug);
}

function readXlsxCOOP($path, &$debug) {
    $rows = [];
    $zip  = new ZipArchive();
    $openResult = $zip->open($path);

    dbg($debug, '5a_zip_open', [
        'result'       => $openResult,   // true = OK, int = error code
        'result_texto' => $openResult === true ? 'OK' : zipErrTxt($openResult),
    ]);

    if ($openResult !== true) {
        dbg($debug, '5b_zip_error', 'No se pudo abrir como ZIP. El archivo puede ser .xls OLE2 o estar corrupto.');
        return $rows;
    }

    // Listar contenido del ZIP para diagnóstico
    $zipFiles = [];
    for ($i = 0; $i < $zip->numFiles; $i++) $zipFiles[] = $zip->getNameIndex($i);
    dbg($debug, '5c_zip_contenido', $zipFiles);

    // Buscar ruta real de la hoja desde relationships
    $sheetPath = 'xl/worksheets/sheet1.xml';
    if ($wbRels = $zip->getFromName('xl/_rels/workbook.xml.rels')) {
        if (preg_match('/Type="[^"]*\/worksheet"\s+Target="([^"]+)"/i', $wbRels, $m)) {
            $target    = $m[1];
            $sheetPath = (strpos($target, '/') === 0) ? ltrim($target, '/') : 'xl/' . $target;
        }
    }
    dbg($debug, '5d_sheet_path', $sheetPath);

    // Shared strings
    $ss = [];
    $sxRaw = $zip->getFromName('xl/sharedStrings.xml');
    dbg($debug, '5e_sharedStrings', $sxRaw !== false ? 'encontrado (' . strlen($sxRaw) . ' bytes)' : 'NO encontrado');
    if ($sxRaw) {
        $sxClean = preg_replace('/\sxmlns[^=]*="[^"]*"/i', '', $sxRaw);
        $xml = @simplexml_load_string($sxClean);
        if ($xml) {
            foreach ($xml->si as $si) {
                if (isset($si->t)) $ss[] = (string)$si->t;
                else { $s = ''; foreach ($si->r as $r) $s .= (string)$r->t; $ss[] = $s; }
            }
        }
        dbg($debug, '5f_sharedStrings_count', count($ss));
    }

    // Hoja de datos
    $shX = $zip->getFromName($sheetPath)
        ?: $zip->getFromName('xl/worksheets/Sheet1.xml')
        ?: $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    dbg($debug, '5g_sheet_xml', $shX !== false ? 'encontrado (' . strlen($shX) . ' bytes)' : 'NO encontrado');
    if (!$shX) return $rows;

    $shClean = preg_replace('/\sxmlns[^=]*="[^"]*"/i', '', $shX);
    $xml     = @simplexml_load_string($shClean);
    dbg($debug, '5h_simplexml', $xml !== false ? 'OK' : 'FALLÓ simplexml_load_string');
    if (!$xml) return $rows;

    foreach ($xml->sheetData->row as $row) {
        $rd = [];
        foreach ($row->c as $cell) {
            $ref = (string)$cell['r'];
            if (!preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) continue;
            $ci = colCOOP($m[1]);
            while (count($rd) < $ci) $rd[] = '';
            $t = (string)$cell['t'];
            $v = (string)$cell->v;
            if ($t === 's')          $v = $ss[(int)$v] ?? '';
            elseif ($t === 'inlineStr') $v = (string)($cell->is->t ?? '');
            $rd[] = $v;
        }
        $hasDato = false;
        foreach ($rd as $val) { if ($val !== '') { $hasDato = true; break; } }
        if ($hasDato) $rows[] = $rd;
    }

    dbg($debug, '5i_rows_parsed', count($rows));
    return $rows;
}

function zipErrTxt($code) {
    return [
        ZipArchive::ER_EXISTS => 'ER_EXISTS: El archivo ya existe',
        ZipArchive::ER_INCONS => 'ER_INCONS: ZIP inconsistente',
        ZipArchive::ER_INVAL  => 'ER_INVAL: Argumento inválido',
        ZipArchive::ER_MEMORY => 'ER_MEMORY: Sin memoria',
        ZipArchive::ER_NOENT  => 'ER_NOENT: Archivo no encontrado',
        ZipArchive::ER_NOZIP  => 'ER_NOZIP: No es un archivo ZIP',
        ZipArchive::ER_OPEN   => 'ER_OPEN: No se puede abrir el archivo',
        ZipArchive::ER_READ   => 'ER_READ: Error de lectura',
        ZipArchive::ER_SEEK   => 'ER_SEEK: Error de seek',
    ][$code] ?? "Código desconocido: $code";
}

function colCOOP($l) {
    $i = 0;
    foreach (str_split(strtoupper($l)) as $c) $i = $i * 26 + (ord($c) - 64);
    return $i - 1;
}

function dateCOOP($s) { return date('Y-m-d', (int)(($s - 25569) * 86400)); }

function parseDateCOOP($v) {
    if (empty($v)) return null;
    $v = trim($v);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $v, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $v, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    $t = strtotime($v);
    return $t ? date('Y-m-d', $t) : date('Y-m-d');
}

function parseNumCOOP($v) {
    if (is_numeric($v)) return (float)$v;
    $v = str_replace(['$', '  ', "\xc2\xa0"], ['', '', ''], trim((string)$v));
    if (preg_match('/^-?[\d\.]+,\d{1,2}$/', $v)) { $v = str_replace('.', '', $v); $v = str_replace(',', '.', $v); }
    else $v = str_replace([',', ' '], ['', ''], $v);
    return (float)$v;
}

function normalizeStrCOOP($s) {
    return iconv('UTF-8', 'ASCII//TRANSLIT', mb_strtolower($s)) ?: mb_strtolower($s);
}
?>
