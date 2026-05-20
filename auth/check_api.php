<?php
// Auth guard for API endpoints — returns JSON error if not authenticated
$rootDir = dirname(__DIR__);
require_once $rootDir . '/config.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado. Iniciá sesión para continuar.']);
    exit;
}
