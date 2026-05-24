<?php
require_once dirname(__DIR__) . '/auth/check.php';
if (empty($_SESSION['fact_cliente_id'])) {
    header('Location: /facturacion/index.php');
    exit;
}
$titulo        = 'Compras';
$clienteId     = (int)$_SESSION['fact_cliente_id'];
$clienteNombre = $_SESSION['fact_cliente_nombre'] ?? '';
require_once __DIR__ . '/includes/header.php';

$anioActual = (int)date('Y');
$mesActual  = (int)date('n');

$tipos = [
    'Factura A','Factura B','Factura C',
    'Nota de Crédito A','Nota de Crédito B','Nota de Crédito C',
    'Nota de Débito A','Nota de Débito B','Nota de Débito C',
    'Recibo',
];
$mesesNombres = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
?>

<!-- ── FORMULARIO ── -->
<div class="card mb-24">
    <div class="flex-between">
        <div class="card-title" id="form-titulo">Nueva Compra</div>
        <span style="font-size:13px;color:var(--text-secondary)">Cliente: <strong style="color:var(--text-primary)"><?= htmlspecialchars($clienteNombre) ?></strong></span>
    </div>
    <input type="hidden" id="compra-id" value="0">

    <!-- Fila 1: tipo + pto + numero -->
    <div class="stats-grid" style="grid-template-columns:2fr 1fr 1fr;gap:16px;margin-bottom:16px">
        <div>
            <label class="form-label">Tipo de Comprobante *</label>
            <select id="inp-tipo" class="form-input" style="width:100%">
                <option value="">— Seleccioná —</option>
                <?php foreach ($tipos as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label">Punto de Venta</label>
            <input type="number" id="inp-pto" class="form-input" placeholder="0001" min="0" style="width:100%">
        </div>
        <div>
            <label class="form-label">Número</label>
            <input type="number" id="inp-num" class="form-input" placeholder="00000001" min="0" style="width:100%">
        </div>
    </div>

    <!-- Fila 2: fecha + mes + año -->
    <div class="stats-grid" style="grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px">
        <div>
            <label class="form-label">Fecha del Comprobante *</label>
            <input type="date" id="inp-fecha" class="form-input" style="width:100%">
        </div>
        <div>
            <label class="form-label">Mes Contable *</label>
            <select id="inp-mes" class="form-input" style="width:100%">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m === $mesActual ? 'selected' : '' ?>><?= $mesesNombres[$m] ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label class="form-label">Año Contable *</label>
            <select id="inp-anio" class="form-input" style="width:100%">
                <?php for ($a = $anioActual; $a >= $anioActual - 5; $a--): ?>
                <option value="<?= $a ?>" <?= $a === $anioActual ? 'selected' : '' ?>><?= $a ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </div>

    <!-- Fila 3: proveedor -->
    <div class="stats-grid" style="grid-template-columns:2fr 1fr;gap:16px;margin-bottom:20px">
        <div>
            <label class="form-label">Proveedor / Razón Social</label>
            <input type="text" id="inp-prov-nombre" class="form-input" placeholder="Nombre del proveedor" style="width:100%">
        </div>
        <div>
            <label class="form-label">CUIT Proveedor</label>
            <input type="text" id="inp-prov-cuit" class="form-input" placeholder="20-12345678-9" style="width:100%">
        </div>
    </div>

    <!-- Renglones -->
    <div style="border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:20px">
        <div style="padding:12px 16px;background:var(--bg-hover);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
            <span style="font-size:13px;font-weight:600;color:var(--text-primary)">Detalle de IVA por alícuota</span>
            <button class="btn btn-secondary btn-sm" id="btn-add-renglon">+ Agregar renglon</button>
        </div>
        <div style="padding:12px">
            <div style="display:grid;grid-template-columns:160px 1fr 1fr 36px;gap:8px;margin-bottom:8px">
                <span style="font-size:11px;color:var(--text-secondary);font-weight:600;padding-left:4px">ALÍCUOTA</span>
                <span style="font-size:11px;color:var(--text-secondary);font-weight:600;padding-left:4px">NETO GRAVADO</span>
                <span style="font-size:11px;color:var(--text-secondary);font-weight:600;padding-left:4px">IVA</span>
                <span></span>
            </div>
            <div id="renglones-container"></div>
        </div>
        <div style="padding:10px 16px;background:var(--bg-hover);border-top:1px solid var(--border);display:flex;gap:24px;font-size:13px">
            <span>Total Neto: <strong id="tot-neto" style="color:var(--text-primary)">$ 0,00</strong></span>
            <span>Total IVA: <strong id="tot-iva" style="color:var(--text-primary)">$ 0,00</strong></span>
        </div>
    </div>

    <!-- Otros conceptos -->
    <div style="margin-bottom:20px">
        <div style="font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:12px;text-transform:uppercase;letter-spacing:0.5px">Otros conceptos</div>
        <div class="stats-grid" style="grid-template-columns:repeat(5,1fr);gap:12px">
            <div>
                <label class="form-label">No Gravado</label>
                <input type="number" id="inp-no-grav" class="form-input mono" placeholder="0.00" step="0.01" min="0" style="width:100%" oninput="recalcTotal()">
            </div>
            <div>
                <label class="form-label">Perc. IIBB</label>
                <input type="number" id="inp-perc-iibb" class="form-input mono" placeholder="0.00" step="0.01" min="0" style="width:100%" oninput="recalcTotal()">
            </div>
            <div>
                <label class="form-label">Perc. IVA</label>
                <input type="number" id="inp-perc-iva" class="form-input mono" placeholder="0.00" step="0.01" min="0" style="width:100%" oninput="recalcTotal()">
            </div>
            <div>
                <label class="form-label">Imp. Interno</label>
                <input type="number" id="inp-imp-int" class="form-input mono" placeholder="0.00" step="0.01" min="0" style="width:100%" oninput="recalcTotal()">
            </div>
            <div>
                <label class="form-label">Imp. Int. Gasoil</label>
                <input type="number" id="inp-imp-gas" class="form-input mono" placeholder="0.00" step="0.01" min="0" style="width:100%" oninput="recalcTotal()">
            </div>
        </div>
    </div>

    <!-- Total -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:rgba(37,99,235,0.07);border:1px solid rgba(37,99,235,0.2);border-radius:10px;margin-bottom:20px">
        <span style="font-size:14px;font-weight:600;color:var(--text-secondary)">TOTAL COMPROBANTE</span>
        <span id="tot-total" style="font-size:22px;font-weight:700;color:var(--text-primary);font-family:'Space Mono',monospace">$ 0,00</span>
    </div>

    <div style="display:flex;gap:10px">
        <button class="btn btn-primary btn-lg" id="btn-guardar-compra">Guardar Compra</button>
        <button class="btn btn-secondary" id="btn-cancelar-edit" style="display:none">Cancelar edición</button>
    </div>
</div>

<!-- ── HISTORIAL ── -->
<div class="card">
    <div class="flex-between mb-16">
        <div class="card-title" style="margin:0">Compras registradas</div>
        <div style="display:flex;gap:10px;align-items:center">
            <select id="fil-mes" class="form-input" style="width:130px">
                <option value="0">Todos los meses</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m === $mesActual ? 'selected' : '' ?>><?= $mesesNombres[$m] ?></option>
                <?php endfor; ?>
            </select>
            <select id="fil-anio" class="form-input" style="width:100px">
                <?php for ($a = $anioActual; $a >= $anioActual - 5; $a--): ?>
                <option value="<?= $a ?>" <?= $a === $anioActual ? 'selected' : '' ?>><?= $a ?></option>
                <?php endfor; ?>
            </select>
            <button class="btn btn-secondary btn-sm" id="btn-refresh-hist">↺</button>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Período</th>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Comprobante</th>
                    <th>Proveedor</th>
                    <th style="text-align:right">Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="hist-body">
                <tr><td colspan="7"><div class="empty-state"><div class="spinner" style="margin:0 auto"></div></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal eliminar -->
<div class="modal-overlay" id="modal-eliminar">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--red)">⚠ Eliminar compra</div>
            <button class="modal-close">✕</button>
        </div>
        <p style="color:var(--text-secondary);font-size:14px;margin-bottom:24px">Esta acción no se puede deshacer.</p>
        <div style="display:flex;gap:10px;justify-content:flex-end">
            <button class="btn btn-secondary" onclick="closeModal('modal-eliminar')">Cancelar</button>
            <button class="btn btn-danger" id="btn-confirmar-elim">Eliminar</button>
        </div>
    </div>
