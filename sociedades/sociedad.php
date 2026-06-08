<?php
require_once dirname(__DIR__) . '/auth/check.php';

$soc_id = (int)($_GET['id'] ?? 0);
if (!$soc_id) { header('Location: /sociedades/index.php'); exit; }

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM soc_sociedades WHERE id=? AND activa=1");
$stmt->execute([$soc_id]);
$soc = $stmt->fetch();
if (!$soc) { header('Location: /sociedades/index.php'); exit; }

$titulo = $soc['nombre'];
require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb + acciones -->
<div class="flex-between mb-16" style="flex-wrap:wrap;gap:10px">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <a href="/sociedades/index.php" style="color:var(--muted);font-size:13px;text-decoration:none">← Todas las sociedades</a>
        <span style="color:var(--border)">|</span>
        <span style="font-family:'Space Mono',monospace;font-size:12px;background:rgba(99,102,241,.15);color:#818cf8;padding:3px 10px;border-radius:10px">
            <?= htmlspecialchars($soc['tipo']) ?>
        </span>
        <?php if ($soc['notas']): ?>
        <span style="font-size:12px;color:var(--muted)" title="<?= htmlspecialchars($soc['notas']) ?>">ℹ <?= htmlspecialchars(mb_substr($soc['notas'], 0, 60)) ?><?= mb_strlen($soc['notas']) > 60 ? '…' : '' ?></span>
        <?php endif; ?>
    </div>
    <div style="display:flex;gap:8px">
        <span id="pct-badge" style="display:none;font-size:13px;font-weight:700;padding:4px 12px;border-radius:20px;background:rgba(99,102,241,.15);color:#818cf8"></span>
        <button class="btn btn-danger btn-sm" onclick="confirmarEliminar()">Eliminar</button>
    </div>
</div>

<!-- Selector de período -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px" id="periodo-tabs"></div>

<!-- Checklist principal -->
<div id="checklist-wrap" style="display:none">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
        <!-- Columna izquierda: Documentación a reunir -->
        <div class="card">
            <div class="card-title" style="margin-bottom:16px">
                📁 Documentación a reunir
                <span id="prog-datos" style="float:right;font-size:12px;color:var(--muted);font-weight:400"></span>
            </div>
            <div id="lista-datos"></div>
        </div>

        <!-- Columna derecha: Presentaciones -->
        <div class="card">
            <div class="card-title" style="margin-bottom:16px">
                📋 Presentaciones
                <span id="prog-pres" style="float:right;font-size:12px;color:var(--muted);font-weight:400"></span>
            </div>
            <div id="lista-pres"></div>
        </div>
    </div>
</div>

<!-- Skeleton loader -->
<div id="checklist-loading" class="card" style="padding:48px;text-align:center">
    <div class="spinner" style="margin:0 auto"></div>
</div>

<!-- Modal confirmar eliminar -->
<div class="modal-overlay" id="modal-eliminar">
    <div class="modal" style="max-width:400px">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--red)">⚠ Eliminar sociedad</div>
            <button class="modal-close">✕</button>
        </div>
        <p style="color:var(--sub);font-size:14px;margin-bottom:16px">
            Se va a eliminar <strong style="color:var(--text)"><?= htmlspecialchars($soc['nombre']) ?></strong> y todo su historial de checklists.
        </p>
        <div style="display:flex;gap:10px;justify-content:flex-end">
            <button class="btn btn-secondary" onclick="closeModal('modal-eliminar')">Cancelar</button>
            <button class="btn btn-danger" id="btn-confirmar-eliminar">Sí, eliminar</button>
        </div>
    </div>
</div>

<style>
.item-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 8px;
    cursor: pointer;
    transition: background .12s;
    user-select: none;
    margin-bottom: 4px;
    border: 1px solid transparent;
}
.item-row:hover { background: var(--surface); }
.item-row.done  { background: rgba(16,185,129,.06); border-color: rgba(16,185,129,.2); }

.item-check {
    width: 22px; height: 22px; min-width: 22px;
    border-radius: 50%;
    border: 2px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
    transition: all .15s;
    background: var(--surface);
}
.item-row.done .item-check {
    background: #10b981;
    border-color: #10b981;
    color: #fff;
}
.item-row.loading .item-check { opacity: .4; }

