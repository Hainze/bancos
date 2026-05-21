<?php
/**
 * api/libro_iva_ddjj.php
 * Genera los dos TXT para Portal IVA DDJJ a partir del Excel procesado:
 *   - *_IVAVentasDigital_Alic.txt  (62 chars/línea)
 *   - *_IVAVentasDigital_Cbte.txt  (266 chars/línea)
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

            [$alicContent, $cbteContent, $periodo, $stats] = generarDDJJTxt($filas);

            echo json_encode([
                'success'     => true,
                'periodo'     => $periodo,
                'alic_lineas' => $stats['alic'],
                'cbte_lineas' => $stats['cbte'],
                'nombre_base' => $stats['nombre_base'],
                'alic_b64'    => base64_encode($alicContent),
                'cbte_b64'    => base64_encode($cbteContent),
            ]);
            break;

        default:
            echo json_encode(['error' => 'Acción no reconocida: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

// ════════════════════════════════════════════════════════════
// LEER XLSX PROCESADO (formato de salida de libro_iva.php)
// ════════════════════════════════════════════════════════════

/**
 * Lee el XLSX procesado (nuestro output) y devuelve array [rowNum => ['A'=>..., 'B'=>..., ...]]
 * Columnas relevantes:
 *   A=Fecha  B=Tipo  C=PtoVta  D=NroDesde  E=NroDocVendedor  F=Denominación
 *   G=TipoCambio  H=NetoGravado  I=NoGravado  J=Exento  K=IVA
 *   L=PercIIBB  M=PercIVA  N=ImpInt  O=Total
 */
function leerXlsxProcesado(string $path): array
{
    $za = new ZipArchive();
    if ($za->open($path) !== true) return [];

    $sheetXml = $za->getFromName('xl/worksheets/sheet1.xml');
    $za->close();
    if (!$sheetXml) return [];

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$dom->loadXML($sheetXml)) return [];
    libxml_clear_errors();

    $filas = [];
    foreach ($dom->getElementsByTagName('row') as $rowEl) {
        $rowNum = (int)$rowEl->getAttribute('r');
        if ($rowNum < 2) continue; // skip header

        $cells = [];
        foreach ($rowEl->getElementsByTagName('c') as $c) {
            $ref = $c->getAttribute('r');
            $col = preg_replace('/\d+/', '', $ref); // e.g. 'A', 'B', 'AA'
            $t   = $c->getAttribute('t');

            if ($t === 'inlineStr') {
                $node = $c->getElementsByTagName('t')->item(0);
                $cells[$col] = $node ? trim($node->textContent) : '';
            } else {
                // Para celdas numéricas y fórmulas con valor cacheado
                $vEl = $c->getElementsByTagName('v')->item(0);
                $cells[$col] = $vEl ? $vEl->textContent : '';
            }
        }

        $noEmpty = array_filter($cells, fn($v) => $v !== '');
        if (!empty($noEmpty)) {
            $filas[$rowNum] = $cells;
        }
    }

    return $filas;
}

// ════════════════════════════════════════════════════════════
// GENERADOR DE TXT
// ════════════════════════════════════════════════════════════

