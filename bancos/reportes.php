<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Reportes';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card mb-16">
    <div class="flex-between" style="flex-wrap:wrap;gap:12px">
        <div class="flex-gap gap-8" style="flex-wrap:wrap">
            <div class="form-group" style="margin:0">
                <label class="form-label">Desde</label>
                <input type="date" class="form-control" id="rep-desde" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label">Hasta</label>
                <input type="date" class="form-control" id="rep-hasta" value="<?= date('Y-m-d') ?>">
            </div>
        </div>
        <div class="flex-gap gap-8" style="align-self:flex-end">
            <button class="btn btn-primary" id="btn-generar">◎ Generar Reporte</button>
            <button class="btn btn-success" id="btn-exportar-totales">⬇ Exportar Totales</button>
        </div>
    </div>
</div>

<div id="reporte-body" style="display:none">
    <div class="stats-grid mb-24">
        <div class="stat-card green">
            <div class="stat-label">Total Ingresos</div>
            <div class="stat-value green" id="rep-ing">—</div>
            <div class="stat-sub" id="rep-ing-cant">—</div>
        </div>
        <div class="stat-card red">
            <div class="stat-label">Total Gastos</div>
            <div class="stat-value red" id="rep-gasto">—</div>
            <div class="stat-sub" id="rep-gasto-cant">—</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-label">Resultado Neto</div>
            <div class="stat-value" id="rep-neto">—</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-label">Sin Clasificar</div>
            <div class="stat-value" id="rep-sin">—</div>
        </div>
    </div>

    <div class="grid-2 mb-24">
        <div class="card">
            <div class="card-title">Gastos por Categoría</div>
            <canvas id="chart-gastos" height="300"></canvas>
        </div>
        <div class="card">
            <div class="card-title">Ingresos por Categoría</div>
            <canvas id="chart-ingresos" height="300"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="card-title">Totales por Categoría</div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Categoría</th>
                        <th>Código</th>
                        <th>Tipo</th>
                        <th>Total</th>
                        <th>Movimientos</th>
                        <th>% del Total</th>
                    </tr>
                </thead>
                <tbody id="rep-tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<div id="reporte-empty" class="card">
    <div class="empty-state" style="padding:64px">
        <div class="empty-state-icon">◎</div>
        <div class="empty-state-text">Seleccioná un rango de fechas y generá el reporte</div>
    </div>
</div>

<script>
let chartGastos = null, chartIngresos = null;
let reporteDesde = '', reporteHasta = '';

async function generarReporte() {
    reporteDesde = document.getElementById('rep-desde').value;
    reporteHasta = document.getElementById('rep-hasta').value;

    if (!reporteDesde || !reporteHasta) { toast('Seleccioná un rango de fechas', 'warning'); return; }

    const params = new URLSearchParams({ action:'stats_dashboard', desde:reporteDesde, hasta:reporteHasta });
    const res  = await fetch('/api/padrones.php?' + params);
    const data = await res.json();

    if (data.error) { toast(data.error, 'error'); return; }

    document.getElementById('reporte-empty').style.display = 'none';
    document.getElementById('reporte-body').style.display  = 'block';

    const t   = data.totales;
    const ing = parseFloat(t.total_ing || 0);
    const gas = parseFloat(t.total_gasto || 0);
    const net = ing - gas;

    document.getElementById('rep-ing').textContent   = formatMoney(ing);
    document.getElementById('rep-gasto').textContent = formatMoney(gas);
    document.getElementById('rep-neto').textContent  = formatMoney(net);
    document.getElementById('rep-neto').className    = 'stat-value ' + (net >= 0 ? 'green' : 'red');
    document.getElementById('rep-sin').textContent   = t.sin_clasificar || '0';

    const porCat = data.por_categoria;
    const totalAbs = porCat.reduce((s,r) => s + parseFloat(r.total), 0);

    // Table
    const tbody = document.getElementById('rep-tbody');
    tbody.innerHTML = porCat.map(r => {
        const pct = totalAbs > 0 ? ((parseFloat(r.total)/totalAbs)*100).toFixed(1) : '0.0';
        return `<tr>
            <td>${r.cat}</td>
            <td class="mono">${r.tipo==='ingreso' ? '' : ''}</td>
            <td><span class="badge ${r.tipo==='ingreso'?'badge-green':'badge-red'}">${r.tipo}</span></td>
            <td class="mono" style="color:${r.tipo==='ingreso'?'var(--green)':'var(--red)'}">${formatMoney(parseFloat(r.total))}</td>
            <td class="mono">${r.cant}</td>
            <td>
                <div class="flex-gap gap-8">
                    <div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:${pct}%"></div></div>
                    <span class="mono" style="font-size:11px">${pct}%</span>
                </div>
            </td>
        </tr>`;
    }).join('');

    // Charts
    const gastosCats = porCat.filter(r => r.tipo === 'gasto').sort((a,b)=>parseFloat(b.total)-parseFloat(a.total));
    const ingresosCats = porCat.filter(r => r.tipo === 'ingreso').sort((a,b)=>parseFloat(b.total)-parseFloat(a.total));
    const palette = ['#ef4444','#f97316','#f59e0b','#84cc16','#22d3ee','#8b5cf6','#ec4899','#06b6d4','#10b981','#6366f1'];
    const paletteGreen = ['#10b981','#34d399','#6ee7b7','#059669','#047857','#065f46','#064e3b','#14b8a6','#0d9488','#0f766e'];

    if (chartGastos) chartGastos.destroy();
    chartGastos = new Chart(document.getElementById('chart-gastos'), {
        type: 'doughnut',
        data: {
            labels: gastosCats.map(r=>r.cat),
            datasets: [{ data: gastosCats.map(r=>parseFloat(r.total)), backgroundColor: palette, borderColor:'#0e1320', borderWidth:2 }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position:'bottom', labels:{ color:'#7a90b0', font:{family:'Syne',size:11}, padding:10 } },
                tooltip: { callbacks: { label: ctx => ' $ ' + ctx.parsed.toLocaleString('es-AR', {minimumFractionDigits:2}) } }
            }
        }
    });

    if (chartIngresos) chartIngresos.destroy();
    chartIngresos = new Chart(document.getElementById('chart-ingresos'), {
        type: 'doughnut',
        data: {
            labels: ingresosCats.map(r=>r.cat),
            datasets: [{ data: ingresosCats.map(r=>parseFloat(r.total)), backgroundColor: paletteGreen, borderColor:'#0e1320', borderWidth:2 }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position:'bottom', labels:{ color:'#7a90b0', font:{family:'Syne',size:11}, padding:10 } },
                tooltip: { callbacks: { label: ctx => ' $ ' + ctx.parsed.toLocaleString('es-AR', {minimumFractionDigits:2}) } }
            }
        }
    });
}

document.getElementById('btn-generar').addEventListener('click', generarReporte);
document.getElementById('btn-exportar-totales').addEventListener('click', () => {
    if (!reporteDesde) { toast('Generá el reporte primero', 'warning'); return; }
    const p = new URLSearchParams({ tipo:'totales', desde:reporteDesde, hasta:reporteHasta });
    window.location.href = '/api/exportar_excel.php?' + p;
});

// Auto-generate on load
generarReporte();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

