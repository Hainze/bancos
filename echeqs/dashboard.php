<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Dashboard Echeqs';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Stats cards -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr)" id="stats-cards">
    <div class="stat-card" style="border-color:rgba(139,92,246,.3)">
        <div class="stat-label">Último lote</div>
        <div class="stat-value" style="color:#a78bfa" id="stat-total">—</div>
        <div class="stat-sub" id="sub-total">—</div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">Vencidos</div>
        <div class="stat-value red" id="stat-vencidos">—</div>
        <div class="stat-sub">atención inmediata</div>
    </div>
    <div class="stat-card amber">
        <div class="stat-label">Endosar ya</div>
        <div class="stat-value amber" id="stat-endosar">—</div>
        <div class="stat-sub">vencen en 7 días</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Pendientes</div>
        <div class="stat-value" style="color:var(--accent-light)" id="stat-pendientes">—</div>
        <div class="stat-sub">más de 7 días</div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Endosados/Dep.</div>
        <div class="stat-value green" id="stat-finalizados">—</div>
        <div class="stat-sub">finalizados</div>
    </div>
</div>

<!-- Gráfico + historial -->
<div class="grid-2 mb-24">
    <div class="card">
        <div class="card-title">Distribución — último lote</div>
        <div id="chart-empty" class="empty-state" style="padding:40px;display:none">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">Sin datos — <a href="/echeqs/index.php" style="color:#a78bfa">Analizar echeqs</a></div>
        </div>
        <canvas id="chart-dist" height="240"></canvas>
    </div>

    <div class="card">
        <div class="flex-between mb-16">
            <div class="card-title" style="margin:0">Historial de análisis</div>
            <div style="display:flex;gap:8px">
                <a href="/echeqs/index.php" class="btn btn-primary btn-sm">⬆ Analizar Excel</a>
                <button class="btn btn-danger btn-sm" onclick="openModal('modal-reset')">⚠ Limpiar</button>
            </div>
        </div>
        <div class="table-wrap">
            <table style="font-size:12px">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th style="text-align:right">Echeqs</th>
                        <th style="text-align:right">Monto</th>
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
</div>

<!-- Modal reset -->
<div class="modal-overlay" id="modal-reset">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--red)">⚠ Limpiar historial</div>
            <button class="modal-close">✕</button>
        </div>
        <p style="color:var(--sub);font-size:14px;margin-bottom:16px">
            Se va a eliminar todo el historial de análisis de echeqs.
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

<script>
let chartDist = null;

function waitForChart(cb) {
    if (typeof Chart !== 'undefined') { cb(); return; }
    const t = setInterval(() => { if (typeof Chart !== 'undefined') { clearInterval(t); cb(); } }, 50);
}

window.addEventListener('load', loadDashboard);

async function loadDashboard() {
    try {
        const res  = await fetch('/echeqs/api/lotes.php?action=dashboard');
        const data = await res.json();
        if (data.error) { toast(data.error, 'error'); return; }
        renderStats(data.ultimo);
        renderLotes(data.lotes);
        waitForChart(() => renderChart(data.ultimo));
    } catch(e) {
        toast('Error al cargar el dashboard', 'error');
        console.error(e);
    }
}

function renderStats(u) {
    const total      = u ? parseInt(u.total_echeqs || 0) : 0;
    const monto      = u ? parseFloat(u.total_monto || 0) : 0;
    const vencidos   = u ? parseInt(u.cant_vencidos || 0) : 0;
    const endosar    = u ? parseInt(u.cant_endosar_ya || 0) : 0;
    const pendientes = u ? (parseInt(u.cant_pendientes || 0) + parseInt(u.cant_proximos || 0)) : 0;
    const final      = u ? (parseInt(u.cant_endosados || 0) + parseInt(u.cant_depositados || 0)) : 0;

    document.getElementById('stat-total').textContent     = total || '—';
    document.getElementById('sub-total').textContent      = total ? formatMoney(monto) : 'Sin análisis';
    document.getElementById('stat-vencidos').textContent  = u ? vencidos : '—';
    document.getElementById('stat-endosar').textContent   = u ? endosar  : '—';
    document.getElementById('stat-pendientes').textContent= u ? pendientes : '—';
    document.getElementById('stat-finalizados').textContent = u ? final : '—';
}

