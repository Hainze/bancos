<?php
require_once __DIR__ . '/auth/check.php';
$user = currentUser();

$tools = [
    [
        'id'          => 'bancos',
        'nombre'      => 'Banco Provincia',
        'descripcion' => 'Control de movimientos del Banco Provincia: importación de extractos, clasificación por categorías y reportes.',
        'icono'       => '🏦',
        'color'       => 'blue',
        'url'         => '/bancos/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Banco Provincia',
        'stats'       => true,
    ],
    [
        'id'          => 'hip',
        'nombre'      => 'Banco Hipotecario',
        'descripcion' => 'Control de movimientos del Banco Hipotecario: importación de extractos, clasificación por categorías y reportes.',
        'icono'       => '🏠',
        'color'       => 'purple',
        'url'         => '/hip/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Banco Hipotecario',
        'stats'       => false,
    ],
    [
        'id'          => 'bna',
        'nombre'      => 'Banco Nacion',
        'descripcion' => 'Control de movimientos del Banco Nación: importación de extractos con formato Débito/Crédito, clasificación por categorías y reportes.',
        'icono'       => '🏛️',
        'color'       => 'amber',
        'url'         => '/bna/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Banco Nación Argentina',
        'stats'       => false,
    ],
    [
        'id'          => 'iva',
        'nombre'      => 'Libro IVA Compras',
        'descripcion' => 'Procesá el Libro de Compras de ARCA: detecta Notas de Crédito, convierte a pesos y genera Excel con fórmulas.',
        'icono'       => '📋',
        'color'       => 'purple',
        'url'         => '/iva/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'AFIP / ARCA',
        'stats'       => false,
    ],
    [
        'id'          => 'proveedores',
        'nombre'      => 'Proveedores Morosos',
        'descripcion' => 'Cargá el saldo de cuenta corriente de proveedores, identificá a quién hay que pagarle primero y exportá con prioridades y observaciones.',
        'icono'       => '🏭',
        'color'       => 'red',
        'url'         => '/proveedores/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Cuentas Corrientes',
        'stats'       => false,
    ],
    [
        'id'          => 'vencimientos',
        'nombre'      => 'Vencimientos',
        'descripcion' => 'Gestioná vencimientos fiscales (IVA, IIBB, Ganancias) y de servicios (luz, gas, teléfono). Guardá credenciales de acceso por cuenta y generá recordatorios.',
        'icono'       => '📅',
        'color'       => 'blue',
        'url'         => '/vencimientos/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Fiscal · Servicios',
        'stats'       => false,
    ],
    [
        'id'          => 'ganancias',
        'nombre'      => 'Ganancias y Balances',
        'descripcion' => 'Armá balances, ganancias y bienes personales por cliente: bancos, proveedores, clientes, rodados, maquinarias y bienes de uso.',
        'icono'       => '📑',
        'color'       => 'amber',
        'url'         => '/ganancias/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'ARCA · Bienes Personales',
        'stats'       => false,
    ],
    [
        'id'          => 'impuestos',
        'nombre'      => 'Impuestos',
        'descripcion' => 'Archivo digital de declaraciones juradas: IIBB, F.931 y Portal IVA por cliente y período.',
        'icono'       => '📊',
        'color'       => 'red',
        'url'         => '/impuestos/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'AFIP / ARBA',
        'stats'       => false,
    ],
    [
        'id'          => 'facturacion',
        'nombre'      => 'Facturación',
        'descripcion' => 'Registro de facturas de compra y venta por cliente, con desglose de IVA, percepciones e informes mensuales.',
        'icono'       => '🧾',
        'color'       => 'green',
        'url'         => '/facturacion/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Compras · Ventas · Informes',
        'stats'       => false,
    ],
    [
        'id'          => 'fiserv',
        'nombre'      => 'Fiserv',
        'descripcion' => 'Importá liquidaciones PDF de Fiserv (Visa, Mastercard, etc.), exportá Excel con todos los descuentos y visualizá reportes mensuales.',
        'icono'       => '💳',
        'color'       => 'amber',
        'url'         => '/fiserv/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Tarjetas de Crédito',
        'stats'       => false,
    ],
    [
        'id'          => 'cobranza',
        'nombre'      => 'Cobranza',
        'descripcion' => 'Cargá la cartera de deudores desde Excel, visualizá la deuda por antigüedad y sistema, priorizá clientes automáticamente y exportá con observaciones.',
        'icono'       => '📞',
        'color'       => 'green',
        'url'         => '/cobranza/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Gestión de Cobros',
        'stats'       => false,
    ],
    [
        'id'          => 'informe',
        'nombre'      => 'Informe General',
        'descripcion' => 'Resumen consolidado de todos los módulos: Bancos, Fiserv y Cobranza. Gráficos por período y exportación a Excel.',
        'icono'       => '📈',
        'color'       => 'purple',
        'url'         => '/informe/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Vista Consolidada',
        'stats'       => false,
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartAdmin — Herramientas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/hub.css">
</head>
<body>

<!-- ── NAVBAR ── -->
<nav class="hub-nav">
    <div class="nav-brand">
        <div class="nav-logo">⬡</div>
        <div>
            <div class="nav-brand-name">SmartAdmin</div>
            <div class="nav-brand-sub">Gestión Empresarial</div>
        </div>
    </div>

    <div class="nav-center">
        <div class="nav-search-wrap">
            <span class="nav-search-icon">⌕</span>
            <input type="text" class="nav-search" id="tool-search" placeholder="Buscar herramienta...">
        </div>
    </div>

    <div class="nav-right">
        <div class="nav-date"><?= date('d/m/Y') ?></div>
        <div class="nav-user">
            <div class="nav-avatar"><?= strtoupper(substr($user['nombre'] ?? 'A', 0, 1)) ?></div>
            <div class="nav-user-info">
                <div class="nav-user-name"><?= htmlspecialchars($user['nombre'] ?? '') ?></div>
                <div class="nav-user-role"><?= ucfirst($user['rol'] ?? 'user') ?></div>
            </div>
        </div>
        <a href="/logout.php" class="btn-logout" title="Cerrar sesión">
            <span>⏻</span>
        </a>
    </div>
</nav>

<!-- ── HERO ── -->
<section class="hub-hero">
    <div class="hub-hero-inner">
        <div class="hero-greeting">
            <?php
            $hour = (int)date('H');
            if      ($hour < 12) $greet = 'Buenos días';
            elseif  ($hour < 19) $greet = 'Buenas tardes';
            else                 $greet = 'Buenas noches';
            $nombre = explode(' ', $user['nombre'] ?? 'Administrador')[0];
            ?>
            <span class="hero-wave">👋</span>
            <?= $greet ?>, <strong><?= htmlspecialchars($nombre) ?></strong>
        </div>
        <h1 class="hero-title">¿Con qué herramienta<br>vas a trabajar hoy?</h1>
        <p class="hero-sub"><?= count(array_filter($tools, fn($t) => $t['badge_type'] === 'green')) ?> herramienta<?= count(array_filter($tools, fn($t) => $t['badge_type'] === 'green')) !== 1 ? 's' : '' ?> activa<?= count(array_filter($tools, fn($t) => $t['badge_type'] === 'green')) !== 1 ? 's' : '' ?> · <?= count($tools) ?> en total</p>
    </div>
</section>

<!-- ── TOOLS GRID ── -->
<main class="hub-main">

    <div class="section-header">
        <h2 class="section-title">Herramientas disponibles</h2>
        <div class="section-filters">
            <button class="filter-pill active" data-filter="all">Todas</button>
            <button class="filter-pill" data-filter="active">Activas</button>
            <button class="filter-pill" data-filter="soon">Próximamente</button>
        </div>
    </div>

    <div class="tools-grid" id="tools-grid">
        <?php foreach ($tools as $tool): ?>
        <div class="tool-card color-<?= $tool['color'] ?> <?= $tool['badge_type'] === 'muted' ? 'tool-soon' : 'tool-active' ?>"
             data-name="<?= htmlspecialchars(strtolower($tool['nombre'] . ' ' . $tool['descripcion'])) ?>"
             data-status="<?= $tool['badge_type'] === 'green' ? 'active' : 'soon' ?>">

            <div class="tool-card-top">
                <div class="tool-icon-wrap">
                    <span class="tool-icon"><?= $tool['icono'] ?></span>
                </div>
                <span class="tool-badge badge-<?= $tool['badge_type'] ?>"><?= $tool['badge'] ?></span>
            </div>

            <div class="tool-card-body">
                <div class="tool-subtitle"><?= htmlspecialchars($tool['subtitulo']) ?></div>
                <h3 class="tool-name"><?= htmlspecialchars($tool['nombre']) ?></h3>
                <p class="tool-desc"><?= htmlspecialchars($tool['descripcion']) ?></p>
            </div>

            <div class="tool-card-footer">
                <?php if ($tool['badge_type'] === 'green'): ?>
                    <a href="<?= $tool['url'] ?>" class="tool-btn tool-btn-active">
                        Abrir herramienta
                        <span class="tool-btn-arrow">→</span>
                    </a>
                <?php else: ?>
                    <span class="tool-btn tool-btn-soon">En desarrollo</span>
                <?php endif; ?>
            </div>

            <?php if ($tool['badge_type'] === 'green'): ?>
            <div class="tool-glow color-glow-<?= $tool['color'] ?>"></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="no-results" id="no-results" style="display:none">
        <div class="no-results-icon">⌕</div>
        <div class="no-results-text">No se encontraron herramientas</div>
    </div>

</main>

<!-- ── FOOTER ── -->
<footer class="hub-footer">
    <span>© <?= date('Y') ?> SmartAdmin — Sistema de Gestión Empresarial</span>
    <span class="footer-dot">·</span>
    <span>v2.0</span>
</footer>

<script>
// Search
const searchInput = document.getElementById('tool-search');
const grid = document.getElementById('tools-grid');
const noResults = document.getElementById('no-results');
let activeFilter = 'all';

function filterTools() {
    const q = searchInput.value.toLowerCase().trim();
    const cards = grid.querySelectorAll('.tool-card');
    let visible = 0;

    cards.forEach(card => {
        const name   = card.dataset.name || '';
        const status = card.dataset.status || '';
        const matchQ = !q || name.includes(q);
        const matchF = activeFilter === 'all' || status === activeFilter;
        const show   = matchQ && matchF;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    noResults.style.display = visible === 0 ? 'flex' : 'none';
}

searchInput.addEventListener('input', filterTools);

document.querySelectorAll('.filter-pill').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        activeFilter = btn.dataset.filter;
        filterTools();
    });
});

// Keyboard shortcut: / to focus search
document.addEventListener('keydown', e => {
    if (e.key === '/' && document.activeElement !== searchInput) {
        e.preventDefault();
        searchInput.focus();
    }
    if (e.key === 'Escape') {
        searchInput.value = '';
        filterTools();
        searchInput.blur();
    }
});
</script>
</body>
</html>
