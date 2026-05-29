<?php
require_once dirname(__DIR__) . '/config.php';
$pdo = getDB();

$queries = [

"CREATE TABLE IF NOT EXISTS cobranza_lotes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    archivo_nombre VARCHAR(255) NOT NULL,
    total_clientes INT DEFAULT 0,
    total_importe  DECIMAL(15,2) DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS cobranza_cartera (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    lote_id  INT NOT NULL,
    sistema  TINYINT(1) NOT NULL COMMENT '1=blanco 2=negro',
    codigo   VARCHAR(50) NOT NULL,
    nombre   VARCHAR(255) NOT NULL,
    d30      DECIMAL(15,2) DEFAULT 0 COMMENT '1-30 dias',
    d60      DECIMAL(15,2) DEFAULT 0 COMMENT '31-60 dias',
    d90      DECIMAL(15,2) DEFAULT 0 COMMENT '61-90 dias',
    d120     DECIMAL(15,2) DEFAULT 0 COMMENT '91-120 dias',
    d120plus DECIMAL(15,2) DEFAULT 0 COMMENT 'Mas de 120',
    total    DECIMAL(15,2) DEFAULT 0,
    FOREIGN KEY (lote_id) REFERENCES cobranza_lotes(id) ON DELETE CASCADE,
    INDEX idx_lote (lote_id),
    INDEX idx_sistema_codigo (sistema, codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS cobranza_padrones (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sistema     TINYINT(1) NOT NULL,
    codigo      VARCHAR(50) NOT NULL,
    observacion VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sistema_codigo (sistema, codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

];

$ok = 0;
$errors = [];
foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        $ok++;
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Instalar Cobranza</title>
    <style>
        body { font-family: monospace; background: #0e1320; color: #c8d8f0; padding: 40px; }
        .ok  { color: #10b981; }
        .err { color: #ef4444; }
        a    { color: #2563eb; }
    </style>
</head>
<body>
<h2>Instalación — Módulo Cobranza</h2>
<?php if (!$errors): ?>
    <p class="ok">✓ <?= $ok ?> tablas creadas correctamente.</p>
    <p>Podés eliminar este archivo y <a href="/cobranza/index.php">abrir el módulo</a>.</p>
<?php else: ?>
    <p class="err">⚠ <?= $ok ?>/<?= $ok + count($errors) ?> tablas OK. Errores:</p>
    <ul><?php foreach ($errors as $e): ?><li class="err"><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
</body>
</html>
