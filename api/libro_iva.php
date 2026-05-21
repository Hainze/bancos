<?php
/**
 * api/libro_iva.php
 * Proceso del Libro IVA Compras de ARCA:
 *  - Lee el XLSX de ARCA (12 columnas)
 *  - Niega Tipo Cambio en filas Nota de Crédito
 *  - Guarda en BD (iva_lotes + iva_registros)
 *  - Genera XLSX de salida con 24 columnas (3 vacías + Diferencia + cols *2 con fórmulas)
 *  - Permite descargar el XLSX generado y listar/eliminar lotes
 */
require_once dirname(__DIR__) . '/auth/check_api.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── DESCARGAR XLSX generado ─────────────────────────────────────
if ($action === 'descargar') {
    $loteId = (int)($_GET['lote_id'] ?? 0);
    if (!$loteId) { http_response_code(400); echo 'Lote inválido'; exit; }
    $pdo = getDB();
    $lote = $pdo->prepare("SELECT * FROM iva_lotes WHERE id=?")->execute([$loteId]) ? null : null;
    $stmt = $pdo->prepare("SELECT * FROM iva_lotes WHERE id=?");
    $stmt->execute([$loteId]);
    $lote = $stmt->fetch();
    if (!$lote) { http_response_code(404); echo 'Lote no encontrado'; exit; }

    $stmt2 = $pdo->prepare("SELECT * FROM iva_registros WHERE lote_id=? ORDER BY id ASC");
    $stmt2->execute([$loteId]);
    $registros = $stmt2->fetchAll();

    $tmpFile = generarIvaXlsx($registros);
    $filename = 'libro_iva_' . ($lote['periodo'] ?: $lote['codigo']) . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache');
    readfile($tmpFile);
    @unlink($tmpFile);
    exit;
}

header('Content-Type: application/json');
$pdo = getDB();