function renderChart(u) {
    const canvas = document.getElementById('chart-dist');
    const empty  = document.getElementById('chart-empty');

    if (!u || parseInt(u.total_echeqs || 0) === 0) {
        empty.style.display  = 'block';
        canvas.style.display = 'none';
        return;
    }
    empty.style.display  = 'none';
    canvas.style.display = 'block';

    const labels = ['Vencidos', 'Endosar ya', 'Próximos', 'Pendientes', 'Endosados', 'Depositados', 'Rechazados', 'Sin fecha'];
    const values = [
        parseInt(u.cant_vencidos    || 0),
        parseInt(u.cant_endosar_ya  || 0),
        parseInt(u.cant_proximos    || 0),
        parseInt(u.cant_pendientes  || 0),
        parseInt(u.cant_endosados   || 0),
        parseInt(u.cant_depositados || 0),
        parseInt(u.cant_rechazados  || 0),
        parseInt(u.cant_sin_fecha   || 0),
    ];
    const colors = ['#ef4444','#f97316','#f59e0b','#3b82f6','#10b981','#059669','#4a5f7a','#8b5cf6'];

    if (chartDist) chartDist.destroy();
    chartDist = new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Echeqs',
                data: values,
                backgroundColor: colors.map(c => c + 'bf'),
                borderColor: colors,
                borderWidth: 1, borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ' ' + ctx.parsed.y + ' echeqs' } }
            },
            scales: {
                x: { ticks: { color:'#4a5f7a', font:{size:11} }, grid: { color:'#1e2d45' } },
                y: { ticks: { color:'#4a5f7a', stepSize:1 }, grid: { color:'#1e2d45' } }
            }
        }
    });
}

function renderLotes(lotes) {
    const tbody = document.getElementById('lotes-body');
    if (!lotes?.length) {
        tbody.innerHTML = `<tr><td colspan="5"><div class="empty-state" style="padding:40px">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">Sin análisis guardados — <a href="/echeqs/index.php" style="color:#a78bfa">Analizar un Excel</a></div>
        </div></td></tr>`;
        return;
    }
    tbody.innerHTML = lotes.map(l => `<tr>
        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(l.archivo_nombre)}">${escHtml(l.archivo_nombre)}</td>
        <td class="mono" style="text-align:right">${l.total_echeqs}</td>
        <td class="mono green" style="text-align:right">${formatMoney(parseFloat(l.total_monto))}</td>
        <td class="mono" style="font-size:11px">${new Date(l.created_at).toLocaleDateString('es-AR')}</td>
        <td><button class="btn btn-danger btn-sm" onclick="eliminarLote(${l.id})">Eliminar</button></td>
    </tr>`).join('');
}

document.getElementById('btn-confirmar-reset').addEventListener('click', async () => {
    const btn = document.getElementById('btn-confirmar-reset');
    btn.disabled = true; btn.textContent = 'Eliminando...';
    const res  = await fetch('/echeqs/api/lotes.php?action=eliminar_todo', { method: 'POST' });
    const data = await res.json();
    closeModal('modal-reset');
    btn.disabled = false; btn.textContent = 'Sí, eliminar todo';
    if (data.success) { toast('Historial eliminado', 'success'); loadDashboard(); }
    else toast(data.error || 'Error', 'error');
});

async function eliminarLote(id) {
    confirmAction('¿Eliminar este análisis del historial?', async () => {
        const res  = await fetch('/echeqs/api/lotes.php?action=eliminar_lote', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });
        const data = await res.json();
        if (data.error) { toast(data.error, 'error'); return; }
        toast('Análisis eliminado', 'success');
        loadDashboard();
    });
}

function escHtml(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