.item-label {
    font-size: 14px;
    color: var(--text);
    flex: 1;
}
.item-row.done .item-label { color: #10b981; }
.item-date { font-size: 11px; color: var(--muted); font-family: 'Space Mono', monospace; }

.periodo-tab {
    padding: 6px 16px;
    border-radius: 20px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--sub);
    font-size: 13px;
    cursor: pointer;
    transition: all .15s;
    font-family: 'Space Mono', monospace;
}
.periodo-tab:hover, .periodo-tab.active {
    background: #6366f1;
    border-color: #6366f1;
    color: #fff;
}
</style>

<script>
const SOC_ID  = <?= $soc_id ?>;
const MES_INI = <?= (int)$soc['mes_inicio'] ?>;
const TIENE   = {
    auditoria: <?= $soc['tiene_auditoria'] ? 'true' : 'false' ?>,
    pj:        <?= $soc['tiene_pj']        ? 'true' : 'false' ?>,
    uif:       <?= $soc['tiene_uif']       ? 'true' : 'false' ?>,
};

const ITEMS_DATOS = [
    { key: 'ventas',        label: 'Ventas' },
    { key: 'compras',       label: 'Compras' },
    { key: 'sueldos',       label: 'Sueldos' },
    { key: 'cargas',        label: 'Cargas sociales' },
    { key: 'iibb',          label: 'IIBB' },
    { key: 'retenciones',   label: 'Retenciones' },
    { key: 'bcra',          label: 'BCRA' },
    { key: 'nuestra_parte', label: 'Nuestra parte' },
    { key: 'facilidades',   label: 'Mis facilidades' },
    { key: 'sigsa',         label: 'SIGSA' },
    { key: 'proveedores',   label: 'Proveedores' },
    { key: 'clientes',      label: 'Clientes' },
    { key: 'cheques',       label: 'Cheques en cartera' },
];

const ITEMS_PRES = [
    { key: 'ganancias',    label: 'Ganancias' },
    { key: 'balance',      label: 'Balance' },
    { key: 'pub',          label: 'PUB' },
    { key: 'notas_doc',    label: 'Notas' },
    { key: 'memoria',      label: 'Memoria' },
    { key: 'acta',         label: 'Acta' },
    { key: 'auditoria',    label: 'Informe de auditoría', cond: 'auditoria' },
    { key: 'cert_literal', label: 'Certificación literal' },
    { key: 'persona_jur',  label: 'Persona jurídica', cond: 'pj' },
    { key: 'uif',          label: 'UIF',               cond: 'uif' },
];

let periodoActivo = '';
let checkState    = {};
let toggling      = new Set();

// ── Calcular períodos ──────────────────────────────────
function getPeriodos() {
    const today = new Date();
    const m = today.getMonth() + 1;
    const y = today.getFullYear();
    const periodos = [];
    if (MES_INI === 1) {
        for (let i = 0; i < 4; i++) periodos.push(String(y - i));
    } else {
        const startYear = m >= MES_INI ? y : y - 1;
        for (let i = 0; i < 4; i++) {
            const sy = startYear - i;
            periodos.push(`${sy}-${sy+1}`);
        }
    }
    return periodos;
}

// ── Init ───────────────────────────────────────────────
window.addEventListener('load', () => {
    const periodos = getPeriodos();
    periodoActivo  = periodos[0];
    renderTabs(periodos);
    loadChecklist(periodoActivo);
});

function renderTabs(periodos) {
    document.getElementById('periodo-tabs').innerHTML = periodos.map((p, i) =>
        `<button class="periodo-tab ${i === 0 ? 'active' : ''}" onclick="cambiarPeriodo('${p}', this)">${p}</button>`
    ).join('');
}