function generarDDJJTxt(array $filas): array
{
    $alicLines = [];
    $cbteLines = [];
    $periodo   = '';

    foreach ($filas as $row) {
        $tipoStr = $row['B'] ?? '';

        // Saltar filas de desglose (↳ 10,5 % / ↳ 21 % generadas por el sistema)
        if (str_starts_with($tipoStr, '↳')) continue;

        $fecha   = trim($row['A'] ?? '');
        $tipoStr = trim($tipoStr);
        if ($fecha === '' || $tipoStr === '') continue;

        // Tipo cambio (negativo para NC — usamos abs)
        $tc = abs((float)($row['G'] ?? 1));
        if ($tc < 0.0001) $tc = 1.0;

        // Importes en moneda original
        $neto    = abs((float)($row['H'] ?? 0));
        $noGrav  = abs((float)($row['I'] ?? 0));
        $exento  = abs((float)($row['J'] ?? 0));
        $iva     = abs((float)($row['K'] ?? 0));
        $percIIBB= abs((float)($row['L'] ?? 0));
        $percIva = abs((float)($row['M'] ?? 0));
        $impInt  = abs((float)($row['N'] ?? 0));

        // Convertir a pesos
        $neto_p    = round($neto    * $tc, 2);
        $noGrav_p  = round($noGrav  * $tc, 2);
        $exento_p  = round($exento  * $tc, 2);
        $iva_p     = round($iva     * $tc, 2);
        $percIIBB_p= round($percIIBB* $tc, 2);
        $percIva_p = round($percIva * $tc, 2);
        $impInt_p  = round($impInt  * $tc, 2);
        $total_p   = round(($neto + $noGrav + $exento + $iva + $percIIBB + $percIva + $impInt) * $tc, 2);

        $ptoVta   = (int)($row['C'] ?? 0);
        $nroDesde = (int)($row['D'] ?? 0);

        // Fecha AFIP (YYYYMMDD)
        $fechaAfip = toFechaAfip($fecha);
        if ($periodo === '' && strlen($fechaAfip) === 8) {
            $periodo = substr($fechaAfip, 0, 6); // YYYYMM
        }

        // Código tipo comprobante AFIP
        $tipoAfip = mapearTipo($tipoStr);

        // CUIT / tipo documento
        $cuitRaw = preg_replace('/[^0-9]/', '', $row['E'] ?? '');
        if (strlen($cuitRaw) === 11) {
            $tipoDoc = '80';
            $cuitPad = str_pad($cuitRaw, 20, '0', STR_PAD_LEFT);
        } elseif ($cuitRaw !== '') {
            $tipoDoc = '96';
            $cuitPad = str_pad(substr($cuitRaw, 0, 20), 20, '0', STR_PAD_LEFT);
        } else {
            $tipoDoc = '96';
            $cuitPad = str_repeat('0', 20);
        }

        // Razón social: 30 chars, mayúsculas, sin tildes
        $razon = mb_strtoupper(trim($row['F'] ?? ''), 'UTF-8');
        $razon = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $razon) ?: $razon;
        $razon = str_pad(substr($razon, 0, 30), 30);

        // Calcular líneas de alícuotas
        $alicRows = calcularAlicuotas($neto_p, $iva_p);
        $cantAlic = count($alicRows);

        // ── ALIC.TXT ──
        foreach ($alicRows as $a) {
            $alicLines[] =
                str_pad($tipoAfip, 3, '0', STR_PAD_LEFT)          .  // 3
                str_pad((string)$ptoVta, 5, '0', STR_PAD_LEFT)    .  // 5
                str_pad((string)$nroDesde, 20, '0', STR_PAD_LEFT) .  // 20
                str_pad((string)(int)round($a['neto'] * 100), 15, '0', STR_PAD_LEFT) . // 15
                $a['cod']                                          .  // 4
                str_pad((string)(int)round($a['iva'] * 100), 15, '0', STR_PAD_LEFT);   // 15
        }

        // ── CBTE.TXT ──
        $cbteLines[] =
            $fechaAfip                                                              .  // 8
            str_pad($tipoAfip, 3, '0', STR_PAD_LEFT)                              .  // 3
            str_pad((string)$ptoVta, 5, '0', STR_PAD_LEFT)                        .  // 5
            str_pad((string)$nroDesde, 20, '0', STR_PAD_LEFT)                     .  // 20 nroDesde
            str_pad((string)$nroDesde, 20, '0', STR_PAD_LEFT)                     .  // 20 nroHasta
            $tipoDoc                                                               .  // 2
            $cuitPad                                                               .  // 20
            $razon                                                                 .  // 30
            str_pad((string)(int)round($total_p   * 100), 15, '0', STR_PAD_LEFT)  .  // 15 total
            str_pad((string)(int)round($noGrav_p  * 100), 15, '0', STR_PAD_LEFT)  .  // 15 noGrav
            str_pad((string)(int)round($exento_p  * 100), 15, '0', STR_PAD_LEFT)  .  // 15 exento
            str_pad((string)(int)round($percIva_p * 100), 15, '0', STR_PAD_LEFT)  .  // 15 percIVA
            str_repeat('0', 15)                                                    .  // 15 percNac (0)
            str_pad((string)(int)round($percIIBB_p* 100), 15, '0', STR_PAD_LEFT)  .  // 15 percIIBB
            str_repeat('0', 15)                                                    .  // 15 percMun (0)
            str_pad((string)(int)round($impInt_p  * 100), 15, '0', STR_PAD_LEFT)  .  // 15 impInt
            'PES'                                                                  .  // 3
            '0001000000'                                                           .  // 10 TC=1.000000
            (string)($cantAlic ?: 1)                                               .  // 1
            ' '                                                                    .  // 1 codOp
            str_repeat('0', 15)                                                    .  // 15 credFisc
            $fechaAfip;                                                               // 8 fechaVto
    }

    // Armar contenido con CRLF (formato AFIP)
    $alicContent = $alicLines ? implode("\r\n", $alicLines) . "\r\n" : '';
    $cbteContent = $cbteLines ? implode("\r\n", $cbteLines) . "\r\n" : '';

    // Período formateado
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
    ];
}

