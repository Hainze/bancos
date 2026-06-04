<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Dashboard Proveedores';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Stats principales -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
    <div class="stat-card red">
        <div class="stat-label">A pagar (deuda)</div>
        <div class="stat-value red" id="stat-deuda">—</div>
        <div class="stat-sub" id="sub-deuda">saldo negativo</div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">A favor (crédito)</div>
        <div class="stat-value green" id="stat-favor">—</div>
        <div class="stat-sub" id="sub-favor">saldo positivo</div>
    </div>
    <div class="stat-card" style="border-color:rgba(139,92,246,.3)">
        <div class="stat-label">Saldo neto</div>
        <div class="stat-value" id="stat-neto" style="color:#8b5cf6">—</div>
        <div class="stat-sub" id="sub-neto">favor − deuda</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Proveedores</div>
        <div class="stat-value" id="stat-total">—</div>
        <div class="stat-sub">con saldo activo</div>
    </div>
</div>

<!-- Stats por sistema -->
<div class="stats-grid" style="grid-template-columns:repeat(2,1fr);margin-top:0">
    <div class="stat-card" style="border-color:rgba(37,99,235,.3)">
        <div class="stat-label">Sistema 1 (blanco)</div>
        <div style="display:flex;gap:16px;margin-top:4px">
            <div>
                <div class="stat-value red" id="stat-sis1-deuda" style="font-size:18px">—</div>
                <div class="stat-sub">a pagar</div>
            </div>
            <div style="color:var(--border)">|</div>
            <div>
                <div class="stat-value green" id="stat-sis1-favor" style="font-size:18px">—</div>
                <div class="stat-sub">a favor</div>
            </div>
        </div>
    </div>
    <div class="stat-card" style="border-color:rgba(239,68,68,.3)">
        <div class="stat-label">Sistema 2 (negro)</div>
        <div style="display:flex;gap:16px;margin-top:4px">
            <div>
                <div class="stat-value red" id="stat-sis2-deuda" style="font-size:18px">—</div>
                <div class="stat-sub">a pagar</div>
            </div>
            <div style="color:var(--border)">|</div>
            <div>
                <div class="stat-value green" id="stat-sis2-favor" style="font-size:18px">—</div>
                <div class="stat-sub">a favor</div>
            </div>
        </div>
    </div>
</div>

<!-- Gráficos fila 1 -->
<div class="grid-2 mb-24">
    <div class="card">
        <div class="card-title">A pagar vs A favor</div>
        <div id="chart-balance-empty" class="empty-state" style="padding:32px;display:none">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">Sin datos — <a href="/proveedores/procesar.php" style="color:var(--accent-light)">Subir Excel</a></div>
        </div>
        <canvas id="chart-balance" height="240"></canvas>
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

<!-- Gráficos fila 2 -->
<div class="grid-2 mb-24">
    <div class="card">
        <div class="card-title">Proveedores por rango de saldo</div>
        <div id="chart-rangos-cant-empty" class="empty-state" style="padding:32px;display:none">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">Sin datos</div>
        </div>
        <canvas id="chart-rangos-cant" height="240"></canvas>
    </div>
    <div class="card">
        <div class="card-title">Monto total por rango de saldo</div>
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
        <div style="display:flex;gap:8px">
            <a href="/proveedores/procesar.php" class="btn btn-primary btn-sm">⬆ Subir Excel</a>
            <button class="btn btn-danger btn-sm" onclick="openModal('modal-reset')">⚠ Eliminar todo</button>
        </div>
    </div>

    <div class="modal-overlay" id="modal-reset">
        <div class="modal" style="max-width:420px">
            <div class="modal-header">
                <div class="modal-title" style="color:var(--red)">⚠ Eliminar todos los datos</div>
                <button class="modal-close">✕</button>
            </div>
            <p style="color:var(--sub);font-size:14px;margin-bottom:16px">
                Se van a eliminar <strong style="color:var(--text)">todas las carteras cargadas y todas las observaciones</strong>.
            </p>
            <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);border-radius:8px;padding:12px 16px;margin-bottom:24px;font-size:13px;color:var(--sub)">
                Esta acción <strong style="color:var(--red)">no se puede deshacer</strong>.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button class="btn btn-secondary" onclick="closeModal('modal-reset')">Cancelar</button>
                <button class="btn btn-danger" id="btn-confirmar-reset">Sí, eliminar todo</button>
            </div>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Archivo</th>
                    <th>Proveedores</th>
                    <th style="text-align:right;color:var(--red)">A pagar</th>
                    <th style="text-align:right;color:var(--green)">A favor</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="lotes-body">
                <tr><td colspan="6"><div class="empty-state"><div class="spinner" style="margin:0 auto"></div></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
