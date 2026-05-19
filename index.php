<?php
require_once 'config.php';
$titulo = 'Dashboard';
require_once 'includes/header.php';
?>

<!-- ══════════ FILTROS ══════════ -->
<div class="filter-bar">
    <div class="filter-bar-header">
        <span class="filter-bar-title">Filtros del período</span>
        <div class="quick-ranges">
            <button class="qrange-btn" data-range="hoy">Hoy</button>
            <button class="qrange-btn" data-range="semana">Esta semana</button>
            <button class="qrange-btn active" data-range="mes">Este mes</button>
            <button class="qrange-btn" data-range="mes_ant">Mes anterior</button>
            <button class="qrange-btn" data-range="anio">Este año</button>
            <button class="qrange-btn" data-range="todo">Todo</button>
        </div>
    </div>
    <div class="filter-bar-body">
        <div class="filter-field">
            <label>Desde</label>
            <input type="date" class="form-control" id="dash-desde">
        </div>
        <div class="filter-field">
            <label>Hasta</label>
            <input type="date" class="form-control" id="dash-hasta">
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" id="btn-filtrar-dash">
                <span>◎</span> Aplicar
            </button>
            <button class="btn btn-secondary" id="btn-reset-dash">Resetear</button>
            <a href="/procesar.php" class="btn btn-success">⬆ Importar</a>
        </div>
    </div>
</div>

<!-- ══════════ STATS ══════════ -->
<div class="stats-grid" id="stats-grid">
    <div class="stat-card green">
        <div class="stat-label">Total Ingresos</div>
        <div class="stat-value green" id="stat-ingresos">—</div>
        <div class="stat-sub" id="stat-ing-cant">— movimientos</div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">Total Gastos</div>
        <div class="stat-value red" id="stat-gastos">—</div>
        <div class="stat-sub" id="stat-gasto-cant">— movimientos</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Resultado Neto</div>
        <div class="stat-value" id="stat-neto">—</div>
        <div class="stat-sub">Ingresos − Gastos</div>
    </div>
    <div class="stat-card amber">
        <div class="stat-label">Sin Clasificar</div>
        <div class="stat-value" id="stat-sin-clasificar">—</div>
        <div class="stat-sub">Requieren atención</div>
    </div>
</div>

<!-- ══════════ GRÁFICOS ══════════ -->
<div class="grid-2 mb-24">
    <div class="card">
        <div class="card-title">Gastos e Ingresos por Categoría</div>
        <div id="chart-cat-empty" class="empty-state" style="padding:32px;display:none">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">Sin datos para el período</div>
        </div>
        <canvas id="chart-categorias" height="260"></canvas>
    </div>
    <div class="card">
        <div class="card-title">Distribución por Categoría</div>
        <div id="chart-torta-empty" class="empty-state" style="padding:32px;display:none">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">Sin datos para el período</div>
        </div>
        <canvas id="chart-torta" height="260"></canvas>
    </div>
</div>

<!-- ══════════ LOTES ══════════ -->
<div class="card">
    <div class="flex-between mb-16">
        <div class="card-title" style="margin:0">Últimos Lotes Importados</div>
        <a href="/movimientos.php" class="btn btn-secondary btn-sm">≡ Ver movimientos</a>
    </div>
    <div class="table-wrap">
        <table id="tabla-lotes">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Archivo</th>
                    <th>Total</th>
                    <th>Clasificadas</th>
                    <th>Sin clasificar</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="lotes-body">
                <tr><td colspan="7" class="empty-state">
                    <div class="spinner" style="margin:0 auto"></div>
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
let chartCat = null, chartTorta = null;
let dashDesde = '', dashHasta = '';

// ── Init dates ──
(function initDates() {
    const r = getDateRange('mes');
    dashDesde = r.desde;
    dashHasta = r.hasta;
    document.getElementById('dash-desde').value = dashDesde;
    document.getElementById('dash-hasta').value = dashHasta;
    updateTopbarPeriod();
})();

