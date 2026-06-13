<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Guías de uso';
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex-between mb-16">
    <p style="color:var(--sub);font-size:14px;margin:0">
        Documentá el paso a paso de cada herramienta para que todos sepan cómo usarla.
    </p>
    <button class="btn btn-primary" onclick="abrirCrear()">+ Nueva guía</button>
</div>

<!-- Grid de guías -->
<div id="guias-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
    <div style="grid-column:1/-1;text-align:center;padding:48px 0;color:var(--sub)">
        <div class="spinner" style="margin:0 auto 12px"></div>
        Cargando guías...
    </div>
</div>

<!-- Empty state (oculto por defecto) -->
<div id="empty-state" style="display:none;text-align:center;padding:64px 24px;color:var(--sub)">
    <div style="font-size:48px;margin-bottom:16px">📖</div>
    <div style="font-size:16px;font-weight:600;margin-bottom:8px">No hay guías todavía</div>
    <div style="font-size:13px;margin-bottom:24px">Creá la primera guía para empezar a documentar los módulos.</div>
    <button class="btn btn-primary" onclick="abrirCrear()">+ Crear primera guía</button>
</div>

<!-- ── MODAL: ver guía ──────────────────────────────── -->
<div class="modal-overlay" id="modal-ver">
    <div class="modal" style="width:620px;max-height:80vh;display:flex;flex-direction:column">
        <div class="modal-header">
            <div style="display:flex;align-items:center;gap:10px">
                <span id="ver-icono" style="font-size:22px"></span>
                <span class="modal-title" id="ver-titulo"></span>
            </div>
            <button class="modal-close">✕</button>
        </div>
        <div style="overflow-y:auto;padding:4px 0 8px" id="ver-pasos-wrap"></div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
            <button class="btn btn-secondary" id="ver-btn-editar">✏ Editar</button>
            <button class="btn btn-secondary" onclick="closeModal('modal-ver')">Cerrar</button>
        </div>
    </div>
</div>

<!-- ── MODAL: crear / editar ───────────────────────── -->
<div class="modal-overlay" id="modal-form">
    <div class="modal" style="width:640px;max-height:85vh;display:flex;flex-direction:column">
        <div class="modal-header">
            <span class="modal-title" id="form-titulo-label">Nueva guía</span>
            <button class="modal-close">✕</button>
        </div>

        <div style="overflow-y:auto;flex:1;padding-right:2px">
            <input type="hidden" id="form-id">

            <!-- Título + icono + color -->
            <div class="grid-2" style="margin-bottom:14px">
                <div class="form-group" style="margin:0">
                    <label class="form-label">Título *</label>
                    <input type="text" class="form-control" id="form-titulo" placeholder="Ej: Banco Provincia">
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">Ícono (emoji)</label>
                    <input type="text" class="form-control" id="form-icono" placeholder="📋" maxlength="4"
                           style="font-size:20px;text-align:center;cursor:pointer">
                </div>
            </div>

            <!-- Color -->
            <div class="form-group" style="margin-bottom:20px">
                <label class="form-label">Color de la tarjeta</label>
                <div style="display:flex;gap:10px;margin-top:4px" id="color-picker">
                    <label class="color-dot-wrap"><input type="radio" name="guia-color" value="blue" checked><span class="color-dot" style="background:#2563eb" title="Azul"></span></label>
                    <label class="color-dot-wrap"><input type="radio" name="guia-color" value="green"><span class="color-dot" style="background:#10b981" title="Verde"></span></label>
                    <label class="color-dot-wrap"><input type="radio" name="guia-color" value="amber"><span class="color-dot" style="background:#f59e0b" title="Amarillo"></span></label>
                    <label class="color-dot-wrap"><input type="radio" name="guia-color" value="purple"><span class="color-dot" style="background:#8b5cf6" title="Violeta"></span></label>
                    <label class="color-dot-wrap"><input type="radio" name="guia-color" value="red"><span class="color-dot" style="background:#ef4444" title="Rojo"></span></label>
                </div>
            </div>

            <!-- Pasos -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                <label class="form-label" style="margin:0">Pasos</label>
                <button type="button" class="btn btn-secondary btn-sm" onclick="agregarPaso()">+ Agregar paso</button>
            </div>

            <div id="pasos-editor" style="display:flex;flex-direction:column;gap:10px"></div>

            <div id="no-pasos-hint" style="text-align:center;padding:24px;color:var(--sub);font-size:13px;border:2px dashed var(--border);border-radius:8px;margin-top:4px">
                Hacé clic en "Agregar paso" para empezar a documentar.
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;padding-top:12px;border-top:1px solid var(--border)">
            <button class="btn btn-secondary" onclick="closeModal('modal-form')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarGuia()">Guardar guía</button>
        </div>
    </div>
</div>

