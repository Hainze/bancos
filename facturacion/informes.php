<?php
require_once dirname(__DIR__) . '/auth/check.php';
if (empty($_SESSION['fact_cliente_id'])) {
    header('Location: /facturacion/index.php');
    exit;
}
$titulo        = 'Informes';
$clienteId     = (int)$_SESSION['fact_cliente_id'];
$clienteNombre = $_SESSION['fact_cliente_nombre'] ?? '';
require_once __DIR__ . '/includes/header.php';

$anioActual = (int)date('Y');
$mesActual  = (int)date('n');
$mesesNombres = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
?>

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:24px;border-bottom:1px solid var(--border);padding-bottom:0">
    <button class="tab-btn active" id="tab-mensual-btn" onclick="switchTab('mensual')">
        Informe Mensual
    </button>
    <button class="tab-btn" id="tab-rango-btn" onclick="switchTab('rango')">
        Informe por Rango
    </button>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: INFORME MENSUAL
════════════════════════════════════════════════════════════════════════ -->
<div id="tab-mensual">

    <!-- Filtros -->
    <div class="card mb-24">
        <div class="card-title">Filtros — Informe Mensual</div>
        <div style="font-size:13px;color:var(--text-secondary);margin-bottom:16px">
            Cliente: <strong style="color:var(--text-primary)"><?= htmlspecialchars($clienteNombre) ?></strong>
        </div>
        <div style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
            <div>
                <label class="form-label">Mes Contable</label>
                <select id="m-mes" class="form-input" style="width:140px">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m === $mesActual ? 'selected' : '' ?>><?= $mesesNombres[$m] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Año</label>
                <select id="m-anio" class="form-input" style="width:100px">
                    <?php for ($a = $anioActual; $a >= $anioActual - 5; $a--): ?>
                    <option value="<?= $a ?>" <?= $a === $anioActual ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Tipo</label>
                <select id="m-tipo" class="form-input" style="width:130px">
                    <option value="compras">Compras</option>
                    <option value="ventas">Ventas</option>
                </select>
            </div>
            <button class="btn btn-primary" id="btn-generar-mensual">Generar informe</button>
        </div>
    </div>

    <!-- Resultado mensual -->
    <div id="res-mensual" style="display:none">

        <!-- Totales top -->
        <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px" id="tot-cards-mensual"></div>

        <!-- Tabla de documentos -->
        <div class="card">
            <div class="card-title" id="tbl-mensual-titulo">Documentos</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Comprobante</th>
                            <th>Nombre</th>
                            <th style="text-align:right">Neto</th>
                            <th style="text-align:right">IVA</th>
                            <th style="text-align:right">Otros</th>
                            <th style="text-align:right">Total</th>
                        </tr>
                    </thead>
                    <tbody id="docs-body-mensual"></tbody>
                    <tfoot id="docs-tfoot-mensual"></tfoot>
                </table>
            </div>
        </div>
    </div>

    <div id="mensual-empty" class="empty-state" style="margin-top:40px">
        <div class="empty-state-icon">📊</div>
        <div class="empty-state-text">Seleccioná el período y hacé clic en "Generar informe"</div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TAB: INFORME POR RANGO