</div>

<style>
.form-label { display:block; font-size:12px; color:var(--text-secondary); margin-bottom:5px; font-weight:500; }
.form-input  { background:var(--bg-hover); border:1px solid var(--border); color:var(--text-primary);
               padding:8px 12px; border-radius:6px; font-size:13px; outline:none; font-family:inherit; }
.form-input:focus { border-color:var(--accent); }
select.form-input option { background:var(--bg-card); }
.renglon-row { display:grid; grid-template-columns:160px 1fr 1fr 36px; gap:8px; margin-bottom:8px; }
.btn-del-renglon { background:rgba(239,68,68,0.12); border:1px solid rgba(239,68,68,0.2); color:var(--red);
                   width:36px; height:36px; border-radius:6px; cursor:pointer; font-size:16px; display:flex;
                   align-items:center; justify-content:center; flex-shrink:0; }
.btn-del-renglon:hover { background:rgba(239,68,68,0.22); }
</style>

<script>
const CLIENTE_ID   = <?= $clienteId ?>;
const MESES_NOMBRES = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
let   compraAEliminar = null;
let   editando        = false;

// ── Renglones ─────────────────────────────────────────────────────────────
function addRenglon(alic = '21', neto = '', iva = '') {
    const c   = document.getElementById('renglones-container');
    const div = document.createElement('div');
    div.className = 'renglon-row';
    div.innerHTML = `
        <select class="form-input r-alic" style="width:100%" onchange="calcIva(this)">
            <option value="0"    ${alic=='0'    ?'selected':''}>0%</option>
            <option value="10.5" ${alic=='10.5' ?'selected':''}>10,5%</option>
            <option value="21"   ${alic=='21'   ?'selected':''}>21%</option>
            <option value="27"   ${alic=='27'   ?'selected':''}>27%</option>
        </select>
        <input type="number" class="form-input mono r-neto" placeholder="0.00" step="0.01" min="0"
               value="${neto}" style="width:100%" oninput="calcIva(this.closest('.renglon-row').querySelector('.r-alic'))">
        <input type="number" class="form-input mono r-iva" placeholder="0.00" step="0.01"
               value="${iva}" style="width:100%;background:rgba(255,255,255,0.03)" readonly>
        <button type="button" class="btn-del-renglon" onclick="this.closest('.renglon-row').remove(); recalcTotal()">✕</button>`;
    c.appendChild(div);
    recalcTotal();
}

