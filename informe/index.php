<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Informe General';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Filtro de período -->
<div class="filter-bar" style="margin-bottom:24px">
    <div class="filter-bar-header">
        <span class="filter-bar-title">Período del informe</span>
        <div class="quick-ranges">
            <button class="qrange-btn" data-range="mes">Este mes</button>
            <button class="qrange-btn" data-range="mes_ant">Mes ant.</button>
            <button class="qrange-btn" data-range="trim">Últ. 3 meses</button>
            <button class="qrange-btn active" data-range="anio">Este año</button>
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
            <button class="btn btn-success" id="btn-exportar" disabled>⬇ Exportar Excel</button>
        </div>
    </div>
    <div style="font-size:12px;color:var(--sub);margin-top:8px;padding:0 4px">
        * El período aplica a Bancos y Fiserv. Cobranza siempre muestra el último archivo cargado.
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- SECCIÓN 1: GESTIÓN BANCARIA                            -->
<!-- ═══════════════════════════════════════════════════════ -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
    <span style="font-size:20px">🏦</span>
    <h2 style="font-size:16px;font-weight:700;color:var(--text);margin:0">Gestión Bancaria</h2>
    <div style="flex:1;height:1px;background:var(--border)"></div>
    <a href="/bancos/index.php" style="font-size:12px;color:var(--accent-light);text-decoration:none">Ver módulo →</a>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:0" id="bancos-cards">
    <div class="stat-card green"><div class="stat-label">Ingresos</div><div class="stat-value green skeleton" id="b-ingresos">—</div><div class="stat-sub" id="b-mov">—</div></div>
    <div class="stat-card red"><div class="stat-label">Gastos</div><div class="stat-value red skeleton" id="b-gastos">—</div><div class="stat-sub">del período</div></div>
    <div class="stat-card blue"><div class="stat-label">Balance neto</div><div class="stat-value skeleton" id="b-balance">—</div><div class="stat-sub">Ingresos − Gastos</div></div>
    <div class="stat-card amber"><div class="stat-label">Movimientos</div><div class="stat-value skeleton" id="b-cant">—</div><div class="stat-sub">registros</div></div>
</div>

<div class="grid-2 mb-24" style="margin-top:16px">
    <div class="card">
        <div class="card-title">Ingresos vs Gastos — evolución mensual</div>
        <div id="chart-bancos-empty" class="empty-state" style="padding:32px;display:none">
            <div class="empty-state-icon">◈</div><div class="empty-state-text">Sin movimientos en el período</div>
        </div>
        <canvas id="chart-bancos" height="220"></canvas>
    </div>
    <div class="card">
        <div class="card-title">Top gastos por categoría</div>
        <div id="chart-cats-empty" class="empty-state" style="padding:32px;display:none">
            <div class="empty-state-icon">◈</div><div class="empty-state-text">Sin datos</div>
        </div>
        <canvas id="chart-cats" height="220"></canvas>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- SECCIÓN 2: FISERV                                      -->
<!-- ═══════════════════════════════════════════════════════ -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
    <span style="font-size:20px">💳</span>
    <h2 style="font-size:16px;font-weight:700;color:var(--text);margin:0">Fiserv — Tarjetas de Crédito</h2>
    <div style="flex:1;height:1px;background:var(--border)"></div>
    <a href="/fiserv/index.php" style="font-size:12px;color:var(--accent-light);text-decoration:none">Ver módulo →</a>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:0">
    <div class="stat-card green"><div class="stat-label">Ventas totales</div><div class="stat-value green skeleton" id="f-ventas">—</div><div class="stat-sub" id="f-liq">—</div></div>
    <div class="stat-card red"><div class="stat-label">Total descuentos</div><div class="stat-value red skeleton" id="f-descuentos">—</div><div class="stat-sub">Retenciones y aranceles</div></div>
    <div class="stat-card blue"><div class="stat-label">Acreditado neto</div><div class="stat-value skeleton" id="f-acreditado">—</div><div class="stat-sub">Lo que llega a la empresa</div></div>
    <div class="stat-card amber"><div class="stat-label">% Descuento</div><div class="stat-value skeleton" id="f-pct">—</div><div class="stat-sub">Sobre ventas</div></div>
