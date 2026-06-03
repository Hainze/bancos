<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo        = 'Balance de datos';
$clienteId     = (int)($_SESSION['fact_cliente_id']     ?? 0);
$clienteNombre = $_SESSION['fact_cliente_nombre'] ?? '';
require_once __DIR__ . '/includes/header.php';
?>

<script>
const CLIENTE_ID     = <?= $clienteId ?>;
const CLIENTE_NOMBRE = <?= json_encode($clienteNombre) ?>;
</script>

<?php if (!$clienteId): ?>
<div style="background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);border-radius:10px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px">
    <span style="font-size:20px">⚠</span>
    <div>
        <strong style="color:#f59e0b">Sin cliente seleccionado</strong>
        <div style="font-size:13px;color:var(--sub);margin-top:2px">
            <a href="/facturacion/index.php" style="color:var(--accent-light)">Seleccioná un cliente</a> para cargar y guardar los datos del ejercicio.
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Barra de ejercicio -->
<div class="card mb-24" style="padding:14px 20px">
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:10px">
            <span style="font-size:13px;color:var(--sub)">Ejercicio fiscal</span>
            <select id="anio-sel" class="form-control" style="display:inline-block;width:auto;padding:4px 10px" onchange="cambiarAnio(this.value)">
                <?php
                $hoy = (int)date('Y');
                for ($y = $hoy + 1; $y >= $hoy - 5; $y--) {
                    $sel = $y === $hoy ? 'selected' : '';
                    echo "<option value=\"$y\" $sel>$y</option>";
                }
                ?>
            </select>
        </div>
        <?php if ($clienteId): ?>
        <span style="font-size:13px;color:var(--sub)">·</span>
        <span style="font-size:13px">Cliente: <strong style="color:var(--amber)"><?= htmlspecialchars($clienteNombre) ?></strong></span>
        <?php endif; ?>
        <div style="margin-left:auto">
            <button class="btn btn-secondary btn-sm" onclick="cargar()" title="Recargar datos">↻ Recargar</button>
        </div>
    </div>
</div>

<!-- Contenedor de secciones — generado por JS -->
<div id="cats-container"></div>

