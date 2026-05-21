<?php
/**
 * api/libro_iva_ddjj.php
 * Genera los dos TXT + Excel resumen para Portal IVA DDJJ
 * a partir del Excel procesado (salida de libro_iva.php).
 *
 * Alic.txt  : 62 chars/línea  — detalle de alícuotas
 * Cbte.txt  : 266 chars/línea — detalle de comprobantes
 * Resumen   : Excel simple — totales por tipo y alícuota
 */
require_once dirname(__DIR__) . '/auth/check_api.php';

$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

try {
    switch ($action) {

        case 'generar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['error' => 'Método no permitido']); exit;
            }
            if (!isset($_FILES['excel']) || $_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['error' => 'No se recibió archivo válido']); exit;
            }
            $ext = strtolower(pathinfo($_FILES['excel']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'xlsx') {
                echo json_encode(['error' => 'Solo se permiten archivos .xlsx (guardados desde Excel/LibreOffice)']); exit;
            }

            $tmpPath = sys_get_temp_dir() . '/' . uniqid('ddjj_') . '.xlsx';
            move_uploaded_file($_FILES['excel']['tmp_name'], $tmpPath);

            $filas = leerXlsxProcesado($tmpPath);
            @unlink($tmpPath);

            if (empty($filas)) {
                echo json_encode(['error' => 'El archivo no contiene datos legibles. Asegurate de haberlo abierto y guardado en Excel antes de subir.']); exit;
            }

            [$alicContent, $cbteContent, $periodo, $stats, $totales] = generarDDJJTxt($filas);

            $resumenXlsx = generarResumenXlsx($totales, $periodo);
            $resumenB64  = base64_encode(file_get_contents($resumenXlsx));
            @unlink($resumenXlsx);

            echo json_encode([
                'success'     => true,
                'periodo'     => $periodo,
                'alic_lineas' => $stats['alic'],
                'cbte_lineas' => $stats['cbte'],
                'nombre_base' => $stats['nombre_base'],
                'alic_b64'    => base64_encode($alicContent),
                'cbte_b64'    => base64_encode($cbteContent),
                'resumen_b64' => $resumenB64,
            ]);
            break;

        default:
            echo json_encode(['error' => 'Acción no reconocida: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

// ════════════════════════════════════════════════════════════
// LEER XLSX PROCESADO
// ════════════════════════════════════════════════════════════

function leerXlsxProcesado(string $path): array
{
    $za = new ZipArchive();
    if ($za->open($path) !== true) return [];

    $sheetXml = $za->getFromName('xl/worksheets/sheet1.xml');

    // Cargar shared strings — Excel/LibreOffice convierte inlineStr a shared strings al guardar
    $sharedStrings = [];
    $ssXml = $za->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $ssDom = new DOMDocument();
        libxml_use_internal_errors(true);
        if ($ssDom->loadXML($ssXml)) {
            foreach ($ssDom->getElementsByTagName('si') as $si) {
                $rNodes = $si->getElementsByTagName('r');
                if ($rNodes->length > 0) {
                    $str = '';
                    foreach ($si->getElementsByTagName('t') as $t) $str .= $t->textContent;
                    $sharedStrings[] = $str;
                } else {
                    $tNode = $si->getElementsByTagName('t')->item(0);
                    $sharedStrings[] = $tNode ? $tNode->textContent : '';
                }
            }
        }
        libxml_clear_errors();
    }

    $za->close();
    if (!$sheetXml) return [];

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$dom->loadXML($sheetXml)) return [];
    libxml_clear_errors();

    $filas = [];
    foreach ($dom->getElementsByTagName('row') as $rowEl) {
        $rowNum = (int)$rowEl->getAttribute('r');
        if ($rowNum < 2) continue;

        $cells = [];
        foreach ($rowEl->getElementsByTagName('c') as $c) {
            $ref = $c->getAttribute('r');
            $col = preg_replace('/\d+/', '', $ref);
            $t   = $c->getAttribute('t');

            if ($t === 'inlineStr') {
                $node = $c->getElementsByTagName('t')->item(0);
                $cells[$col] = $node ? trim($node->textContent) : '';
            } elseif ($t === 's') {
                // Shared string (Excel/LibreOffice convierte texto a este formato al guardar)
                $vEl = $c->getElementsByTagName('v')->item(0);
                $idx = $vEl ? (int)$vEl->textContent : 0;
                $cells[$col] = $sharedStrings[$idx] ?? '';
            } else {
                $vEl = $c->getElementsByTagName('v')->item(0);
                $cells[$col] = $vEl ? $vEl->textContent : '';
            }
        }

        $noEmpty = array_filter($cells, fn($v) => $v !== '');
        if (!empty($noEmpty)) $filas[$rowNum] = $cells;
    }

    return $filas;
}

// ════════════════════════════════════════════════════════════
// GENERADOR TXT + TOTALES
// ════════════════════════════════════════════════════════════

function generarDDJJTxt(array $filas): array
{
    // Grupos de tipos de comprobante
    $grupos = [
        '001' => ['nombre' => 'Facturas A',         'letra' => 'A', 'is_nc' => false],
        '002' => ['nombre' => 'Notas de Débito A',  'letra' => 'A', 'is_nc' => false],
        '003' => ['nombre' => 'Notas de Crédito A', 'letra' => 'A', 'is_nc' => true ],
        '006' => ['nombre' => 'Facturas B',          'letra' => 'B', 'is_nc' => false],
        '007' => ['nombre' => 'Notas de Débito B',  'letra' => 'B', 'is_nc' => false],
        '008' => ['nombre' => 'Notas de Crédito B', 'letra' => 'B', 'is_nc' => true ],
        '011' => ['nombre' => 'Facturas C',          'letra' => 'C', 'is_nc' => false],
        '012' => ['nombre' => 'Notas de Débito C',  'letra' => 'C', 'is_nc' => false],
        '013' => ['nombre' => 'Notas de Crédito C', 'letra' => 'C', 'is_nc' => true ],
        '015' => ['nombre' => 'Recibos B',           'letra' => 'B', 'is_nc' => false],
        '016' => ['nombre' => 'Recibos C',           'letra' => 'C', 'is_nc' => false],
        '_'   => ['nombre' => 'Otros',               'letra' => '-', 'is_nc' => false],
    ];

    $totales = [];
    foreach ($grupos as $code => $g) {
        $totales[$code] = $g + [
            'cant'     => 0,
            'n105'     => 0.0, 'i105'    => 0.0,
            'n21'      => 0.0, 'i21'     => 0.0,
            'n27'      => 0.0, 'i27'     => 0.0,
            'noGrav'   => 0.0, 'exento'  => 0.0,
            'percIIBB' => 0.0, 'percIva' => 0.0,
            'impInt'   => 0.0, 'total'   => 0.0,
        ];
    }

    $alicLines = [];
    $cbteLines = [];
    $periodo   = '';

    foreach ($filas as $row) {
        $tipoStr = $row['B'] ?? '';

        // Omitir filas de desglose (↳)
        if (str_starts_with($tipoStr, '↳')) continue;

        $fecha   = trim($row['A'] ?? '');
        $tipoStr = trim($tipoStr);
        if ($fecha === '' || $tipoStr === '') continue;

        $tc       = abs((float)($row['G'] ?? 1));
        if ($tc < 0.0001) $tc = 1.0;

        $neto     = abs((float)($row['H'] ?? 0));
        $noGrav   = abs((float)($row['I'] ?? 0));
        $exento   = abs((float)($row['J'] ?? 0));
        $iva      = abs((float)($row['K'] ?? 0));
        $percIIBB = abs((float)($row['L'] ?? 0));
        $percIva  = abs((float)($row['M'] ?? 0));
        $impInt   = abs((float)($row['N'] ?? 0));

        $neto_p     = round($neto     * $tc, 2);
        $noGrav_p   = round($noGrav   * $tc, 2);
        $exento_p   = round($exento   * $tc, 2);
        $iva_p      = round($iva      * $tc, 2);
        $percIIBB_p = round($percIIBB * $tc, 2);
        $percIva_p  = round($percIva  * $tc, 2);
        $impInt_p   = round($impInt   * $tc, 2);
        $total_p    = round(($neto + $noGrav + $exento + $iva + $percIIBB + $percIva + $impInt) * $tc, 2);

        $ptoVta   = (int)($row['C'] ?? 0);
        $nroDesde = (int)($row['D'] ?? 0);

        $fechaAfip = toFechaAfip($fecha);
        if ($periodo === '' && strlen($fechaAfip) === 8) {
            $periodo = substr($fechaAfip, 0, 6);
        }

        $tipoAfip = mapearTipo($tipoStr);

        $cuitRaw = preg_replace('/[^0-9]/', '', $row['E'] ?? '');
        if (strlen($cuitRaw) === 11) {
            $tipoDoc = '80';
            $cuitPad = str_pad($cuitRaw, 20, '0', STR_PAD_LEFT);
        } else {
            $tipoDoc = '96';
            $cuitPad = str_pad(substr($cuitRaw, 0, 20), 20, '0', STR_PAD_LEFT);
        }

        $razon = mb_strtoupper(trim($row['F'] ?? ''), 'UTF-8');
        $razon = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $razon) ?: $razon;
        $razon = str_pad(substr($razon, 0, 30), 30);

        $alicRows = calcularAlicuotas($neto_p, $iva_p);
        $cantAlic  = count($alicRows);

        // ── ALIC.TXT ──
        foreach ($alicRows as $a) {
            $alicLines[] =
                str_pad($tipoAfip, 3, '0', STR_PAD_LEFT)          .
                str_pad((string)$ptoVta, 5, '0', STR_PAD_LEFT)    .
                str_pad((string)$nroDesde, 20, '0', STR_PAD_LEFT) .
                str_pad((string)(int)round($a['neto'] * 100), 15, '0', STR_PAD_LEFT) .
                $a['cod']                                          .
                str_pad((string)(int)round($a['iva'] * 100), 15, '0', STR_PAD_LEFT);
        }

        // ── CBTE.TXT ──
        $cbteLines[] =
            $fechaAfip                                                              .
            str_pad($tipoAfip, 3, '0', STR_PAD_LEFT)                              .
            str_pad((string)$ptoVta, 5, '0', STR_PAD_LEFT)                        .
            str_pad((string)$nroDesde, 20, '0', STR_PAD_LEFT)                     .
            str_pad((string)$nroDesde, 20, '0', STR_PAD_LEFT)                     .
            $tipoDoc                                                               .
            $cuitPad                                                               .
            $razon                                                                 .
            str_pad((string)(int)round($total_p    * 100), 15, '0', STR_PAD_LEFT) .
            str_pad((string)(int)round($noGrav_p   * 100), 15, '0', STR_PAD_LEFT) .
            str_pad((string)(int)round($exento_p   * 100), 15, '0', STR_PAD_LEFT) .
            str_pad((string)(int)round($percIva_p  * 100), 15, '0', STR_PAD_LEFT) .
            str_repeat('0', 15)                                                    .
            str_pad((string)(int)round($percIIBB_p * 100), 15, '0', STR_PAD_LEFT) .
            str_repeat('0', 15)                                                    .
            str_pad((string)(int)round($impInt_p   * 100), 15, '0', STR_PAD_LEFT) .
            'PES'                                                                  .
            '0001000000'                                                           .
            (string)($cantAlic ?: 1)                                               .
            ' '                                                                    .
            str_repeat('0', 15)                                                    .
            $fechaAfip;

        // ── ACUMULAR TOTALES (sin match+&& para evitar short-circuit) ──
        $key  = array_key_exists($tipoAfip, $totales) ? $tipoAfip : '_';
        $sign = $totales[$key]['is_nc'] ? -1 : 1;

        $totales[$key]['cant']++;

        foreach ($alicRows as $a) {
            if ($a['cod'] === '0004') {
                $totales[$key]['n105'] += $sign * $a['neto'];
                $totales[$key]['i105'] += $sign * $a['iva'];
            } elseif ($a['cod'] === '0005') {
                $totales[$key]['n21'] += $sign * $a['neto'];
                $totales[$key]['i21'] += $sign * $a['iva'];
            } elseif ($a['cod'] === '0006') {
                $totales[$key]['n27'] += $sign * $a['neto'];
                $totales[$key]['i27'] += $sign * $a['iva'];
            }
        }

        $totales[$key]['noGrav']   += $sign * $noGrav_p;
        $totales[$key]['exento']   += $sign * $exento_p;
        $totales[$key]['percIIBB'] += $sign * $percIIBB_p;
        $totales[$key]['percIva']  += $sign * $percIva_p;
        $totales[$key]['impInt']   += $sign * $impInt_p;
        $totales[$key]['total']    += $sign * $total_p;
    }

    $alicContent = $alicLines ? implode("\r\n", $alicLines) . "\r\n" : '';
    $cbteContent = $cbteLines ? implode("\r\n", $cbteLines) . "\r\n" : '';

    $periodoFmt = $periodo
        ? substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2)
        : date('Y-m');

    return [
        $alicContent,
        $cbteContent,
        $periodoFmt,
        [
            'alic'        => count($alicLines),
            'cbte'        => count($cbteLines),
            'nombre_base' => 'IVA_' . $periodoFmt,
        ],
        $totales,
    ];
}