</div>

<div class="card mb-24" style="margin-top:16px">
    <div class="card-title">Acreditado mensual — Fiserv</div>
    <div id="chart-fiserv-empty" class="empty-state" style="padding:32px;display:none">
        <div class="empty-state-icon">◈</div><div class="empty-state-text">Sin liquidaciones en el período</div>
    </div>
    <canvas id="chart-fiserv" height="180"></canvas>
</div>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- SECCIÓN 3: COBRANZA                                    -->
<!-- ═══════════════════════════════════════════════════════ -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
    <span style="font-size:20px">📞</span>
    <h2 style="font-size:16px;font-weight:700;color:var(--text);margin:0">Cobranza</h2>
    <div style="flex:1;height:1px;background:var(--border)"></div>
    <span id="cob-lote-info" style="font-size:12px;color:var(--sub)"></span>
    <a href="/cobranza/index.php" style="font-size:12px;color:var(--accent-light);text-decoration:none;margin-left:12px">Ver módulo →</a>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:0" id="cob-no-data" style="display:none">
    <div class="stat-card green"><div class="stat-label">Total deuda activa</div><div class="stat-value green skeleton" id="c-total">—</div><div class="stat-sub" id="c-cant">—</div></div>
    <div class="stat-card" style="border-color:rgba(239,68,68,.3)"><div class="stat-label">Urgencia</div><div class="stat-value red" id="c-urgencia">—</div><div class="stat-sub">clientes</div></div>
    <div class="stat-card" style="border-color:rgba(249,115,22,.3)"><div class="stat-label">Llamar</div><div class="stat-value" style="color:#f97316" id="c-llamar">—</div><div class="stat-sub">clientes</div></div>
    <div class="stat-card amber"><div class="stat-label">Cobrar al final</div><div class="stat-value amber" id="c-final">—</div><div class="stat-sub">clientes</div></div>
</div>

<div class="grid-2 mb-24" style="margin-top:16px" id="cob-charts">
    <div class="card">
        <div class="card-title">Deuda por antigüedad</div>
        <canvas id="chart-cob-aging" height="220"></canvas>
    </div>
    <div class="card">
        <div class="card-title">Prioridades de llamado</div>
        <canvas id="chart-cob-pri" height="220"></canvas>
    </div>
</div>

<div id="cob-sin-datos" style="display:none" class="card mb-24">
    <div class="empty-state" style="padding:40px">
        <div class="empty-state-icon">📞</div>
        <div class="empty-state-text">No hay cartera cargada — <a href="/cobranza/procesar.php" style="color:var(--accent-light)">Subir Excel</a></div>
    </div>
</div>

<script>
// ──────────────────────────────────────────────────────────
// Estado y variables de gráficos
// ──────────────────────────────────────────────────────────
let informeData = null;
let chartBancos = null, chartCats = null, chartFiserv = null;
let chartCobAging = null, chartCobPri = null;
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

// ── Init ────────────────────────────────────────────────
window.addEventListener('load', () => {
    const r = getMonthRange('anio');
    document.getElementById('dash-desde').value = r.desde;
    document.getElementById('dash-hasta').value = r.hasta;
    applyMonths();
    loadInforme();
});

document.querySelectorAll('.qrange-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.qrange-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const r = getMonthRange(btn.dataset.range);
        document.getElementById('dash-desde').value = r.desde;
        document.getElementById('dash-hasta').value = r.hasta;
        applyMonths();
        loadInforme();
    });
});

document.getElementById('btn-filtrar').addEventListener('click', () => {
    if (!document.getElementById('dash-desde').value || !document.getElementById('dash-hasta').value) {
        toast('Seleccioná un rango de meses', 'warning'); return;
    }
    applyMonths();
    loadInforme();
});

