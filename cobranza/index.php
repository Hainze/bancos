<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Dashboard Cobranza';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Stats cards — por antigüedad -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr)">
    <div class="stat-card blue">
        <div class="stat-label">1–30 días</div>
        <div class="stat-value" id="stat-d30">—</div>
        <div class="stat-sub" id="sub-d30">— clientes</div>
    </div>
    <div class="stat-card amber">
        <div class="stat-label">31–60 días</div>
        <div class="stat-value" id="stat-d60">—</div>
        <div class="stat-sub" id="sub-d60">— clientes</div>
    </div>
    <div class="stat-card" style="border-color:rgba(249,115,22,.3)">
        <div class="stat-label">61–90 días</div>
        <div class="stat-value" style="color:#f97316" id="stat-d90">—</div>
        <div class="stat-sub" id="sub-d90">— clientes</div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">91–120 días</div>
        <div class="stat-value red" id="stat-d120">—</div>
        <div class="stat-sub" id="sub-d120">— clientes</div>
    </div>
    <div class="stat-card" style="border-color:rgba(139,92,246,.3)">
        <div class="stat-label">Más de 120</div>
        <div class="stat-value" style="color:#8b5cf6" id="stat-d120p">—</div>
        <div class="stat-sub" id="sub-d120p">— clientes</div>
    </div>
</div>

<!-- Total general -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-top:0">
    <div class="stat-card green">
        <div class="stat-label">Total deuda activa</div>
        <div class="stat-value green" id="stat-total">—</div>
        <div class="stat-sub" id="sub-total">— clientes con saldo positivo</div>
    </div>
    <div class="stat-card" style="border-color:rgba(37,99,235,.3)">
        <div class="stat-label">Sistema 1 (blanco)</div>
        <div class="stat-value" id="stat-sis1">—</div>
        <div class="stat-sub" id="sub-sis1">—</div>
    </div>
    <div class="stat-card" style="border-color:rgba(239,68,68,.3)">
        <div class="stat-label">Sistema 2 (negro)</div>
        <div class="stat-value red" id="stat-sis2">—</div>
        <div class="stat-sub" id="sub-sis2">—</div>
    </div>
</div>

<!-- Gráficos fila 1 -->
<div class="grid-2 mb-24">
    <div class="card">
        <div class="card-title">Deuda por antigüedad</div>
        <div id="chart-aging-empty" class="empty-state" style="padding:32px;display:none">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">Sin datos — <a href="/cobranza/procesar.php" style="color:var(--accent-light)">Subir Excel</a></div>
        </div>
        <canvas id="chart-aging" height="240"></canvas>
    </div>
    <div class="card">
        <div class="card-title">Sistema 1 vs Sistema 2</div>
        <div id="chart-sistemas-empty" class="empty-state" style="padding:32px;display:none">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">Sin datos</div>
        </div>
        <canvas id="chart-sistemas" height="240"></canvas>
    </div>
</div>

<!-- Gráficos fila 2: rangos separados -->
<div class="grid-2 mb-24">
    <div class="card">
        <div class="card-title">Clientes por rango de deuda</div>
        <div id="chart-rangos-cant-empty" class="empty-state" style="padding:32px;display:none">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">Sin datos</div>
        </div>
        <canvas id="chart-rangos-cant" height="240"></canvas>
    </div>
    <div class="card">
        <div class="card-title">Monto total por rango de deuda</div>
        <div id="chart-rangos-monto-empty" class="empty-state" style="padding:32px;display:none">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">Sin datos</div>
        </div>
        <canvas id="chart-rangos-monto" height="240"></canvas>
    </div>
</div>

<!-- Últimas cargas -->
<div class="card">
    <div class="flex-between mb-16">
        <div class="card-title" style="margin:0">Últimas carteras cargadas</div>
        <a href="/cobranza/procesar.php" class="btn btn-primary btn-sm">⬆ Subir Excel</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Archivo</th>
                    <th>Clientes</th>
                    <th>Total deuda</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="lotes-body">
                <tr><td colspan="5"><div class="empty-state"><div class="spinner" style="margin:0 auto"></div></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
let chartAging = null, chartSistemas = null;

