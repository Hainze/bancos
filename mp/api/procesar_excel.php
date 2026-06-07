<?php
/**
 * mp/api/procesar_excel.php
 * Parsea extractos de Mercado Pago. Auto-detecta columnas y formato.
 *
 * Formatos soportados:
 *  A) Monto firmado:   Fecha | Descripción/Tipo | Monto/Importe (positivo=ingreso, negativo=egreso)
 *  B) Crédito/Débito:  Fecha | Descripción | Crédito | Débito
 *  C) MP estándar:     Fecha | Tipo de movimiento | Descripción | Monto | Disponible
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

$tmpPath = sys_get_temp_dir() . '/' . uniqid('mp_') . '.' . $ext;
move_uploaded_file($_FILES['excel']['tmp_name'], $tmpPath);

try {
    $pdo = getDB();

    // ── Auto-crear tablas ────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS mp_lotes (
        id                   INT AUTO_INCREMENT PRIMARY KEY,
        codigo               VARCHAR(50) NOT NULL UNIQUE,
        archivo_nombre       VARCHAR(200),
        total_filas          INT DEFAULT 0,
        filas_clasificadas   INT DEFAULT 0,
        filas_sin_clasificar INT DEFAULT 0,
        created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS mp_movimientos (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        fecha            DATE NOT NULL,
        descripcion      TEXT NOT NULL,
        tipo_movimiento  VARCHAR(150) DEFAULT NULL,
        importe          DECIMAL(15,2) NOT NULL,
        tipo             VARCHAR(10) NOT NULL,
        categoria_id     INT DEFAULT NULL,
        categoria_nombre VARCHAR(100) DEFAULT NULL,
        codigo           VARCHAR(20) DEFAULT NULL,
        referencia       VARCHAR(100) DEFAULT NULL,
        nombre_cliente   VARCHAR(150) DEFAULT NULL,
        lote_importacion VARCHAR(50) DEFAULT NULL,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_fecha (fecha),
        INDEX idx_tipo (tipo),
        INDEX idx_lote (lote_importacion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Cargar categorías y palabras clave (compartidas) ─────
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

    // ── Cargar clientes ───────────────────────────────────────
    $clientes  = $pdo->query("SELECT * FROM clientes WHERE activo=1")->fetchAll();
    $cuitMap   = []; $cuentaMap = []; $dniMap = [];
    foreach ($clientes as $cl) {
        if ($cl['cuit'])          $cuitMap[trim($cl['cuit'])]            = $cl;
        if ($cl['numero_cuenta']) $cuentaMap[trim($cl['numero_cuenta'])] = $cl;
        if ($cl['dni'])           $dniMap[trim($cl['dni'])]              = $cl;
    }

    // ── Leer Excel ────────────────────────────────────────────
    $rawRows = parseExcelMP($tmpPath, $ext);
    if (empty($rawRows)) {
        echo json_encode(['error' => 'El archivo no tiene datos o no pudo leerse']); exit;
    }

    // ── Detectar columnas ─────────────────────────────────────
    $header = $rawRows[0] ?? [];
    $normHeader = array_map('normStrMP', $header);

    $colFecha   = -1; $colDesc  = -1; $colTipo   = -1;
    $colMonto   = -1; $colCred  = -1; $colDeb    = -1;
    $colRef     = -1; $colDisp  = -1;

    foreach ($normHeader as $i => $h) {
        if      (preg_match('/^fecha/', $h))                                      $colFecha = $i;
        elseif  (preg_match('/tipo.*mov|tipo.*op|tipo.*trans/', $h))              $colTipo  = $i;
        elseif  (preg_match('/^desc|detalle|concepto|motivo/', $h))               $colDesc  = $i;
        elseif  (preg_match('/^monto|^importe|^valor/', $h))                      $colMonto = $i;
        elseif  (preg_match('/credito|ingreso|haber|entrada/', $h))               $colCred  = $i;
        elseif  (preg_match('/debito|egreso|debe|salida/', $h))                   $colDeb   = $i;
        elseif  (preg_match('/ref|id.*op|n.*trans|comprobante/', $h))             $colRef   = $i;
        elseif  (preg_match('/disponible|saldo/', $h))                            $colDisp  = $i;
    }

    // Defaults posicionales si no hay encabezados claros
    if ($colFecha < 0)  $colFecha = 0;

    // Detectar formato
    $formatoCreditoDebito = ($colCred >= 0 && $colDeb >= 0);
    $formatoMPEstandar    = ($colTipo >= 0 && $colMonto >= 0);

    if (!$formatoCreditoDebito && $colMonto < 0) {
        // Buscar la primera columna numérica como monto
        $dataRow = $rawRows[1] ?? [];
        for ($i = 1; $i < count($dataRow); $i++) {
            if (is_numeric(str_replace([',','.','$',' '], '', $dataRow[$i])) && $i !== $colFecha) {
                $colMonto = $i; break;
            }
        }
        if ($colMonto < 0) $colMonto = count($header) - 1; // última columna
    }

    // Descripción: preferir col específica, sino col tipo, sino la que quede
    if ($colDesc < 0)  $colDesc = ($colTipo >= 0 && $colMonto > 1) ? 1 : ($colMonto > 1 ? 1 : -1);

    // Determinar texto del formato detectado
    if ($formatoCreditoDebito)      $formatoTexto = 'Crédito/Débito separados';
    elseif ($formatoMPEstandar)     $formatoTexto = 'Mercado Pago estándar (Tipo + Monto)';
    else                            $formatoTexto = 'Monto firmado (positivo/negativo)';

    // ── Procesar filas ────────────────────────────────────────
    $loteCode   = 'MP-' . date('Ymd-His');
    $processed  = [];
    $clasificadas = 0;
    $dataRows   = array_slice($rawRows, 1);

    foreach ($dataRows as $row) {
        $fechaRaw    = $row[$colFecha] ?? '';
        $descripcion = $colDesc >= 0 ? trim($row[$colDesc] ?? '') : '';
        $tipoMov     = $colTipo >= 0 ? trim($row[$colTipo] ?? '') : '';

        // Si no hay descripción, usar tipo de movimiento
        if ($descripcion === '' && $tipoMov !== '') $descripcion = $tipoMov;
        if ($descripcion === '') continue;

        // Calcular importe
        if ($formatoCreditoDebito) {
            $cred    = parseMPNum($row[$colCred] ?? '');
            $deb     = parseMPNum($row[$colDeb]  ?? '');
            if ($cred == 0 && $deb == 0) continue;
            $importe = $cred > 0 ? $cred : -$deb;
        } else {
            $importe = parseMPNum($row[$colMonto] ?? 0);
        }

        if ($importe == 0 && $descripcion === '') continue;

        $fecha = parseDateMP($fechaRaw);
        $tipo  = $importe >= 0 ? 'ingreso' : 'gasto';

        // Clasificar por palabra clave (descripcion + tipo_movimiento)
        $cat_id = null; $cat_nom = null; $cat_cod = null;
        $haystack = mb_strtolower($descripcion . ' ' . $tipoMov);
        foreach ($keywordMap as $kw) {
            if ($kw['tipo'] !== 'ambos' && $kw['tipo'] !== $tipo) continue;
            if (strpos($haystack, $kw['palabra']) !== false) {
                $cat_id = $kw['id']; $cat_nom = $kw['nombre']; $cat_cod = $kw['codigo'];
                break;
            }
        }
        if ($cat_id) $clasificadas++;

        // Referencia / ID operación
        $referencia = $colRef >= 0 ? trim($row[$colRef] ?? '') : null;
        if ($referencia === '') $referencia = null;

        // Intentar extraer nombre de cliente de la descripción
        $nombre = null;
        if (preg_match('/(?:de|para|a)\s+([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s]+?)(?:\s*[-–]|\s*$)/u', $descripcion, $m)) {
            $nombre = trim($m[1]);
        }

        // Buscar CUIT en descripción
        $cuit = null;
        if (preg_match('/\b(\d{11})\b/', $descripcion, $m)) $cuit = $m[1];
        if ($cuit && isset($cuitMap[$cuit]) && !$nombre) $nombre = $cuitMap[$cuit]['nombre'];

        $processed[] = [
            'fecha'          => $fecha,
            'descripcion'    => $descripcion,
            'tipo_movimiento'=> $tipoMov ?: null,
            'importe'        => $importe,
            'tipo'           => $tipo,
            'categoria_id'   => $cat_id,
            'categoria'      => $cat_nom,
            'codigo'         => $cat_cod,
            'referencia'     => $referencia,
            'nombre'         => $nombre,
            'lote'           => $loteCode,
        ];
    }

    if (empty($processed)) {
        echo json_encode(['error' => 'No se encontraron filas válidas. Verificá el formato del archivo.']); exit;
    }

    // ── Guardar en mp_movimientos ─────────────────────────────
    $stmt = $pdo->prepare("INSERT INTO mp_movimientos
        (fecha, descripcion, tipo_movimiento, importe, tipo, categoria_id, categoria_nombre, codigo, referencia, nombre_cliente, lote_importacion)
        VALUES (:fecha, :desc, :tipo_mov, :imp, :tipo, :cat_id, :cat_nom, :cod, :ref, :nombre, :lote)");

    foreach ($processed as $p) {
        $stmt->execute([
            ':fecha'   => $p['fecha'] ?: date('Y-m-d'),
            ':desc'    => $p['descripcion'],
            ':tipo_mov'=> $p['tipo_movimiento'],
            ':imp'     => $p['importe'],
            ':tipo'    => $p['tipo'],
            ':cat_id'  => $p['categoria_id'],
            ':cat_nom' => $p['categoria'],
            ':cod'     => $p['codigo'],
            ':ref'     => $p['referencia'],
            ':nombre'  => $p['nombre'],
            ':lote'    => $p['lote'],
        ]);
    }

    $sinClasificar = count($processed) - $clasificadas;
    $pdo->prepare("INSERT INTO mp_lotes (codigo, archivo_nombre, total_filas, filas_clasificadas, filas_sin_clasificar) VALUES (?,?,?,?,?)")
        ->execute([$loteCode, $_FILES['excel']['name'], count($processed), $clasificadas, $sinClasificar]);

    @unlink($tmpPath);

    echo json_encode([
        'success'        => true,
        'lote'           => $loteCode,
        'total'          => count($processed),
        'clasificadas'   => $clasificadas,
        'sin_clasificar' => $sinClasificar,
        'formato'        => $formatoTexto,
        'rows'           => $processed,
    ]);

} catch (Exception $e) {
    @unlink($tmpPath);
    echo json_encode(['error' => $e->getMessage()]);
}

// ── HELPERS ──────────────────────────────────────────────────

function parseExcelMP($path, $ext) {
    $rows = [];
    if ($ext === 'csv') {
        if (($h = fopen($path, 'r')) !== false) {
            // BOM UTF-8
            $bom = fread($h, 3);
            if ($bom !== "\xEF\xBB\xBF") rewind($h);
            $first = fgets($h); rewind($h);
            if ($bom !== "\xEF\xBB\xBF") fread($h, 3); // skip BOM again
            $delim = (substr_count($first, ';') >= substr_count($first, ',')) ? ';' : ',';
            while (($row = fgetcsv($h, 0, $delim)) !== false) {
                $filtered = array_filter($row, fn($v) => trim($v) !== '');
                if (!empty($filtered)) $rows[] = $row;
            }
            fclose($h);
        }
    } else {
        $rows = readXlsxMP($path);
    }
    return $rows;
}

function readXlsxMP($path) {
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
        $rowData = [];
        foreach ($row->c as $cell) {
            $ref = (string)$cell['r'];
            preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
            $colIdx = colLetterMP($m[1]);
            while (count($rowData) < $colIdx) $rowData[] = '';
            $type = (string)$cell['t'];
            $val  = (string)$cell->v;
            if ($type === 's') {
                $val = $sharedStrings[(int)$val] ?? '';
            } elseif (isset($cell->v) && $colIdx === 0 && is_numeric($val) && (float)$val > 40000) {
                $val = date('Y-m-d', (int)(((float)$val - 25569) * 86400));
            }
            $rowData[] = $val;
        }
        $filtered = array_filter($rowData, fn($v) => $v !== '' && $v !== null);
        if (!empty($filtered)) $rows[] = $rowData;
    }
    return $rows;
}

function colLetterMP($letters) {
    $idx = 0;
    foreach (str_split(strtoupper($letters)) as $ch) { $idx = $idx * 26 + (ord($ch) - 64); }
    return $idx - 1;
}

function normStrMP($s) {
    $s = mb_strtolower(trim($s));
    $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s;
    return preg_replace('/[^a-z0-9 ]/', ' ', $s);
}

function parseDateMP($val) {
    if (empty($val)) return null;
    $val = trim($val);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) return $val;
    // dd/mm/yyyy
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $val, $m))
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    // dd/mm/yy
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/', $val, $m)) {
        $yr = (int)$m[3] + ((int)$m[3] < 50 ? 2000 : 1900);
        return sprintf('%04d-%02d-%02d', $yr, $m[2], $m[1]);
    }
    // dd-mm-yyyy
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $val, $m))
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    // yyyy-mm-dd con hora
    if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T]/', $val, $m)) return $m[1];
    $ts = strtotime($val);
    return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
}

function parseMPNum($val) {
    if (is_numeric($val)) return (float)$val;
    $val = str_replace(['$', "\xc2\xa0", ' '], '', trim($val));
    // Formato argentino: 1.234,56
    if (preg_match('/^-?[\d\.]+,\d{1,2}$/', $val)) {
        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);
    } else {
        $val = str_replace([','], [''], $val);
    }
    return (float)$val;
}
?>
