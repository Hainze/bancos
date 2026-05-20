<?php
// Auth guard for HTML pages — redirect to login if not authenticated
$rootDir = dirname(__DIR__);
require_once $rootDir . '/config.php';

if (!isLoggedIn()) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: /login.php?r=' . $redirect);
    exit;
}
