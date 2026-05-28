<?php
require_once __DIR__ . '/config.php';

$statements = [

"CREATE TABLE IF NOT EXISTS fiserv_lotes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS fiserv_liquidaciones (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    lote_id            INT NOT NULL,
    nro_liq            VARCHAR(20)  NOT NULL DEFAULT '',
    fecha_pago         DATE         NULL,
    fecha_pres         DATE         NULL,
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
    FOREIGN KEY (lote_id) REFERENCES fiserv_lotes(id) ON DELETE CASCADE,
    INDEX idx_lote (lote_id),
    INDEX idx_fecha_pago (fecha_pago)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

];

$ok = []; $err = [];
try {
    $pdo = getDB();
    foreach ($statements as $sql) {
        try {
            $pdo->exec($sql);
            $ok[] = substr(trim($sql), 0, 70) . '…';
        } catch (Exception $e) {
            $err[] = $e->getMessage();
        }
    }
} catch (Exception $e) {
    $err[] = 'Conexión fallida: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Instalación Fiserv — SmartAdmin</title>
<style>
  body{font-family:monospace;background:#080b10;color:#e8edf5;padding:40px}
  h2{color:#f59e0b;margin-bottom:24px}
  .ok{color:#10b981} .err{color:#ef4444}
  ul{list-style:none;padding:0}
  li{padding:6px 0;border-bottom:1px solid #1e2d45;font-size:13px}
  .ok-item::before{content:'✓ ';color:#10b981}
  .err-item::before{content:'✕ ';color:#ef4444}
  a{color:#3b7ff5;text-decoration:none}
  a:hover{text-decoration:underline}
  .warn{color:#f59e0b;font-size:13px;margin-top:16px}
  .note{background:#121929;border:1px solid #253450;border-radius:8px;padding:16px;margin:24px 0;font-size:13px;color:#7a90b0}
  .note strong{color:#e8edf5}
</style>
</head>
<body>
<h2>⬡ SmartAdmin — Instalación Módulo Fiserv</h2>

<?php if (!empty($err)): ?>
    <p class="err">⚠ Errores:</p>
    <ul><?php foreach ($err as $e): ?><li class="err-item"><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
<?php endif; ?>

<?php if (!empty($ok)): ?>
    <p class="ok">✓ Tablas creadas/verificadas:</p>
    <ul><?php foreach ($ok as $o): ?><li class="ok-item"><?= htmlspecialchars($o) ?></li><?php endforeach; ?></ul>
<?php endif; ?>

<div class="note">
    <p><strong>Paso siguiente:</strong> instalá la librería para parsear PDFs via SSH:</p>
    <pre style="margin:8px 0;color:#10b981">cd /ruta/al/proyecto && composer require smalot/pdfparser</pre>
    <p style="margin-top:8px">Si ya la instalaste, podés ignorar este mensaje.</p>
</div>

<p class="warn">⚠ Eliminá este archivo una vez instalado el módulo.</p>
<p style="margin-top:16px"><a href="/fiserv/index.php">→ Ir a Fiserv</a></p>
</body>
</html>