document.getElementById('btn-reset').addEventListener('click', () => {
    document.querySelectorAll('.qrange-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('[data-range="anio"]')?.classList.add('active');
    const r = getMonthRange('anio');
    document.getElementById('dash-desde').value = r.desde;
    document.getElementById('dash-hasta').value = r.hasta;
    applyMonths();
    loadInforme();
});

// ──────────────────────────────────────────────────────────
// Carga de datos
// ──────────────────────────────────────────────────────────
async function loadInforme() {
    document.getElementById('btn-exportar').disabled = true;
    ['b-ingresos','b-gastos','b-balance','b-cant','f-ventas','f-descuentos','f-acreditado','f-pct','c-total'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.add('skeleton');
    });

    try {
        const params = new URLSearchParams({ action: 'resumen', desde: dashDesde, hasta: dashHasta });
        const res  = await fetch('/informe/api/stats.php?' + params);
        const data = await res.json();
        if (data.error) { toast(data.error, 'error'); return; }

        informeData = data;
        renderBancos(data.bancos);
        renderFiserv(data.fiserv);
        renderCobranza(data.cobranza);
        document.getElementById('btn-exportar').disabled = false;
    } catch(e) {
        toast('Error al cargar el informe', 'error');
        console.error(e);
    }
}

// ──────────────────────────────────────────────────────────
// Bancos
// ──────────────────────────────────────────────────────────
function renderBancos(b) {
    if (!b || b.error) {
        ['b-ingresos','b-gastos','b-balance','b-cant'].forEach(id => {
            const el = document.getElementById(id);
            if (el) { el.textContent = b?.error ? 'Error' : '—'; el.classList.remove('skeleton'); }
        });
        return;
    }

    const setVal = (id, val, cls) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = val;
        el.classList.remove('skeleton');
        if (cls) el.className = 'stat-value ' + cls;
    };

    setVal('b-ingresos', formatMoney(b.ingresos), 'green');
    setVal('b-gastos',   formatMoney(b.gastos),   'red');
    setVal('b-balance',  formatMoney(b.balance),  b.balance >= 0 ? 'green' : 'red');
    setVal('b-cant',     b.cant_movimientos);
    const movEl = document.getElementById('b-mov');
    if (movEl) movEl.textContent = b.cant_movimientos + ' movimientos';

    // Gráfico evolución
    const ev = b.evolucion || [];
    document.getElementById('chart-bancos-empty').style.display = ev.length ? 'none' : 'block';
    document.getElementById('chart-bancos').style.display        = ev.length ? 'block' : 'none';
    if (ev.length) {
        if (chartBancos) chartBancos.destroy();
        chartBancos = new Chart(document.getElementById('chart-bancos'), {
            type: 'bar',
            data: {
                labels: ev.map(r => fmtMes(r.mes)),
                datasets: [
                    { label:'Ingresos', data: ev.map(r => parseFloat(r.ingresos||0)), backgroundColor:'rgba(16,185,129,.7)', borderColor:'#10b981', borderWidth:1, borderRadius:4 },
                    { label:'Gastos',   data: ev.map(r => parseFloat(r.gastos||0)),   backgroundColor:'rgba(239,68,68,.65)',  borderColor:'#ef4444', borderWidth:1, borderRadius:4 },
                ]
            },
            options: chartBarOpts(v => formatMoney(v))
        });
    }

    // Gráfico categorías
    const cats = b.top_categorias || [];
    document.getElementById('chart-cats-empty').style.display = cats.length ? 'none' : 'block';
    document.getElementById('chart-cats').style.display        = cats.length ? 'block' : 'none';
    if (cats.length) {
        const palette = ['#ef4444','#f97316','#f59e0b','#eab308','#84cc16','#10b981'];
        if (chartCats) chartCats.destroy();
        chartCats = new Chart(document.getElementById('chart-cats'), {
            type: 'doughnut',
            data: {
                labels: cats.map(c => c.nombre || 'Sin categoría'),
                datasets: [{ data: cats.map(c => parseFloat(c.total||0)), backgroundColor: palette, borderColor:'#0e1320', borderWidth:2, hoverOffset:8 }]
            },
            options: {
                responsive: true, cutout: '58%',
                plugins: {
                    legend: { position:'bottom', labels:{ color:'#7a90b0', font:{family:'Syne',size:10}, padding:10, usePointStyle:true, pointStyleWidth:7 } },
                    tooltip: { callbacks: { label: ctx => ' ' + ctx.label + ': ' + formatMoney(ctx.parsed) } }
                }
            }
        });
    }
}

