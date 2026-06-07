<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="filter-bar">
    <div class="filter-bar-header">
        <span class="filter-bar-title">Período</span>
        <div class="quick-ranges">
            <button class="qrange-btn active" data-range="mes">Este mes</button>
            <button class="qrange-btn" data-range="mes_ant">Mes ant.</button>
            <button class="qrange-btn" data-range="trim">Últ. 3 meses</button>
            <button class="qrange-btn" data-range="anio">Este año</button>
            <button class="qrange-btn" data-range="todo">Todo</button>
        </div>
    </div>
    <div class="filter-bar-body">
        <div class="filter-field">
            <label>Mes desde</label>
            <input type="month" class="form-control" id="dash-desde">
        </div>
        <div class="filter-field">
            <label>Mes hasta</label>
            <input type="month" class="form-control" id="dash-hasta">
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" id="btn-filtrar-dash"><span>◎</span> Aplicar</button>
            <button class="btn btn-secondary" id="btn-reset-dash">Resetear</button>
            <a href="/mp/procesar.php" class="btn btn-success">⬆ Importar</a>
            <button class="btn btn-danger" id="btn-limpiar-prueba" title="Elimina todos los movimientos y lotes importados">⚠ Limpiar datos</button>
        </div>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card green">
        <div class="stat-label">Total Ingresos</div>
        <div class="stat-value green" id="stat-ingresos">—</div>
        <div class="stat-sub" id="stat-ing-cant">— movimientos</div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">Total Egresos</div>
        <div class="stat-value red" id="stat-gastos">—</div>
        <div class="stat-sub" id="stat-gasto-cant">— movimientos</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Resultado Neto</div>
        <div class="stat-value" id="stat-neto">—</div>
        <div class="stat-sub">Ingresos − Egresos</div>
    </div>
    <div class="stat-card amber">
        <div class="stat-label">Sin Clasificar</div>
        <div class="stat-value" id="stat-sin-clasificar">—</div>
        <div class="stat-sub">Requieren atención</div>
    </div>
</div>

<div class="grid-2 mb-24">
    <div class="card">
        <div class="card-title">Egresos e Ingresos por Categoría</div>
        <div id="chart-cat-empty" class="empty-state" style="padding:32px;display:none">
            <div class="empty-state-icon">◈</div><div class="empty-state-text">Sin datos para el período</div>
        </div>
        <canvas id="chart-categorias" height="260"></canvas>
    </div>
    <div class="card">
        <div class="card-title">Distribución por Categoría</div>
        <div id="chart-torta-empty" class="empty-state" style="padding:32px;display:none">
            <div class="empty-state-icon">◈</div><div class="empty-state-text">Sin datos para el período</div>
        </div>
        <canvas id="chart-torta" height="260"></canvas>
    </div>
</div>

<div class="card">
    <div class="flex-between mb-16">
        <div class="card-title" style="margin:0">Últimos Lotes Importados</div>
        <a href="/mp/movimientos.php" class="btn btn-secondary btn-sm">≡ Ver todos</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Código</th><th>Archivo</th><th>Total</th>
                    <th>Clasificadas</th><th>Sin clasificar</th><th>Fecha</th><th></th>
                </tr>
            </thead>
            <tbody id="lotes-body">
                <tr><td colspan="7"><div class="empty-state"><div class="spinner" style="margin:0 auto"></div></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal limpiar datos -->
<div class="modal-overlay" id="modal-limpiar">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--red)">⚠ Limpiar datos de prueba</div>
            <button class="modal-close" onclick="closeModal('modal-limpiar')">✕</button>
        </div>
        <p style="color:var(--sub);font-size:14px;margin-bottom:16px">
            Esto va a eliminar <strong style="color:var(--text)">todos los movimientos y lotes de Mercado Pago</strong>.
        </p>
        <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);border-radius:8px;padding:12px 16px;display:flex;gap:10px;align-items:flex-start;margin-bottom:24px">
            <span style="color:var(--red);font-size:16px;flex-shrink:0">✕</span>
            <div style="font-size:13px;color:var(--sub)">
                Las <strong style="color:var(--text)">categorías, palabras clave y clientes</strong> no se tocan.<br>
                Esta acción <strong style="color:var(--red)">no se puede deshacer</strong>.
            </div>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end">
            <button class="btn btn-secondary" onclick="closeModal('modal-limpiar')">Cancelar</button>
            <button class="btn btn-danger" id="btn-confirmar-limpiar">Sí, eliminar todo</button>
        </div>
    </div>
</div>

<script>
let chartCat = null, chartTorta = null;
let dashDesde = '', dashHasta = '';

