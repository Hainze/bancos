<?php
$pagina_actual = basename($_SERVER['PHP_SELF'], '.php');
$user = currentUser();
$nombre_corto = explode(' ', $user['nombre'] ?? 'Usuario')[0];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($titulo) ? $titulo . ' — ' : '' ?>Echeqs · SmartAdmin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js" defer></script>
</head>
<body>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <a href="/index.php" class="back-hub" title="Volver al hub">
                <span class="brand-icon">⬡</span>
            </a>
            <div>
                <div class="brand-name">SmartAdmin</div>
                <div class="brand-sub">Echeqs</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-label">Principal</div>
            <a href="/echeqs/dashboard.php" class="nav-item <?= $pagina_actual === 'dashboard' ? 'active' : '' ?>">
                <span class="nav-icon">◈</span>
                <span>Dashboard</span>
            </a>
            <a href="/echeqs/index.php" class="nav-item <?= $pagina_actual === 'index' ? 'active' : '' ?>">
                <span class="nav-icon">🧾</span>
                <span>Analizar Echeqs</span>
            </a>

            <div class="nav-section-label">Sistema</div>
            <a href="/index.php" class="nav-item">
                <span class="nav-icon">⊞</span>
                <span>Otras herramientas</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-avatar"><?= strtoupper(substr($nombre_corto, 0, 1)) ?></div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= htmlspecialchars($nombre_corto) ?></div>
                    <a href="/logout.php" class="sidebar-logout">Cerrar sesión</a>
                </div>
            </div>
            <div class="banco-badge" style="margin-top:10px">
                <span class="banco-dot" style="background:#8b5cf6"></span>
                <span>Cheques Electrónicos</span>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger" aria-label="Menú">
                    <span></span><span></span><span></span>
                </button>
                <h1 class="page-title"><?= isset($titulo) ? $titulo : 'Echeqs' ?></h1>
            </div>
            <div class="topbar-right">
                <a href="/index.php" class="btn-hub" title="Hub de herramientas">⊞ Hub</a>
            </div>
        </div>
        <div class="content-area">
