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
    // Formato argentino: 1.274,69 → 1274.69
    $clean = str_replace('.', '', trim($raw)); // quita separador de miles
    $clean = str_replace(',', '.', $clean);    // decimal
    return (float)$clean;
}

function parseDate(string $raw): ?string {
    // DD/MM/YYYY → YYYY-MM-DD
    if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $raw, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return null;
}

function extractAmount(string $text, string $pattern): float {
    if (preg_match($pattern, $text, $m)) {
        return parseAmount($m[1]);
    }
    return 0.0;
}

function extractAmountSum(string $text, string $pattern): float {
    // Suma todas las ocurrencias (por si aparece 2 veces en un bloque)
    $total = 0.0;
    if (preg_match_all($pattern, $text, $matches)) {
        foreach ($matches[1] as $val) {
            $total += parseAmount($val);
        }
    }
    return $total;
}

function parseFiservHeader(string $text): array {
    $header = [];

    // Tarjeta + período (ej: "TARJETA DE CREDITO PESOS    MARZO 2026")
    if (preg_match('/TARJETA\s+DE\s+CREDITO\s+(\w+)\s+([\w\s]+?\d{4})/i', $text, $m)) {
        $header['tarjeta'] = 'Visa Crédito ' . trim($m[1]);
        $header['periodo'] = trim($m[2]);
    } elseif (preg_match('/(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)\s+(\d{4})/i', $text, $m)) {
        $header['periodo'] = $m[1] . ' ' . $m[2];
    }

    // Nro Comercio (ej: "003138142 / 1" o "N° Comercio: 003138142")
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

    // Normalizar saltos de línea
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // Dividir el texto por cada línea "F.de Pago:" que cierra un bloque
    // Cada bloque termina con esa línea
    $parts = preg_split('/(?=F\.?\s*de\s+Pago\s*:)/i', $text);

    foreach ($parts as $chunk) {
        // Solo procesar chunks que tengan una línea F.de Pago al inicio
        if (!preg_match('/^F\.?\s*de\s+Pago\s*:/i', ltrim($chunk))) {
            continue;
        }

        // Extraer metadata de la línea F.de Pago
        $fdePago = '';
        $lines   = explode("\n", $chunk);
        foreach ($lines as $line) {
            if (preg_match('/^F\.?\s*de\s+Pago\s*:/i', ltrim($line))) {
                $fdePago = $line;
                break;
            }
        }

        // Nro. Liquidación
        $nro_liq = '';
        if (preg_match('/Nro\.?\s*Liq[:\s.]*(\d+)/i', $fdePago, $m)) {
            $nro_liq = $m[1];
        }

        // Fecha de pago
        $fecha_pago = null;
        if (preg_match('/el\s+d[íi]a\s+(\d{2}\/\d{2}\/\d{4})/i', $fdePago, $m)) {
            $fecha_pago = parseDate($m[1]);
        }

        // Fecha de presentación
        $fecha_pres = null;
        if (preg_match('/F\.?\s*Pres(?:\.|\s)\s*(\d{2}\/\d{2}\/\d{4})/i', $fdePago, $m)) {
            $fecha_pres = parseDate($m[1]);
        }

        // Si no hay nro_liq ni fecha_pago probablemente es ruido, skip
        if (empty($nro_liq) && $fecha_pago === null) {
            continue;
        }

        // ── Extraer importes del bloque ────────────────────────────────────
        // Para buscar ARANCEL puro (sin CUOTAS ni IVA) usamos lookahead negativo
        $ventas_contado    = extractAmountSum($chunk, '/VENTAS\s+C\/DESCUENTO\s+CONTADO\s+\$\s*([\d.,]+)/i');
        $arancel           = extractAmountSum($chunk, '/[-+]?\s*ARANCEL(?!\s+CUOTAS)(?!\s+IVA)\s+\$\s*([\d.,]+)/i');
        $iva_arancel       = extractAmount($chunk, '/IVA\s+CRED\.?FISC\.?COMERCIO\s+S\/ARANC\s+[\d,]+%?\s+\$\s*([\d.,]+)/i');
        $arancel_cuotas    = extractAmount($chunk, '/ARANCEL\s+CUOTAS\s+\$\s*([\d.,]+)/i');
        $iva_arancel_cuotas= extractAmount($chunk, '/IVA\s+ARANCEL\s+CUOTAS\s+[\d,]+%?\s+\$\s*([\d.,]+)/i');
        $promo_cuota_ahora = extractAmount($chunk, '/PROMO\s+CUOTA\s+AHORA\s+SIMPLE\s+\$\s*([\d.,]+)/i');
        $dto_financ_cuotas = extractAmount($chunk, '/DESCUENTO\s+FINANC\s+OTORG\.?\s*CUOTAS\s+\$\s*([\d.,]+)/i');
        $iva_ri_dto_financ = extractAmount($chunk, '/IVA\s+RI\s+CRED\.?FISC\.?COMERCIO\s+S\/DTO\s+F\.OTORG\s+[\d,]+%?\s+\$\s*([\d.,]+)/i');
        $dto_ventas_fin_adq= extractAmount($chunk, '/DTO\s+S\/VENTAS\s+FIN\s+ADQ\s+CONT\s+\$\s*([\d.,]+)/i');
        $per_bai_brdn      = extractAmount($chunk, '/PER\s+B\.A\.I\.?\s*BR\.?D?N?\.?\s*\d+\/\d+\s+\$\s*([\d.,]+)/i');
        $ret_iibb_sirtac   = extractAmount($chunk, '/RETENCION\s+ING\.?\s*BRUTOS\s+(?:BSAS\s+)?SIRTAC\s+\$\s*([\d.,]+)/i');
        $iva_promo_cuota   = extractAmount($chunk, '/IVA\s+PROMO\s+CUOTA\s+AHORA\s+SIMPLE\s+[\d,]+%?\s+\$\s*([\d.,]+)/i');
        $iva_dto_fin_adq   = extractAmount($chunk, '/IVA\s+S\/DTO\s+FIN\s+ADQ\s+CONT\s+[\d,]+%?\s+\$\s*([\d.,]+)/i');
        $perc_iva_1_5      = extractAmount($chunk, '/PERCEPCION\s+IVA\s+R\.?G\.?\s*2408\s+1[,.]?5[0]?%\s+\$\s*([\d.,]+)/i');
        $perc_iva_3        = extractAmount($chunk, '/PERCEPCION\s+IVA\s+R\.?G\.?\s*2408\s+3%\s+\$\s*([\d.,]+)/i');
        $cargo_terminal    = extractAmount($chunk, '/CARGO\s+TERMINAL\s+FISERV\s+\$\s*([\d.,]+)/i');
        $cargo_sist_cuotas = extractAmount($chunk, '/CARGO\s+SISTEMA\s+CUOTAS\s+MENS\s+\$\s*([\d.,]+)/i');
        $iva_ri_sist_cuotas= extractAmount($chunk, '/IVA\s+RI\s+SIST\s+CUOTAS\s+\$\s*([\d.,]+)/i');
        $qr_perc_iva       = extractAmount($chunk, '/QR\s+PERCEPCION\s+IVA\s+\$\s*([\d.,]+)/i');
        $qr_ret_iibb       = extractAmount($chunk, '/QR\s+RETENCION\s+IIBB\s+(?:BS\.?AS\.?\s+)?\$\s*([\d.,]+)/i');

        // TOTAL = suma de todos los descuentos
        $total_descuentos = $arancel + $iva_arancel + $arancel_cuotas + $iva_arancel_cuotas
            + $promo_cuota_ahora + $dto_financ_cuotas + $iva_ri_dto_financ + $dto_ventas_fin_adq
            + $per_bai_brdn + $ret_iibb_sirtac + $iva_promo_cuota + $iva_dto_fin_adq
            + $perc_iva_1_5 + $perc_iva_3 + $cargo_terminal + $cargo_sist_cuotas
            + $iva_ri_sist_cuotas + $qr_perc_iva + $qr_ret_iibb;

        // ACREDITADO: preferir IMPORTE NETO DE PAGOS del bloque (más preciso)
        $acreditado = extractAmount($chunk, '/IMPORTE\s+NETO\s+DE\s+PAGOS\s+\$\s*([\d.,]+)/i');
        if ($acreditado == 0) {
            $acreditado = $ventas_contado - $total_descuentos;
        }

        $liquidaciones[] = [
            'nro_liq'           => $nro_liq,
            'fecha_pago'        => $fecha_pago,
            'fecha_pres'        => $fecha_pres,
            'ventas_contado'    => round($ventas_contado, 2),
            'arancel'           => round($arancel, 2),
            'iva_arancel'       => round($iva_arancel, 2),
            'arancel_cuotas'    => round($arancel_cuotas, 2),
            'iva_arancel_cuotas'=> round($iva_arancel_cuotas, 2),
            'promo_cuota_ahora' => round($promo_cuota_ahora, 2),
            'dto_financ_cuotas' => round($dto_financ_cuotas, 2),
            'iva_ri_dto_financ' => round($iva_ri_dto_financ, 2),
            'dto_ventas_fin_adq'=> round($dto_ventas_fin_adq, 2),
            'per_bai_brdn'      => round($per_bai_brdn, 2),
            'ret_iibb_sirtac'   => round($ret_iibb_sirtac, 2),
            'iva_promo_cuota'   => round($iva_promo_cuota, 2),
            'iva_dto_fin_adq'   => round($iva_dto_fin_adq, 2),
            'perc_iva_1_5'      => round($perc_iva_1_5, 2),
            'perc_iva_3'        => round($perc_iva_3, 2),
            'cargo_terminal'    => round($cargo_terminal, 2),
            'cargo_sist_cuotas' => round($cargo_sist_cuotas, 2),
            'iva_ri_sist_cuotas'=> round($iva_ri_sist_cuotas, 2),
            'qr_perc_iva'       => round($qr_perc_iva, 2),
            'qr_ret_iibb'       => round($qr_ret_iibb, 2),
            'total_descuentos'  => round($total_descuentos, 2),
            'acreditado'        => round($acreditado, 2),
        ];
    }

    return $liquidaciones;
}
