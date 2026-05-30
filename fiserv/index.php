<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Dashboard Fiserv';
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
            <button class="btn btn-primary" id="btn-filtrar"><span>◎</span> Aplicar</button>
            <button class="btn btn-secondary" id="btn-reset">Resetear</button>
            <a href="/fiserv/procesar.php" class="btn btn-success">⬆ Subir PDF</a>
            <button class="btn btn-danger" onclick="openModal('modal-reset-fiserv')">⚠ Eliminar todo</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-reset-fiserv">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--red)">⚠ Eliminar todos los datos de Fiserv</div>
            <button class="modal-close">✕</button>
        </div>
        <p style="color:var(--sub);font-size:14px;margin-bottom:16px">
            Se van a eliminar <strong style="color:var(--text)">todas las liquidaciones y lotes</strong> importados.
        </p>
        <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);border-radius:8px;padding:12px 16px;margin-bottom:24px;font-size:13px;color:var(--sub)">
            Esta acción <strong style="color:var(--red)">no se puede deshacer</strong>.
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end">
            <button class="btn btn-secondary" onclick="closeModal('modal-reset-fiserv')">Cancelar</button>
            <button class="btn btn-danger" id="btn-confirmar-reset-fiserv">Sí, eliminar todo</button>
        </div>
    </div>
</div>

<!-- Stats cards -->
<div class="stats-grid">
    <div class="stat-card green">
        <div class="stat-label">Total Ventas</div>
        <div class="stat-value green" id="stat-ventas">—</div>
        <div class="stat-sub" id="stat-cant">— liquidaciones</div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">Total Descuentos</div>
        <div class="stat-value red" id="stat-descuentos">—</div>
        <div class="stat-sub">Retenciones y aranceles</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Total Acreditado</div>
        <div class="stat-value" id="stat-acreditado">—</div>
        <div class="stat-sub">Lo que llega a la empresa</div>
    </div>
    <div class="stat-card amber">
        <div class="stat-label">% Descuento</div>
        <div class="stat-value" id="stat-pct">—</div>
        <div class="stat-sub">Sobre ventas totales</div>
    </div>
</div>

<!-- Gráficos -->
<div class="grid-2 mb-24">
    <div class="card">
        <div class="card-title">Evolución mensual</div>
        <div id="chart-mensual-empty" class="empty-state" style="padding:32px;display:none">
            <div class="empty-state-icon">◈</div><div class="empty-state-text">Sin datos para el período</div>
        </div>
        <canvas id="chart-mensual" height="260"></canvas>
    </div>
    <div class="card">
        <div class="card-title">Desglose de descuentos</div>
        <div id="chart-dsctos-empty" class="empty-state" style="padding:32px;display:none">
            <div class="empty-state-icon">◈</div><div class="empty-state-text">Sin datos para el período</div>
        </div>
        <canvas id="chart-dsctos" height="260"></canvas>
    </div>
</div>

<!-- Últimos lotes -->
<div class="card">
    <div class="flex-between mb-16">
        <div class="card-title" style="margin:0">Últimas liquidaciones importadas</div>
        <a href="/fiserv/reportes.php" class="btn btn-secondary btn-sm">≡ Ver todas</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Código</th><th>Archivo</th><th>Tarjeta</th><th>Período</th>
                    <th>Liq.</th><th>Neto pagos</th><th>Fecha</th><th></th>
                </tr>
            </thead>
            <tbody id="lotes-body">
                <tr><td colspan="8"><div class="empty-state"><div class="spinner" style="margin:0 auto"></div></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
let chartMensual = null, chartDsctos = null;
let dashDesde = '', dashHasta = '';