<style>
.cat-total {
    font-size:13px;font-weight:700;font-family:'Space Mono',monospace;
    color:var(--accent-light);background:rgba(139,92,246,0.1);
    padding:2px 10px;border-radius:20px;
}
.btn-del {
    background:none;border:none;color:var(--muted);cursor:pointer;
    font-size:18px;line-height:1;padding:2px 6px;border-radius:4px;transition:color .15s;
}
.btn-del:hover { color:var(--red); }
.form-row td { background:rgba(139,92,246,0.04); }
.form-row input {
    background:var(--surface);border:1px solid var(--border);border-radius:6px;
    padding:4px 8px;font-size:12px;color:var(--text);width:100%;box-sizing:border-box;
}
.form-row input:focus { outline:none;border-color:#8b5cf6; }
.form-row input[type=number] { text-align:right; }
.empty-row td { text-align:center;color:var(--muted);padding:24px;font-size:13px; }
</style>

<script>
let ANIO = <?= (int)date('Y') ?>;
let dataStore = {};

const CATS = [
    {
        id: 'bancos',
        label: 'Bancos',
        icon: '🏦',
        cols: [
            { key:'descripcion', label:'Banco',      ph:'Banco Galicia' },
            { key:'campo2',      label:'Tipo',        ph:'Cta. Cte. / Caja Ahorro' },
            { key:'campo3',      label:'N° Cuenta',   ph:'123-456789/0' },
            { key:'valor_origen',label:'Saldo',       ph:'0',   num:true },
        ],
        totalFn:    rows => rows.reduce((s,r) => s + n(r.valor_origen), 0),
        totalLabel: 'Saldo total',
    },
    {
        id: 'proveedores',
        label: 'Proveedores',
        icon: '🏭',
        cols: [
            { key:'descripcion', label:'Nombre', ph:'Proveedor S.A.' },
            { key:'campo2',      label:'CUIT',   ph:'20-12345678-9' },
            { key:'valor_origen',label:'Deuda',  ph:'0', num:true },
        ],
        totalFn:    rows => rows.reduce((s,r) => s + n(r.valor_origen), 0),
        totalLabel: 'Deuda total',
    },
    {
        id: 'clientes',
        label: 'Clientes',
        icon: '👤',
        cols: [
            { key:'descripcion', label:'Nombre',  ph:'Cliente S.A.' },
            { key:'campo2',      label:'CUIT',    ph:'30-12345678-9' },
            { key:'valor_origen',label:'Crédito', ph:'0', num:true },
        ],
        totalFn:    rows => rows.reduce((s,r) => s + n(r.valor_origen), 0),
        totalLabel: 'Crédito total',
    },
    {
        id: 'rodados',
        label: 'Rodados',
        icon: '🚗',
        cols: [
            { key:'descripcion', label:'Descripción',  ph:'Ford Ranger 2020' },
            { key:'campo2',      label:'Año adq.',     ph:'2020', w:'80px' },
            { key:'valor_origen',label:'Valor origen', ph:'0', num:true },
            { key:'amort_acum',  label:'Amort. acum.', ph:'0', num:true },
        ],
        extraCols: [{ label:'Valor residual', fn: r => n(r.valor_origen) - n(r.amort_acum) }],
        totalFn:    rows => rows.reduce((s,r) => s + n(r.valor_origen) - n(r.amort_acum), 0),
        totalLabel: 'Residual total',
    },
    {
        id: 'maquinarias',
        label: 'Maquinarias',
        icon: '⚙️',
        cols: [
            { key:'descripcion', label:'Descripción',  ph:'Tractor 4x4' },
            { key:'campo2',      label:'Año adq.',     ph:'2018', w:'80px' },
            { key:'valor_origen',label:'Valor origen', ph:'0', num:true },
            { key:'amort_acum',  label:'Amort. acum.', ph:'0', num:true },
        ],
        extraCols: [{ label:'Valor residual', fn: r => n(r.valor_origen) - n(r.amort_acum) }],
        totalFn:    rows => rows.reduce((s,r) => s + n(r.valor_origen) - n(r.amort_acum), 0),
        totalLabel: 'Residual total',
    },
    {
        id: 'bienes_utiles',
        label: 'Bienes y Útiles',
        icon: '🖥️',
        cols: [
            { key:'descripcion', label:'Descripción',  ph:'Computadora 2021' },
            { key:'campo2',      label:'Año adq.',     ph:'2021', w:'80px' },
            { key:'valor_origen',label:'Valor origen', ph:'0', num:true },
            { key:'amort_acum',  label:'Amort. acum.', ph:'0', num:true },
        ],
        extraCols: [{ label:'Valor residual', fn: r => n(r.valor_origen) - n(r.amort_acum) }],
        totalFn:    rows => rows.reduce((s,r) => s + n(r.valor_origen) - n(r.amort_acum), 0),
        totalLabel: 'Residual total',
    },
];

// ── Inicialización ────────────────────────────────────────
function initSections() {
    document.getElementById('cats-container').innerHTML = CATS.map(cat => {
        const headers = cat.cols.map(c =>
            `<th style="${c.num ? 'text-align:right' : ''}${c.w ? ';width:' + c.w : ''}">${c.label}</th>`
        ).join('') +
        (cat.extraCols || []).map(ec =>
            `<th style="text-align:right;color:var(--accent-light)">${ec.label}</th>`
        ).join('') +
        `<th style="width:36px"></th>`;

        return `
        <div class="card mb-20">
            <div class="flex-between mb-16">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <span style="font-size:20px">${cat.icon}</span>
                    <span class="card-title" style="margin:0">${cat.label}</span>
                    <span class="cat-total" id="total-${cat.id}">$ 0,00</span>
                    <span style="font-size:11px;color:var(--muted)">${cat.totalLabel}</span>
                </div>
                <button onclick="addRow('${cat.id}')" class="btn btn-primary btn-sm">+ Agregar</button>
            </div>
            <div style="overflow-x:auto">
                <table style="font-size:13px;width:100%">
                    <thead><tr>${headers}</tr></thead>
                    <tbody id="tbody-${cat.id}">
                        <tr class="empty-row"><td colspan="${colCount(cat)}">Cargando…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>`;
    }).join('');
}

// ── Carga de datos ────────────────────────────────────────
async function cargar() {
    if (!CLIENTE_ID) {
        initSections();
        for (const cat of CATS) renderCat(cat, []);
        return;
    }
    try {
        const res  = await fetch(`/ganancias/api/items.php?action=listar&anio=${ANIO}`);
        const json = await res.json();
        if (json.error) { toast(json.error, 'error'); return; }

        dataStore = {};
        for (const cat of CATS) dataStore[cat.id] = [];
        for (const item of json.data || []) {
            if (dataStore[item.categoria]) dataStore[item.categoria].push(item);
        }
        for (const cat of CATS) renderCat(cat, dataStore[cat.id]);
    } catch(e) {
        toast('Error al cargar: ' + e.message, 'error');
    }
}

function cambiarAnio(val) {
    ANIO = parseInt(val);
    cargar();
}

// ── Render de sección ─────────────────────────────────────
function renderCat(cat, items) {
    // Total badge
    const total = cat.totalFn(items);
    const badge = document.getElementById(`total-${cat.id}`);
    if (badge) badge.textContent = '$ ' + fmt(total);

    // Filas
    const tbody = document.getElementById(`tbody-${cat.id}`);
    if (!tbody) return;

    if (!items.length) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="${colCount(cat)}">Sin registros — hacé clic en <strong>+ Agregar</strong> para empezar</td></tr>`;
        return;
    }

    tbody.innerHTML = items.map(item => dataRow(cat, item)).join('');
}

function dataRow(cat, item) {
    const cells = cat.cols.map(col => {
        const val = item[col.key] ?? '';
        return col.num
            ? `<td class="mono" style="text-align:right">$ ${fmt(n(val))}</td>`
            : `<td>${esc(val)}</td>`;
    }).join('') +
    (cat.extraCols || []).map(ec =>
        `<td class="mono" style="text-align:right;font-weight:700;color:var(--accent-light)">$ ${fmt(ec.fn(item))}</td>`
    ).join('') +
    `<td style="text-align:center"><button class="btn-del" onclick="eliminar('${cat.id}',${item.id})" title="Eliminar">×</button></td>`;

    return `<tr data-id="${item.id}">${cells}</tr>`;
}

// ── Agregar fila ──────────────────────────────────────────
function addRow(catId) {
    const cat   = CATS.find(c => c.id === catId);
    const tbody = document.getElementById(`tbody-${cat.id}`);

    // Si ya hay un form row, hacer foco en el primer input
    const existing = tbody.querySelector('.form-row');
    if (existing) { existing.querySelector('input')?.focus(); return; }

    const inputCells = cat.cols.map((col, i) => {
        const attrs = col.num
            ? `type="number" step="0.01" min="0"`
            : `type="text"`;
        const style = `style="min-width:${col.w || (col.num ? '130px' : '150px')}"`;
        return `<td><input ${attrs} ${style} class="form-input" placeholder="${esc(col.ph || col.label)}"></td>`;
    }).join('') +
    (cat.extraCols || []).map(() => '<td></td>').join('') +
    `<td style="white-space:nowrap;text-align:center">
        <button onclick="guardarRow('${catId}',this)" class="btn btn-primary" style="padding:3px 10px;font-size:11px">✓</button>
        <button onclick="this.closest('tr').remove()" class="btn btn-secondary" style="padding:3px 8px;font-size:11px;margin-left:4px">✗</button>
    </td>`;

    const tr = document.createElement('tr');
    tr.className = 'form-row';
    tr.innerHTML = inputCells;

    // Quitar fila vacía si existe
    tbody.querySelector('.empty-row')?.remove();
    tbody.appendChild(tr);
    tr.querySelectorAll('input')[0]?.focus();

    // Enter en los inputs avanza al siguiente o guarda
    const inputs = tr.querySelectorAll('input');
    inputs.forEach((inp, idx) => {
        inp.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (idx < inputs.length - 1) inputs[idx + 1].focus();
                else guardarRow(catId, tr.querySelector('.btn.btn-primary'));
            }
            if (e.key === 'Escape') tr.remove();
        });
    });
}

// ── Guardar fila ──────────────────────────────────────────
async function guardarRow(catId, btn) {
    const cat    = CATS.find(c => c.id === catId);
    const tr     = btn.closest('tr');
    const inputs = tr.querySelectorAll('input');

    const payload = { categoria: catId, anio: ANIO };
    cat.cols.forEach((col, i) => {
        payload[col.key] = col.num
            ? parseFloat(inputs[i].value || 0)
            : inputs[i].value.trim();
    });
    // Campos no usados por esta categoría quedan vacíos
    if (!payload.campo2) payload.campo2 = '';
    if (!payload.campo3) payload.campo3 = '';
    if (!payload.amort_acum) payload.amort_acum = 0;

    if (!payload.descripcion) {
        toast('Ingresá al menos la descripción', 'warning');
        inputs[0].focus();
        return;
    }

    btn.disabled    = true;
    btn.textContent = '…';

    try {
        const res  = await fetch('/ganancias/api/items.php?action=guardar', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        });
        const data = await res.json();

        if (!data.success) {
            toast(data.error || 'Error al guardar', 'error');
            btn.disabled    = false;
            btn.textContent = '✓';
            return;
        }

        const newItem = { id: data.id, ...payload };
        if (!dataStore[catId]) dataStore[catId] = [];
        dataStore[catId].push(newItem);
        renderCat(cat, dataStore[catId]);
        toast('Guardado', 'success');
    } catch(e) {
        toast('Error: ' + e.message, 'error');
        btn.disabled    = false;
        btn.textContent = '✓';
    }
}

// ── Eliminar ──────────────────────────────────────────────
async function eliminar(catId, id) {
    if (!confirm('¿Eliminar este registro?')) return;
    try {
        const res  = await fetch('/ganancias/api/items.php?action=eliminar', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ id }),
        });
        const data = await res.json();
        if (!data.success) { toast(data.error || 'Error al eliminar', 'error'); return; }

        dataStore[catId] = dataStore[catId].filter(i => i.id !== id);
        renderCat(CATS.find(c => c.id === catId), dataStore[catId]);
        toast('Eliminado', 'success');
    } catch(e) {
        toast('Error: ' + e.message, 'error');
    }
}

// ── Helpers ───────────────────────────────────────────────
function n(v)   { return parseFloat(v || 0); }
function fmt(v) { return v.toLocaleString('es-AR', { minimumFractionDigits:2, maximumFractionDigits:2 }); }
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function colCount(cat) { return cat.cols.length + (cat.extraCols||[]).length + 1; }

// ── Init ──────────────────────────────────────────────────
initSections();
cargar();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