// Esperar a que Chart.js (cargado con defer) esté disponible antes de renderizar
function waitForChart(cb) {
    if (typeof Chart !== 'undefined') { cb(); return; }
    const t = setInterval(() => { if (typeof Chart !== 'undefined') { clearInterval(t); cb(); } }, 50);
}

window.addEventListener('load', loadDashboard);

async function loadDashboard() {
    try {
        const res  = await fetch('/cobranza/api/stats.php?action=dashboard');
        const data = await res.json();
        if (data.error) { toast(data.error, 'error'); return; }

        renderCards(data);
        renderLotes(data.lotes);   // independiente de Chart.js
        waitForChart(() => {
            renderAging(data.aging);
            renderSistemas(data.sistemas);
            renderRangos(data.rangos);
        });
    } catch(e) {
        toast('Error al cargar el dashboard', 'error');
        console.error(e);
    }
}

function renderCards(data) {
    const aging = data.aging || {};
    const cols = ['d30','d60','d90','d120','d120plus'];
    cols.forEach(c => {
        const v = aging[c] || {};
        const el = document.getElementById('stat-' + c.replace('plus','p'));
        if (el) el.textContent = formatMoney(parseFloat(v.monto || 0));
        const sub = document.getElementById('sub-' + c.replace('plus','p'));
        if (sub) sub.textContent = (v.cant || 0) + ' clientes';
    });

    const s = data.sistemas || {};
    document.getElementById('stat-total').textContent = formatMoney(parseFloat(data.total_deuda || 0));
    document.getElementById('sub-total').textContent  = (data.total_clientes || 0) + ' clientes con saldo positivo';

    document.getElementById('stat-sis1').textContent = formatMoney(parseFloat(s.sis1_monto || 0));
    document.getElementById('sub-sis1').textContent  = (s.sis1_cant || 0) + ' clientes';
    document.getElementById('stat-sis2').textContent = formatMoney(parseFloat(s.sis2_monto || 0));
    document.getElementById('sub-sis2').textContent  = (s.sis2_cant || 0) + ' clientes';
}

function renderAging(aging) {
    if (!aging) return;
    const labels = ['1–30 días','31–60 días','61–90 días','91–120 días','Más de 120'];
    const keys   = ['d30','d60','d90','d120','d120plus'];
    const values = keys.map(k => parseFloat(aging[k]?.monto || 0));
    const hasData = values.some(v => v > 0);

    document.getElementById('chart-aging-empty').style.display = hasData ? 'none' : 'block';
    document.getElementById('chart-aging').style.display        = hasData ? 'block' : 'none';
    if (!hasData) return;

    if (chartAging) chartAging.destroy();
    chartAging = new Chart(document.getElementById('chart-aging'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Deuda',
                data: values,
                backgroundColor: ['rgba(37,99,235,.7)','rgba(245,158,11,.7)','rgba(249,115,22,.7)','rgba(239,68,68,.7)','rgba(139,92,246,.7)'],
                borderColor:     ['#2563eb','#f59e0b','#f97316','#ef4444','#8b5cf6'],
                borderWidth: 1, borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ' ' + formatMoney(ctx.parsed.y) } }
            },
            scales: {
                x: { ticks: { color:'#4a5f7a', font:{size:11} }, grid: { color:'#1e2d45' } },
                y: { ticks: { color:'#4a5f7a', callback: v => '$' + Intl.NumberFormat('es-AR',{notation:'compact'}).format(v) }, grid: { color:'#1e2d45' } }
            }
        }
    });
}

function renderSistemas(s) {
    if (!s) return;
    const vals = [parseFloat(s.sis1_monto||0), parseFloat(s.sis2_monto||0)];
    const hasData = vals.some(v => v > 0);

    document.getElementById('chart-sistemas-empty').style.display = hasData ? 'none' : 'block';
    document.getElementById('chart-sistemas').style.display        = hasData ? 'block' : 'none';
    if (!hasData) return;

    if (chartSistemas) chartSistemas.destroy();
    chartSistemas = new Chart(document.getElementById('chart-sistemas'), {
        type: 'doughnut',
        data: {
            labels: ['Sistema 1 (blanco)','Sistema 2 (negro)'],
            datasets: [{
                data: vals,
                backgroundColor: ['rgba(37,99,235,.75)','rgba(239,68,68,.75)'],
                borderColor: ['#2563eb','#ef4444'],
                borderWidth: 2, hoverOffset: 8,
            }]
        },
        options: {
            responsive: true, cutout: '60%',
            plugins: {
                legend: { position:'bottom', labels:{ color:'#7a90b0', font:{family:'Syne',size:11}, padding:14, usePointStyle:true, pointStyleWidth:8 } },
                tooltip: { callbacks: { label: ctx => ' ' + ctx.label + ': ' + formatMoney(ctx.parsed) } }
            }
        }
    });
}

