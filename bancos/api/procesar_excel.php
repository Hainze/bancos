<?php
/**
 * api/procesar_excel.php
 * Receives uploaded Excel, parses it, classifies rows, returns JSON
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
    echo json_encode(['error' => 'Solo se permiten archivos .xlsx, .xls o .csv']); exit;
}

// Save temp file
$tmpPath = sys_get_temp_dir() . '/' . uniqid('excel_') . '.' . $ext;
move_uploaded_file($_FILES['excel']['tmp_name'], $tmpPath);

try {
    $pdo = getDB();

    // ── Load categories and keywords ──
    $cats = $pdo->query("SELECT c.id, c.nombre, c.codigo, c.tipo, p.palabra 
                         FROM palabras_clave p 
                         JOIN categorias c ON p.categoria_id = c.id 
                         WHERE c.activo=1 AND p.activo=1
                         ORDER BY c.id")->fetchAll();
    
    // Build keyword → category map
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

    // ── Load clients ──
    $clientes = $pdo->query("SELECT * FROM clientes WHERE activo=1")->fetchAll();
    $cuitMap   = [];
    $cuentaMap = [];
    $dniMap    = [];
    foreach ($clientes as $cl) {
        if ($cl['cuit'])          $cuitMap[trim($cl['cuit'])]           = $cl;
        if ($cl['numero_cuenta']) $cuentaMap[trim($cl['numero_cuenta'])]= $cl;
        if ($cl['dni'])           $dniMap[trim($cl['dni'])]             = $cl;
    }

    // ── Parse Excel ──
    $rows = parseExcel($tmpPath, $ext);

    if (empty($rows)) {
        echo json_encode(['error' => 'El archivo no tiene datos o no pudo leerse']); exit;
    }

    // ── Process rows ──
    $loteCode = 'LOTE-' . date('Ymd-His');
    $processed = [];
    $clasificadas = 0;

    foreach ($rows as $row) {
        $fecha       = parseDate($row[0] ?? '');
        $descripcion = trim($row[1] ?? '');
        $importe     = parseImporte($row[2] ?? 0);

        if (empty($descripcion) && $importe == 0) continue;

        $tipo = $importe >= 0 ? 'ingreso' : 'gasto';

        // ── Classify by keyword ──
        $cat_id   = null;
        $cat_nom  = null;
        $cat_cod  = null;

        $descLower = mb_strtolower($descripcion);
        foreach ($keywordMap as $kw) {
            // Check compatibility with tipo
            if ($kw['tipo'] !== 'ambos' && $kw['tipo'] !== $tipo) continue;
            if (strpos($descLower, $kw['palabra']) !== false) {
                $cat_id  = $kw['id'];
                $cat_nom = $kw['nombre'];
                $cat_cod = $kw['codigo'];
                break;
            }
        }
        if ($cat_id) $clasificadas++;

        // ── Extract client data (only for ingresos) ──
        $cuit   = null;
        $dni    = null;
        $cuenta = null;
        $nombre = null;

        if ($tipo === 'ingreso') {
            // Extract CUIT (11 digits, sometimes in parens)
            if (preg_match('/(?:cuit[:\s]+)?[(\s]?(\d{11})[)\s]?/i', $descripcion, $m)) {
                $cuit = $m[1];
            }
            // Extract DNI (7-8 digits after "DNI")
            if (!$cuit && preg_match('/dni[:\s]+(\d{7,8})/i', $descripcion, $m)) {
                $dni = $m[1];
            }
            // Extract account number (C:digits or C.digits or after ORI:...-) 
            if (preg_match('/(?:C[:\.]|ORI:[^-]+-[^-]+-[^-]+-[^-]+-?)(\d{8,})/i', $descripcion, $m)) {
                $cuenta = $m[1];
            }
            // Also BIP pattern: last segment after last -
            if (!$cuenta && preg_match('/BIP.+?-([A-Z0-9]+)$/i', $descripcion, $m) && strlen($m[1]) >= 8) {
                $cuenta = $m[1];
            }
            // Extract name: "TRANSF DE NOMBRE (cuit)" or "DEP...DNI... NOMBRE" etc
            if (preg_match('/(?:TRANSF(?:ERENCIA)?\s+DE\s+)([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s,\.]+?)(?:\s*\(|\s+FAC|\s+\d)/i', $descripcion, $m)) {
                $nombre = trim($m[1], ' ,');
            } elseif (preg_match('/(?:CUIT\s+\d{11}\s+)([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s]+)$/i', $descripcion, $m)) {
                $nombre = trim($m[1]);
            } elseif (preg_match('/DNI\s+\d{7,8}\s+([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s]+)$/i', $descripcion, $m)) {
                $nombre = trim($m[1]);
            } elseif (preg_match('/(?:DEP\.INTER\.\s+CUIT\s+\d{11}\s+)(.+)$/i', $descripcion, $m)) {
                $nombre = trim($m[1]);
            }

            // Match against client DB
            $clienteMatch = null;
            if ($cuit && isset($cuitMap[$cuit]))   $clienteMatch = $cuitMap[$cuit];
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
            'fecha'         => $fecha,
            'descripcion'   => $descripcion,
            'importe'       => $importe,
            'tipo'          => $tipo,
            'categoria_id'  => $cat_id,
            'categoria'     => $cat_nom,
            'codigo'        => $cat_cod,
            'cuit'          => $cuit,
            'dni'           => $dni,
            'numero_cuenta' => $cuenta,
            'nombre'        => $nombre,
            'lote'          => $loteCode,
        ];
    }

    // ── Save to DB ──
    $inserts = 0;
    $stmt = $pdo->prepare("INSERT INTO movimientos 
        (banco_id, fecha, descripcion, importe, tipo, categoria_id, categoria_nombre, codigo, cuit, dni, numero_cuenta, nombre_cliente, lote_importacion)
        VALUES (1, :fecha, :desc, :imp, :tipo, :cat_id, :cat_nom, :cod, :cuit, :dni, :cuenta, :nombre, :lote)");

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
        $inserts++;
    }

    // ── Save lote ──
    $sinClasificar = $inserts - $clasificadas;
    $pdo->prepare("INSERT INTO lotes (codigo, banco_id, archivo_nombre, total_filas, filas_clasificadas, filas_sin_clasificar)
                   VALUES (?,1,?,?,?,?)")
        ->execute([$loteCode, $_FILES['excel']['name'], $inserts, $clasificadas, $sinClasificar]);

    @unlink($tmpPath);

    echo json_encode([
        'success' => true,
        'lote'    => $loteCode,
        'total'   => $inserts,
        'clasificadas' => $clasificadas,
        'sin_clasificar' => $sinClasificar,
        'rows'    => $processed,
    ]);

} catch (Exception $e) {
    @unlink($tmpPath);
    echo json_encode(['error' => $e->getMessage()]);
}

// ── HELPERS ─────────────────────────────────────

function parseExcel($path, $ext) {
    $rows = [];
    if ($ext === 'csv') {
        if (($h = fopen($path, 'r')) !== false) {
            $header = true;
            while (($row = fgetcsv($h, 0, ',')) !== false) {
                if ($header) { $header = false; continue; }
                $rows[] = $row;
            }
            fclose($h);
        }
    } else {
        // Use simple xlsx reader without composer
        $rows = readXlsx($path);
    }
    return $rows;
}

function readXlsx($path) {
    // Lightweight XLSX reader using ZipArchive
    $rows = [];
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return $rows;

    // Read shared strings
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $xml = simplexml_load_string($ssXml);
        if ($xml) {
            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } else {
                    // Concatenate all <r><t> fragments
                    $str = '';
                    foreach ($si->r as $r) {
                        $str .= (string)$r->t;
                    }
                    $sharedStrings[] = $str;
                }
            }
        }
    }

    // Read sheet1
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if (!$sheetXml) return $rows;
    $xml = simplexml_load_string($sheetXml);
    if (!$xml) return $rows;

    $skipHeader = true;
    foreach ($xml->sheetData->row as $row) {
        if ($skipHeader) { $skipHeader = false; continue; }
        $rowData = [];
        $lastCol = -1;
        foreach ($row->c as $cell) {
            // Determine column index
            $ref = (string)$cell['r'];
            preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
            $colIdx = colLetterToIndex($m[1]);
            // Fill gaps
            while (count($rowData) < $colIdx) $rowData[] = '';
            // Get value
            $type = (string)$cell['t'];
            $val  = (string)$cell->v;
            if ($type === 's') {
                $val = $sharedStrings[(int)$val] ?? '';
            } elseif ($type === 'b') {
                $val = $val ? 'TRUE' : 'FALSE';
            } elseif (isset($cell->v)) {
                // Check if it's a date serial (column 0 = fecha)
                if ($colIdx === 0 && is_numeric($val) && (float)$val > 40000) {
                    $val = excelDateToMysql((float)$val);
                }
            }
            $rowData[] = $val;
        }
        // Only add rows with some data
        $filtered = array_filter($rowData, fn($v) => $v !== '' && $v !== null);
        if (!empty($filtered)) $rows[] = $rowData;
    }
    return $rows;
}

function colLetterToIndex($letters) {
    $idx = 0;
    foreach (str_split(strtoupper($letters)) as $ch) {
        $idx = $idx * 26 + (ord($ch) - 64);
    }
    return $idx - 1;
}

function excelDateToMysql($serial) {
    // Excel date serial → MySQL date
    $unix = ($serial - 25569) * 86400;
    return date('Y-m-d', $unix);
}

function parseDate($val) {
    if (empty($val)) return null;
    $val = trim($val);
    // Already Y-m-d
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) return $val;
    // d/m/Y
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $val, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    // d-m-Y
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $val, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    // Try strtotime
    $ts = strtotime($val);
    return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
}

function parseImporte($val) {
    if (is_numeric($val)) return (float)$val;
    $val = str_replace(['$','  '], ['',''], trim($val));
    // Handle Argentine format: 1.234,56
    if (preg_match('/^-?[\d\.]+,\d{1,2}$/', $val)) {
        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);
    } else {
        $val = str_replace(',', '', $val);
    }
    return (float)$val;
}
?>

