<?php
define('DB_HOST',    'localhost');
define('DB_NAME',    'u940050695_bancos_dos');
define('DB_USER',    'u940050695_bancosuser_');
define('DB_PASS',    'Bancos2026!SmartAdmin');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',   'SmartAdmin');
define('APP_URL',    'https://bancos.smartadmin.me');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function currentUser(): ?array {
    return $_SESSION['usuario'] ?? null;
}

function isLoggedIn(): bool {
    return isset($_SESSION['usuario_id']);
}
