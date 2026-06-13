<?php
require_once __DIR__ . '/auth/check.php';
$user = currentUser();

$grupos_config = [
    'bancos'       => ['label' => 'Bancos & Finanzas',     'icon' => '🏦'],
    'contabilidad' => ['label' => 'Contabilidad & Fiscal', 'icon' => '📊'],
    'gestion'      => ['label' => 'Gestión',               'icon' => '◈'],
    'utilidades'   => ['label' => 'Utilidades',            'icon' => '⚙'],
];

$tools = [
    // ── BANCOS ──────────────────────────────────────────────────────────────────
    [
        'id'          => 'bancos',
        'grupo'       => 'bancos',
        'nombre'      => 'Banco Provincia',
        'descripcion' => 'Control de movimientos del Banco Provincia: importación de extractos, clasificación por categorías y reportes.',
        'icono'       => '🏦',
        'color'       => 'blue',
        'url'         => '/bancos/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Banco Provincia',
    ],
    [
        'id'          => 'bna',
        'grupo'       => 'bancos',
        'nombre'      => 'Banco Nacion',
        'descripcion' => 'Control de movimientos del Banco Nación: importación de extractos con formato Débito/Crédito, clasificación por categorías y reportes.',
        'icono'       => '🏛️',
        'color'       => 'amber',
        'url'         => '/bna/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Banco Nación Argentina',
    ],
    [
        'id'          => 'credicoop',
        'grupo'       => 'bancos',
        'nombre'      => 'Banco Credicoop',
        'descripcion' => 'Control de movimientos del Banco Credicoop: importación de extractos, clasificación por categorías y reportes.',
        'icono'       => '🤝',
        'color'       => 'green',
        'url'         => '/credicoop/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Banco Credicoop Coop. Ltdo.',
    ],
    [
        'id'          => 'hip',
        'grupo'       => 'bancos',
        'nombre'      => 'Banco Hipotecario',
        'descripcion' => 'Control de movimientos del Banco Hipotecario: importación de extractos, clasificación por categorías y reportes.',
        'icono'       => '🏠',
        'color'       => 'purple',
        'url'         => '/hip/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Banco Hipotecario',
    ],
    [
        'id'          => 'mp',
        'grupo'       => 'bancos',
        'nombre'      => 'Mercado Pago',
        'descripcion' => 'Control de movimientos de Mercado Pago: importación de extractos, clasificación por categorías y reportes.',
        'icono'       => '💳',
        'color'       => 'blue',
        'url'         => '/mp/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Billetera Digital',
    ],

    // ── CONTABILIDAD & FISCAL ────────────────────────────────────────────────────
    [
        'id'          => 'facturacion',
        'grupo'       => 'contabilidad',
        'nombre'      => 'Facturación',
        'descripcion' => 'Registro de facturas de compra y venta por cliente, con desglose de IVA, percepciones e informes mensuales.',
        'icono'       => '🧾',
        'color'       => 'green',
        'url'         => '/facturacion/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Compras · Ventas · Informes',
    ],
    [
        'id'          => 'fiserv',
        'grupo'       => 'contabilidad',
        'nombre'      => 'Fiserv',
        'descripcion' => 'Importá liquidaciones PDF de Fiserv (Visa, Mastercard, etc.), exportá Excel con todos los descuentos y visualizá reportes mensuales.',
        'icono'       => '💳',
        'color'       => 'amber',
        'url'         => '/fiserv/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Tarjetas de Crédito',
    ],
    [
        'id'          => 'iva',
        'grupo'       => 'contabilidad',
        'nombre'      => 'Libro IVA Compras',
        'descripcion' => 'Procesá el Libro de Compras de ARCA: detecta Notas de Crédito, convierte a pesos y genera Excel con fórmulas.',
        'icono'       => '📋',
        'color'       => 'purple',
        'url'         => '/iva/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'AFIP / ARCA',
    ],
    [
        'id'          => 'impuestos',
        'grupo'       => 'contabilidad',
        'nombre'      => 'Impuestos',
        'descripcion' => 'Archivo digital de declaraciones juradas: IIBB, F.931 y Portal IVA por cliente y período.',
        'icono'       => '📊',
        'color'       => 'red',
        'url'         => '/impuestos/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'AFIP / ARBA',
    ],
    [
        'id'          => 'ganancias',
        'grupo'       => 'contabilidad',
        'nombre'      => 'Ganancias y Balances',
        'descripcion' => 'Armá balances, ganancias y bienes personales por cliente: bancos, proveedores, clientes, rodados, maquinarias y bienes de uso.',
        'icono'       => '📑',
        'color'       => 'amber',
        'url'         => '/ganancias/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'ARCA · Bienes Personales',
    ],

    // ── GESTIÓN ─────────────────────────────────────────────────────────────────
    [
        'id'          => 'informe',
        'grupo'       => 'gestion',
        'featured'    => true,
        'nombre'      => 'Informe General',
        'descripcion' => 'Resumen consolidado de todos los módulos: Bancos, Fiserv y Cobranza. Gráficos por período y exportación a Excel.',
        'icono'       => '📈',
        'color'       => 'purple',
        'url'         => '/informe/index.php',
        'badge'       => 'Consolidado',
        'badge_type'  => 'blue',
        'subtitulo'   => 'Vista Consolidada',
    ],
    [
        'id'          => 'cobranza',
        'grupo'       => 'gestion',
        'nombre'      => 'Cobranza',
        'descripcion' => 'Cargá la cartera de deudores desde Excel, visualizá la deuda por antigüedad y sistema, priorizá clientes automáticamente y exportá con observaciones.',
        'icono'       => '📞',
        'color'       => 'green',
        'url'         => '/cobranza/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Gestión de Cobros',
    ],
    [
        'id'          => 'echeqs',
        'grupo'       => 'gestion',
        'alert'       => true,
        'nombre'      => 'Echeqs',
        'descripcion' => 'Subí un listado de echeqs y el sistema te devuelve un análisis: cuáles vencen pronto, cuáles hay que endosar, cuáles ya están endosados o depositados.',
        'icono'       => '🧾',
        'color'       => 'purple',
        'url'         => '/echeqs/dashboard.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Cheques Electrónicos',
    ],
    [
        'id'          => 'proveedores',
        'grupo'       => 'gestion',
        'nombre'      => 'Proveedores Morosos',
        'descripcion' => 'Cargá el saldo de cuenta corriente de proveedores, identificá a quién hay que pagarle primero y exportá con prioridades y observaciones.',
        'icono'       => '🏭',
        'color'       => 'red',
        'url'         => '/proveedores/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Cuentas Corrientes',
    ],
    [
        'id'          => 'vencimientos',
        'grupo'       => 'gestion',
        'alert'       => true,
        'nombre'      => 'Vencimientos',
        'descripcion' => 'Gestioná vencimientos fiscales (IVA, IIBB, Ganancias) y de servicios (luz, gas, teléfono). Guardá credenciales de acceso por cuenta y generá recordatorios.',
        'icono'       => '📅',
        'color'       => 'blue',
        'url'         => '/vencimientos/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Fiscal · Servicios',
    ],
    [
        'id'          => 'sociedades',
        'grupo'       => 'gestion',
        'nombre'      => 'Sociedades',
        'descripcion' => 'Seguimiento de balances y presentaciones por sociedad. Tildá cada tarea completada por ejercicio: reunión de información, balance, ganancias, actas y más.',
        'icono'       => '🏢',
        'color'       => 'purple',
        'url'         => '/sociedades/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Balances · SRL · SA',
    ],

    // ── UTILIDADES ───────────────────────────────────────────────────────────────
    [
        'id'          => 'separacion',
        'grupo'       => 'utilidades',
        'nombre'      => 'Separación',
        'descripcion' => 'Subí un Excel con columna Comprobante (ej: FAC A0007-00060132) y el sistema la separa automáticamente en Tipo, Punto de venta y Número de factura.',
        'icono'       => '✂',
        'color'       => 'amber',
        'url'         => '/separacion/index.php',
        'badge'       => 'Activo',
        'badge_type'  => 'green',
        'subtitulo'   => 'Tipo · Punto · Factura',
    ],
];