// ════════════════════════════════════════════════════════════
// GENERADOR EXCEL RESUMEN
// ════════════════════════════════════════════════════════════

/**
 * Genera un Excel de 2 columnas (Concepto | Importe) con exactamente
 * lo que pide el usuario para verificar el DDJJ IVA.
 */
function generarResumenXlsx(array $tot, string $periodo): string
{
    // Totales de percepciones e impuestos sumados de TODOS los tipos
    $percIIBB = 0.0; $percIva = 0.0; $impInt = 0.0;
    foreach ($tot as $t) {
        $percIIBB += $t['percIIBB'];
        $percIva  += $t['percIva'];
        $impInt   += $t['impInt'];
    }

    // Abreviaturas para legibilidad
    $fa  = $tot['001'];  // Facturas A
    $nda = $tot['002'];  // Notas de Débito A
    $nca = $tot['003'];  // Notas de Crédito A  (valores negativos — mostramos abs)
    $fc  = $tot['011'];  // Facturas C

    // ── Construir filas ──────────────────────────────────
    // Cada entrada: ['tipo', 'label', 'valor' (opcional)]
    // tipo: 'title' | 'section' | 'data' | 'bold' | 'empty'
    $filas = [
        ['title',   "RESUMEN DDJJ IVA – Período: $periodo"],
        ['empty'],
        ['section', 'FACTURAS A'],
        ['data',    'Neto gravado al 10,5%',  round($fa['n105'], 2)],
        ['data',    'IVA al 10,5%',            round($fa['i105'], 2)],
        ['data',    'Neto gravado al 21%',     round($fa['n21'],  2)],
        ['data',    'IVA al 21%',              round($fa['i21'],  2)],
        ['data',    'Neto gravado al 27%',     round($fa['n27'],  2)],
        ['data',    'IVA al 27%',              round($fa['i27'],  2)],
        ['empty'],
        ['section', 'NOTAS DE CRÉDITO A'],
        ['data',    'Neto gravado al 10,5%',  round(abs($nca['n105']), 2)],
        ['data',    'IVA al 10,5%',            round(abs($nca['i105']), 2)],
        ['data',    'Neto gravado al 21%',     round(abs($nca['n21']),  2)],
        ['data',    'IVA al 21%',              round(abs($nca['i21']),  2)],
        ['data',    'Neto gravado al 27%',     round(abs($nca['n27']),  2)],
        ['data',    'IVA al 27%',              round(abs($nca['i27']),  2)],
        ['empty'],
        ['section', 'NOTAS DE DÉBITO A'],
        ['data',    'Neto gravado al 10,5%',  round($nda['n105'], 2)],
        ['data',    'IVA al 10,5%',            round($nda['i105'], 2)],
        ['data',    'Neto gravado al 21%',     round($nda['n21'],  2)],
        ['data',    'IVA al 21%',              round($nda['i21'],  2)],
        ['data',    'Neto gravado al 27%',     round($nda['n27'],  2)],
        ['data',    'IVA al 27%',              round($nda['i27'],  2)],
        ['empty'],
        ['section', 'FACTURAS C (Monotributistas)'],
        ['data',    'Total',                   round(abs($fc['total']), 2)],
        ['empty'],
        ['bold',    'PERCEPCIÓN IIBB',         round(abs($percIIBB), 2)],
        ['bold',    'PERCEPCIÓN IVA',          round(abs($percIva),  2)],
        ['bold',    'IMPUESTO INTERNO',        round(abs($impInt),   2)],
    ];

    // ── Generar XML del sheet ────────────────────────────
    //
    // Estilos:
    //  0 = texto normal
    //  1 = texto bold
    //  2 = número #,##0.00
    //  3 = número bold #,##0.00
    //  4 = texto bold + fondo azul   (bold importante)
    //  5 = número bold + fondo azul
    //  6 = texto bold + fondo violeta (secciones)

    $sheet = '';
    $r = 1;

    foreach ($filas as $fila) {
        switch ($fila[0]) {
            case 'title':
                $sheet .= xr($r, [xs('A'.$r, $fila[1], 1)]);
                break;
            case 'section':
                $sheet .= xr($r, [xs('A'.$r, $fila[1], 6)]);
                break;
            case 'data':
                $sheet .= xr($r, [
                    xs('A'.$r, $fila[1], 0),
                    xn('B'.$r, $fila[2], 2),
                ]);
                break;
            case 'bold':
                $sheet .= xr($r, [
                    xs('A'.$r, $fila[1], 4),
                    xn('B'.$r, $fila[2], 5),
                ]);
                break;
            case 'empty':
                $sheet .= xr($r, []);
                break;
        }
        $r++;
    }

    // ── Ensamblar XLSX ────────────────────────────────────
    $colWidths = '<cols>'
        . '<col min="1" max="1" width="36" customWidth="1"/>'
        . '<col min="2" max="2" width="20" customWidth="1"/>'
        . '</cols>';

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . $colWidths
        . '<sheetData>' . $sheet . '</sheetData>'
        . '</worksheet>';

    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
        . '<fonts count="2">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="4">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFEDE9FE"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="7">'
        . '<xf numFmtId="0"   fontId="0" fillId="0" borderId="0" xfId="0"/>'               // 0 texto normal
        . '<xf numFmtId="0"   fontId="1" fillId="0" borderId="0" xfId="0"/>'               // 1 texto bold
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0"/>'               // 2 número
        . '<xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0"/>'               // 3 número bold
        . '<xf numFmtId="0"   fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1"/>' // 4 bold text + azul
        . '<xf numFmtId="164" fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1"/>' // 5 bold num + azul
        . '<xf numFmtId="0"   fontId="1" fillId="3" borderId="0" xfId="0" applyFill="1"/>' // 6 bold text + violeta
        . '</cellXfs>'
        . '</styleSheet>';

    $tmp = sys_get_temp_dir() . '/' . uniqid('resumen_') . '.xlsx';
    $za  = new ZipArchive();
    $za->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $za->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml"  ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>');
    $za->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');
    $za->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Resumen IVA" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>');
    $za->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>');
    $za->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $za->addFromString('xl/styles.xml', $stylesXml);
    $za->close();

    return $tmp;
}