let chartRangosCant = null, chartRangosMonto = null;

function renderRangos(rangos) {
    const noData = !rangos?.length;

    // ── Gráfico 1: Clientes por rango ──────────────────
    document.getElementById('chart-rangos-cant-empty').style.display = noData ? 'block' : 'none';
    document.getElementById('chart-rangos-cant').style.display        = noData ? 'none'  : 'block';

    // ── Gráfico 2: Monto por rango ─────────────────────
    document.getElementById('chart-rangos-monto-empty').style.display = noData ? 'block' : 'none';
    document.getElementById('chart-rangos-monto').style.display        = noData ? 'none'  : 'block';

    if (noData) return;

    const labels = rangos.map(r => r.label);
    const moneyFmt = v => '$' + Intl.NumberFormat('es-AR', { notation:'compact', maximumFractionDigits:1 }).format(v);

    if (chartRangosCant) chartRangosCant.destroy();
    chartRangosCant = new Chart(document.getElementById('chart-rangos-cant'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Clientes',
                data: rangos.map(r => r.cant),
                backgroundColor: 'rgba(37,99,235,.75)',
                borderColor: '#2563eb',
                borderWidth: 1, borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ' ' + ctx.parsed.y + ' clientes' } }
            },
            scales: {
                x: { ticks:{ color:'#4a5f7a', font:{size:11} }, grid:{ color:'#1e2d45' } },
                y: { ticks:{ color:'#4a5f7a', stepSize:1 }, grid:{ color:'#1e2d45' } }
            }
        }
    });

    if (chartRangosMonto) chartRangosMonto.destroy();
    chartRangosMonto = new Chart(document.getElementById('chart-rangos-monto'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Monto total',
                data: rangos.map(r => parseFloat(r.monto || 0)),
                backgroundColor: 'rgba(16,185,129,.75)',
                borderColor: '#10b981',
                borderWidth: 1, borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ' ' + formatMoney(ctx.parsed.y) } }
            },
            scales: {
                x: { ticks:{ color:'#4a5f7a', font:{size:11} }, grid:{ color:'#1e2d45' } },
                y: { ticks:{ color:'#4a5f7a', callback: moneyFmt }, grid:{ color:'#1e2d45' } }
            }
        }
    });
}

function renderLotes(lotes) {
    const tbody = document.getElementById('lotes-body');
    if (!lotes?.length) {
        tbody.innerHTML = `<tr><td colspan="5"><div class="empty-state">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">Sin carteras cargadas — <a href="/cobranza/procesar.php" style="color:var(--accent-light)">Subir Excel</a></div>
        </div></td></tr>`;
        return;
    }
    tbody.innerHTML = lotes.map(l => `<tr>
        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${l.archivo_nombre}</td>
        <td class="mono">${l.total_clientes}</td>
        <td class="mono green">${formatMoney(l.total_importe)}</td>
        <td class="mono" style="font-size:11px">${new Date(l.created_at).toLocaleDateString('es-AR')}</td>
        <td>
            <button class="btn btn-danger btn-sm" onclick="eliminarLote(${l.id})">Eliminar</button>
        </td>
    </tr>`).join('');
}

async function eliminarLote(id) {
    confirmAction('¿Eliminar esta cartera? Se borrarán todos los clientes asociados.', async () => {
        const res  = await fetch('/cobranza/api/cartera.php?action=eliminar_lote', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });
        const data = await res.json();
        if (data.error) { toast(data.error, 'error'); return; }
        toast('Cartera eliminada', 'success');
        loadDashboard();
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