function getMonthRange(range) {
    const now = new Date();
    const y = now.getFullYear(), m = now.getMonth() + 1;
    const pad = n => String(n).padStart(2, '0');
    const curr = `${y}-${pad(m)}`;
    switch (range) {
        case 'mes':     return { desde: curr, hasta: curr };
        case 'mes_ant': { const pm = m===1?12:m-1, py = m===1?y-1:y; const s=`${py}-${pad(pm)}`; return {desde:s,hasta:s}; }
        case 'trim':    { const pm3 = m<=3?12+m-3:m-3, py3 = m<=3?y-1:y; return {desde:`${py3}-${pad(pm3)}`,hasta:curr}; }
        case 'anio':    return { desde: `${y}-01`, hasta: curr };
        case 'todo':    return { desde: '2000-01', hasta: curr };
        default:        return { desde: curr, hasta: curr };
    }
}
function monthStart(m)  { return m ? m + '-01' : ''; }
function monthEnd(m) {
    if (!m) return '';
    const [y, mo] = m.split('-').map(Number);
    return `${y}-${String(mo).padStart(2,'0')}-${new Date(y, mo, 0).getDate()}`;
}
function applyMonths() {
    const mD = document.getElementById('dash-desde').value;
    const mH = document.getElementById('dash-hasta').value;
    dashDesde = monthStart(mD);
    dashHasta = monthEnd(mH);
    updatePeriod(mD, mH);
}

(function() {
    const r = getMonthRange('mes');
    document.getElementById('dash-desde').value = r.desde;
    document.getElementById('dash-hasta').value = r.hasta;
    applyMonths();
    loadDashboard();
})();

function updatePeriod(mD, mH) {
    const el = document.getElementById('topbar-period');
    if (!el || !mD || !mH) return;
    const fmt = m => { const [y,mo] = m.split('-').map(Number); return new Date(y,mo-1).toLocaleDateString('es-AR',{month:'long',year:'numeric'}); };
    el.textContent = mD === mH ? fmt(mD) : fmt(mD) + ' — ' + fmt(mH);
}

document.querySelectorAll('.qrange-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.qrange-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const r = getMonthRange(btn.dataset.range);
        document.getElementById('dash-desde').value = r.desde;
        document.getElementById('dash-hasta').value = r.hasta;
        applyMonths();
        loadDashboard();
    });
});

document.getElementById('btn-filtrar-dash').addEventListener('click', () => {
    applyMonths();
    loadDashboard();
});