// ──────────────────────────────────────────────────────────
// Fiserv
// ──────────────────────────────────────────────────────────
function renderFiserv(f) {
    if (!f || f.error) {
        ['f-ventas','f-descuentos','f-acreditado','f-pct'].forEach(id => {
            const el = document.getElementById(id);
            if (el) { el.textContent = f?.error ? 'Error' : '—'; el.classList.remove('skeleton'); }
        });
        return;
    }

    const setVal = (id, val, cls) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = val;
        el.classList.remove('skeleton');
        if (cls) el.className = 'stat-value ' + cls;
    };

    const pct = f.ventas > 0 ? (f.descuentos / f.ventas * 100).toFixed(1) + '%' : '0%';
    setVal('f-ventas',     formatMoney(f.ventas),     'green');
    setVal('f-descuentos', formatMoney(f.descuentos), 'red');
    setVal('f-acreditado', formatMoney(f.acreditado), f.acreditado >= 0 ? '' : 'red');
    setVal('f-pct',        pct);
    const liqEl = document.getElementById('f-liq');
    if (liqEl) liqEl.textContent = f.cant_liquidaciones + ' liquidaciones';

    // Gráfico evolución fiserv
    const ev = f.evolucion || [];
    document.getElementById('chart-fiserv-empty').style.display = ev.length ? 'none' : 'block';
    document.getElementById('chart-fiserv').style.display        = ev.length ? 'block' : 'none';
    if (ev.length) {
        if (chartFiserv) chartFiserv.destroy();
        chartFiserv = new Chart(document.getElementById('chart-fiserv'), {
            type: 'bar',
            data: {
                labels: ev.map(r => fmtMes(r.mes)),
                datasets: [
                    { label:'Ventas',     data: ev.map(r => parseFloat(r.ventas||0)),     backgroundColor:'rgba(16,185,129,.5)',  borderColor:'#10b981', borderWidth:1, borderRadius:4 },
                    { label:'Descuentos', data: ev.map(r => parseFloat(r.descuentos||0)), backgroundColor:'rgba(239,68,68,.5)',   borderColor:'#ef4444', borderWidth:1, borderRadius:4 },
                    { label:'Acreditado', data: ev.map(r => parseFloat(r.acreditado||0)), backgroundColor:'rgba(37,99,235,.75)',  borderColor:'#2563eb', borderWidth:1, borderRadius:4 },
                ]
            },
            options: chartBarOpts(v => formatMoney(v))
        });
    }
}

// ──────────────────────────────────────────────────────────
// Cobranza
// ──────────────────────────────────────────────────────────
function renderCobranza(c) {
    const sinDatos = document.getElementById('cob-sin-datos');
    const cobCharts = document.getElementById('cob-charts');

    if (!c) {
        sinDatos.style.display = 'block';
        cobCharts.style.display = 'none';
        return;
    }

    sinDatos.style.display = 'none';
    cobCharts.style.display = 'grid';

    const loteInfo = document.getElementById('cob-lote-info');
    if (loteInfo) {
        const fecha = new Date(c.lote_fecha).toLocaleDateString('es-AR');
        loteInfo.textContent = `Último archivo: ${c.lote_nombre} (${fecha})`;
    }

    const setVal = (id, val, cls) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = val;
        el.classList.remove('skeleton');
        if (cls) el.className = 'stat-value ' + cls;
    };

    setVal('c-total',    formatMoney(c.total_deuda), 'green');
    setVal('c-urgencia', c.prioridades['URGENCIA']       || 0);
    setVal('c-llamar',   c.prioridades['LLAMAR']         || 0);
    setVal('c-final',    c.prioridades['COBRAR AL FINAL']|| 0);
    const cantEl = document.getElementById('c-cant');
    if (cantEl) cantEl.textContent = c.cant_deudores + ' clientes con saldo positivo';

    // Gráfico aging cobranza
    const agingLabels = ['1–30 días','31–60 días','61–90 días','91–120 días','Más de 120'];
    const agingVals   = [c.aging.d30, c.aging.d60, c.aging.d90, c.aging.d120, c.aging.d120plus];
    if (chartCobAging) chartCobAging.destroy();
    chartCobAging = new Chart(document.getElementById('chart-cob-aging'), {
        type: 'bar',
        data: {
            labels: agingLabels,
            datasets: [{
                label: 'Deuda',
                data: agingVals,
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
                x: { ticks:{ color:'#4a5f7a', font:{size:10} }, grid:{ color:'#1e2d45' } },
                y: { ticks:{ color:'#4a5f7a', callback: v => '$'+Intl.NumberFormat('es-AR',{notation:'compact'}).format(v) }, grid:{ color:'#1e2d45' } }
            }
        }
    });

    // Gráfico prioridades
    const pri = c.prioridades;
    if (chartCobPri) chartCobPri.destroy();
    chartCobPri = new Chart(document.getElementById('chart-cob-pri'), {
        type: 'doughnut',
        data: {
            labels: ['Urgencia','Llamar','Cobrar al final','Sin acción'],
            datasets: [{
                data: [pri['URGENCIA']||0, pri['LLAMAR']||0, pri['COBRAR AL FINAL']||0, pri['SIN ACCIÓN']||0],
                backgroundColor: ['rgba(239,68,68,.8)','rgba(249,115,22,.8)','rgba(245,158,11,.8)','rgba(74,95,122,.6)'],
                borderColor: ['#ef4444','#f97316','#f59e0b','#4a5f7a'],
                borderWidth: 2, hoverOffset: 8,
            }]
        },
        options: {
            responsive: true, cutout: '58%',
            plugins: {
                legend: { position:'bottom', labels:{ color:'#7a90b0', font:{family:'Syne',size:11}, padding:12, usePointStyle:true, pointStyleWidth:8 } },
                tooltip: { callbacks: { label: ctx => ' ' + ctx.label + ': ' + ctx.parsed + ' clientes' } }
            }
        }
    });
}