let chartBalance = null, chartSistemas = null, chartRangosCant = null, chartRangosMonto = null;

function waitForChart(cb) {
    if (typeof Chart !== 'undefined') { cb(); return; }
    const t = setInterval(() => { if (typeof Chart !== 'undefined') { clearInterval(t); cb(); } }, 50);
}

window.addEventListener('load', loadDashboard);

async function loadDashboard() {
    try {
        const res  = await fetch('/proveedores/api/stats.php?action=dashboard');
        const data = await res.json();
        if (data.error) { toast(data.error, 'error'); return; }
        renderCards(data);
        renderLotes(data.lotes);
        waitForChart(() => {
            renderBalance(data);
            renderSistemas(data.sistemas);
            renderRangos(data.rangos);
        });
    } catch(e) {
        toast('Error al cargar el dashboard', 'error');
        console.error(e);
    }
}

function renderCards(data) {
    const d = parseFloat(data.deuda || 0);
    const f = parseFloat(data.favor || 0);
    const n = parseFloat(data.neto  || 0);

    document.getElementById('stat-deuda').textContent = formatMoney(d);
    document.getElementById('stat-favor').textContent = formatMoney(f);

    const netoEl = document.getElementById('stat-neto');
    netoEl.textContent = (n >= 0 ? '' : '-') + formatMoney(Math.abs(n));
    netoEl.style.color = n >= 0 ? 'var(--green)' : 'var(--red)';

    document.getElementById('stat-total').textContent = data.total_proveedores || 0;

    const s = data.sistemas || {};
    document.getElementById('stat-sis1-deuda').textContent = formatMoney(parseFloat(s.sis1_deuda || 0));
    document.getElementById('stat-sis1-favor').textContent = formatMoney(parseFloat(s.sis1_favor || 0));
    document.getElementById('stat-sis2-deuda').textContent = formatMoney(parseFloat(s.sis2_deuda || 0));
    document.getElementById('stat-sis2-favor').textContent = formatMoney(parseFloat(s.sis2_favor || 0));
}

