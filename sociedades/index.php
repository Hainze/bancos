<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Sociedades';
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex-between mb-24">
    <p style="color:var(--sub);font-size:14px;margin:0">
        Seguimiento de balances y presentaciones por ejercicio. Tildá cada tarea completada.
    </p>
    <button class="btn btn-primary" onclick="abrirModal()">+ Nueva sociedad</button>
</div>

<!-- Grid de cards -->
<div id="soc-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
    <div class="card" style="display:flex;align-items:center;justify-content:center;padding:40px;color:var(--muted)">
        <div class="spinner"></div>
    </div>
</div>

<div id="soc-empty" style="display:none">
    <div class="card">
        <div class="empty-state" style="padding:64px">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">No hay sociedades cargadas aún</div>
            <button class="btn btn-primary" style="margin-top:16px" onclick="abrirModal()">+ Agregar primera sociedad</button>
        </div>
    </div>
</div>

<!-- Modal nueva/editar sociedad -->
<div class="modal-overlay" id="modal-soc">
    <div class="modal" style="max-width:500px">
        <div class="modal-header">
            <div class="modal-title" id="modal-titulo">Nueva sociedad</div>
            <button class="modal-close">✕</button>
        </div>
        <input type="hidden" id="soc-id" value="0">

        <div class="form-group">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control" id="soc-nombre" placeholder="Razón social">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="form-group">
                <label class="form-label">Tipo</label>
                <select class="form-control" id="soc-tipo">
                    <option value="SRL">SRL</option>
                    <option value="SA">SA</option>
                    <option value="SAS">SAS</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Inicio ejercicio</label>
                <select class="form-control" id="soc-mes">
                    <option value="1">Enero (Ene–Dic)</option>
                    <option value="2">Febrero (Feb–Ene)</option>
                    <option value="3">Marzo (Mar–Feb)</option>
                    <option value="4">Abril (Abr–Mar)</option>
                    <option value="5">Mayo (May–Abr)</option>
                    <option value="6">Junio (Jun–May)</option>
                    <option value="7">Julio (Jul–Jun)</option>
                    <option value="8">Agosto (Ago–Jul)</option>
                    <option value="9">Septiembre (Sep–Ago)</option>
                    <option value="10">Octubre (Oct–Sep)</option>
                    <option value="11">Noviembre (Nov–Oct)</option>
                    <option value="12">Diciembre (Dic–Nov)</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Presentaciones adicionales</label>
            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:4px">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
                    <input type="checkbox" id="soc-auditoria"> Informe de auditoría
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
                    <input type="checkbox" id="soc-pj"> Persona jurídica
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
                    <input type="checkbox" id="soc-uif"> UIF
                </label>
            </div>
        </div>

        <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Notas internas</label>
            <textarea class="form-control" id="soc-notas" rows="2" placeholder="Observaciones, contacto, etc."></textarea>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
            <button class="btn btn-secondary" onclick="closeModal('modal-soc')">Cancelar</button>
            <button class="btn btn-primary" id="btn-guardar-soc">Guardar</button>
        </div>
    </div>
</div>

<style>
.soc-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    cursor: pointer;
    transition: border-color .15s, transform .15s;
    text-decoration: none;
    display: block;
    color: inherit;
}
.soc-card:hover { border-color: #6366f1; transform: translateY(-2px); }

.soc-card-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 12px; }
.soc-nombre { font-size: 15px; font-weight: 700; color: var(--text); }
.soc-tipo   { font-size: 11px; font-weight: 700; font-family: 'Space Mono', monospace;
              background: rgba(99,102,241,.15); color: #818cf8;
              padding: 3px 8px; border-radius: 10px; }
.soc-periodo { font-size: 12px; color: var(--muted); margin-bottom: 14px; }

.prog-label { display: flex; justify-content: space-between; font-size: 12px; color: var(--sub); margin-bottom: 5px; }
.prog-bar   { height: 6px; background: var(--surface); border-radius: 3px; overflow: hidden; }
.prog-fill  { height: 100%; border-radius: 3px; transition: width .3s; }

.soc-card-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 14px; }
.soc-edit-btn { font-size: 12px; color: var(--muted); background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 6px; }
.soc-edit-btn:hover { background: var(--surface); color: var(--text); }
</style>

<script>
window.addEventListener('load', loadSociedades);

