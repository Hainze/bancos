<?php
/**
 * credicoop/api/convertir_excel.php
 * Convierte extracto Banco Credicoop al formato simplificado:
 * Fecha | Descripción | Importe | Tipo
 */
require_once dirname(__DIR__, 2) . '/auth/check_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

if (!isset($_FILES['excel']) || $_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No se recibió archivo válido']);
    exit;
}

$ext = strtolower(pathinfo($_FILES['excel']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Solo se permiten .xlsx, .xls o .csv']);
    exit;
}

$tmpPath = sys_get_temp_dir() . '/' . uniqid('conv_') . '.' . $ext;
move_uploaded_file($_FILES['excel']['tmp_name'], $tmpPath);

try {
    $rawRows = parseExcelConv($tmpPath, $ext);
    @unlink($tmpPath);

    if (empty($rawRows)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'El archivo no tiene datos o no pudo leerse']);
        exit;
    }

    // Detectar columnas desde la fila de encabezado
    $header = array_map(fn($h) => normalizeConv(trim((string)$h)), $rawRows[0] ?? []);
    $colFecha = -1; $colDesc = -1; $colDebe = -1; $colHaber = -1;

    foreach ($header as $i => $h) {
        if      (preg_match('/^fecha/', $h))                             $colFecha = $i;
        elseif  (preg_match('/concepto|^desc|detalle|movimiento/', $h))  $colDesc  = $i;
        elseif  (preg_match('/debito|debe|cargo|extraccion/', $h))       $colDebe  = $i;
        elseif  (preg_match('/credito|haber|abono|deposito/', $h))       $colHaber = $i;
    }

    // Posiciones por defecto (Credicoop: A=vacío, B=Fecha, C=Concepto, D=Nro, E=Débito, F=Crédito)
    if ($colFecha < 0) $colFecha = 1;
    if ($colDesc  < 0) $colDesc  = 2;
    if ($colDebe  < 0) $colDebe  = 4;
    if ($colHaber < 0) $colHaber = 5;

    $output = [];
    foreach (array_slice($rawRows, 1) as $row) {
        $fecha  = parseDateConv($row[$colFecha] ?? '');
        $desc   = trim((string)($row[$colDesc] ?? ''));
        $debe   = parseNumConv($row[$colDebe]  ?? 0);
        $haber  = parseNumConv($row[$colHaber] ?? 0);

        if (!$desc && $debe == 0 && $haber == 0) continue;

        if ($haber > 0) {
            $importe = $haber;
            $tipo    = 'Ingreso';
        } else {
            $importe = $debe;
            $tipo    = 'Gasto';
        }

        $output[] = [$fecha, $desc, $importe, $tipo];
    }

    if (empty($output)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No se encontraron filas válidas. Verificá el formato del archivo.']);
        exit;
    }

    $allRows = array_merge([['Fecha', 'Descripción', 'Importe', 'Tipo']], $output);
    $xlsx    = buildSimpleXLSX($allRows);
    $fname   = 'credicoop_' . date('Ymd_His') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Content-Length: ' . strlen($xlsx));
    header('Cache-Control: no-cache, must-revalidate');
    echo $xlsx;

} catch (Exception $e) {
    @unlink($tmpPath ?? '');
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}