════════════════════════════════════════════════════════════════════════ -->
<div id="tab-rango" style="display:none">

    <!-- Filtros -->
    <div class="card mb-24">
        <div class="card-title">Filtros — Informe por Rango</div>
        <div style="font-size:13px;color:var(--text-secondary);margin-bottom:16px">
            Cliente: <strong style="color:var(--text-primary)"><?= htmlspecialchars($clienteNombre) ?></strong>
        </div>
        <div style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
            <div>
                <label class="form-label">Desde — Mes</label>
                <select id="r-mes-desde" class="form-input" style="width:140px">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m === 1 ? 'selected' : '' ?>><?= $mesesNombres[$m] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Desde — Año</label>
                <select id="r-anio-desde" class="form-input" style="width:100px">
                    <?php for ($a = $anioActual; $a >= $anioActual - 5; $a--): ?>
                    <option value="<?= $a ?>" <?= $a === $anioActual ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div style="color:var(--text-secondary);padding-bottom:8px;font-size:18px">→</div>
            <div>
                <label class="form-label">Hasta — Mes</label>
                <select id="r-mes-hasta" class="form-input" style="width:140px">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m === $mesActual ? 'selected' : '' ?>><?= $mesesNombres[$m] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Hasta — Año</label>
                <select id="r-anio-hasta" class="form-input" style="width:100px">
                    <?php for ($a = $anioActual; $a >= $anioActual - 5; $a--): ?>
                    <option value="<?= $a ?>" <?= $a === $anioActual ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button class="btn btn-primary" id="btn-generar-rango">Generar informe</button>
        </div>
    </div>

    <!-- Resultado rango -->
    <div id="res-rango" style="display:none">

        <!-- Tabla mensual compras+ventas -->
        <div class="card mb-24">
            <div class="card-title">Detalle mensual</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Período</th>
                            <th colspan="3" style="text-align:center;background:rgba(239,68,68,0.05);border-bottom:2px solid rgba(239,68,68,0.2)">COMPRAS</th>
                            <th colspan="3" style="text-align:center;background:rgba(16,185,129,0.05);border-bottom:2px solid rgba(16,185,129,0.2)">VENTAS</th>
                        </tr>
                        <tr>
                            <th></th>
                            <th style="text-align:right;background:rgba(239,68,68,0.04);font-size:11px">Neto</th>
                            <th style="text-align:right;background:rgba(239,68,68,0.04);font-size:11px">IVA</th>
                            <th style="text-align:right;background:rgba(239,68,68,0.04);font-size:11px">Total</th>
                            <th style="text-align:right;background:rgba(16,185,129,0.04);font-size:11px">Neto</th>
                            <th style="text-align:right;background:rgba(16,185,129,0.04);font-size:11px">IVA</th>
                            <th style="text-align:right;background:rgba(16,185,129,0.04);font-size:11px">Total</th>
                        </tr>
                    </thead>
                    <tbody id="rango-meses-body"></tbody>
                    <tfoot id="rango-meses-tfoot"></tfoot>
                </table>
            </div>
        </div>

        <!-- Totales globales -->
        <div class="grid-2" style="gap:24px;margin-bottom:24px">
            <!-- Compras -->
            <div class="card" style="border-color:rgba(239,68,68,0.2)">
                <div class="card-title" style="color:var(--red)">Total Compras</div>
                <div id="tot-compras-detalle"></div>
            </div>
            <!-- Ventas -->
            <div class="card" style="border-color:rgba(16,185,129,0.2)">
                <div class="card-title" style="color:var(--green)">Total Ventas</div>
                <div id="tot-ventas-detalle"></div>
            </div>
        </div>

        <!-- Saldo IVA -->
        <div class="card" id="saldo-iva-card">
            <div class="card-title">Posición IVA</div>
            <div class="stats-grid" style="grid-template-columns:1fr 1fr 1fr" id="saldo-iva-grid"></div>
        </div>
    </div>

    <div id="rango-empty" class="empty-state" style="margin-top:40px">
        <div class="empty-state-icon">📊</div>
        <div class="empty-state-text">Seleccioná el rango de meses y hacé clic en "Generar informe"</div>
    </div>
</div>

<style>
.form-label { display:block; font-size:12px; color:var(--text-secondary); margin-bottom:5px; font-weight:500; }
.form-input  { background:var(--bg-hover); border:1px solid var(--border); color:var(--text-primary);
               padding:8px 12px; border-radius:6px; font-size:13px; outline:none; font-family:inherit; }
.form-input:focus { border-color:var(--accent); }
select.form-input option { background:var(--bg-card); }

.tab-btn {
    padding:10px 20px; background:transparent; border:none; border-bottom:2px solid transparent;
    color:var(--text-secondary); font-size:14px; font-weight:600; cursor:pointer; font-family:inherit;
    margin-bottom:-1px; transition:all 0.15s;
}
.tab-btn:hover  { color:var(--text-primary); }
.tab-btn.active { color:var(--accent-light); border-bottom-color:var(--accent-light); }

