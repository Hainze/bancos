<?php
require_once 'config.php';

$sql = "
-- Categorias con codigos
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    codigo VARCHAR(20) NOT NULL,
    tipo ENUM('ingreso','gasto','ambos') DEFAULT 'ambos',
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Palabras clave para clasificacion automatica
CREATE TABLE IF NOT EXISTS palabras_clave (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    palabra VARCHAR(100) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clientes: CUIT, cuenta, nombre
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cuit VARCHAR(20) DEFAULT NULL,
    dni VARCHAR(20) DEFAULT NULL,
    numero_cuenta VARCHAR(50) DEFAULT NULL,
    nombre VARCHAR(150) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bancos disponibles
CREATE TABLE IF NOT EXISTS bancos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    activo TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Movimientos importados
CREATE TABLE IF NOT EXISTS movimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    banco_id INT DEFAULT 1,
    fecha DATE NOT NULL,
    descripcion TEXT NOT NULL,
    importe DECIMAL(15,2) NOT NULL,
    tipo ENUM('ingreso','gasto') NOT NULL,
    categoria_id INT DEFAULT NULL,
    categoria_nombre VARCHAR(100) DEFAULT NULL,
    codigo VARCHAR(20) DEFAULT NULL,
    cuit VARCHAR(20) DEFAULT NULL,
    dni VARCHAR(20) DEFAULT NULL,
    numero_cuenta VARCHAR(50) DEFAULT NULL,
    nombre_cliente VARCHAR(150) DEFAULT NULL,
    lote_importacion VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lotes de importacion
CREATE TABLE IF NOT EXISTS lotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    banco_id INT DEFAULT 1,
    archivo_nombre VARCHAR(200),
    total_filas INT DEFAULT 0,
    filas_clasificadas INT DEFAULT 0,
    filas_sin_clasificar INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datos iniciales
INSERT IGNORE INTO bancos (id, nombre) VALUES (1, 'Banco Provincia');
";

try {
    $pdo = getDB();
    // Execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt);
        }
    }
    echo '<div style="font-family:monospace;padding:20px;background:#0a0a0a;color:#00ff88;min-height:100vh">';
    echo '<h2>✅ Instalación completada</h2>';
    echo '<p>Tablas creadas correctamente:</p>';
    echo '<ul>';
    echo '<li>categorias</li>';
    echo '<li>palabras_clave</li>';
    echo '<li>clientes</li>';
    echo '<li>bancos</li>';
    echo '<li>movimientos</li>';
    echo '<li>lotes</li>';
    echo '</ul>';
    echo '<p><a href="index.php" style="color:#00ff88">→ Ir al sistema</a></p>';
    echo '</div>';
} catch (Exception $e) {
    echo '<div style="font-family:monospace;padding:20px;background:#1a0000;color:#ff4444;min-height:100vh">';
    echo '<h2>❌ Error en instalación</h2>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
}
?>