function calcIva(alicSelect) {
    const row   = alicSelect.closest('.renglon-row');
    const alic  = parseFloat(alicSelect.value) / 100;
    const neto  = parseFloat(row.querySelector('.r-neto').value) || 0;
    row.querySelector('.r-iva').value = (neto * alic).toFixed(2);
    recalcTotal();
}

function recalcTotal() {
    let totNeto = 0, totIva = 0;
    document.querySelectorAll('.renglon-row').forEach(row => {
        totNeto += parseFloat(row.querySelector('.r-neto').value) || 0;
        totIva  += parseFloat(row.querySelector('.r-iva').value)  || 0;
    });
    const noGrav  = parseFloat(document.getElementById('inp-no-grav').value)   || 0;
    const pIIBB   = parseFloat(document.getElementById('inp-perc-iibb').value)  || 0;
    const pIva    = parseFloat(document.getElementById('inp-perc-iva').value)   || 0;
    const impInt  = parseFloat(document.getElementById('inp-imp-int').value)    || 0;
    const impGas  = parseFloat(document.getElementById('inp-imp-gas').value)    || 0;
    const total   = totNeto + totIva + noGrav + pIIBB + pIva + impInt + impGas;
    document.getElementById('tot-neto').textContent  = formatMoney(totNeto);
    document.getElementById('tot-iva').textContent   = formatMoney(totIva);
    document.getElementById('tot-total').textContent = formatMoney(total);
}

document.getElementById('btn-add-renglon').addEventListener('click', () => addRenglon());
addRenglon(); // initial row