.tot-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0;
           border-bottom:1px solid var(--border); font-size:13px; }
.tot-row:last-child { border-bottom:none; }
.tot-row .label { color:var(--text-secondary); }
.tot-row .value { font-family:'Space Mono',monospace; font-weight:600; }
.tot-row.grand  { font-size:15px; padding-top:12px; margin-top:4px; border-top:2px solid var(--border); border-bottom:none; }
</style>

<script>
const CLIENTE_ID    = <?= $clienteId ?>;
const MESES_NOMBRES = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

// ── Tab switch ────────────────────────────────────────────────────────────
function switchTab(tab) {
    document.getElementById('tab-mensual').style.display = tab === 'mensual' ? '' : 'none';
    document.getElementById('tab-rango').style.display   = tab === 'rango'   ? '' : 'none';
    document.getElementById('tab-mensual-btn').classList.toggle('active', tab === 'mensual');
    document.getElementById('tab-rango-btn').classList.toggle('active', tab === 'rango');
}

// ── Informe Mensual ───────────────────────────────────────────────────────
document.getElementById('btn-generar-mensual').addEventListener('click', async () => {
    const mes  = document.getElementById('m-mes').value;
    const anio = document.getElementById('m-anio').value;
    const tipo = document.getElementById('m-tipo').value;
    const btn  = document.getElementById('btn-generar-mensual');
    btn.disabled = true; btn.textContent = 'Generando…';

    const res  = await fetch(`/api/facturacion.php?action=informe_mensual&cliente_id=${CLIENTE_ID}&mes=${mes}&anio=${anio}&tipo=${tipo}`);
    const data = await res.json();
    btn.disabled = false; btn.textContent = 'Generar informe';
    if (data.error) { toast(data.error, 'error'); return; }

    renderMensual(data, tipo, mes, anio);
});

function renderMensual(data, tipo, mes, anio) {
    const t    = data.totales;
    const docs = data.docs;
    const esTipo = tipo === 'compras';
    const mesNom = MESES_NOMBRES[parseInt(mes)];

    // Título tabla
    document.getElementById('tbl-mensual-titulo').textContent =
        `${docs.length} documento${docs.length !== 1?'s':''} — ${esTipo?'Compras':'Ventas'} ${mesNom} ${anio}`;

    // Stat cards
    document.getElementById('tot-cards-mensual').innerHTML = `
        <div class="stat-card blue">
            <div class="stat-label">Neto Gravado</div>
            <div class="stat-value" style="font-size:16px">${formatMoney(t.neto)}</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-label">IVA</div>
            <div class="stat-value" style="font-size:16px">${formatMoney(t.iva)}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">No Gravado</div>
            <div class="stat-value" style="font-size:16px">${formatMoney(t.no_gravado)}</div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Total</div>
            <div class="stat-value" style="font-size:16px">${formatMoney(t.total)}</div>
        </div>`;

    // Tabla docs
    const tbody = document.getElementById('docs-body-mensual');
    if (!docs.length) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:var(--text-secondary);padding:24px">Sin documentos para este período</td></tr>`;
    } else {
        tbody.innerHTML = docs.map(d => {
            const isNC = d.sign === -1;
            const totStyle = isNC ? 'color:var(--red)' : '';
            const rNeto = d.renglones.reduce((s,r) => s + r.neto, 0);
            const rIva  = d.renglones.reduce((s,r) => s + r.iva,  0);
            const otros = d.no_gravado + (d.perc_iibb||0) + (d.perc_iva||0) + (d.imp_interno||0) + (d.imp_interno_gasoil||0) + (d.retencion||0);
            return `<tr style="${isNC?'background:rgba(239,68,68,0.03)':''}">
                <td class="mono" style="font-size:11px">${formatDate(d.fecha)}</td>
                <td style="font-size:12px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(d.tipo)}">${escHtml(d.tipo)}</td>
                <td class="mono" style="font-size:11px">${String(d.punto_venta).padStart(4,'0')}-${String(d.numero).padStart(8,'0')}</td>
                <td style="font-size:12px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(d.nombre)}">${escHtml(d.nombre)||'—'}</td>
                <td class="mono" style="text-align:right;font-size:12px;${totStyle}">${formatMoney(rNeto)}</td>
                <td class="mono" style="text-align:right;font-size:12px;${totStyle}">${formatMoney(rIva)}</td>
                <td class="mono" style="text-align:right;font-size:12px;${totStyle}">${otros !== 0 ? formatMoney(otros) : '—'}</td>
                <td class="mono" style="text-align:right;font-size:13px;font-weight:600;${totStyle}">${formatMoney(d.total)}</td>
            </tr>`;
        }).join('');
    }

    // Foot totales
    document.getElementById('docs-tfoot-mensual').innerHTML = `
        <tr style="font-weight:700;border-top:2px solid var(--border);background:var(--bg-hover)">
            <td colspan="4" style="font-size:12px;color:var(--text-secondary);padding:10px 12px">TOTALES</td>
            <td class="mono" style="text-align:right">${formatMoney(t.neto)}</td>
            <td class="mono" style="text-align:right">${formatMoney(t.iva)}</td>
            <td class="mono" style="text-align:right">${formatMoney(t.no_gravado + t.perc_iibb + t.perc_iva + t.imp_interno + t.imp_interno_gasoil + t.retencion)}</td>
            <td class="mono" style="text-align:right">${formatMoney(t.total)}</td>
        </tr>`;

    document.getElementById('mensual-empty').style.display = 'none';
    document.getElementById('res-mensual').style.display   = '';
}