// ── Helpers XML ──────────────────────────────────────────────

function xr(int $r, array $cells): string
{
    return '<row r="' . $r . '">' . implode('', $cells) . '</row>';
}

function xs(string $ref, string $v, int $s): string
{
    return '<c r="' . $ref . '" t="inlineStr" s="' . $s . '"><is><t>' . xe($v) . '</t></is></c>';
}

function xn(string $ref, $v, int $s): string
{
    return '<c r="' . $ref . '" s="' . $s . '"><v>' . $v . '</v></c>';
}

function xe(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// ════════════════════════════════════════════════════════════
// HELPERS COMUNES
// ════════════════════════════════════════════════════════════

function calcularAlicuotas(float $neto, float $iva): array
{
    if ($neto < 0.01) return [];

    $pct = $iva / $neto * 100;

    if (abs($pct -  0.0) < 0.05) return [['neto' => $neto, 'iva' => $iva, 'cod' => '0003']];
    if (abs($pct -  2.5) < 0.1)  return [['neto' => $neto, 'iva' => $iva, 'cod' => '0009']];
    if (abs($pct -  5.0) < 0.1)  return [['neto' => $neto, 'iva' => $iva, 'cod' => '0008']];
    if (abs($pct - 10.5) < 0.1)  return [['neto' => $neto, 'iva' => $iva, 'cod' => '0004']];
    if (abs($pct - 21.0) < 0.1)  return [['neto' => $neto, 'iva' => $iva, 'cod' => '0005']];
    if (abs($pct - 27.0) < 0.1)  return [['neto' => $neto, 'iva' => $iva, 'cod' => '0006']];

    // Mezcla 10,5% + 21%
    if ($pct > 10.6 && $pct < 20.9) {
        $N2 = ($iva - 0.105 * $neto) / 0.105;
        $N1 = $neto - $N2;
        if ($N1 > 0.01 && $N2 > 0.01) {
            return [
                ['neto' => round($N1, 2), 'iva' => round($N1 * 0.105, 2), 'cod' => '0004'],
                ['neto' => round($N2, 2), 'iva' => round($N2 * 0.21,  2), 'cod' => '0005'],
            ];
        }
    }

    // Mezcla 21% + 27%
    if ($pct > 21.1 && $pct < 26.9) {
        $N2 = ($iva - 0.21 * $neto) / 0.06;
        $N1 = $neto - $N2;
        if ($N1 > 0.01 && $N2 > 0.01) {
            return [
                ['neto' => round($N1, 2), 'iva' => round($N1 * 0.21, 2), 'cod' => '0005'],
                ['neto' => round($N2, 2), 'iva' => round($N2 * 0.27, 2), 'cod' => '0006'],
            ];
        }
    }

    if ($pct < 15)  return [['neto' => $neto, 'iva' => $iva, 'cod' => '0004']];
    if ($pct < 24)  return [['neto' => $neto, 'iva' => $iva, 'cod' => '0005']];
    return              [['neto' => $neto, 'iva' => $iva, 'cod' => '0006']];
}

function toFechaAfip(string $fecha): string
{
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $fecha, $m)) {
        return $m[3] . str_pad($m[2], 2, '0', STR_PAD_LEFT) . str_pad($m[1], 2, '0', STR_PAD_LEFT);
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m)) return $m[1] . $m[2] . $m[3];
    if (preg_match('/^\d{8}$/', $fecha)) return $fecha;
    return date('Ymd');
}