try {
    switch ($action) {

        // ── PROCESAR UPLOAD ─────────────────────────────────────────
        case 'procesar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['error' => 'Método no permitido']); break;
            }
            if (!isset($_FILES['excel']) || $_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['error' => 'No se recibió archivo válido']); break;
            }
            $ext = strtolower(pathinfo($_FILES['excel']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx','xls'])) {
                echo json_encode(['error' => 'Solo se permiten archivos .xlsx o .xls']); break;
            }

            $tmpPath = sys_get_temp_dir() . '/' . uniqid('iva_') . '.' . $ext;
            move_uploaded_file($_FILES['excel']['tmp_name'], $tmpPath);

            $rawRows = leerArcaXlsx($tmpPath);
            @unlink($tmpPath);

            if (empty($rawRows)) {
                echo json_encode(['error' => 'El archivo no tiene datos o no pudo leerse. Verificá que sea el Libro de Compras de ARCA (.xlsx)']); break;
            }

            // Detectar período desde las fechas
            $periodo = detectarPeriodo($rawRows);

            $loteCode = 'IVA-' . date('Ymd-His');
            $notasCredito = 0;
            $registros = [];

            foreach ($rawRows as $row) {
                $tipo = trim($row[1] ?? '');
                if (empty($tipo)) continue;

                $esNC = stripos($tipo, 'nota de cr') !== false;
                if ($esNC) $notasCredito++;

                $tipoCambio = parsearNumero($row[6] ?? 1);
                if ($esNC) $tipoCambio = -abs($tipoCambio);

                $registros[] = [
                    'fecha'                => formatearFecha($row[0] ?? ''),
                    'tipo'                 => $tipo,
                    'punto_venta'          => (int)($row[2] ?? 0),
                    'numero_desde'         => (int)($row[3] ?? 0),
                    'nro_doc_vendedor'     => trim($row[4] ?? ''),
                    'denominacion_vendedor'=> trim($row[5] ?? ''),
                    'tipo_cambio'          => $tipoCambio,
                    'neto_gravado'         => parsearNumero($row[7] ?? 0),
                    'no_gravado'           => parsearNumero($row[8] ?? 0),
                    'exento'               => parsearNumero($row[9] ?? 0),
                    'iva_monto'            => parsearNumero($row[10] ?? 0),
                    'total'                => parsearNumero($row[11] ?? 0),
                    'es_nota_credito'      => $esNC ? 1 : 0,
                ];
            }

            // Guardar lote en BD
            $pdo->prepare("INSERT INTO iva_lotes (codigo, archivo_nombre, periodo, total_filas, notas_credito) VALUES (?,?,?,?,?)")
                ->execute([$loteCode, $_FILES['excel']['name'], $periodo, count($registros), $notasCredito]);
            $loteId = (int)$pdo->lastInsertId();

            // Guardar registros
            $stmtIns = $pdo->prepare("INSERT INTO iva_registros
                (lote_id, fecha, tipo, punto_venta, numero_desde, nro_doc_vendedor, denominacion_vendedor,
                 tipo_cambio, neto_gravado, no_gravado, exento, iva_monto, total, es_nota_credito)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            foreach ($registros as $r) {
                $stmtIns->execute([
                    $loteId, $r['fecha'], $r['tipo'], $r['punto_venta'], $r['numero_desde'],
                    $r['nro_doc_vendedor'], $r['denominacion_vendedor'], $r['tipo_cambio'],
                    $r['neto_gravado'], $r['no_gravado'], $r['exento'], $r['iva_monto'],
                    $r['total'], $r['es_nota_credito'],
                ]);
            }

            // Preview para la UI (primeras 50 filas)
            $preview = array_slice($registros, 0, 50);

            echo json_encode([
                'success'       => true,
                'lote_id'       => $loteId,
                'lote_codigo'   => $loteCode,
                'periodo'       => $periodo,
                'total'         => count($registros),
                'notas_credito' => $notasCredito,
                'preview'       => $preview,
            ]);
            break;

        // ── LISTAR LOTES ───────────────────────────────────────────
        case 'listar_lotes':
            $stmt = $pdo->query("SELECT * FROM iva_lotes ORDER BY created_at DESC LIMIT 50");
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        // ── ELIMINAR LOTE ──────────────────────────────────────────
        case 'eliminar_lote':
            $d = json_decode(file_get_contents('php://input'), true);
            $id = (int)($d['id'] ?? 0);
            $pdo->prepare("DELETE FROM iva_lotes WHERE id=?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['error' => 'Acción no reconocida: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

// ════════════════════════════════════════════════════════════
// HELPERS
// ════════════════════════════════════════════════════════════

/**
 * Lee el XLSX de ARCA y devuelve array de filas (sin header).
 * Columnas esperadas: Fecha, Tipo, PtoVta, NroDesde, NroDocVendedor,
 *                    DenomVendedor, TipoCambio, NetoGrav, NoGrav,
 *                    Exento, IVA, Total
 */
function leerArcaXlsx(string $path): array {
    $rows = [];
    $zip  = new ZipArchive();
    if ($zip->open($path) !== true) return $rows;

    // Shared strings
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $xml = simplexml_load_string($ssXml);
        if ($xml) {
            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } else {
                    $str = '';
                    foreach ($si->r as $r) $str .= (string)$r->t;
                    $sharedStrings[] = $str;
                }
            }
        }
    }

    // Sheet1
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$sheetXml) return $rows;

    $xml = simplexml_load_string($sheetXml);
    if (!$xml) return $rows;

    $skipHeader = true;
    foreach ($xml->sheetData->row as $row) {
        if ($skipHeader) { $skipHeader = false; continue; }

        $rowData  = [];
        $maxCol   = 11; // 0..11 = 12 columns (A..L)

        foreach ($row->c as $cell) {
            $ref = (string)$cell['r'];
            preg_match('/^([A-Z]+)/', $ref, $m);
            $colIdx = colIdx($m[1]);
            if ($colIdx > $maxCol) continue;

            // Fill gaps
            while (count($rowData) < $colIdx) $rowData[] = '';

            $type = (string)$cell['t'];
            $val  = (string)($cell->v ?? '');

            if ($type === 's') {
                $val = $sharedStrings[(int)$val] ?? '';
            } elseif ($type === 'inlineStr') {
                $val = (string)($cell->is->t ?? '');
            } elseif ($val !== '' && is_numeric($val)) {
                // Col 0 = fecha: detectar si es serial de fecha de Excel
                if ($colIdx === 0 && (float)$val > 40000) {
                    $val = gmdate('d/m/Y', ((float)$val - 25569) * 86400);
                }
            }
            $rowData[] = $val;
        }

        // Rellenar hasta 12 columnas
        while (count($rowData) <= $maxCol) $rowData[] = '';

        // Ignorar filas completamente vacías
        $noEmpty = array_filter($rowData, fn($v) => $v !== '');
        if (!empty($noEmpty)) $rows[] = $rowData;
    }

    return $rows;
}

