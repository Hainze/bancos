<?php
$pagina_actual = basename($_SERVER['PHP_SELF'], '.php');
$user = currentUser();
$nombre_corto = explode(' ', $user['nombre'] ?? 'Usuario')[0];

$fact_cliente_id     = $_SESSION['fact_cliente_id']     ?? 0;
$fact_cliente_nombre = $_SESSION['fact_cliente_nombre'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($titulo) ? $titulo . ' — ' : '' ?>Vencimientos · SmartAdmin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js"></script>
    <style>
        .cliente-badge-sidebar {
            display:flex;align-items:center;gap:8px;padding:10px 14px;
            background:rgba(37,99,235,0.08);border:1px solid rgba(37,99,235,0.2);
            border-radius:8px;margin:0 12px 8px;font-size:12px;color:var(--accent-light);
        }
        .cliente-badge-sidebar .cb-dot { width:7px;height:7px;border-radius:50%;background:var(--accent-light);flex-shrink:0; }
        .cliente-badge-sidebar .cb-nombre { font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
        .cliente-badge-sidebar .cb-label  { color:var(--text-secondary);font-size:11px; }
        .cliente-vacio-sidebar {
            display:flex;align-items:center;gap:8px;padding:10px 14px;
            background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.25);
            border-radius:8px;margin:0 12px 8px;font-size:12px;color:var(--amber);
        }
    </style>
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
                <div class="brand-sub">Vencimientos</div>
            </div>
        </div>

        <?php if ($fact_cliente_id): ?>
        <div class="cliente-badge-sidebar">
            <div class="cb-dot"></div>
            <div>
                <div class="cb-label">Cliente activo</div>
                <div class="cb-nombre"><?= htmlspecialchars($fact_cliente_nombre) ?></div>
            </div>
        </div>
        <?php else: ?>
        <div class="cliente-vacio-sidebar">⚠ Sin cliente seleccionado</div>
        <?php endif; ?>

        <nav class="sidebar-nav">
            <div class="nav-section-label">Vencimientos</div>
            <a href="/vencimientos/index.php" class="nav-item <?= $pagina_actual === 'index' ? 'active' : '' ?>">
                <span class="nav-icon">📅</span>
                <span>Dashboard</span>
            </a>
            <a href="/vencimientos/cuentas.php" class="nav-item <?= $pagina_actual === 'cuentas' ? 'active' : '' ?>"
               <?= !$fact_cliente_id ? 'style="opacity:0.5;pointer-events:none"' : '' ?>>
                <span class="nav-icon">🔑</span>
                <span>Cuentas y credenciales</span>
            </a>

            <div class="nav-section-label">Clientes</div>
            <a href="/facturacion/index.php" class="nav-item">
                <span class="nav-icon">◈</span>
                <span>Cambiar Cliente</span>
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
                <span class="banco-dot" style="background:#2563eb"></span>
                <span>Fiscal · Servicios</span>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger" aria-label="Menú">
                    <span></span><span></span><span></span>
                </button>
                <h1 class="page-title"><?= isset($titulo) ? $titulo : 'Vencimientos' ?></h1>
            </div>
            <div class="topbar-right">
                <?php if ($fact_cliente_id): ?>
                <span style="font-size:12px;color:var(--accent-light);padding:4px 12px;background:rgba(37,99,235,0.1);border:1px solid rgba(37,99,235,0.2);border-radius:20px">
                    ● <?= htmlspecialchars($fact_cliente_nombre) ?>
                </span>
                <?php endif; ?>
                <a href="/index.php" class="btn-hub" title="Hub de herramientas">⊞ Hub</a>
            </div>
        </div>
        <div class="content-area">