// ──────────────────────────────────────────────────────────
// Exportar Excel
// ──────────────────────────────────────────────────────────
document.getElementById('btn-exportar').addEventListener('click', () => {
    if (!informeData) return;
    exportarExcel(informeData);
});

function exportarExcel(data) {
    const wb = XLSX.utils.book_new();
    const fmt = m => { const [y,mo]=m.split('-').map(Number); return new Date(y,mo-1).toLocaleDateString('es-AR',{month:'long',year:'numeric'}); };
    const mD = document.getElementById('dash-desde').value;
    const mH = document.getElementById('dash-hasta').value;
    const periodo = mD===mH ? fmt(mD) : fmt(mD)+' al '+fmt(mH);

    // ── Hoja 1: Resumen ──────────────────────────────────
    const sheetResumen = [];
    sheetResumen.push(['INFORME GENERAL SMARTADMIN', '', '', '']);
    sheetResumen.push([`Período: ${periodo}`, '', '', '']);
    sheetResumen.push(['', '', '', '']);

    sheetResumen.push(['GESTIÓN BANCARIA', '', '', '']);
    sheetResumen.push(['Ingresos', data.bancos?.ingresos || 0, '', '']);
    sheetResumen.push(['Gastos',   data.bancos?.gastos   || 0, '', '']);
    sheetResumen.push(['Balance',  data.bancos?.balance  || 0, '', '']);
    sheetResumen.push(['Movimientos', data.bancos?.cant_movimientos || 0, '', '']);
    sheetResumen.push(['', '', '', '']);

    sheetResumen.push(['FISERV', '', '', '']);
    sheetResumen.push(['Ventas totales', data.fiserv?.ventas      || 0, '', '']);
    sheetResumen.push(['Descuentos',     data.fiserv?.descuentos  || 0, '', '']);
    sheetResumen.push(['Acreditado',     data.fiserv?.acreditado  || 0, '', '']);
    sheetResumen.push(['Liquidaciones',  data.fiserv?.cant_liquidaciones || 0, '', '']);
    sheetResumen.push(['', '', '', '']);

    if (data.cobranza) {
        sheetResumen.push(['COBRANZA', '', '', '']);
        sheetResumen.push(['Total deuda activa', data.cobranza.total_deuda || 0, '', '']);
        sheetResumen.push(['Clientes deudores',  data.cobranza.cant_deudores || 0, '', '']);
        sheetResumen.push(['Urgencia',           data.cobranza.prioridades?.URGENCIA || 0, '', '']);
        sheetResumen.push(['Llamar',             data.cobranza.prioridades?.LLAMAR || 0, '', '']);
        sheetResumen.push(['Cobrar al final',    data.cobranza.prioridades?.['COBRAR AL FINAL'] || 0, '', '']);
        sheetResumen.push(['', '', '', '']);
        sheetResumen.push(['Deuda 1–30 días',    data.cobranza.aging?.d30     || 0, '', '']);
        sheetResumen.push(['Deuda 31–60 días',   data.cobranza.aging?.d60     || 0, '', '']);
        sheetResumen.push(['Deuda 61–90 días',   data.cobranza.aging?.d90     || 0, '', '']);
        sheetResumen.push(['Deuda 91–120 días',  data.cobranza.aging?.d120    || 0, '', '']);
        sheetResumen.push(['Deuda +120 días',    data.cobranza.aging?.d120plus|| 0, '', '']);
    }

    const wsResumen = XLSX.utils.aoa_to_sheet(sheetResumen);
    wsResumen['!cols'] = [{ wch: 30 }, { wch: 20 }, { wch: 5 }, { wch: 5 }];
    XLSX.utils.book_append_sheet(wb, wsResumen, 'Resumen');

    // ── Hoja 2: Bancos evolución ──────────────────────────
    if (data.bancos?.evolucion?.length) {
        const rows = [['Mes', 'Ingresos', 'Gastos']];
        data.bancos.evolucion.forEach(r => rows.push([r.mes, parseFloat(r.ingresos||0), parseFloat(r.gastos||0)]));
        const ws = XLSX.utils.aoa_to_sheet(rows);
        ws['!cols'] = [{ wch: 12 }, { wch: 18 }, { wch: 18 }];
        XLSX.utils.book_append_sheet(wb, ws, 'Bancos — Evolución');
    }

    // ── Hoja 3: Fiserv evolución ──────────────────────────
    if (data.fiserv?.evolucion?.length) {
        const rows = [['Mes', 'Ventas', 'Descuentos', 'Acreditado']];
        data.fiserv.evolucion.forEach(r => rows.push([r.mes, parseFloat(r.ventas||0), parseFloat(r.descuentos||0), parseFloat(r.acreditado||0)]));
        const ws = XLSX.utils.aoa_to_sheet(rows);
        ws['!cols'] = [{ wch: 12 }, { wch: 18 }, { wch: 18 }, { wch: 18 }];
        XLSX.utils.book_append_sheet(wb, ws, 'Fiserv — Evolución');
    }

    // ── Hoja 4: Top categorías bancos ────────────────────
    if (data.bancos?.top_categorias?.length) {
        const rows = [['Categoría', 'Total gastos']];
        data.bancos.top_categorias.forEach(c => rows.push([c.nombre || 'Sin categoría', parseFloat(c.total||0)]));
        const ws = XLSX.utils.aoa_to_sheet(rows);
        ws['!cols'] = [{ wch: 30 }, { wch: 18 }];
        XLSX.utils.book_append_sheet(wb, ws, 'Bancos — Categorías');
    }

    const fname = `Informe_SmartAdmin_${periodo.replace(/ /g,'_').replace(/\//g,'-')}.xlsx`;
    XLSX.writeFile(wb, fname);
    toast('Excel exportado', 'success');
}

// ──────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────
function fmtMes(mes) {
    if (!mes) return '';
    const [y, m] = mes.split('-');
    return new Date(y, m-1).toLocaleDateString('es-AR', { month:'short', year:'2-digit' });
}

function chartBarOpts(tooltipFmt) {
    return {
        responsive: true,
        plugins: {
            legend: { labels:{ color:'#7a90b0', font:{family:'Syne',size:11}, padding:12 } },
            tooltip: { callbacks: { label: ctx => ' ' + ctx.dataset.label + ': ' + tooltipFmt(ctx.parsed.y) } }
        },
        scales: {
            x: { ticks:{ color:'#4a5f7a', font:{size:10} }, grid:{ color:'#1e2d45' } },
            y: { ticks:{ color:'#4a5f7a', callback: v => '$'+Intl.NumberFormat('es-AR',{notation:'compact'}).format(v) }, grid:{ color:'#1e2d45' } }
        }
    };
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