// ── Guardar ───────────────────────────────────────────────────────────────
document.getElementById('btn-guardar-compra').addEventListener('click', async () => {
    const tipo  = document.getElementById('inp-tipo').value;
    const fecha = document.getElementById('inp-fecha').value;
    const mes   = parseInt(document.getElementById('inp-mes').value);
    const anio  = parseInt(document.getElementById('inp-anio').value);
    if (!tipo)  { toast('Seleccioná un tipo de comprobante', 'error'); return; }
    if (!fecha) { toast('Ingresá la fecha del comprobante', 'error'); return; }

    const renglones = [];
    document.querySelectorAll('.renglon-row').forEach(row => {
        const neto = parseFloat(row.querySelector('.r-neto').value) || 0;
        const iva  = parseFloat(row.querySelector('.r-iva').value)  || 0;
        if (neto > 0 || iva > 0)
            renglones.push({ alicuota: parseFloat(row.querySelector('.r-alic').value), neto, iva });
    });

    const noGrav  = parseFloat(document.getElementById('inp-no-grav').value)   || 0;
    const pIIBB   = parseFloat(document.getElementById('inp-perc-iibb').value)  || 0;
    const pIva    = parseFloat(document.getElementById('inp-perc-iva').value)   || 0;
    const impInt  = parseFloat(document.getElementById('inp-imp-int').value)    || 0;
    const impGas  = parseFloat(document.getElementById('inp-imp-gas').value)    || 0;
    let totNeto = 0, totIva = 0;
    renglones.forEach(r => { totNeto += r.neto; totIva += r.iva; });
    const total = totNeto + totIva + noGrav + pIIBB + pIva + impInt + impGas;

    const body = {
        id:                  parseInt(document.getElementById('compra-id').value),
        cliente_id:          CLIENTE_ID,
        tipo, fecha, mes_contable: mes, anio_contable: anio,
        punto_venta:         parseInt(document.getElementById('inp-pto').value) || 0,
        numero:              parseInt(document.getElementById('inp-num').value) || 0,
        proveedor_nombre:    document.getElementById('inp-prov-nombre').value.trim(),
        proveedor_cuit:      document.getElementById('inp-prov-cuit').value.trim(),
        no_gravado:          noGrav, perc_iibb: pIIBB, perc_iva: pIva,
        imp_interno:         impInt, imp_interno_gasoil: impGas, total, renglones,
    };

    const btn = document.getElementById('btn-guardar-compra');
    btn.disabled = true; btn.textContent = 'Guardando…';
    const res  = await fetch('/api/facturacion.php?action=compras_guardar', {
        method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(body)
    });
    const data = await res.json();
    btn.disabled = false; btn.textContent = 'Guardar Compra';
    if (data.error) { toast(data.error, 'error'); return; }
    toast(body.id > 0 ? '✓ Compra actualizada' : '✓ Compra guardada', 'success');
    resetForm();
    cargarHistorial();
});

// ── Reset form ────────────────────────────────────────────────────────────
function resetForm() {
    document.getElementById('compra-id').value = 0;
    document.getElementById('inp-tipo').value = '';
    document.getElementById('inp-pto').value  = '';
    document.getElementById('inp-num').value  = '';
    document.getElementById('inp-fecha').value = '';
    document.getElementById('inp-mes').value  = <?= $mesActual ?>;
    document.getElementById('inp-anio').value = <?= $anioActual ?>;
    document.getElementById('inp-prov-nombre').value = '';
    document.getElementById('inp-prov-cuit').value   = '';
    document.getElementById('inp-no-grav').value   = '';
    document.getElementById('inp-perc-iibb').value  = '';
    document.getElementById('inp-perc-iva').value   = '';
    document.getElementById('inp-imp-int').value    = '';
    document.getElementById('inp-imp-gas').value    = '';
    document.getElementById('renglones-container').innerHTML = '';
    document.getElementById('form-titulo').textContent = 'Nueva Compra';
    document.getElementById('btn-cancelar-edit').style.display = 'none';
    addRenglon();
    recalcTotal();
    editando = false;
}

document.getElementById('btn-cancelar-edit').addEventListener('click', resetForm);

// ── Historial ─────────────────────────────────────────────────────────────
async function cargarHistorial() {
    const mes  = document.getElementById('fil-mes').value;
    const anio = document.getElementById('fil-anio').value;
    let url = `/api/facturacion.php?action=compras_listar&cliente_id=${CLIENTE_ID}&anio=${anio}`;
    if (mes > 0) url += `&mes=${mes}`;
    const res  = await fetch(url);
    const data = await res.json();
    renderHistorial(data.data || []);
}

