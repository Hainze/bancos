<?php
require_once __DIR__ . '/config.php';
$pdo = getDB();
$pdo->prepare("UPDATE usuarios SET nombre = 'Hainze' WHERE rol = 'admin'")->execute();
@unlink(__FILE__); // se borra solo después de ejecutarse
header('Location: /index.php');
exit;
