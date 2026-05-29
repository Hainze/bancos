<?php
require_once dirname(__DIR__) . '/config.php';

$defaultPassword = 'Admin2026!';
$defaultHash     = password_hash($defaultPassword, PASSWORD_BCRYPT);

$statements = [

"CREATE TABLE IF NOT EXISTS usuarios (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(100) NOT NULL,
    email        VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol          ENUM('admin','user') DEFAULT 'user',
    activo       TINYINT(1) DEFAULT 1,
    last_login   TIMESTAMP NULL DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS categorias (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(100) NOT NULL,
    codigo     VARCHAR(20) NOT NULL,
    tipo       ENUM('ingreso','gasto','ambos') DEFAULT 'ambos',
    activo     TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS palabras_clave (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    palabra      VARCHAR(100) NOT NULL,
    activo       TINYINT(1) DEFAULT 1,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS clientes (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    cuit           VARCHAR(20)  DEFAULT NULL,
    dni            VARCHAR(20)  DEFAULT NULL,
    numero_cuenta  VARCHAR(50)  DEFAULT NULL,
    nombre         VARCHAR(150) NOT NULL,
    activo         TINYINT(1) DEFAULT 1,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS bancos (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    activo TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS movimientos (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    banco_id         INT DEFAULT 1,
    fecha            DATE NOT NULL,
    descripcion      TEXT NOT NULL,
    importe          DECIMAL(15,2) NOT NULL,
    tipo             ENUM('ingreso','gasto') NOT NULL,
    categoria_id     INT DEFAULT NULL,
    categoria_nombre VARCHAR(100) DEFAULT NULL,
    codigo           VARCHAR(20)  DEFAULT NULL,
    cuit             VARCHAR(20)  DEFAULT NULL,
    dni              VARCHAR(20)  DEFAULT NULL,
    numero_cuenta    VARCHAR(50)  DEFAULT NULL,
    nombre_cliente   VARCHAR(150) DEFAULT NULL,
    lote_importacion VARCHAR(50)  DEFAULT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS lotes (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    codigo               VARCHAR(50) NOT NULL UNIQUE,
    banco_id             INT DEFAULT 1,
    archivo_nombre       VARCHAR(200),
    total_filas          INT DEFAULT 0,
    filas_clasificadas   INT DEFAULT 0,
    filas_sin_clasificar INT DEFAULT 0,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"INSERT IGNORE INTO bancos (id, nombre) VALUES (1, 'Banco Provincia')",

"INSERT IGNORE INTO usuarios (nombre, email, password_hash, rol)
 VALUES ('Administrador', 'admin@smartadmin.me', '$defaultHash', 'admin')",
];

$ok = [];
$err = [];

try {
    $pdo = getDB();
    foreach ($statements as $sql) {
        try {
            $pdo->exec($sql);
            $preview = substr(trim($sql), 0, 60);
            $ok[] = $preview . '…';
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
<title>Instalación — SmartAdmin</title>
<style>
  body{font-family:monospace;background:#080b10;color:#e8edf5;padding:40px;min-height:100vh}
  h2{color:#3b7ff5;margin-bottom:24px}
  .ok{color:#10b981} .err{color:#ef4444}
  ul{list-style:none;padding:0}
  li{padding:6px 0;border-bottom:1px solid #1e2d45;font-size:13px}
  li::before{margin-right:10px}
  .ok-item::before{content:'✓';color:#10b981}
  .err-item::before{content:'✕';color:#ef4444}
  .creds{background:#121929;border:1px solid #253450;border-radius:8px;padding:20px;margin:24px 0}
  .creds p{margin:6px 0;font-size:14px}
  a{color:#3b7ff5;text-decoration:none}
  a:hover{text-decoration:underline}
  .warn{color:#f59e0b;font-size:13px;margin-top:8px}
</style>
</head>
<body>
<h2>⬡ SmartAdmin — Instalación</h2>

<?php if (!empty($err)): ?>
    <p class="err">⚠ Se encontraron errores:</p>
    <ul><?php foreach ($err as $e): ?><li class="err-item"><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
<?php endif; ?>

<?php if (!empty($ok)): ?>
    <p class="ok">✓ Operaciones ejecutadas:</p>
    <ul><?php foreach ($ok as $o): ?><li class="ok-item"><?= htmlspecialchars($o) ?></li><?php endforeach; ?></ul>
<?php endif; ?>

<div class="creds">
    <p><strong>Credenciales por defecto:</strong></p>
    <p>Email: <strong>admin@smartadmin.me</strong></p>
    <p>Contraseña: <strong><?= htmlspecialchars($defaultPassword) ?></strong></p>
    <p class="warn">⚠ Eliminá este archivo una vez instalado el sistema.</p>
</div>

<p><a href="/login.php">→ Ir al login</a></p>
</body>
</html>