// Agrupar
$grouped = [];
foreach ($tools as $t) {
    $grouped[$t['grupo']][] = $t;
}
$totalActivos = count(array_filter($tools, fn($t) => $t['badge_type'] === 'green'));
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
        <a href="/logout.php" class="btn-logout" title="Cerrar sesión"><span>⏻</span></a>
    </div>
</nav>

<!-- ── HERO ── -->
<section class="hub-hero">
    <div class="hub-hero-inner">
        <div class="hero-greeting">
            <?php
            $hour = (int)date('H');
            $greet = $hour < 12 ? 'Buenos días' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');
            $nombre = explode(' ', $user['nombre'] ?? 'Administrador')[0];
            ?>
            <span class="hero-wave">👋</span>
            <?= $greet ?>, <strong><?= htmlspecialchars($nombre) ?></strong>
        </div>
        <h1 class="hero-title">¿Con qué herramienta<br>vas a trabajar hoy?</h1>
        <p class="hero-sub"><?= $totalActivos ?> herramienta<?= $totalActivos !== 1 ? 's' : '' ?> activa<?= $totalActivos !== 1 ? 's' : '' ?> · <?= count($tools) ?> en total</p>
    </div>
</section>

<!-- ── MAIN ── -->
<main class="hub-main">

    <div class="section-header">
        <h2 class="section-title">Herramientas disponibles</h2>
        <div style="display:flex;align-items:center;gap:12px">
            <a href="/pasos/index.php" class="btn-pasos">📖 Guías</a>
            <div class="section-filters">
                <button class="filter-pill active" data-filter="all">Todas</button>
                <button class="filter-pill" data-filter="active">Activas</button>
                <button class="filter-pill" data-filter="soon">Próximamente</button>
            </div>
        </div>
    </div>

    <div id="tools-grid-wrap">
        <?php foreach ($grupos_config as $gid => $gcfg): ?>
        <?php if (empty($grouped[$gid])) continue; ?>
        <div class="group-section" data-grupo="<?= $gid ?>">
            <div class="group-header">
                <span class="group-header-icon"><?= $gcfg['icon'] ?></span>
                <span class="group-header-label"><?= $gcfg['label'] ?></span>
                <span class="group-count"><?= count($grouped[$gid]) ?> herramienta<?= count($grouped[$gid]) !== 1 ? 's' : '' ?></span>
            </div>
            <div class="tools-grid">
                <?php foreach ($grouped[$gid] as $tool): ?>
                <div class="tool-card color-<?= $tool['color'] ?> <?= $tool['badge_type'] === 'muted' ? 'tool-soon' : 'tool-active' ?> <?= !empty($tool['featured']) ? 'featured' : '' ?>"
                     data-module="<?= $tool['id'] ?>"
                     data-name="<?= htmlspecialchars(strtolower($tool['nombre'] . ' ' . $tool['descripcion'] . ' ' . $tool['subtitulo'])) ?>"
                     data-status="<?= $tool['badge_type'] === 'green' || $tool['badge_type'] === 'blue' ? 'active' : 'soon' ?>"
                     <?php if ($tool['badge_type'] !== 'muted'): ?>onclick="window.location='<?= $tool['url'] ?>'"<?php endif; ?>>

                    <div class="tool-card-top">
                        <div class="tool-icon-wrap">
                            <span class="tool-icon"><?= $tool['icono'] ?></span>
                        </div>
                        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
                            <?php if (!empty($tool['alert'])): ?>
                            <span class="tool-alert-badge" id="hub-alert-<?= $tool['id'] ?>"></span>
                            <?php endif; ?>
                            <span class="tool-badge badge-<?= $tool['badge_type'] ?>"><?= $tool['badge'] ?></span>
                        </div>
                    </div>

                    <div class="tool-card-body">
                        <div class="tool-subtitle"><?= htmlspecialchars($tool['subtitulo']) ?></div>
                        <h3 class="tool-name"><?= htmlspecialchars($tool['nombre']) ?></h3>
                        <p class="tool-desc"><?= htmlspecialchars($tool['descripcion']) ?></p>
                    </div>

                    <div class="tool-card-footer">
                        <?php if ($tool['badge_type'] !== 'muted'): ?>
                            <a href="<?= $tool['url'] ?>" class="tool-btn tool-btn-active" onclick="event.stopPropagation()">
                                Abrir herramienta
                                <span class="tool-btn-arrow">→</span>
                            </a>
                        <?php else: ?>
                            <span class="tool-btn tool-btn-soon">En desarrollo</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($tool['badge_type'] !== 'muted'): ?>
                    <div class="tool-glow color-glow-<?= $tool['color'] ?>"></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
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
// ── Búsqueda y filtros ─────────────────────────────────
const searchInput = document.getElementById('tool-search');
const noResults   = document.getElementById('no-results');
let activeFilter  = 'all';

