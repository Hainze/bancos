<?php
require_once dirname(__DIR__) . '/config.php';

$statements = [

"CREATE TABLE IF NOT EXISTS fact_clientes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(200) NOT NULL,
    cuit       VARCHAR(20)  NOT NULL DEFAULT '',
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS fact_compras (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id          INT            NOT NULL,
    tipo                VARCHAR(50)    NOT NULL,
    punto_venta         INT            NOT NULL DEFAULT 0,
    numero              INT            NOT NULL DEFAULT 0,
    fecha               DATE           NOT NULL,
    mes_contable        TINYINT        NOT NULL,
    anio_contable       SMALLINT       NOT NULL,
    proveedor_nombre    VARCHAR(200)   NOT NULL DEFAULT '',
    proveedor_cuit      VARCHAR(20)    NOT NULL DEFAULT '',
    no_gravado          DECIMAL(14,2)  NOT NULL DEFAULT 0,
    perc_iibb           DECIMAL(14,2)  NOT NULL DEFAULT 0,
    perc_iva            DECIMAL(14,2)  NOT NULL DEFAULT 0,
    imp_interno         DECIMAL(14,2)  NOT NULL DEFAULT 0,
    imp_interno_gasoil  DECIMAL(14,2)  NOT NULL DEFAULT 0,
    total               DECIMAL(14,2)  NOT NULL DEFAULT 0,
    created_at          TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cliente  (cliente_id),
    INDEX idx_periodo  (cliente_id, anio_contable, mes_contable)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS fact_compras_renglones (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    compra_id  INT           NOT NULL,
    alicuota   DECIMAL(5,2)  NOT NULL DEFAULT 21.00,
    neto       DECIMAL(14,2) NOT NULL DEFAULT 0,
    iva        DECIMAL(14,2) NOT NULL DEFAULT 0,
    INDEX idx_compra (compra_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS fact_ventas (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id           INT           NOT NULL,
    tipo                 VARCHAR(50)   NOT NULL,
    punto_venta          INT           NOT NULL DEFAULT 0,
    numero               INT           NOT NULL DEFAULT 0,
    fecha                DATE          NOT NULL,
    mes_contable         TINYINT       NOT NULL,
    anio_contable        SMALLINT      NOT NULL,
    destinatario_nombre  VARCHAR(200)  NOT NULL DEFAULT '',
    destinatario_cuit    VARCHAR(20)   NOT NULL DEFAULT '',
    no_gravado           DECIMAL(14,2) NOT NULL DEFAULT 0,
    retencion            DECIMAL(14,2) NOT NULL DEFAULT 0,
    total                DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at           TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cliente  (cliente_id),
    INDEX idx_periodo  (cliente_id, anio_contable, mes_contable)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS fact_ventas_renglones (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    venta_id  INT           NOT NULL,
    alicuota  DECIMAL(5,2)  NOT NULL DEFAULT 21.00,
    neto      DECIMAL(14,2) NOT NULL DEFAULT 0,
    iva       DECIMAL(14,2) NOT NULL DEFAULT 0,
    INDEX idx_venta (venta_id)
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
<title>Instalación Facturación — SmartAdmin</title>
<style>
  body{font-family:monospace;background:#080b10;color:#e8edf5;padding:40px}
  h2{color:#10b981;margin-bottom:24px}
  .ok{color:#10b981} .err{color:#ef4444}
  ul{list-style:none;padding:0}
  li{padding:6px 0;border-bottom:1px solid #1e2d45;font-size:13px}
  .ok-item::before{content:'✓ ';color:#10b981}
  .err-item::before{content:'✕ ';color:#ef4444}
  a{color:#3b7ff5;text-decoration:none}
  a:hover{text-decoration:underline}
  .warn{color:#f59e0b;font-size:13px;margin-top:16px}
</style>
</head>
<body>
<h2>⬡ SmartAdmin — Instalación Módulo Facturación</h2>

<?php if (!empty($err)): ?>
    <p class="err">⚠ Errores:</p>
    <ul><?php foreach ($err as $e): ?><li class="err-item"><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
<?php endif; ?>

<?php if (!empty($ok)): ?>
    <p class="ok">✓ Tablas creadas/verificadas:</p>
    <ul><?php foreach ($ok as $o): ?><li class="ok-item"><?= htmlspecialchars($o) ?></li><?php endforeach; ?></ul>
<?php endif; ?>

<p class="warn">⚠ Eliminá este archivo una vez instalado el módulo.</p>
<p style="margin-top:16px"><a href="/facturacion/index.php">→ Ir a Facturación</a></p>
</body>
</html>
