<?php
$pagina_actual = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($titulo) ? $titulo . ' — ' : '' ?>SmartAdmin Bancos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/main.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
</head>
<body>

<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-icon">⬡</span>
            <div>
                <div class="brand-name">SmartAdmin</div>
                <div class="brand-sub">Gestión Bancaria</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-label">Principal</div>
            <a href="/index.php" class="nav-item <?= $pagina_actual === 'index' ? 'active' : '' ?>">
                <span class="nav-icon">◈</span>
                <span>Dashboard</span>
            </a>
            <a href="/procesar.php" class="nav-item <?= $pagina_actual === 'procesar' ? 'active' : '' ?>">
                <span class="nav-icon">⬆</span>
                <span>Importar Excel</span>
            </a>
            <a href="/movimientos.php" class="nav-item <?= $pagina_actual === 'movimientos' ? 'active' : '' ?>">
                <span class="nav-icon">≡</span>
                <span>Movimientos</span>
            </a>
            <a href="/reportes.php" class="nav-item <?= $pagina_actual === 'reportes' ? 'active' : '' ?>">
                <span class="nav-icon">◎</span>
                <span>Reportes</span>
            </a>

            <div class="nav-section-label" style="margin-top:1.5rem">Padrones</div>
            <a href="/padrones.php?tab=categorias" class="nav-item <?= ($pagina_actual === 'padrones' && ($_GET['tab'] ?? '') === 'categorias') ? 'active' : '' ?>">
                <span class="nav-icon">▦</span>
                <span>Categorías</span>
            </a>
            <a href="/padrones.php?tab=palabras" class="nav-item <?= ($pagina_actual === 'padrones' && ($_GET['tab'] ?? '') === 'palabras') ? 'active' : '' ?>">
                <span class="nav-icon">◉</span>
                <span>Palabras Clave</span>
            </a>
            <a href="/padrones.php?tab=clientes" class="nav-item <?= ($pagina_actual === 'padrones' && ($_GET['tab'] ?? '') === 'clientes') ? 'active' : '' ?>">
                <span class="nav-icon">◷</span>
                <span>Clientes</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="banco-badge">
                <span class="banco-dot"></span>
                Banco Provincia
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <h1 class="page-title"><?= isset($titulo) ? $titulo : 'Dashboard' ?></h1>
            <div class="topbar-right">
                <span class="date-display"><?= date('d/m/Y') ?></span>
            </div>
        </div>
        <div class="content-area">
