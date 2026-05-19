<?php
require_once 'config.php';
$titulo = 'Dashboard';
require_once 'includes/header.php';
?>

<div class="stats-grid" id="stats-grid">
    <div class="stat-card green">
        <div class="stat-label">Total Ingresos (mes)</div>
        <div class="stat-value green" id="stat-ingresos">—</div>
        <div class="stat-sub" id="stat-ing-cant">— movimientos</div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">Total Gastos (mes)</div>
        <div class="stat-value red" id="stat-gastos">—</div>
        <div class="stat-sub" id="stat-gasto-cant">— movimientos</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Neto del Mes</div>
        <div class="stat-value" id="stat-neto">—</div>
        <div class="stat-sub">Ingresos - Gastos</div>
    </div>
    <div class="stat-card amber">
        <div class="stat-label">Sin Clasificar</div>
        <div class="stat-value" id="stat-sin-clasificar">—</div>
        <div class="stat-sub">Requieren atención</div>
    </div>
</div>

<div class="grid-2 mb-24">
    <div class="card">
        <div class="card-title">Ingresos vs Gastos por Categoría</div>
        <canvas id="chart-categorias" height="260"></canvas>
    </div>
    <div class="card">
        <div class="card-title">Distribución por Tipo</div>
        <canvas id="chart-torta" height="260"></canvas>
    </div>
</div>

<div class="card">
    <div class="flex-between mb-16">
        <div class="card-title" style="margin:0">Últimos Lotes Importados</div>
        <a href="procesar.php" class="btn btn-primary btn-sm">⬆ Importar nuevo</a>
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
                <tr><td colspan="7" class="empty-state"><div class="empty-state-icon">◈</div><div class="empty-state-text">Cargando...</div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
let chartCat = null, chartTorta = null;

async function loadDashboard() {
    const hoy = new Date();
    const desde = hoy.getFullYear() + '-' + String(hoy.getMonth()+1).padStart(2,'0') + '-01';
    const hasta = hoy.toISOString().slice(0,10);

    const res = await fetch(`/api/padrones.php?action=stats_dashboard&desde=${desde}&hasta=${hasta}`);
    const data = await res.json();

    if (data.error) { toast(data.error, 'error'); return; }

    const t = data.totales;
    const ing   = parseFloat(t.total_ing || 0);
    const gasto = parseFloat(t.total_gasto || 0);
    const neto  = ing - gasto;

    document.getElementById('stat-ingresos').textContent = formatMoney(ing);
    document.getElementById('stat-gastos').textContent   = formatMoney(gasto);
    document.getElementById('stat-neto').textContent     = formatMoney(neto);
    document.getElementById('stat-neto').className       = 'stat-value ' + (neto >= 0 ? 'green' : 'red');
    document.getElementById('stat-sin-clasificar').textContent = t.sin_clasificar || '0';

    // Chart categorias
    const porCat = data.por_categoria;
    const labels = [...new Set(porCat.map(r => r.cat))].slice(0,10);
    const ingData   = labels.map(l => { const f = porCat.find(r=>r.cat===l && r.tipo==='ingreso'); return f ? parseFloat(f.total) : 0; });
    const gastoData = labels.map(l => { const f = porCat.find(r=>r.cat===l && r.tipo==='gasto');   return f ? parseFloat(f.total) : 0; });

    if (chartCat) chartCat.destroy();
    chartCat = new Chart(document.getElementById('chart-categorias'), {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Ingresos', data: ingData,   backgroundColor: 'rgba(16,185,129,0.7)', borderColor: '#10b981', borderWidth: 1 },
                { label: 'Gastos',   data: gastoData, backgroundColor: 'rgba(239,68,68,0.7)',  borderColor: '#ef4444', borderWidth: 1 },
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { labels: { color: '#7a90b0', font: { family: 'Syne' } } } },
            scales: {
                x: { ticks: { color: '#4a5f7a', maxRotation: 40, font: { size: 10 } }, grid: { color: '#1e2d45' } },
                y: { ticks: { color: '#4a5f7a', callback: v => '$ ' + v.toLocaleString('es-AR') }, grid: { color: '#1e2d45' } }
            }
        }
    });

    // Torta
    const tortaLabels = labels;
    const tortaData   = labels.map(l => {
        const total = porCat.filter(r=>r.cat===l).reduce((s,r)=>s+parseFloat(r.total),0);
        return total;
    });
    const palette = ['#2563eb','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#14b8a6','#ec4899','#6366f1'];

    if (chartTorta) chartTorta.destroy();
    chartTorta = new Chart(document.getElementById('chart-torta'), {
        type: 'doughnut',
        data: {
            labels: tortaLabels,
            datasets: [{ data: tortaData, backgroundColor: palette, borderColor: '#0e1320', borderWidth: 2 }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { color: '#7a90b0', font: { family: 'Syne', size: 11 }, padding: 12 } }
            }
        }
    });

    // Lotes
    const lotes = data.lotes_recientes;
    const tbody = document.getElementById('lotes-body');
    if (!lotes.length) {
        tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">◈</div><div class="empty-state-text">No hay lotes importados aún</div></div></td></tr>`;
        return;
    }
    tbody.innerHTML = lotes.map(l => `
        <tr>
            <td class="mono">${l.codigo}</td>
            <td>${l.archivo_nombre || '—'}</td>
            <td class="mono">${l.total_filas}</td>
            <td><span class="badge badge-green">${l.filas_clasificadas}</span></td>
            <td>${l.filas_sin_clasificar > 0 ? `<span class="badge badge-amber">${l.filas_sin_clasificar}</span>` : '<span class="badge badge-muted">0</span>'}</td>
            <td class="mono">${new Date(l.created_at).toLocaleDateString('es-AR')}</td>
            <td><a href="/movimientos.php?lote=${l.codigo}" class="btn btn-secondary btn-sm">Ver</a></td>
        </tr>
    `).join('');
}

loadDashboard();
</script>

<?php require_once 'includes/footer.php'; ?>