// ── Helpers de mes ──────────────────────────────────────
function getMonthRange(range) {
    const now = new Date();
    const y = now.getFullYear(), m = now.getMonth() + 1;
    const pad = n => String(n).padStart(2, '0');
    const curr = `${y}-${pad(m)}`;
    switch (range) {
        case 'mes':     return { desde: curr, hasta: curr };
        case 'mes_ant': { const pm=m===1?12:m-1, py=m===1?y-1:y; const s=`${py}-${pad(pm)}`; return {desde:s,hasta:s}; }
        case 'trim':    { const pm3=m<=3?12+m-3:m-3, py3=m<=3?y-1:y; return {desde:`${py3}-${pad(pm3)}`,hasta:curr}; }
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
    dashDesde = monthStart(document.getElementById('dash-desde').value);
    dashHasta = monthEnd(document.getElementById('dash-hasta').value);
    const el = document.getElementById('topbar-period');
    if (el) {
        const fmt = m => { const [y,mo]=m.split('-').map(Number); return new Date(y,mo-1).toLocaleDateString('es-AR',{month:'long',year:'numeric'}); };
        const mD = document.getElementById('dash-desde').value;
        const mH = document.getElementById('dash-hasta').value;
        el.textContent = mD===mH ? fmt(mD) : fmt(mD)+' — '+fmt(mH);
    }
}

(function() {
    const r = getMonthRange('mes');
    document.getElementById('dash-desde').value = r.desde;
    document.getElementById('dash-hasta').value = r.hasta;
    applyMonths();
    loadDashboard();
})();

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

document.getElementById('btn-filtrar').addEventListener('click', () => {
    if (!document.getElementById('dash-desde').value || !document.getElementById('dash-hasta').value) {
        toast('Seleccioná un rango de meses', 'warning'); return;
    }
    applyMonths();
    loadDashboard();
});

document.getElementById('btn-reset').addEventListener('click', () => {
    document.querySelectorAll('.qrange-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('[data-range="mes"]')?.classList.add('active');
    const r = getMonthRange('mes');
    document.getElementById('dash-desde').value = r.desde;
    document.getElementById('dash-hasta').value = r.hasta;
    applyMonths();
    loadDashboard();
});

async function loadDashboard() {
    ['stat-ventas','stat-descuentos','stat-acreditado','stat-pct'].forEach(id =>
        document.getElementById(id)?.classList.add('skeleton'));

    try {
        const params = new URLSearchParams({ action:'dashboard', desde:dashDesde, hasta:dashHasta });
        const res    = await fetch('/fiserv/api/stats.php?' + params);
        const data   = await res.json();
        if (data.error) { toast(data.error, 'error'); return; }

        const t      = data.totales;
        const ventas = parseFloat(t.total_ventas     || 0);
        const dsctos = parseFloat(t.total_descuentos || 0);
        const acred  = parseFloat(t.total_acreditado || 0);
        const pct    = ventas > 0 ? (dsctos / ventas * 100).toFixed(1) : 0;

        ['stat-ventas','stat-descuentos','stat-acreditado','stat-pct'].forEach(id =>
            document.getElementById(id)?.classList.remove('skeleton'));

        document.getElementById('stat-ventas').textContent     = formatMoney(ventas);
        document.getElementById('stat-descuentos').textContent = formatMoney(dsctos);
        document.getElementById('stat-acreditado').textContent = formatMoney(acred);
        document.getElementById('stat-acreditado').className   = 'stat-value ' + (acred >= 0 ? 'green' : 'red');
        document.getElementById('stat-pct').textContent        = pct + '%';
        document.getElementById('stat-cant').textContent       = (t.cant_liquidaciones || 0) + ' liquidaciones';

        renderMensual(data.evolucion);
        renderDsctos(data.descuentos);
        renderLotes(data.lotes);
    } catch(e) {
        toast('Error al cargar el dashboard', 'error');
    }
}

function renderMensual(evolucion) {
    const hasData = evolucion?.length > 0;
    document.getElementById('chart-mensual-empty').style.display = hasData ? 'none' : 'block';
    document.getElementById('chart-mensual').style.display        = hasData ? 'block' : 'none';
    if (!hasData) return;

    if (chartMensual) chartMensual.destroy();
    const labels = evolucion.map(r => {
        const [y, m] = r.mes.split('-');
        return new Date(y, m-1).toLocaleDateString('es-AR', {month:'short', year:'2-digit'});
    });
    chartMensual = new Chart(document.getElementById('chart-mensual'), {
        type: 'bar',
        data: { labels, datasets: [
            { label:'Ventas',    data: evolucion.map(r => parseFloat(r.ventas    || 0)), backgroundColor:'rgba(16,185,129,0.75)', borderColor:'#10b981', borderWidth:1, borderRadius:4 },
            { label:'Acreditado',data: evolucion.map(r => parseFloat(r.acreditado|| 0)), backgroundColor:'rgba(37,99,235,0.75)',  borderColor:'#2563eb', borderWidth:1, borderRadius:4 },
        ]},
        options: {
            responsive: true,
            plugins: {
                legend: { labels: { color:'#7a90b0', font:{family:'Syne',size:11}, padding:12 } },
                tooltip: { callbacks: { label: ctx => ` ${ctx.dataset.label}: ${formatMoney(ctx.parsed.y)}` } }
            },
            scales: {
                x: { ticks:{color:'#4a5f7a',font:{size:10}}, grid:{color:'#1e2d45'} },
                y: { ticks:{color:'#4a5f7a', callback:v=>'$'+Intl.NumberFormat('es-AR',{notation:'compact'}).format(v)}, grid:{color:'#1e2d45'} }
            }
        }
    });
}

function renderDsctos(d) {
    const palette = ['#ef4444','#f97316','#f59e0b','#eab308','#84cc16','#10b981','#06b6d4','#2563eb','#8b5cf6','#ec4899','#6366f1','#a78bfa'];
    const entries = [
        ['Arancel',           parseFloat(d.arancel          || 0)],
        ['IVA s/Arancel',     parseFloat(d.iva_arancel      || 0)],
        ['Ret. IIBB SIRTAC',  parseFloat(d.ret_iibb_sirtac  || 0)],
        ['Per. B.A.I.',       parseFloat(d.per_bai_brdn     || 0)],
        ['Arancel Cuotas',    parseFloat(d.arancel_cuotas   || 0)],
        ['Promo Cuota Ahora', parseFloat(d.promo_cuota_ahora|| 0)],
        ['Dto Financ Cuotas', parseFloat(d.dto_financ_cuotas|| 0)],
        ['Perc IVA 1,5%',     parseFloat(d.perc_iva_1_5     || 0)],
        ['Perc IVA 3%',       parseFloat(d.perc_iva_3       || 0)],
        ['Cargo Terminal',    parseFloat(d.cargo_terminal   || 0)],
        ['QR Perc IVA',       parseFloat(d.qr_perc_iva      || 0)],
        ['QR Ret IIBB',       parseFloat(d.qr_ret_iibb      || 0)],
    ].filter(([,v]) => v > 0);

    const hasData = entries.length > 0;
    document.getElementById('chart-dsctos-empty').style.display = hasData ? 'none' : 'block';
    document.getElementById('chart-dsctos').style.display        = hasData ? 'block' : 'none';
    if (!hasData) return;

    if (chartDsctos) chartDsctos.destroy();
    chartDsctos = new Chart(document.getElementById('chart-dsctos'), {
        type: 'doughnut',
        data: { labels: entries.map(e=>e[0]), datasets:[{ data: entries.map(e=>e[1]), backgroundColor:palette.slice(0,entries.length), borderColor:'#0e1320', borderWidth:2, hoverOffset:8 }] },
        options: {
            responsive: true, cutout:'62%',
            plugins: {
                legend: { position:'bottom', labels:{color:'#7a90b0',font:{family:'Syne',size:11},padding:12,usePointStyle:true,pointStyleWidth:8} },
                tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${formatMoney(ctx.parsed)}` } }
            }
        }
    });
}

document.getElementById('btn-confirmar-reset-fiserv')?.addEventListener('click', async () => {
    const btn = document.getElementById('btn-confirmar-reset-fiserv');
    btn.disabled = true; btn.textContent = 'Eliminando...';
    const res  = await fetch('/fiserv/api/stats.php?action=eliminar_todo', { method: 'POST' });
    const data = await res.json();
    closeModal('modal-reset-fiserv');
    btn.disabled = false; btn.textContent = 'Sí, eliminar todo';
    if (data.success) { toast('Todos los datos de Fiserv eliminados', 'success'); loadDashboard(); }
    else toast(data.error || 'Error', 'error');
});

function renderLotes(lotes) {
    const tbody = document.getElementById('lotes-body');
    if (!lotes?.length) {
        tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">No hay liquidaciones importadas — <a href="/fiserv/procesar.php" style="color:var(--accent-light)">Subir PDF</a></div>
        </div></td></tr>`;
        return;
    }
    tbody.innerHTML = lotes.map(l => `<tr>
        <td class="mono" style="font-size:11px">${l.codigo}</td>
        <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${l.archivo_nombre||'—'}</td>
        <td><span class="badge badge-muted">${l.tarjeta||'—'}</span></td>
        <td>${l.periodo||'—'}</td>
        <td class="mono">${l.total_filas}</td>
        <td class="mono green">${formatMoney(l.neto_pagos)}</td>
        <td class="mono" style="font-size:11px">${new Date(l.created_at).toLocaleDateString('es-AR')}</td>
        <td><a href="/fiserv/reportes.php?lote=${l.id}" class="btn btn-secondary btn-sm">Ver</a></td>
    </tr>`).join('');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