async function loadSociedades() {
    try {
        const res  = await fetch('/sociedades/api/main.php?action=listar_sociedades');
        const data = await res.json();
        if (data.error) { toast(data.error, 'error'); return; }
        renderGrid(data.data || []);
    } catch(e) {
        toast('Error al cargar las sociedades', 'error');
    }
}

function renderGrid(socs) {
    const grid  = document.getElementById('soc-grid');
    const empty = document.getElementById('soc-empty');

    if (!socs.length) {
        grid.style.display  = 'none';
        empty.style.display = 'block';
        return;
    }
    empty.style.display = 'none';
    grid.style.display  = 'grid';

    grid.innerHTML = socs.map(s => {
        const pct     = s.total_items > 0 ? Math.round((s.completados / s.total_items) * 100) : 0;
        const color   = pct === 100 ? '#10b981' : pct >= 50 ? '#6366f1' : pct > 0 ? '#f59e0b' : '#4a5f7a';
        const meses   = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        const mesLabel = meses[s.mes_inicio] || '';

        return `<a class="soc-card" href="/sociedades/sociedad.php?id=${s.id}">
            <div class="soc-card-top">
                <div class="soc-nombre">${escHtml(s.nombre)}</div>
                <span class="soc-tipo">${escHtml(s.tipo)}</span>
            </div>
            <div class="soc-periodo">Ejercicio ${s.periodo_actual} · Inicio ${mesLabel}</div>
            <div class="prog-label">
                <span>Progreso ${s.periodo_actual}</span>
                <span style="color:${color};font-weight:700">${s.completados}/${s.total_items}</span>
            </div>
            <div class="prog-bar">
                <div class="prog-fill" style="width:${pct}%;background:${color}"></div>
            </div>
            <div class="soc-card-footer">
                <span style="font-size:11px;color:var(--muted)">${pct === 100 ? '✓ Completo' : pct === 0 ? 'Sin iniciar' : 'En progreso'}</span>
                <button class="soc-edit-btn" onclick="event.preventDefault();event.stopPropagation();editarSoc(${s.id})">✎ Editar</button>
            </div>
        </a>`;
    }).join('');
}

// ── Modal ────────────────────────────────────────────────
function abrirModal(soc = null) {
    document.getElementById('soc-id').value         = soc?.id || 0;
    document.getElementById('soc-nombre').value     = soc?.nombre || '';
    document.getElementById('soc-tipo').value       = soc?.tipo || 'SRL';
    document.getElementById('soc-mes').value        = soc?.mes_inicio || 1;
    document.getElementById('soc-auditoria').checked= !!soc?.tiene_auditoria;
    document.getElementById('soc-pj').checked       = !!soc?.tiene_pj;
    document.getElementById('soc-uif').checked      = !!soc?.tiene_uif;
    document.getElementById('soc-notas').value      = soc?.notas || '';
    document.getElementById('modal-titulo').textContent = soc ? 'Editar sociedad' : 'Nueva sociedad';
    openModal('modal-soc');
    document.getElementById('soc-nombre').focus();
}

let _editCache = {};

async function editarSoc(id) {
    if (!_editCache[id]) {
        const res  = await fetch(`/sociedades/api/main.php?action=listar_sociedades`);
        const data = await res.json();
        (data.data || []).forEach(s => { _editCache[s.id] = s; });
    }
    abrirModal(_editCache[id]);
}

document.getElementById('btn-guardar-soc').addEventListener('click', async () => {
    const nombre = document.getElementById('soc-nombre').value.trim();
    if (!nombre) { toast('Ingresá el nombre', 'warning'); return; }
    const btn = document.getElementById('btn-guardar-soc');
    btn.disabled = true;
    const res  = await fetch('/sociedades/api/main.php?action=guardar_sociedad', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id:              parseInt(document.getElementById('soc-id').value),
            nombre,
            tipo:            document.getElementById('soc-tipo').value,
            mes_inicio:      parseInt(document.getElementById('soc-mes').value),
            tiene_auditoria: document.getElementById('soc-auditoria').checked,
            tiene_pj:        document.getElementById('soc-pj').checked,
            tiene_uif:       document.getElementById('soc-uif').checked,
            notas:           document.getElementById('soc-notas').value.trim(),
        }),
    });
    const data = await res.json();
    btn.disabled = false;
    if (data.error) { toast(data.error, 'error'); return; }
    _editCache = {};
    closeModal('modal-soc');
    toast('Sociedad guardada', 'success');
    loadSociedades();
});

function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