function colIdx(string $letters): int {
    $idx = 0;
    foreach (str_split(strtoupper($letters)) as $ch) {
        $idx = $idx * 26 + (ord($ch) - 64);
    }
    return $idx - 1;
}

function parsearNumero($val): float {
    if (is_numeric($val)) return (float)$val;
    $val = trim((string)$val);
    // Argentine format: 1.234,56
    if (preg_match('/^-?[\d\.]+,\d{1,2}$/', $val)) {
        return (float)str_replace(['.', ','], ['', '.'], $val);
    }
    return (float)str_replace(',', '', $val);
}

function formatearFecha($val): string {
    $val = trim((string)$val);
    if (empty($val)) return '';
    // DD/MM/YYYY (ARCA default)
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $val)) return $val;
    // YYYY-MM-DD
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $val, $m)) {
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }
    return $val;
}

function detectarPeriodo(array $rows): string {
    foreach ($rows as $row) {
        $fecha = trim($row[0] ?? '');
        if (preg_match('/(\d{2})\/(\d{4})$/', $fecha, $m)) {
            return $m[1] . '-' . $m[2]; // MM-YYYY
        }
        if (preg_match('/^(\d{4})-(\d{2})/', $fecha, $m)) {
            return $m[2] . '-' . $m[1];
        }
    }
    return date('m-Y');
}

// ════════════════════════════════════════════════════════════
// GENERADOR XLSX DE SALIDA
// ════════════════════════════════════════════════════════════

/**
 * Genera el XLSX procesado con 25 columnas:
 * A-K  : columnas originales (Tipo Cambio negado en NC)
 * L-N  : Perc IIBB, Perc IVA, Imp Int (vacías - usuario completa)
 * O    : Total
 * P    : Diferencia = O-(H+I+J+K+L+M+N)  [fórmula] — celda resaltada en amarillo si ≠ 0
 * Q-X  : Neto Gravado 2..Total 2 = G*col  [fórmulas]
 * Y    : Porcentaje = IVA 2 / Neto Gravado 2 * 100  [fórmula] — resaltada en rojo si ≠ 10,5 / 21 / 27
 */