// ════════════════════════════════════════════════════════════
// HELPERS
// ════════════════════════════════════════════════════════════

/**
 * Determina las líneas de alícuotas para una factura.
 * Detecta mezcla 10,5%+21% y 21%+27% automáticamente.
 * Retorna array de ['neto', 'iva', 'cod'] por alícuota.
 */
function calcularAlicuotas(float $neto, float $iva): array
{
    if ($neto < 0.01) return [];

    $pct = $iva / $neto * 100;

    // Alícuotas simples
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

    // Fallback: alícuota más cercana
    if ($pct < 15)  return [['neto' => $neto, 'iva' => $iva, 'cod' => '0004']];
    if ($pct < 24)  return [['neto' => $neto, 'iva' => $iva, 'cod' => '0005']];
    return              [['neto' => $neto, 'iva' => $iva, 'cod' => '0006']];
}

/** Convierte fecha DD/MM/YYYY o YYYY-MM-DD a YYYYMMDD */
function toFechaAfip(string $fecha): string
{
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $fecha, $m)) {
        return $m[3] . str_pad($m[2], 2, '0', STR_PAD_LEFT) . str_pad($m[1], 2, '0', STR_PAD_LEFT);
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m)) {
        return $m[1] . $m[2] . $m[3];
    }
    if (preg_match('/^\d{8}$/', $fecha)) return $fecha;
    return date('Ymd');
}

/** Mapea descripción de tipo de comprobante ARCA → código AFIP 3 dígitos */
function mapearTipo(string $tipo): string
{
    // Normalizar: mayúsculas, sin tildes, solo A-Z y espacios
    $t = mb_strtoupper(trim($tipo), 'UTF-8');
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t;
    $t = preg_replace('/[^A-Z0-9 ]/', ' ', $t);
    $t = trim(preg_replace('/\s+/', ' ', $t));

    // Código numérico explícito al inicio: "001", "006 - Factura B", etc.
    if (preg_match('/^(\d{3})\b/', $t, $m)) return $m[1];

    $map = [
        'FACTURA A'            => '001',
        'NOTA DE DEBITO A'     => '002',
        'NOTA DE CREDITO A'    => '003',
        'RECIBO A'             => '004',
        'FACTURA B'            => '006',
        'NOTA DE DEBITO B'     => '007',
        'NOTA DE CREDITO B'    => '008',
        'RECIBO B'             => '015',
        'FACTURA C'            => '011',
        'NOTA DE DEBITO C'     => '012',
        'NOTA DE CREDITO C'    => '013',
        'RECIBO C'             => '016',
        'LIQUIDACION A'        => '063',
        'LIQUIDACION B'        => '064',
        'LIQUIDACION C'        => '027',
        'CUENTA DE VENTA A'    => '060',
        'CUENTA DE VENTA B'    => '061',
        'DESPACHO DE IMP'      => '029',
    ];

    foreach ($map as $key => $code) {
        if (str_contains($t, $key)) return $code;
    }

    return '099'; // desconocido
}