function mapearTipo(string $tipo): string
{
    $t = mb_strtoupper(trim($tipo), 'UTF-8');
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t;
    $t = preg_replace('/[^A-Z0-9 ]/', ' ', $t);
    $t = trim(preg_replace('/\s+/', ' ', $t));

    if (preg_match('/^(\d{3})\b/', $t, $m)) return $m[1];

    $map = [
        'FACTURA A'         => '001', 'NOTA DE DEBITO A'  => '002',
        'NOTA DE CREDITO A' => '003', 'RECIBO A'          => '004',
        'FACTURA B'         => '006', 'NOTA DE DEBITO B'  => '007',
        'NOTA DE CREDITO B' => '008', 'RECIBO B'          => '015',
        'FACTURA C'         => '011', 'NOTA DE DEBITO C'  => '012',
        'NOTA DE CREDITO C' => '013', 'RECIBO C'          => '016',
        'LIQUIDACION A'     => '063', 'LIQUIDACION B'     => '064',
        'LIQUIDACION C'     => '027', 'CUENTA DE VENTA A' => '060',
        'CUENTA DE VENTA B' => '061', 'DESPACHO DE IMP'   => '029',
    ];

    foreach ($map as $key => $code) {
        if (str_contains($t, $key)) return $code;
    }

    return '099';
}