<style>
/* Tarjetas de guías */
.guia-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    cursor: pointer;
    transition: all .18s;
    display: flex;
    flex-direction: column;
    gap: 10px;
    position: relative;
}
.guia-card:hover { transform: translateY(-2px); border-color: var(--bl); }
.guia-card-top { display:flex; align-items:flex-start; justify-content:space-between; gap:8px; }
.guia-icon { font-size: 28px; line-height:1; }
.guia-acciones { display:flex; gap:6px; opacity:0; transition: opacity .15s; }
.guia-card:hover .guia-acciones { opacity: 1; }
.guia-titulo { font-size:15px; font-weight:700; margin:0; }
.guia-meta { font-size:12px; color:var(--sub); }
.guia-steps-preview { font-size:12px; color:var(--muted); margin-top:2px; }
.guia-strip { height:3px; border-radius:0 0 10px 10px; position:absolute; bottom:0; left:0; right:0; }

/* Color strips */
.guia-strip.blue   { background: #2563eb; }
.guia-strip.green  { background: #10b981; }
.guia-strip.amber  { background: #f59e0b; }
.guia-strip.purple { background: #8b5cf6; }
.guia-strip.red    { background: #ef4444; }

/* Step list en modal ver */
.step-row {
    display: flex;
    gap: 14px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
    align-items: flex-start;
}
.step-row:last-child { border-bottom: none; }
.step-num {
    width: 26px; height: 26px; flex-shrink: 0;
    background: var(--accent); color: #fff;
    border-radius: 50%; font-size: 12px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
}
.step-text { font-size:14px; line-height:1.55; color: var(--text); padding-top:3px; }

/* Editor de pasos */
.paso-editor-row {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 10px 12px;
}
.paso-editor-num {
    width: 24px; height: 24px; flex-shrink: 0;
    background: var(--bl); color: var(--sub);
    border-radius: 50%; font-size: 11px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    margin-top: 3px;
}
.paso-editor-row textarea {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: var(--text);
    font-family: inherit;
    font-size: 13px;
    resize: vertical;
    min-height: 38px;
    line-height: 1.5;
}
.paso-editor-row textarea::placeholder { color: var(--muted); }
.paso-editor-row .btn-rm { flex-shrink:0; background:none; border:none; color:var(--muted); cursor:pointer; font-size:14px; padding:4px; transition: color .15s; }
.paso-editor-row .btn-rm:hover { color: var(--red); }

/* Color picker */
.color-dot-wrap { cursor:pointer; display:flex; align-items:center; }
.color-dot-wrap input { display:none; }
.color-dot { width:24px; height:24px; border-radius:50%; border:3px solid transparent; transition: all .15s; }
.color-dot-wrap input:checked + .color-dot { border-color: #fff; box-shadow: 0 0 0 3px var(--accent); }
.color-dot-wrap:hover .color-dot { transform: scale(1.15); }
</style>

<script>
let guias = [];
let editingId = null;

// ── Cargar guías ──────────────────────────────────────
async function cargarGuias() {
    const res  = await fetch('/pasos/api/main.php?action=listar');
    const data = await res.json();
    guias = data.data || [];
    renderGuias();
}

function renderGuias() {
    const grid  = document.getElementById('guias-grid');
    const empty = document.getElementById('empty-state');

    if (!guias.length) {
        grid.style.display  = 'none';
        empty.style.display = 'block';
        return;
    }

    grid.style.display  = '';
    empty.style.display = 'none';

    grid.innerHTML = guias.map(g => {
        const pasos = parsePasos(g.pasos);
        const n = pasos.length;
        return `
        <div class="guia-card" onclick="verGuia(${g.id})">
            <div class="guia-card-top">
                <span class="guia-icon">${escHtml(g.icono || '📋')}</span>
                <div class="guia-acciones" onclick="event.stopPropagation()">
                    <button class="btn btn-secondary btn-sm" onclick="editarGuia(${g.id})">✏</button>
                    <button class="btn btn-danger btn-sm" onclick="eliminarGuia(${g.id})">✕</button>
                </div>
            </div>
            <div>
                <h3 class="guia-titulo">${escHtml(g.titulo)}</h3>
                <div class="guia-meta">${n} paso${n !== 1 ? 's' : ''}</div>
                ${n > 0 ? `<div class="guia-steps-preview">▸ ${escHtml(pasos[0]).substring(0, 60)}${pasos[0].length > 60 ? '…' : ''}</div>` : ''}
            </div>
            <div class="guia-strip ${escHtml(g.color || 'blue')}"></div>
        </div>`;
    }).join('');
}

function parsePasos(raw) {
    try {
        const arr = typeof raw === 'string' ? JSON.parse(raw) : (raw || []);
        return Array.isArray(arr) ? arr.filter(s => String(s).trim()) : [];
    } catch { return []; }
}

// ── Ver guía ──────────────────────────────────────────
function verGuia(id) {
    const g = guias.find(x => x.id == id);
    if (!g) return;
    const pasos = parsePasos(g.pasos);

    document.getElementById('ver-icono').textContent  = g.icono || '📋';
    document.getElementById('ver-titulo').textContent = g.titulo;
    document.getElementById('ver-btn-editar').onclick = () => { closeModal('modal-ver'); editarGuia(id); };

    const wrap = document.getElementById('ver-pasos-wrap');
    if (!pasos.length) {
        wrap.innerHTML = '<div style="text-align:center;padding:32px;color:var(--sub);font-size:13px">Esta guía aún no tiene pasos cargados.</div>';
    } else {
        const colorMap = { blue:'#2563eb', green:'#10b981', amber:'#f59e0b', purple:'#8b5cf6', red:'#ef4444' };
        const col = colorMap[g.color] || '#2563eb';
        wrap.innerHTML = pasos.map((p, i) => `
            <div class="step-row">
                <div class="step-num" style="background:${col}">${i + 1}</div>
                <div class="step-text">${escHtml(p)}</div>
            </div>`).join('');
    }

    openModal('modal-ver');
}

// ── Crear / editar ────────────────────────────────────
function abrirCrear() {
    editingId = null;
    document.getElementById('form-id').value      = '';
    document.getElementById('form-titulo').value  = '';
    document.getElementById('form-icono').value   = '📋';
    document.querySelector('input[name="guia-color"][value="blue"]').checked = true;
    document.getElementById('form-titulo-label').textContent = 'Nueva guía';
    limpiarEditor();
    openModal('modal-form');
    document.getElementById('form-titulo').focus();
}

function editarGuia(id) {
    const g = guias.find(x => x.id == id);
    if (!g) return;
    editingId = id;

    document.getElementById('form-id').value     = id;
    document.getElementById('form-titulo').value = g.titulo;
    document.getElementById('form-icono').value  = g.icono || '📋';
    document.getElementById('form-titulo-label').textContent = 'Editar guía';

    const radio = document.querySelector(`input[name="guia-color"][value="${g.color}"]`);
    if (radio) radio.checked = true;
    else document.querySelector('input[name="guia-color"][value="blue"]').checked = true;

    limpiarEditor();
    const pasos = parsePasos(g.pasos);
    pasos.forEach(p => agregarPaso(p));

    openModal('modal-form');
}

function limpiarEditor() {
    document.getElementById('pasos-editor').innerHTML = '';
    actualizarHint();
}

let pasoCount = 0;
function agregarPaso(texto = '') {
    pasoCount++;
    const editor = document.getElementById('pasos-editor');
    const div    = document.createElement('div');
    div.className = 'paso-editor-row';
    div.dataset.orden = pasoCount;
    div.innerHTML = `
        <div class="paso-editor-num">${editor.children.length + 1}</div>
        <textarea rows="2" placeholder="Describir el paso...">${escHtml(texto)}</textarea>
        <button type="button" class="btn-rm" onclick="eliminarPasoEditor(this)" title="Eliminar paso">✕</button>
    `;
    editor.appendChild(div);
    actualizarHint();
    renumerarPasos();
    if (!texto) div.querySelector('textarea').focus();
}

function eliminarPasoEditor(btn) {
    btn.closest('.paso-editor-row').remove();
    renumerarPasos();
    actualizarHint();
}

function renumerarPasos() {
    document.querySelectorAll('#pasos-editor .paso-editor-num').forEach((el, i) => {
        el.textContent = i + 1;
    });
}

function actualizarHint() {
    const n = document.getElementById('pasos-editor').children.length;
    document.getElementById('no-pasos-hint').style.display = n === 0 ? 'block' : 'none';
}

async function guardarGuia() {
    const titulo = document.getElementById('form-titulo').value.trim();
    const icono  = document.getElementById('form-icono').value.trim() || '📋';
    const color  = document.querySelector('input[name="guia-color"]:checked')?.value || 'blue';
    const id     = document.getElementById('form-id').value;

    if (!titulo) { toast('El título es requerido', 'warning'); document.getElementById('form-titulo').focus(); return; }

    const pasos = [...document.querySelectorAll('#pasos-editor textarea')]
        .map(t => t.value.trim())
        .filter(Boolean);

    const res  = await fetch('/pasos/api/main.php?action=guardar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id ? parseInt(id) : 0, titulo, icono, color, pasos }),
    });
    const data = await res.json();
    if (data.error) { toast(data.error, 'error'); return; }

    toast(id ? 'Guía actualizada' : 'Guía creada', 'success');
    closeModal('modal-form');
    cargarGuias();
}

// ── Eliminar ──────────────────────────────────────────
function eliminarGuia(id) {
    const g = guias.find(x => x.id == id);
    confirmAction(`¿Eliminar la guía "${g?.titulo || ''}"?`, async () => {
        const res  = await fetch('/pasos/api/main.php?action=eliminar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });
        const data = await res.json();
        if (data.error) { toast(data.error, 'error'); return; }
        toast('Guía eliminada', 'success');
        cargarGuias();
    });
}

function escHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

cargarGuias();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