function filterTools() {
    const q     = searchInput.value.toLowerCase().trim();
    const cards = document.querySelectorAll('.tool-card');
    let visible = 0;

    cards.forEach(card => {
        const name    = card.dataset.name   || '';
        const status  = card.dataset.status || '';
        const matchQ  = !q || name.includes(q);
        const matchF  = activeFilter === 'all' || status === activeFilter;
        const show    = matchQ && matchF;
        card.classList.toggle('card-hidden', !show);
        if (show) visible++;
    });

    // Ocultar secciones de grupo vacías
    document.querySelectorAll('.group-section').forEach(section => {
        const hasVisible = section.querySelectorAll('.tool-card:not(.card-hidden)').length > 0;
        section.style.display = hasVisible ? '' : 'none';
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

// Atajo teclado: / para buscar
document.addEventListener('keydown', e => {
    if (e.key === '/' && document.activeElement !== searchInput) {
        e.preventDefault(); searchInput.focus();
    }
    if (e.key === 'Escape') {
        searchInput.value = ''; filterTools(); searchInput.blur();
    }
});

// ── Alertas ────────────────────────────────────────────
async function cargarAlertas() {
    // Echeqs: vencidos en el último lote
    try {
        const res  = await fetch('/echeqs/api/lotes.php?action=dashboard');
        const data = await res.json();
        if (!data.error && data.ultimo) {
            const n = parseInt(data.ultimo.cant_vencidos || 0);
            if (n > 0) {
                const badge = document.getElementById('hub-alert-echeqs');
                if (badge) {
                    badge.textContent = n + ' vencido' + (n !== 1 ? 's' : '');
                    badge.className = 'tool-alert-badge danger visible';
                }
            }
        }
    } catch(e) {}

    // Vencimientos: próximos 7 días + vencidos
    try {
        const res  = await fetch('/vencimientos/api/vencimientos.php?action=alertas_hub');
        const data = await res.json();
        if (!data.error) {
            const total = (data.vencidos || 0) + (data.proximos || 0);
            if (total > 0) {
                const badge = document.getElementById('hub-alert-vencimientos');
                if (badge) {
                    const tipo = data.vencidos > 0 ? 'danger' : 'warning';
                    badge.textContent = total + (data.vencidos > 0 ? ' vencido' + (data.vencidos !== 1 ? 's' : '') : ' próximo' + (data.proximos !== 1 ? 's' : ''));
                    badge.className = 'tool-alert-badge ' + tipo + ' visible';
                }
            }
        }
    } catch(e) {}
}

cargarAlertas();
</script>
</body>
</html>