function renderHistorial(lista) {
    const tbody = document.getElementById('hist-body');
    if (!lista.length) {
        tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">No hay compras registradas para este período</div>
        </div></td></tr>`;
        return;
    }
    tbody.innerHTML = lista.map(c => {
        const isNC = c.tipo.toLowerCase().includes('nota de cr');
        const totStyle = isNC ? 'color:var(--red)' : '';
        return `<tr>
            <td class="mono" style="font-size:11px">${MESES_NOMBRES[parseInt(c.mes_contable)]} ${c.anio_contable}</td>
            <td class="mono" style="font-size:11px">${formatDate(c.fecha)}</td>
            <td style="font-size:12px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(c.tipo)}">${escHtml(c.tipo)}</td>
            <td class="mono" style="font-size:11px">${String(c.punto_venta).padStart(4,'0')}-${String(c.numero).padStart(8,'0')}</td>
            <td style="font-size:12px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(c.proveedor_nombre)}">${escHtml(c.proveedor_nombre) || '—'}</td>
            <td class="mono" style="text-align:right;font-size:13px;${totStyle}">${isNC?'-':''}${formatMoney(parseFloat(c.total))}</td>
            <td style="display:flex;gap:6px">
                <button class="btn btn-secondary btn-sm" onclick="editarCompra(${c.id})">✎</button>
                <button class="btn btn-danger btn-sm" onclick="pedirEliminar(${c.id})">✕</button>
            </td>
        </tr>`;
    }).join('');
}

async function editarCompra(id) {
    const mes  = document.getElementById('fil-mes').value;
    const anio = document.getElementById('fil-anio').value;
    let url = `/api/facturacion.php?action=compras_listar&cliente_id=${CLIENTE_ID}&anio=${anio}`;
    if (mes > 0) url += `&mes=${mes}`;
    const res  = await fetch(url);
    const data = await res.json();
    const c    = (data.data || []).find(x => x.id == id);
    if (!c) { toast('No se encontró la compra', 'error'); return; }

    document.getElementById('compra-id').value          = c.id;
    document.getElementById('inp-tipo').value            = c.tipo;
    document.getElementById('inp-pto').value             = c.punto_venta;
    document.getElementById('inp-num').value             = c.numero;
    document.getElementById('inp-fecha').value           = c.fecha;
    document.getElementById('inp-mes').value             = c.mes_contable;
    document.getElementById('inp-anio').value            = c.anio_contable;
    document.getElementById('inp-prov-nombre').value     = c.proveedor_nombre;
    document.getElementById('inp-prov-cuit').value       = c.proveedor_cuit;
    document.getElementById('inp-no-grav').value         = c.no_gravado   || '';
    document.getElementById('inp-perc-iibb').value       = c.perc_iibb    || '';
    document.getElementById('inp-perc-iva').value        = c.perc_iva     || '';
    document.getElementById('inp-imp-int').value         = c.imp_interno  || '';
    document.getElementById('inp-imp-gas').value         = c.imp_interno_gasoil || '';

    document.getElementById('renglones-container').innerHTML = '';
    (c.renglones || []).forEach(r => addRenglon(r.alicuota, r.neto, r.iva));
    if (!c.renglones || !c.renglones.length) addRenglon();
    recalcTotal();

    document.getElementById('form-titulo').textContent = 'Editar Compra';
    document.getElementById('btn-cancelar-edit').style.display = 'inline-flex';
    document.getElementById('btn-guardar-compra').textContent  = 'Guardar Compra';
    editando = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function pedirEliminar(id) { compraAEliminar = id; openModal('modal-eliminar'); }

document.getElementById('btn-confirmar-elim').addEventListener('click', async () => {
    if (!compraAEliminar) return;
    const btn = document.getElementById('btn-confirmar-elim');
    btn.disabled = true; btn.textContent = 'Eliminando…';
    const res  = await fetch('/api/facturacion.php?action=compras_eliminar', {
        method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({id: compraAEliminar})
    });
    const data = await res.json();
    closeModal('modal-eliminar');
    if (data.success) { toast('Compra eliminada', 'success'); cargarHistorial(); }
    else toast(data.error || 'Error', 'error');
    btn.disabled = false; btn.textContent = 'Eliminar';
    compraAEliminar = null;
});

document.getElementById('btn-refresh-hist').addEventListener('click', cargarHistorial);
document.getElementById('fil-mes').addEventListener('change', cargarHistorial);
document.getElementById('fil-anio').addEventListener('change', cargarHistorial);

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

cargarHistorial();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