// ─── Generador XLSX sin librerías externas ─────────────────────────────────────
function buildSimpleXLSX(array $rows): string {
    $tmp = tempnam(sys_get_temp_dir(), 'xlx_');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);

    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
        '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
        '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
        '</Types>');

    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
        '</Relationships>');

    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<sheets><sheet name="Movimientos" sheetId="1" r:id="rId1"/></sheets>' .
        '</workbook>');

    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
        '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
        '</Relationships>');

    // Estilos: 0 = normal, 1 = negrita (encabezado)
    $zip->addFromString('xl/styles.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<fonts count="2">' .
        '<font><sz val="11"/><name val="Calibri"/></font>' .
        '<font><b/><sz val="11"/><name val="Calibri"/></font>' .
        '</fonts>' .
        '<fills count="2">' .
        '<fill><patternFill patternType="none"/></fill>' .
        '<fill><patternFill patternType="gray125"/></fill>' .
        '</fills>' .
        '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
        '<cellXfs>' .
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .
        '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>' .
        '</cellXfs>' .
        '</styleSheet>');

    $cols = ['A', 'B', 'C', 'D'];
    $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<cols>' .
            '<col min="1" max="1" width="13" customWidth="1"/>' .
            '<col min="2" max="2" width="52" customWidth="1"/>' .
            '<col min="3" max="3" width="16" customWidth="1"/>' .
            '<col min="4" max="4" width="10" customWidth="1"/>' .
            '</cols>' .
            '<sheetData>';

    foreach ($rows as $ri => $row) {
        $r   = $ri + 1;
        $hdr = ($ri === 0);
        $xml .= '<row r="' . $r . '">';
        foreach ($row as $ci => $val) {
            $ref = ($cols[$ci] ?? chr(65 + $ci)) . $r;
            if (is_numeric($val) && !$hdr) {
                $xml .= '<c r="' . $ref . '"><v>' . htmlspecialchars((string)$val, ENT_XML1) . '</v></c>';
            } else {
                $s   = $hdr ? ' s="1"' : '';
                $esc = htmlspecialchars((string)$val, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= '<c r="' . $ref . '" t="inlineStr"' . $s . '><is><t>' . $esc . '</t></is></c>';
            }
        }
        $xml .= '</row>';
    }

    $xml .= '</sheetData></worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
    $zip->close();

    $content = file_get_contents($tmp);
    @unlink($tmp);
    return $content;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function parseExcelConv(string $path, string $ext): array {
    if ($ext === 'csv') {
        $rows = [];
        if (($h = fopen($path, 'r')) !== false) {
            $f = fgets($h); rewind($h);
            $d = (substr_count($f, ';') >= substr_count($f, ',')) ? ';' : ',';
            while (($r = fgetcsv($h, 0, $d)) !== false) $rows[] = $r;
            fclose($h);
        }
        return $rows;
    }
    return readXlsxConv($path);
}

function readXlsxConv(string $path): array {
    $rows = []; $zip = new ZipArchive();
    if ($zip->open($path) !== true) return $rows;
    $ss = [];
    if ($sx = $zip->getFromName('xl/sharedStrings.xml')) {
        $xml = simplexml_load_string($sx);
        if ($xml) foreach ($xml->si as $si) {
            if (isset($si->t)) $ss[] = (string)$si->t;
            else { $s = ''; foreach ($si->r as $r) $s .= (string)$r->t; $ss[] = $s; }
        }
    }
    $shX = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$shX) return $rows;
    $xml = simplexml_load_string($shX);
    if (!$xml) return $rows;
    foreach ($xml->sheetData->row as $row) {
        $rd = [];
        foreach ($row->c as $cell) {
            preg_match('/^([A-Z]+)(\d+)$/', (string)$cell['r'], $m);
            $ci = colIdxConv($m[1]);
            while (count($rd) < $ci) $rd[] = '';
            $t  = (string)$cell['t'];
            $v  = (string)$cell->v;
            if ($t === 's') $v = $ss[(int)$v] ?? '';
            $rd[] = $v;
        }
        if (array_filter($rd, fn($x) => $x !== '')) $rows[] = $rd;
    }
    return $rows;
}

function colIdxConv(string $l): int {
    $i = 0;
    foreach (str_split(strtoupper($l)) as $c) $i = $i * 26 + (ord($c) - 64);
    return $i - 1;
}

function parseDateConv($v): string {
    if ($v === '' || $v === null) return '';
    $v = trim((string)$v);
    // Número serial de fecha Excel
    if (is_numeric($v) && (float)$v > 40000 && (float)$v < 60000) {
        return date('d/m/Y', (int)(((float)$v - 25569) * 86400));
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return date('d/m/Y', strtotime($v));
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $v, $m)) return sprintf('%02d/%02d/%04d', $m[1], $m[2], $m[3]);
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $v, $m)) return sprintf('%02d/%02d/%04d', $m[1], $m[2], $m[3]);
    $t = strtotime($v);
    return $t ? date('d/m/Y', $t) : $v;
}

function parseNumConv($v): float {
    if (is_numeric($v)) return (float)$v;
    $v = str_replace(['$', "\xc2\xa0", ' '], ['', '', ''], trim((string)$v));
    if (preg_match('/^-?[\d\.]+,\d{1,2}$/', $v)) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    } else {
        $v = str_replace(',', '', $v);
    }
    return (float)$v;
}

function normalizeConv(string $s): string {
    return iconv('UTF-8', 'ASCII//TRANSLIT', mb_strtolower($s)) ?: mb_strtolower($s);
}
?>