function renderBalance(data) {
    const d = parseFloat(data.deuda || 0);
    const f = parseFloat(data.favor || 0);
    const hasData = d > 0 || f > 0;

    document.getElementById('chart-balance-empty').style.display = hasData ? 'none' : 'block';
    document.getElementById('chart-balance').style.display        = hasData ? 'block' : 'none';
    if (!hasData) return;

    if (chartBalance) chartBalance.destroy();
    chartBalance = new Chart(document.getElementById('chart-balance'), {
        type: 'doughnut',
        data: {
            labels: ['A pagar (deuda)', 'A favor (crédito)'],
            datasets: [{
                data: [d, f],
                backgroundColor: ['rgba(239,68,68,.75)', 'rgba(16,185,129,.75)'],
                borderColor:     ['#ef4444', '#10b981'],
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

function renderSistemas(s) {
    if (!s) return;
    const d1 = parseFloat(s.sis1_deuda || 0);
    const d2 = parseFloat(s.sis2_deuda || 0);
    const hasData = d1 > 0 || d2 > 0;

    document.getElementById('chart-sistemas-empty').style.display = hasData ? 'none' : 'block';
    document.getElementById('chart-sistemas').style.display        = hasData ? 'block' : 'none';
    if (!hasData) return;

    if (chartSistemas) chartSistemas.destroy();
    chartSistemas = new Chart(document.getElementById('chart-sistemas'), {
        type: 'doughnut',
        data: {
            labels: ['Sistema 1 (blanco)', 'Sistema 2 (negro)'],
            datasets: [{
                data: [d1, d2],
                backgroundColor: ['rgba(37,99,235,.75)', 'rgba(239,68,68,.75)'],
                borderColor: ['#2563eb', '#ef4444'],
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

function renderRangos(rangos) {
    const noData = !rangos?.length || rangos.every(r => r.cant === 0);

    document.getElementById('chart-rangos-cant-empty').style.display = noData ? 'block' : 'none';
    document.getElementById('chart-rangos-cant').style.display        = noData ? 'none'  : 'block';
    document.getElementById('chart-rangos-monto-empty').style.display = noData ? 'block' : 'none';
    document.getElementById('chart-rangos-monto').style.display        = noData ? 'none'  : 'block';
    if (noData) return;

    const labels   = rangos.map(r => r.label);
    const moneyFmt = v => '$' + Intl.NumberFormat('es-AR', { notation:'compact', maximumFractionDigits:1 }).format(v);

    if (chartRangosCant) chartRangosCant.destroy();
    chartRangosCant = new Chart(document.getElementById('chart-rangos-cant'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{ label:'Proveedores', data: rangos.map(r => r.cant),
                backgroundColor:'rgba(239,68,68,.75)', borderColor:'#ef4444', borderWidth:1, borderRadius:6 }]
        },
        options: { responsive:true, plugins:{ legend:{display:false}, tooltip:{callbacks:{label:ctx=>' '+ctx.parsed.y+' proveedores'}} },
            scales:{ x:{ticks:{color:'#4a5f7a',font:{size:11}},grid:{color:'#1e2d45'}}, y:{ticks:{color:'#4a5f7a',stepSize:1},grid:{color:'#1e2d45'}} } }
    });

    if (chartRangosMonto) chartRangosMonto.destroy();
    chartRangosMonto = new Chart(document.getElementById('chart-rangos-monto'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{ label:'Monto', data: rangos.map(r => parseFloat(r.monto || 0)),
                backgroundColor:'rgba(245,158,11,.75)', borderColor:'#f59e0b', borderWidth:1, borderRadius:6 }]
        },
        options: { responsive:true, plugins:{ legend:{display:false}, tooltip:{callbacks:{label:ctx=>' '+formatMoney(ctx.parsed.y)}} },
            scales:{ x:{ticks:{color:'#4a5f7a',font:{size:11}},grid:{color:'#1e2d45'}}, y:{ticks:{color:'#4a5f7a',callback:moneyFmt},grid:{color:'#1e2d45'}} } }
    });
}

function renderLotes(lotes) {
    const tbody = document.getElementById('lotes-body');
    if (!lotes?.length) {
        tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">Sin carteras cargadas — <a href="/proveedores/procesar.php" style="color:var(--accent-light)">Subir Excel</a></div>
        </div></td></tr>`;
        return;
    }
    tbody.innerHTML = lotes.map(l => `<tr>
        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${l.archivo_nombre}</td>
        <td class="mono">${l.total_proveedores}</td>
        <td class="mono red" style="text-align:right">-${formatMoney(l.total_deuda)}</td>
        <td class="mono green" style="text-align:right">${formatMoney(l.total_favor)}</td>
        <td class="mono" style="font-size:11px">${new Date(l.created_at).toLocaleDateString('es-AR')}</td>
        <td><button class="btn btn-danger btn-sm" onclick="eliminarLote(${l.id})">Eliminar</button></td>
    </tr>`).join('');
}

document.getElementById('btn-confirmar-reset')?.addEventListener('click', async () => {
    const btn = document.getElementById('btn-confirmar-reset');
    btn.disabled = true; btn.textContent = 'Eliminando...';
    const res  = await fetch('/proveedores/api/cartera.php?action=eliminar_todo', { method:'POST' });
    const data = await res.json();
    closeModal('modal-reset');
    btn.disabled = false; btn.textContent = 'Sí, eliminar todo';
    if (data.success) { toast('Todos los datos eliminados', 'success'); loadDashboard(); }
    else toast(data.error || 'Error', 'error');
});

async function eliminarLote(id) {
    confirmAction('¿Eliminar esta cartera?', async () => {
        const res  = await fetch('/proveedores/api/cartera.php?action=eliminar_lote', {
            method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id}),
        });
        const data = await res.json();
        if (data.error) { toast(data.error, 'error'); return; }
        toast('Cartera eliminada', 'success');
        loadDashboard();
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