function updateTopbarPeriod() {
    const el = document.getElementById('topbar-period');
    if (!el) return;
    const d = (s) => s.split('-').reverse().join('/');
    el.textContent = d(dashDesde) + ' — ' + d(dashHasta);
}

// ── Quick range buttons ──
document.querySelectorAll('.qrange-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.qrange-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const r = getDateRange(btn.dataset.range);
        dashDesde = r.desde;
        dashHasta = r.hasta;
        document.getElementById('dash-desde').value = dashDesde;
        document.getElementById('dash-hasta').value = dashHasta;
        loadDashboard();
    });
});

// ── Manual date change clears quick range ──
['dash-desde', 'dash-hasta'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => {
        document.querySelectorAll('.qrange-btn').forEach(b => b.classList.remove('active'));
    });
});

// ── Apply filter ──
document.getElementById('btn-filtrar-dash').addEventListener('click', () => {
    dashDesde = document.getElementById('dash-desde').value;
    dashHasta = document.getElementById('dash-hasta').value;
    if (!dashDesde || !dashHasta) { toast('Seleccioná un rango de fechas', 'warning'); return; }
    loadDashboard();
});

// ── Reset ──
document.getElementById('btn-reset-dash').addEventListener('click', () => {
    document.querySelectorAll('.qrange-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('[data-range="mes"]')?.classList.add('active');
    const r = getDateRange('mes');
    dashDesde = r.desde;
    dashHasta = r.hasta;
    document.getElementById('dash-desde').value = dashDesde;
    document.getElementById('dash-hasta').value = dashHasta;
    loadDashboard();
});

// ── Skeleton loading state ──
function setLoadingState(loading) {
    const vals = ['stat-ingresos','stat-gastos','stat-neto','stat-sin-clasificar'];
    vals.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.toggle('skeleton', loading);
    });
}

// ── Load dashboard ──
async function loadDashboard() {
    setLoadingState(true);
    updateTopbarPeriod();

    try {
        const params = new URLSearchParams({ action: 'stats_dashboard', desde: dashDesde, hasta: dashHasta });
        const res  = await fetch(`/api/padrones.php?${params}`);
        const data = await res.json();

        if (data.error) { toast(data.error, 'error'); return; }

        setLoadingState(false);

        const t = data.totales;
        const ing   = parseFloat(t.total_ing   || 0);
        const gasto = parseFloat(t.total_gasto  || 0);
        const neto  = ing - gasto;
        const ingCant   = parseInt(t.cant_ing   || 0);
        const gastoCant = parseInt(t.cant_gasto || 0);

        document.getElementById('stat-ingresos').textContent     = formatMoney(ing);
        document.getElementById('stat-gastos').textContent       = formatMoney(gasto);
        document.getElementById('stat-neto').textContent         = formatMoney(neto);
        document.getElementById('stat-neto').className           = 'stat-value ' + (neto >= 0 ? 'green' : 'red');
        document.getElementById('stat-sin-clasificar').textContent = t.sin_clasificar || '0';
        document.getElementById('stat-ing-cant').textContent     = ingCant + ' movimientos';
        document.getElementById('stat-gasto-cant').textContent   = gastoCant + ' movimientos';

        renderCharts(data.por_categoria);
        renderLotes(data.lotes_recientes);

    } catch(e) {
        setLoadingState(false);
        toast('Error al cargar el dashboard', 'error');
    }
}

const palette = ['#2563eb','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#14b8a6','#ec4899','#6366f1','#84cc16','#a78bfa'];