function generarIvaXlsx(array $registros): string {
    $headers = [
        'A'=>'Fecha', 'B'=>'Tipo', 'C'=>'Punto de Venta', 'D'=>'Número Desde',
        'E'=>'Nro. Doc. Vendedor', 'F'=>'Denominación Vendedor', 'G'=>'Tipo Cambio',
        'H'=>'Neto Gravado', 'I'=>'No Gravado', 'J'=>'Exento', 'K'=>'IVA',
        'L'=>'Perc IIBB', 'M'=>'Perc IVA', 'N'=>'Imp Int',
        'O'=>'Total',
        'P'=>'Diferencia',
        'Q'=>'Neto Gravado 2', 'R'=>'No Gravado 2', 'S'=>'Exento 2', 'T'=>'IVA 2',
        'U'=>'Perc IIBB 2', 'V'=>'Perc IVA 2', 'W'=>'Imp Int 2', 'X'=>'Total 2',
        'Y'=>'Porcentaje',
    ];

    $sheetRows = '';

    // Header row
    $sheetRows .= '<row r="1">';
    foreach ($headers as $col => $label) {
        $sheetRows .= '<c r="' . $col . '1" t="inlineStr"><is><t>' . xe($label) . '</t></is></c>';
    }
    $sheetRows .= '</row>';

    // Data rows
    $rn = 2;
    foreach ($registros as $reg) {
        $sheetRows .= '<row r="' . $rn . '">';

        // A: Fecha (texto)
        $sheetRows .= '<c r="A' . $rn . '" t="inlineStr"><is><t>' . xe($reg['fecha']) . '</t></is></c>';
        // B: Tipo (texto)
        $sheetRows .= '<c r="B' . $rn . '" t="inlineStr"><is><t>' . xe($reg['tipo']) . '</t></is></c>';
        // C: Punto de Venta (número)
        $sheetRows .= '<c r="C' . $rn . '"><v>' . (int)$reg['punto_venta'] . '</v></c>';
        // D: Número Desde (número)
        $sheetRows .= '<c r="D' . $rn . '"><v>' . (int)$reg['numero_desde'] . '</v></c>';
        // E: Nro. Doc. Vendedor (texto para no perder ceros)
        $sheetRows .= '<c r="E' . $rn . '" t="inlineStr"><is><t>' . xe($reg['nro_doc_vendedor']) . '</t></is></c>';
        // F: Denominación Vendedor (texto)
        $sheetRows .= '<c r="F' . $rn . '" t="inlineStr"><is><t>' . xe($reg['denominacion_vendedor']) . '</t></is></c>';
        // G: Tipo Cambio (número, negativo si NC)
        $sheetRows .= '<c r="G' . $rn . '"><v>' . (float)$reg['tipo_cambio'] . '</v></c>';
        // H: Neto Gravado
        $sheetRows .= '<c r="H' . $rn . '"><v>' . (float)$reg['neto_gravado'] . '</v></c>';
        // I: No Gravado
        $sheetRows .= '<c r="I' . $rn . '"><v>' . (float)$reg['no_gravado'] . '</v></c>';
        // J: Exento
        $sheetRows .= '<c r="J' . $rn . '"><v>' . (float)$reg['exento'] . '</v></c>';
        // K: IVA
        $sheetRows .= '<c r="K' . $rn . '"><v>' . (float)$reg['iva_monto'] . '</v></c>';
        // L, M, N: vacías (usuario las completa)
        $sheetRows .= '<c r="L' . $rn . '"/><c r="M' . $rn . '"/><c r="N' . $rn . '"/>';
        // O: Total
        $sheetRows .= '<c r="O' . $rn . '"><v>' . (float)$reg['total'] . '</v></c>';

        // P: Diferencia = O - (H+I+J+K+L+M+N)
        $sheetRows .= '<c r="P' . $rn . '"><f>O' . $rn . '-(H' . $rn . '+I' . $rn . '+J' . $rn . '+K' . $rn . '+L' . $rn . '+M' . $rn . '+N' . $rn . ')</f><v>0</v></c>';

        // Q-X: columnas *2 = G * original
        $pairs = ['Q'=>'H','R'=>'I','S'=>'J','T'=>'K','U'=>'L','V'=>'M','W'=>'N','X'=>'O'];
        foreach ($pairs as $out => $orig) {
            $sheetRows .= '<c r="' . $out . $rn . '"><f>G' . $rn . '*' . $orig . $rn . '</f><v>0</v></c>';
        }

        // Y: Porcentaje = IVA 2 / Neto Gravado 2 * 100 (vacío si Neto Gravado 2 = 0)
        $sheetRows .= '<c r="Y' . $rn . '"><f>IFERROR(T' . $rn . '/Q' . $rn . '*100,"")</f><v>0</v></c>';

        $sheetRows .= '</row>';
        $rn++;
    }

    $lastRow = $rn - 1;

    // Conditional formatting:
    // dxfId=0 (amber): Diferencia != 0
    // dxfId=1 (light red): Porcentaje presente y distinto de 10.5 / 21 / 27
    $condFmt = '<conditionalFormatting sqref="P2:P' . $lastRow . '">'
             . '<cfRule type="expression" dxfId="0" priority="1">'
             . '<formula>P2&lt;&gt;0</formula>'
             . '</cfRule>'
             . '</conditionalFormatting>'
             . '<conditionalFormatting sqref="Y2:Y' . $lastRow . '">'
             . '<cfRule type="expression" dxfId="1" priority="2">'
             . '<formula>AND(Y2&lt;&gt;"",NOT(OR(ABS(Y2-10.5)&lt;0.01,ABS(Y2-21)&lt;0.01,ABS(Y2-27)&lt;0.01)))</formula>'
             . '</cfRule>'
             . '</conditionalFormatting>';

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetData>' . $sheetRows . '</sheetData>'
        . $condFmt
        . '</worksheet>';

    // styles.xml with two dxf entries for conditional formatting fills
    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="2">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '</fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
        . '<dxfs count="2">'
        . '<dxf><fill><patternFill patternType="solid"><fgColor rgb="FFFEF08A"/></patternFill></fill></dxf>'
        . '<dxf><fill><patternFill patternType="solid"><fgColor rgb="FFFECACA"/></patternFill></fill></dxf>'
        . '</dxfs>'
        . '</styleSheet>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Libro IVA Compras" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $tmpFile = sys_get_temp_dir() . '/' . uniqid('iva_out_') . '.xlsx';
    $zip = new ZipArchive();
    $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml',             $contentTypes);
    $zip->addFromString('_rels/.rels',                     $rels);
    $zip->addFromString('xl/workbook.xml',                 $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels',      $wbRels);
    $zip->addFromString('xl/worksheets/sheet1.xml',        $sheetXml);
    $zip->addFromString('xl/styles.xml',                   $stylesXml);
    $zip->close();

    return $tmpFile;
}

function xe(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}
?>
