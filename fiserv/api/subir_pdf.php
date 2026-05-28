<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/auth/check_api.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']); exit;
}

// ── Autoload Composer ────────────────────────────────────────────────────────
$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    echo json_encode(['error' => 'Librería de PDF no instalada. Ejecutá: composer require smalot/pdfparser']); exit;
}
require_once $autoload;

// ── Validar archivo ──────────────────────────────────────────────────────────
if (empty($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No se recibió ningún archivo PDF']); exit;
}

$file = $_FILES['pdf'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'pdf') {
    echo json_encode(['error' => 'El archivo debe ser un PDF']); exit;
}
if ($file['size'] > 20 * 1024 * 1024) {
    echo json_encode(['error' => 'El archivo supera los 20 MB']); exit;
}

// ── Parsear PDF ──────────────────────────────────────────────────────────────
try {
    $parser = new \Smalot\PdfParser\Parser();
    $pdf    = $parser->parseFile($file['tmp_name']);
    $text   = $pdf->getText();
} catch (Exception $e) {
    echo json_encode(['error' => 'Error al leer el PDF: ' . $e->getMessage()]); exit;
}

if (empty(trim($text))) {
    echo json_encode(['error' => 'No se pudo extraer texto del PDF. Verificá que no sea un PDF escaneado']); exit;
}

// ── Modo debug: devuelve el texto crudo para diagnóstico ─────────────────────
if (!empty($_POST['debug'])) {
    echo json_encode(['debug_text' => substr($text, 0, 4000)]); exit;
}

// ── Extraer header del PDF ───────────────────────────────────────────────────
$header = parseFiservHeader($text);

// ── Parsear liquidaciones ────────────────────────────────────────────────────
$liquidaciones = parseFiservLiquidaciones($text);

if (empty($liquidaciones)) {
    echo json_encode(['error' => 'No se encontraron liquidaciones en el PDF. Verificá que sea un resumen Fiserv válido']); exit;
}

// ── Guardar en BD ────────────────────────────────────────────────────────────
try {
    $pdo    = getDB();
    $codigo = 'FSV-' . strtoupper(substr(md5($file['name'] . microtime()), 0, 8));

    $pdo->prepare("
        INSERT INTO fiserv_lotes (codigo, archivo_nombre, tarjeta, periodo, nro_comercio, total_presentado, neto_pagos, total_filas)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $codigo,
        $file['name'],
        $header['tarjeta']          ?? null,
        $header['periodo']          ?? null,
        $header['nro_comercio']     ?? null,
        $header['total_presentado'] ?? 0,
        $header['neto_pagos']       ?? 0,
        count($liquidaciones),
    ]);

    $lote_id = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO fiserv_liquidaciones
            (lote_id, nro_liq, fecha_pago, fecha_pres,
             ventas_contado, arancel, iva_arancel, arancel_cuotas, iva_arancel_cuotas,
             promo_cuota_ahora, dto_financ_cuotas, iva_ri_dto_financ, dto_ventas_fin_adq,
             per_bai_brdn, ret_iibb_sirtac, iva_promo_cuota, iva_dto_fin_adq,
             perc_iva_1_5, perc_iva_3, cargo_terminal, cargo_sist_cuotas, iva_ri_sist_cuotas,
             qr_perc_iva, qr_ret_iibb, total_descuentos, acreditado)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    foreach ($liquidaciones as $liq) {
        $stmt->execute([
            $lote_id,
            $liq['nro_liq'],
            $liq['fecha_pago'],
            $liq['fecha_pres'],
            $liq['ventas_contado'],
            $liq['arancel'],
            $liq['iva_arancel'],
            $liq['arancel_cuotas'],
            $liq['iva_arancel_cuotas'],
            $liq['promo_cuota_ahora'],
            $liq['dto_financ_cuotas'],
            $liq['iva_ri_dto_financ'],
            $liq['dto_ventas_fin_adq'],
            $liq['per_bai_brdn'],
            $liq['ret_iibb_sirtac'],
            $liq['iva_promo_cuota'],
            $liq['iva_dto_fin_adq'],
            $liq['perc_iva_1_5'],
            $liq['perc_iva_3'],
            $liq['cargo_terminal'],
            $liq['cargo_sist_cuotas'],
            $liq['iva_ri_sist_cuotas'],
            $liq['qr_perc_iva'],
            $liq['qr_ret_iibb'],
            $liq['total_descuentos'],
            $liq['acreditado'],
        ]);
    }

    echo json_encode([
        'success'       => true,
        'lote_id'       => $lote_id,
        'codigo'        => $codigo,
        'header'        => $header,
        'liquidaciones' => $liquidaciones,
        'total'         => count($liquidaciones),
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Error al guardar en base de datos: ' . $e->getMessage()]);
}

// ═══════════════════════════════════════════════════════════════════════════════
// FUNCIONES DE PARSEO
// ═══════════════════════════════════════════════════════════════════════════════

function parseAmount(string $raw): float {
    $clean = str_replace('.', '', trim($raw));
    $clean = str_replace(',', '.', $clean);
    return (float)$clean;
}

function parseDate(string $raw): ?string {
    if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $raw, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return null;
}

function parseFiservHeader(string $text): array {
    $header = [];

    // Período
    if (preg_match('/(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)\s+(\d{4})/i', $text, $m)) {
        $header['periodo'] = strtoupper($m[1]) . ' ' . $m[2];
    }

    // Marca de tarjeta — buscar en el texto; si no aparece usar genérico
    if (preg_match('/\b(MASTERCARD|VISA|AMERICAN\s+EXPRESS|AMEX|CABAL)\b/i', $text, $m)) {
        $header['tarjeta'] = ucwords(strtolower(trim($m[1]))) . ' Crédito';
    } elseif (preg_match('/TARJETA\s+DE\s+CREDITO/i', $text)) {
        $header['tarjeta'] = 'Tarjeta Crédito';
    }

    // Nro Comercio
    if (preg_match('/N[°º]?\s*Comercio[:\s]+([\d]+)/i', $text, $m)) {
        $header['nro_comercio'] = $m[1];
    } elseif (preg_match('/(\d{8,10})\s*\/\s*\d/', $text, $m)) {
        $header['nro_comercio'] = $m[1];
    }

    // Total presentado / Neto de pagos
    if (preg_match('/Total\s+presentado[:\s]+([\d.,]+)/i', $text, $m)) {
        $header['total_presentado'] = parseAmount($m[1]);
    }
    if (preg_match('/Neto\s+de\s+pagos[:\s]+([\d.,]+)/i', $text, $m)) {
        $header['neto_pagos'] = parseAmount($m[1]);
    }

    return $header;
}

function parseFiservLiquidaciones(string $text): array {
    $liquidaciones = [];

    $text  = str_replace(["\r\n", "\r"], "\n", $text);
    $parts = preg_split('/(?=F\.?\s*de\s+Pago\s*:)/i', $text);

    foreach ($parts as $chunk) {
        if (!preg_match('/^F\.?\s*de\s+Pago\s*:/i', ltrim($chunk))) continue;

        $lines   = explode("\n", $chunk);
        $fdePago = '';
        foreach ($lines as $line) {
            if (preg_match('/^F\.?\s*de\s+Pago\s*:/i', ltrim($line))) { $fdePago = $line; break; }
        }

        $nro_liq    = '';
        $fecha_pago = null;
        $fecha_pres = null;

        // Normalizar todo el chunk a una sola línea para encontrar metadatos aunque
        // el PDF extraiga cada celda de la tabla F.de Pago en líneas separadas
        $chunkFlat = preg_replace('/\s+/', ' ', $chunk);

        if (preg_match('/Nro\.?\s*Liq[:\s.]*(\d+)/i', $chunkFlat, $m))               $nro_liq    = $m[1];
        if (preg_match('/el\s+d[íi]a\s+(\d{2}\/\d{2}\/\d{4})/i', $chunkFlat, $m))   $fecha_pago = parseDate($m[1]);
        if (preg_match('/F\.?\s*Pres\.?\s+(\d{2}\/\d{2}\/\d{4})/i', $chunkFlat, $m)) $fecha_pres = parseDate($m[1]);

        if (empty($nro_liq) && $fecha_pago === null) continue;

        // ── Parseo línea por línea con detección de signo ─────────────────
        $v = array_fill_keys([
            'ventas_contado', 'arancel', 'iva_arancel', 'arancel_cuotas', 'iva_arancel_cuotas',
            'promo_cuota_ahora', 'dto_financ_cuotas', 'iva_ri_dto_financ', 'dto_ventas_fin_adq',
            'per_bai_brdn', 'ret_iibb_sirtac', 'iva_promo_cuota', 'iva_dto_fin_adq',
            'perc_iva_1_5', 'perc_iva_3', 'cargo_terminal', 'cargo_sist_cuotas',
            'iva_ri_sist_cuotas', 'qr_perc_iva', 'qr_ret_iibb',
        ], 0.0);

        $acreditado = null;

        // Intentar parseo línea a línea (funciona si el PDF extrae cada ítem en una sola línea)
        foreach ($lines as $line) {
            $t = trim($line);

            // IMPORTE NETO DE PAGOS — el "-" final indica débito (negativo para el comercio)
            if (preg_match('/IMPORTE\s+NETO\s+DE\s+PAGOS\s+\$\s*([\d.,]+)\s*(-?)/i', $t, $m)) {
                $val        = parseAmount($m[1]);
                $acreditado = ($m[2] === '-') ? -$val : $val;
                continue;
            }

            // Líneas con signo: "- DESCRIPCION $ IMPORTE" o "+ DESCRIPCION $ IMPORTE"
            if (!preg_match('/^([-+])\s+(.+?)\s+\$\s*([\d.,]+)\s*$/', $t, $m)) continue;

            $sign  = $m[1];
            $desc  = $m[2];
            $amt   = parseAmount($m[3]);
            $delta = ($sign === '-') ? $amt : -$amt;

            if      (preg_match('/VENTAS\s+C\/DESCUENTO\s+CONTADO/i', $desc))
                $v['ventas_contado']     += ($sign === '+') ? $amt : -$amt;
            elseif  (preg_match('/IVA\s+ARANCEL\s+CUOTAS/i', $desc))
                $v['iva_arancel_cuotas'] += $delta;
            elseif  (preg_match('/ARANCEL\s+CUOTAS/i', $desc))
                $v['arancel_cuotas']     += $delta;
            elseif  (preg_match('/IVA\s+CRED\.?FISC\.?.*S\/ARANC/i', $desc))
                $v['iva_arancel']        += $delta;
            elseif  (preg_match('/^ARANCEL\s*$/i', $desc))
                $v['arancel']            += $delta;
            elseif  (preg_match('/IVA\s+PROMO\s+CUOTA\s+AHORA/i', $desc))
                $v['iva_promo_cuota']    += $delta;
            elseif  (preg_match('/PROMO\s+CUOTA\s+AHORA/i', $desc))
                $v['promo_cuota_ahora']  += $delta;
            elseif  (preg_match('/DESCUENTO\s+FINANC\s+OTORG/i', $desc))
                $v['dto_financ_cuotas']  += $delta;
            elseif  (preg_match('/IVA\s+RI\s+CRED.*S\/DTO/i', $desc))
                $v['iva_ri_dto_financ']  += $delta;
            elseif  (preg_match('/DTO\s+S\/VENTAS\s+FIN\s+ADQ/i', $desc))
                $v['dto_ventas_fin_adq'] += $delta;
            elseif  (preg_match('/IVA\s+S\/DTO\s+FIN\s+ADQ/i', $desc))
                $v['iva_dto_fin_adq']    += $delta;
            elseif  (preg_match('/PER\s+B\.?A\.?I/i', $desc))
                $v['per_bai_brdn']       += $delta;
            elseif  (preg_match('/RETENCION\s+ING\.?\s*BRUTOS.*SIRTAC/i', $desc))
                $v['ret_iibb_sirtac']    += $delta;
            elseif  (preg_match('/PERCEPCION\s+IVA\s+R\.?G\.?\s*2408\s+1[,.]?5/i', $desc))
                $v['perc_iva_1_5']       += $delta;
            elseif  (preg_match('/PERCEPCION\s+IVA\s+R\.?G\.?\s*2408\s+3/i', $desc))
                $v['perc_iva_3']         += $delta;
            elseif  (preg_match('/CARGO\s+TERMINAL\s+FISERV/i', $desc))
                $v['cargo_terminal']     += $delta;
            elseif  (preg_match('/CARGO\s+SISTEMA\s+CUOTAS\s+MENS/i', $desc))
                $v['cargo_sist_cuotas']  += $delta;
            elseif  (preg_match('/IVA\s+RI\s+SIST\s+CUOTAS/i', $desc))
                $v['iva_ri_sist_cuotas'] += $delta;
            elseif  (preg_match('/QR\s+PERCEPCION\s+IVA/i', $desc))
                $v['qr_perc_iva']        += $delta;
            elseif  (preg_match('/QR\s+RETENCION\s+IIBB/i', $desc))
                $v['qr_ret_iibb']        += $delta;
        }

        // ── Fallback: si el parseo línea a línea no encontró nada, buscar en chunk aplanado ──
        // (cubre el caso donde el PDF extrae cada celda/columna en líneas separadas)
        $anyValue = false;
        foreach ($v as $val) { if ($val != 0) { $anyValue = true; break; } }
        if (!$anyValue && $acreditado === null) {
            extractFromChunk($chunkFlat, $v, $acreditado);
        }

        // TOTAL = suma de columnas con valor positivo neto (descuentos reales)
        $total_descuentos = 0.0;
        foreach ($v as $k => $val) {
            if ($k !== 'ventas_contado' && $val > 0) $total_descuentos += $val;
        }

        // ACREDITADO: usar IMPORTE NETO del PDF si está disponible; sino calcular
        if ($acreditado === null) {
            $acreditado = $v['ventas_contado'] - $total_descuentos;
        }

        $liquidaciones[] = [
            'nro_liq'           => $nro_liq,
            'fecha_pago'        => $fecha_pago,
            'fecha_pres'        => $fecha_pres,
            'ventas_contado'    => round($v['ventas_contado'], 2),
            'arancel'           => round($v['arancel'], 2),
            'iva_arancel'       => round($v['iva_arancel'], 2),
            'arancel_cuotas'    => round($v['arancel_cuotas'], 2),
            'iva_arancel_cuotas'=> round($v['iva_arancel_cuotas'], 2),
            'promo_cuota_ahora' => round($v['promo_cuota_ahora'], 2),
            'dto_financ_cuotas' => round($v['dto_financ_cuotas'], 2),
            'iva_ri_dto_financ' => round($v['iva_ri_dto_financ'], 2),
            'dto_ventas_fin_adq'=> round($v['dto_ventas_fin_adq'], 2),
            'per_bai_brdn'      => round($v['per_bai_brdn'], 2),
            'ret_iibb_sirtac'   => round($v['ret_iibb_sirtac'], 2),
            'iva_promo_cuota'   => round($v['iva_promo_cuota'], 2),
            'iva_dto_fin_adq'   => round($v['iva_dto_fin_adq'], 2),
            'perc_iva_1_5'      => round($v['perc_iva_1_5'], 2),
            'perc_iva_3'        => round($v['perc_iva_3'], 2),
            'cargo_terminal'    => round($v['cargo_terminal'], 2),
            'cargo_sist_cuotas' => round($v['cargo_sist_cuotas'], 2),
            'iva_ri_sist_cuotas'=> round($v['iva_ri_sist_cuotas'], 2),
            'qr_perc_iva'       => round($v['qr_perc_iva'], 2),
            'qr_ret_iibb'       => round($v['qr_ret_iibb'], 2),
            'total_descuentos'  => round($total_descuentos, 2),
            'acreditado'        => round($acreditado, 2),
        ];
    }

    return $liquidaciones;
}

// Fallback: extrae valores con regex sobre texto aplanado (sin saltos de línea).
// Se usa cuando el PDF separa cada celda en líneas distintas y el parser línea-a-línea no encuentra nada.
function extractFromChunk(string $flat, array &$v, ?float &$acreditado): void {
    // IMPORTE NETO DE PAGOS
    if (preg_match('/IMPORTE\s+NETO\s+DE\s+PAGOS\s+\$\s*([\d.,]+)\s*(-?)/i', $flat, $m)) {
        $val = parseAmount($m[1]);
        $acreditado = ($m[2] === '-') ? -$val : $val;
    }

    // Patrón genérico: busca "CONCEPTO $ MONTO" con signo opcional antes del concepto
    // Suma todos los matches por concepto respetando el signo + / -
    $rows = [];
    if (preg_match_all('/([+-])\s+([\w][^\$\n]{2,60?}?)\s+\$\s*([\d.,]+)/u', $flat, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $sign  = $m[1];
            $desc  = trim($m[2]);
            $amt   = parseAmount($m[3]);
            $delta = ($sign === '-') ? $amt : -$amt;
            $rows[] = [$sign, $desc, $amt, $delta];
        }
    }

    foreach ($rows as [$sign, $desc, $amt, $delta]) {
        if      (preg_match('/VENTAS\s+C\/DESCUENTO\s+CONTADO/i', $desc))
            $v['ventas_contado']     += ($sign === '+') ? $amt : -$amt;
        elseif  (preg_match('/IVA\s+ARANCEL\s+CUOTAS/i', $desc))
            $v['iva_arancel_cuotas'] += $delta;
        elseif  (preg_match('/ARANCEL\s+CUOTAS/i', $desc))
            $v['arancel_cuotas']     += $delta;
        elseif  (preg_match('/IVA\s+CRED\.?FISC\.?.*S\/ARANC/i', $desc))
            $v['iva_arancel']        += $delta;
        elseif  (preg_match('/^ARANCEL\s*$/i', $desc))
            $v['arancel']            += $delta;
        elseif  (preg_match('/IVA\s+PROMO\s+CUOTA\s+AHORA/i', $desc))
            $v['iva_promo_cuota']    += $delta;
        elseif  (preg_match('/PROMO\s+CUOTA\s+AHORA/i', $desc))
            $v['promo_cuota_ahora']  += $delta;
        elseif  (preg_match('/DESCUENTO\s+FINANC\s+OTORG/i', $desc))
            $v['dto_financ_cuotas']  += $delta;
        elseif  (preg_match('/IVA\s+RI\s+CRED.*S\/DTO/i', $desc))
            $v['iva_ri_dto_financ']  += $delta;
        elseif  (preg_match('/DTO\s+S\/VENTAS\s+FIN\s+ADQ/i', $desc))
            $v['dto_ventas_fin_adq'] += $delta;
        elseif  (preg_match('/IVA\s+S\/DTO\s+FIN\s+ADQ/i', $desc))
            $v['iva_dto_fin_adq']    += $delta;
        elseif  (preg_match('/PER\s+B\.?A\.?I/i', $desc))
            $v['per_bai_brdn']       += $delta;
        elseif  (preg_match('/RETENCION\s+ING\.?\s*BRUTOS.*SIRTAC/i', $desc))
            $v['ret_iibb_sirtac']    += $delta;
        elseif  (preg_match('/PERCEPCION\s+IVA\s+R\.?G\.?\s*2408\s+1[,.]?5/i', $desc))
            $v['perc_iva_1_5']       += $delta;
        elseif  (preg_match('/PERCEPCION\s+IVA\s+R\.?G\.?\s*2408\s+3/i', $desc))
            $v['perc_iva_3']         += $delta;
        elseif  (preg_match('/CARGO\s+TERMINAL\s+FISERV/i', $desc))
            $v['cargo_terminal']     += $delta;
        elseif  (preg_match('/CARGO\s+SISTEMA\s+CUOTAS\s+MENS/i', $desc))
            $v['cargo_sist_cuotas']  += $delta;
        elseif  (preg_match('/IVA\s+RI\s+SIST\s+CUOTAS/i', $desc))
            $v['iva_ri_sist_cuotas'] += $delta;
        elseif  (preg_match('/QR\s+PERCEPCION\s+IVA/i', $desc))
            $v['qr_perc_iva']        += $delta;
        elseif  (preg_match('/QR\s+RETENCION\s+IIBB/i', $desc))
            $v['qr_ret_iibb']        += $delta;
    }
}
