<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/auth/check_api.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']); exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || empty($data['liquidaciones'])) {
    echo json_encode(['error' => 'Datos inválidos o sin liquidaciones']); exit;
}

$header         = $data['header']         ?? [];
$liquidaciones  = $data['liquidaciones'];
$archivo_nombre = $data['archivo_nombre'] ?? 'desconocido.pdf';

try {
    $pdo = getDB();

    $pdo->exec("CREATE TABLE IF NOT EXISTS fiserv_lotes (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        codigo           VARCHAR(50)  NOT NULL UNIQUE,
        archivo_nombre   VARCHAR(200) DEFAULT NULL,
        tarjeta          VARCHAR(80)  DEFAULT NULL,
        periodo          VARCHAR(30)  DEFAULT NULL,
        nro_comercio     VARCHAR(50)  DEFAULT NULL,
        total_presentado DECIMAL(15,2) NOT NULL DEFAULT 0,
        neto_pagos       DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_filas      INT NOT NULL DEFAULT 0,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS fiserv_liquidaciones (
        id                 INT AUTO_INCREMENT PRIMARY KEY,
        lote_id            INT NOT NULL,
        nro_liq            VARCHAR(20)   NOT NULL DEFAULT '',
        fecha_pago         DATE          NULL,
        fecha_pres         DATE          NULL,
        ventas_contado     DECIMAL(15,2) NOT NULL DEFAULT 0,
        arancel            DECIMAL(15,2) NOT NULL DEFAULT 0,
        iva_arancel        DECIMAL(15,2) NOT NULL DEFAULT 0,
        arancel_cuotas     DECIMAL(15,2) NOT NULL DEFAULT 0,
        iva_arancel_cuotas DECIMAL(15,2) NOT NULL DEFAULT 0,
        promo_cuota_ahora  DECIMAL(15,2) NOT NULL DEFAULT 0,
        dto_financ_cuotas  DECIMAL(15,2) NOT NULL DEFAULT 0,
        iva_ri_dto_financ  DECIMAL(15,2) NOT NULL DEFAULT 0,
        dto_ventas_fin_adq DECIMAL(15,2) NOT NULL DEFAULT 0,
        per_bai_brdn       DECIMAL(15,2) NOT NULL DEFAULT 0,
        ret_iibb_sirtac    DECIMAL(15,2) NOT NULL DEFAULT 0,
        iva_promo_cuota    DECIMAL(15,2) NOT NULL DEFAULT 0,
        iva_dto_fin_adq    DECIMAL(15,2) NOT NULL DEFAULT 0,
        perc_iva_1_5       DECIMAL(15,2) NOT NULL DEFAULT 0,
        perc_iva_3         DECIMAL(15,2) NOT NULL DEFAULT 0,
        cargo_terminal     DECIMAL(15,2) NOT NULL DEFAULT 0,
        cargo_sist_cuotas  DECIMAL(15,2) NOT NULL DEFAULT 0,
        iva_ri_sist_cuotas DECIMAL(15,2) NOT NULL DEFAULT 0,
        qr_perc_iva        DECIMAL(15,2) NOT NULL DEFAULT 0,
        qr_ret_iibb        DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_descuentos   DECIMAL(15,2) NOT NULL DEFAULT 0,
        acreditado         DECIMAL(15,2) NOT NULL DEFAULT 0,
        created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_lote (lote_id),
        INDEX idx_fecha_pago (fecha_pago)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $codigo = 'FSV-' . strtoupper(substr(md5($archivo_nombre . microtime()), 0, 8));

    $pdo->prepare("
        INSERT INTO fiserv_lotes (codigo, archivo_nombre, tarjeta, periodo, nro_comercio, total_presentado, neto_pagos, total_filas)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $codigo,
        $archivo_nombre,
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
            $liq['nro_liq']           ?? '',
            $liq['fecha_pago']        ?? null,
            $liq['fecha_pres']        ?? null,
            $liq['ventas_contado']    ?? 0,
            $liq['arancel']           ?? 0,
            $liq['iva_arancel']       ?? 0,
            $liq['arancel_cuotas']    ?? 0,
            $liq['iva_arancel_cuotas']?? 0,
            $liq['promo_cuota_ahora'] ?? 0,
            $liq['dto_financ_cuotas'] ?? 0,
            $liq['iva_ri_dto_financ'] ?? 0,
            $liq['dto_ventas_fin_adq']?? 0,
            $liq['per_bai_brdn']      ?? 0,
            $liq['ret_iibb_sirtac']   ?? 0,
            $liq['iva_promo_cuota']   ?? 0,
            $liq['iva_dto_fin_adq']   ?? 0,
            $liq['perc_iva_1_5']      ?? 0,
            $liq['perc_iva_3']        ?? 0,
            $liq['cargo_terminal']    ?? 0,
            $liq['cargo_sist_cuotas'] ?? 0,
            $liq['iva_ri_sist_cuotas']?? 0,
            $liq['qr_perc_iva']       ?? 0,
            $liq['qr_ret_iibb']       ?? 0,
            $liq['total_descuentos']  ?? 0,
            $liq['acreditado']        ?? 0,
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