document.getElementById('btn-reset-dash').addEventListener('click', () => {
    document.querySelectorAll('.qrange-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('[data-range="mes"]')?.classList.add('active');
    const r = getMonthRange('mes');
    document.getElementById('dash-desde').value = r.desde;
    document.getElementById('dash-hasta').value = r.hasta;
    applyMonths();
    loadDashboard();
});

function setLoading(on) {
    ['stat-ingresos','stat-gastos','stat-neto','stat-sin-clasificar'].forEach(id => {
        document.getElementById(id)?.classList.toggle('skeleton', on);
    });
}

const palette = ['#009ee3','#00b1ea','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#14b8a6','#ec4899','#6366f1','#84cc16'];

async function loadDashboard() {
    setLoading(true);
    try {
        const params = new URLSearchParams({ action:'stats_dashboard', desde:dashDesde, hasta:dashHasta });
        const res  = await fetch('/mp/api/padrones.php?' + params);
        const data = await res.json();
        if (data.error) { toast(data.error, 'error'); return; }
        setLoading(false);

        const t   = data.totales;
        const ing = parseFloat(t.total_ing || 0);
        const gas = parseFloat(t.total_gasto || 0);
        const net = ing - gas;

        document.getElementById('stat-ingresos').textContent      = formatMoney(ing);
        document.getElementById('stat-gastos').textContent        = formatMoney(gas);
        document.getElementById('stat-neto').textContent          = formatMoney(net);
        document.getElementById('stat-neto').className            = 'stat-value ' + (net >= 0 ? 'green' : 'red');
        document.getElementById('stat-sin-clasificar').textContent= t.sin_clasificar || '0';
        document.getElementById('stat-ing-cant').textContent      = (t.cant_ing || 0) + ' movimientos';
        document.getElementById('stat-gasto-cant').textContent    = (t.cant_gasto || 0) + ' movimientos';

        renderCharts(data.por_categoria);
        renderLotes(data.lotes_recientes);
    } catch(e) {
        setLoading(false);
        toast('Error al cargar el dashboard', 'error');
    }
}

function renderCharts(porCat) {
    const catMap = {};
    porCat.forEach(r => {
        if (!catMap[r.cat]) catMap[r.cat] = { ing:0, gasto:0 };
        if (r.tipo === 'ingreso') catMap[r.cat].ing   += parseFloat(r.total);
        else                      catMap[r.cat].gasto += parseFloat(r.total);
    });
    const cats = Object.keys(catMap).sort((a,b) => (catMap[b].gasto+catMap[b].ing)-(catMap[a].gasto+catMap[a].ing)).slice(0,10);
    const hasData = cats.length > 0;

    document.getElementById('chart-cat-empty').style.display  = hasData ? 'none' : 'block';
    document.getElementById('chart-categorias').style.display = hasData ? 'block' : 'none';
    if (hasData) {
        if (chartCat) chartCat.destroy();
        chartCat = new Chart(document.getElementById('chart-categorias'), {
            type: 'bar',
            data: { labels: cats, datasets: [
                { label:'Ingresos', data:cats.map(c=>catMap[c].ing),   backgroundColor:'rgba(16,185,129,0.75)', borderColor:'#10b981', borderWidth:1, borderRadius:4 },
                { label:'Egresos',  data:cats.map(c=>catMap[c].gasto), backgroundColor:'rgba(239,68,68,0.75)',  borderColor:'#ef4444', borderWidth:1, borderRadius:4 },
            ]},
            options: {
                responsive: true,
                plugins: {
                    legend: { labels: { color:'#7a90b0', font:{family:'Syne',size:11}, padding:12 } },
                    tooltip: { callbacks: { label: ctx => ` ${ctx.dataset.label}: ${formatMoney(ctx.parsed.y)}` } }
                },
                scales: {
                    x: { ticks:{color:'#4a5f7a',maxRotation:35,font:{size:10}}, grid:{color:'#1e2d45'} },
                    y: { ticks:{color:'#4a5f7a', callback:v=>'$ '+Intl.NumberFormat('es-AR',{notation:'compact'}).format(v)}, grid:{color:'#1e2d45'} }
                }
            }
        });
    }

    const tortaData = cats.map(c => catMap[c].gasto + catMap[c].ing);
    const hasTorta  = tortaData.some(v => v > 0);
    document.getElementById('chart-torta-empty').style.display = hasTorta ? 'none' : 'block';
    document.getElementById('chart-torta').style.display        = hasTorta ? 'block' : 'none';
    if (hasTorta) {
        if (chartTorta) chartTorta.destroy();
        chartTorta = new Chart(document.getElementById('chart-torta'), {
            type: 'doughnut',
            data: { labels:cats, datasets:[{ data:tortaData, backgroundColor:palette, borderColor:'#0e1320', borderWidth:2, hoverOffset:8 }] },
            options: {
                responsive: true, cutout:'62%',
                plugins: {
                    legend: { position:'bottom', labels:{color:'#7a90b0',font:{family:'Syne',size:11},padding:12,usePointStyle:true,pointStyleWidth:8} },
                    tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${formatMoney(ctx.parsed)}` } }
                }
            }
        });
    }
}

function renderLotes(lotes) {
    const tbody = document.getElementById('lotes-body');
    if (!lotes?.length) {
        tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">No hay lotes importados — <a href="/mp/procesar.php" style="color:var(--accent-light)">Importar</a></div>
        </div></td></tr>`;
        return;
    }
    tbody.innerHTML = lotes.map(l => `<tr>
        <td class="mono" style="font-size:11px">${l.codigo}</td>
        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${l.archivo_nombre||'—'}</td>
        <td class="mono">${l.total_filas}</td>
        <td><span class="badge badge-green">${l.filas_clasificadas}</span></td>
        <td>${parseInt(l.filas_sin_clasificar)>0?`<span class="badge badge-amber">${l.filas_sin_clasificar}</span>`:'<span class="badge badge-muted">0</span>'}</td>
        <td class="mono" style="font-size:11px">${new Date(l.created_at).toLocaleDateString('es-AR')}</td>
        <td><a href="/mp/movimientos.php?lote=${l.codigo}" class="btn btn-secondary btn-sm">Ver</a></td>
    </tr>`).join('');
}

document.getElementById('btn-limpiar-prueba').addEventListener('click', () => openModal('modal-limpiar'));

document.getElementById('btn-confirmar-limpiar').addEventListener('click', async () => {
    const btn = document.getElementById('btn-confirmar-limpiar');
    btn.disabled = true; btn.textContent = 'Eliminando...';
    try {
        const res  = await fetch('/mp/api/padrones.php?action=resetear_movimientos', { method: 'POST' });
        const data = await res.json();
        closeModal('modal-limpiar');
        if (data.success) { toast('Datos eliminados', 'success'); loadDashboard(); }
        else toast(data.error || 'Error al limpiar', 'error');
    } catch(e) { toast('Error: ' + e.message, 'error'); }
    finally { btn.disabled = false; btn.textContent = 'Sí, eliminar todo'; }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