// ── Informe por Rango ─────────────────────────────────────────────────────
document.getElementById('btn-generar-rango').addEventListener('click', async () => {
    const mesD  = document.getElementById('r-mes-desde').value;
    const anioD = document.getElementById('r-anio-desde').value;
    const mesH  = document.getElementById('r-mes-hasta').value;
    const anioH = document.getElementById('r-anio-hasta').value;
    const btn   = document.getElementById('btn-generar-rango');
    btn.disabled = true; btn.textContent = 'Generando…';

    const url  = `/api/facturacion.php?action=informe_rango&cliente_id=${CLIENTE_ID}&mes_desde=${mesD}&anio_desde=${anioD}&mes_hasta=${mesH}&anio_hasta=${anioH}`;
    const res  = await fetch(url);
    const data = await res.json();
    btn.disabled = false; btn.textContent = 'Generar informe';
    if (data.error) { toast(data.error, 'error'); return; }

    renderRango(data);
});

function renderRango(data) {
    const meses = data.meses;
    const totC  = data.tot_compras;
    const totV  = data.tot_ventas;
    const saldo = data.saldo_iva;

    // Tabla mensual
    const tbody = document.getElementById('rango-meses-body');
    tbody.innerHTML = meses.map(m => {
        const hasC = m.compras.total !== 0;
        const hasV = m.ventas.total  !== 0;
        return `<tr>
            <td class="mono" style="font-size:12px;font-weight:600">${MESES_NOMBRES[m.mes]} ${m.anio}</td>
            <td class="mono" style="text-align:right;font-size:12px;background:rgba(239,68,68,0.03);${!hasC?'color:var(--text-muted)':''}">${hasC?formatMoney(m.compras.neto):'—'}</td>
            <td class="mono" style="text-align:right;font-size:12px;background:rgba(239,68,68,0.03);${!hasC?'color:var(--text-muted)':''}">${hasC?formatMoney(m.compras.iva):'—'}</td>
            <td class="mono" style="text-align:right;font-size:12px;font-weight:600;background:rgba(239,68,68,0.03);${!hasC?'color:var(--text-muted)':''}">${hasC?formatMoney(m.compras.total):'—'}</td>
            <td class="mono" style="text-align:right;font-size:12px;background:rgba(16,185,129,0.03);${!hasV?'color:var(--text-muted)':''}">${hasV?formatMoney(m.ventas.neto):'—'}</td>
            <td class="mono" style="text-align:right;font-size:12px;background:rgba(16,185,129,0.03);${!hasV?'color:var(--text-muted)':''}">${hasV?formatMoney(m.ventas.iva):'—'}</td>
            <td class="mono" style="text-align:right;font-size:12px;font-weight:600;background:rgba(16,185,129,0.03);${!hasV?'color:var(--text-muted)':''}">${hasV?formatMoney(m.ventas.total):'—'}</td>
        </tr>`;
    }).join('');

    // Foot tabla
    document.getElementById('rango-meses-tfoot').innerHTML = `
        <tr style="font-weight:700;border-top:2px solid var(--border);background:var(--bg-hover)">
            <td style="font-size:12px;padding:10px 12px">TOTAL</td>
            <td class="mono" style="text-align:right;background:rgba(239,68,68,0.06)">${formatMoney(totC.neto)}</td>
            <td class="mono" style="text-align:right;background:rgba(239,68,68,0.06)">${formatMoney(totC.iva)}</td>
            <td class="mono" style="text-align:right;background:rgba(239,68,68,0.06)">${formatMoney(totC.total)}</td>
            <td class="mono" style="text-align:right;background:rgba(16,185,129,0.06)">${formatMoney(totV.neto)}</td>
            <td class="mono" style="text-align:right;background:rgba(16,185,129,0.06)">${formatMoney(totV.iva)}</td>
            <td class="mono" style="text-align:right;background:rgba(16,185,129,0.06)">${formatMoney(totV.total)}</td>
        </tr>`;

    // Detalle compras
    document.getElementById('tot-compras-detalle').innerHTML = totDetalle([
        ['Neto Gravado',        totC.neto],
        ['IVA Crédito Fiscal',  totC.iva],
        ['No Gravado',          totC.no_gravado],
        ['Percepción IIBB',     totC.perc_iibb],
        ['Percepción IVA',      totC.perc_iva],
        ['Imp. Interno',        totC.imp_interno],
        ['Imp. Int. Gasoil',    totC.imp_interno_gasoil],
    ], 'TOTAL COMPRAS', totC.total, 'var(--red)');

    // Detalle ventas
    document.getElementById('tot-ventas-detalle').innerHTML = totDetalle([
        ['Neto Gravado',       totV.neto],
        ['IVA Débito Fiscal',  totV.iva],
        ['No Gravado',         totV.no_gravado],
        ['Retenciones',        totV.retencion],
    ], 'TOTAL VENTAS', totV.total, 'var(--green)');

    // Saldo IVA
    const saldoColor = saldo >= 0 ? 'var(--green)' : 'var(--red)';
    const saldoLabel = saldo >= 0 ? 'Saldo a favor del Fisco' : 'Saldo a favor del Contribuyente';
    document.getElementById('saldo-iva-grid').innerHTML = `
        <div class="stat-card green">
            <div class="stat-label">IVA Débito Fiscal (Ventas)</div>
            <div class="stat-value" style="font-size:16px">${formatMoney(totV.iva)}</div>
        </div>
        <div class="stat-card" style="border-color:rgba(239,68,68,0.3)">
            <div class="stat-label">IVA Crédito Fiscal (Compras)</div>
            <div class="stat-value" style="font-size:16px;color:var(--red)">${formatMoney(totC.iva)}</div>
        </div>
        <div class="stat-card" style="border-color:rgba(37,99,235,0.3);background:rgba(37,99,235,0.05)">
            <div class="stat-label">${saldoLabel}</div>
            <div class="stat-value" style="font-size:20px;color:${saldoColor}">${formatMoney(Math.abs(saldo))}</div>
        </div>`;

    document.getElementById('rango-empty').style.display = 'none';
    document.getElementById('res-rango').style.display   = '';
}

function totDetalle(rows, grandLabel, grandVal, grandColor) {
    const nonZero = rows.filter(([,v]) => v !== 0);
    return nonZero.map(([label, val]) =>
        `<div class="tot-row"><span class="label">${label}</span><span class="value">${formatMoney(val)}</span></div>`
    ).join('') +
    `<div class="tot-row grand"><span style="font-weight:700;color:${grandColor}">${grandLabel}</span>
     <span class="value" style="font-size:16px;color:${grandColor}">${formatMoney(grandVal)}</span></div>`;
}

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