function renderCharts(porCat) {
    // ── Bar chart: categorías ──
    const catMap = {};
    porCat.forEach(r => {
        if (!catMap[r.cat]) catMap[r.cat] = { ing: 0, gasto: 0 };
        if (r.tipo === 'ingreso') catMap[r.cat].ing   += parseFloat(r.total);
        else                      catMap[r.cat].gasto += parseFloat(r.total);
    });

    const cats = Object.keys(catMap)
        .sort((a,b) => (catMap[b].gasto + catMap[b].ing) - (catMap[a].gasto + catMap[a].ing))
        .slice(0, 10);

    const ingData   = cats.map(c => catMap[c].ing);
    const gastoData = cats.map(c => catMap[c].gasto);
    const hasData   = cats.length > 0;

    document.getElementById('chart-cat-empty').style.display  = hasData ? 'none' : 'block';
    document.getElementById('chart-categorias').style.display = hasData ? 'block' : 'none';

    if (hasData) {
        if (chartCat) chartCat.destroy();
        chartCat = new Chart(document.getElementById('chart-categorias'), {
            type: 'bar',
            data: {
                labels: cats,
                datasets: [
                    {
                        label: 'Ingresos',
                        data: ingData,
                        backgroundColor: 'rgba(16,185,129,0.75)',
                        borderColor: '#10b981',
                        borderWidth: 1,
                        borderRadius: 4,
                    },
                    {
                        label: 'Gastos',
                        data: gastoData,
                        backgroundColor: 'rgba(239,68,68,0.75)',
                        borderColor: '#ef4444',
                        borderWidth: 1,
                        borderRadius: 4,
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        labels: { color: '#7a90b0', font: { family: 'Syne', size: 11 }, padding: 12 }
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.dataset.label}: ${formatMoney(ctx.parsed.y)}`
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#4a5f7a', maxRotation: 35, font: { size: 10 }, autoSkip: true },
                        grid: { color: '#1e2d45' }
                    },
                    y: {
                        ticks: {
                            color: '#4a5f7a',
                            callback: v => '$ ' + Intl.NumberFormat('es-AR',{notation:'compact'}).format(v)
                        },
                        grid: { color: '#1e2d45' }
                    }
                }
            }
        });
    }

    // ── Donut chart ──
    const tortaData = cats.map(c => catMap[c].gasto + catMap[c].ing);
    const hasDataTorta = tortaData.some(v => v > 0);

    document.getElementById('chart-torta-empty').style.display = hasDataTorta ? 'none' : 'block';
    document.getElementById('chart-torta').style.display        = hasDataTorta ? 'block' : 'none';

    if (hasDataTorta) {
        if (chartTorta) chartTorta.destroy();
        chartTorta = new Chart(document.getElementById('chart-torta'), {
            type: 'doughnut',
            data: {
                labels: cats,
                datasets: [{
                    data: tortaData,
                    backgroundColor: palette,
                    borderColor: '#0e1320',
                    borderWidth: 2,
                    hoverOffset: 8,
                }]
            },
            options: {
                responsive: true,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#7a90b0',
                            font: { family: 'Syne', size: 11 },
                            padding: 12,
                            usePointStyle: true,
                            pointStyleWidth: 8,
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${formatMoney(ctx.parsed)}`
                        }
                    }
                }
            }
        });
    }
}

function renderLotes(lotes) {
    const tbody = document.getElementById('lotes-body');
    if (!lotes || !lotes.length) {
        tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">No hay lotes importados aún — <a href="/procesar.php" style="color:var(--accent-light)">Importar primero</a></div>
        </div></td></tr>`;
        return;
    }
    tbody.innerHTML = lotes.map(l => `
        <tr>
            <td class="mono" style="font-size:11px">${l.codigo}</td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${l.archivo_nombre||''}">${l.archivo_nombre || '—'}</td>
            <td class="mono">${l.total_filas}</td>
            <td><span class="badge badge-green">${l.filas_clasificadas}</span></td>
            <td>${parseInt(l.filas_sin_clasificar) > 0
                ? `<span class="badge badge-amber">${l.filas_sin_clasificar}</span>`
                : '<span class="badge badge-muted">0</span>'}</td>
            <td class="mono" style="font-size:11px">${new Date(l.created_at).toLocaleDateString('es-AR')}</td>
            <td><a href="/movimientos.php?lote=${l.codigo}" class="btn btn-secondary btn-sm">Ver</a></td>
        </tr>
    `).join('');
}

loadDashboard();
</script>

<?php require_once 'includes/footer.php'; ?>