function cambiarPeriodo(p, btn) {
    periodoActivo = p;
    document.querySelectorAll('.periodo-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadChecklist(p);
}

async function loadChecklist(periodo) {
    document.getElementById('checklist-wrap').style.display    = 'none';
    document.getElementById('checklist-loading').style.display = 'block';
    try {
        const res  = await fetch(`/sociedades/api/main.php?action=get_checklist&sociedad_id=${SOC_ID}&periodo=${periodo}`);
        const data = await res.json();
        if (data.error) { toast(data.error, 'error'); return; }
        checkState = data.checklist || {};
        renderChecklist();
    } catch(e) {
        toast('Error al cargar el checklist', 'error');
    } finally {
        document.getElementById('checklist-loading').style.display = 'none';
        document.getElementById('checklist-wrap').style.display    = 'block';
    }
}

// ── Render ─────────────────────────────────────────────
function renderChecklist() {
    const datosActivos = ITEMS_DATOS;
    const presActivos  = ITEMS_PRES.filter(it => !it.cond || TIENE[it.cond]);

    renderLista('lista-datos', datosActivos, 'prog-datos');
    renderLista('lista-pres',  presActivos,  'prog-pres');

    // Badge total
    const total = datosActivos.length + presActivos.length;
    const done  = [...datosActivos, ...presActivos].filter(it => checkState[it.key]?.completado).length;
    const pct   = total > 0 ? Math.round((done / total) * 100) : 0;
    const badge = document.getElementById('pct-badge');
    badge.textContent = `${done}/${total} — ${pct}%`;
    badge.style.display = 'inline-block';
    badge.style.background = pct === 100 ? 'rgba(16,185,129,.2)'  : 'rgba(99,102,241,.15)';
    badge.style.color      = pct === 100 ? '#10b981'              : '#818cf8';
}

function renderLista(containerId, items, progId) {
    const done  = items.filter(it => checkState[it.key]?.completado).length;
    document.getElementById(progId).textContent = `${done}/${items.length}`;

    document.getElementById(containerId).innerHTML = items.map(it => {
        const st    = checkState[it.key] || { completado: false, fecha: null };
        const cls   = st.completado ? 'done' : '';
        const fecha = st.completado && st.fecha ? formatFecha(st.fecha) : '';
        return `<div class="item-row ${cls}" id="item-${it.key}" onclick="toggleItem('${it.key}')">
            <div class="item-check">${st.completado ? '✓' : ''}</div>
            <div class="item-label">${escHtml(it.label)}</div>
            ${fecha ? `<div class="item-date">${fecha}</div>` : ''}
        </div>`;
    }).join('');
}

// ── Toggle ─────────────────────────────────────────────
async function toggleItem(key) {
    if (toggling.has(key)) return;
    toggling.add(key);

    const row       = document.getElementById('item-' + key);
    const current   = checkState[key]?.completado || false;
    const nuevo     = !current;

    // Optimistic update
    row.classList.add('loading');
    checkState[key] = { completado: nuevo, fecha: nuevo ? new Date().toISOString().slice(0,10) : null };
    renderChecklist();

    try {
        const res  = await fetch('/sociedades/api/main.php?action=toggle_item', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sociedad_id: SOC_ID, periodo: periodoActivo, item_key: key, completado: nuevo }),
        });
        const data = await res.json();
        if (data.error) {
            // Revert
            checkState[key] = { completado: current, fecha: current ? checkState[key]?.fecha : null };
            renderChecklist();
            toast(data.error, 'error');
        } else {
            checkState[key] = { completado: data.completado, fecha: data.fecha };
            renderChecklist();
        }
    } catch(e) {
        checkState[key] = { completado: current, fecha: null };
        renderChecklist();
        toast('Error al guardar', 'error');
    } finally {
        toggling.delete(key);
        const r = document.getElementById('item-' + key);
        if (r) r.classList.remove('loading');
    }
}

// ── Eliminar sociedad ──────────────────────────────────
function confirmarEliminar() { openModal('modal-eliminar'); }

document.getElementById('btn-confirmar-eliminar').addEventListener('click', async () => {
    const btn = document.getElementById('btn-confirmar-eliminar');
    btn.disabled = true; btn.textContent = 'Eliminando...';
    const res  = await fetch('/sociedades/api/main.php?action=eliminar_sociedad', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: SOC_ID }),
    });
    const data = await res.json();
    if (data.success) {
        toast('Sociedad eliminada', 'success');
        setTimeout(() => window.location.href = '/sociedades/index.php', 800);
    } else {
        btn.disabled = false; btn.textContent = 'Sí, eliminar';
        toast(data.error || 'Error', 'error');
    }
});

// ── Helpers ────────────────────────────────────────────
function formatFecha(iso) {
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}
function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
