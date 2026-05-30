<?php
require_once dirname(__DIR__) . '/auth/check.php';
if (empty($_SESSION['fact_cliente_id'])) {
    header('Location: /facturacion/index.php');
    exit;
}
$titulo        = 'Declaraciones Juradas';
$clienteId     = (int)$_SESSION['fact_cliente_id'];
$clienteNombre = $_SESSION['fact_cliente_nombre'] ?? '';
require_once __DIR__ . '/includes/header.php';

$anioActual = (int)date('Y');
$mesActual  = (int)date('n');
$mesesNombres = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
?>

<div class="grid-2" style="gap:24px">

    <!-- ── IZQUIERDA: Carga ── -->
    <div>
        <div class="card mb-24">
            <div class="card-title">Cargar Archivo</div>

            <div style="font-size:13px;color:var(--text-secondary);margin-bottom:20px">
                Cliente: <strong style="color:var(--text-primary)"><?= htmlspecialchars($clienteNombre) ?></strong>
            </div>

            <!-- Tipo -->
            <div style="margin-bottom:16px">
                <label class="form-label">Tipo de Declaración *</label>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px" id="tipo-selector">
                    <button type="button" class="tipo-btn" data-tipo="iibb"
                        style="padding:14px 10px;border-radius:8px;border:2px solid rgba(245,158,11,0.3);background:rgba(245,158,11,0.08);cursor:pointer;font-family:inherit;transition:all 0.15s">
                        <div style="font-size:20px;margin-bottom:4px">📋</div>
                        <div style="font-size:13px;font-weight:700;color:#f59e0b">IIBB</div>
                        <div style="font-size:11px;color:var(--text-secondary)">Ingresos Brutos</div>
                    </button>
                    <button type="button" class="tipo-btn" data-tipo="931"
                        style="padding:14px 10px;border-radius:8px;border:2px solid rgba(16,185,129,0.3);background:rgba(16,185,129,0.08);cursor:pointer;font-family:inherit;transition:all 0.15s">
                        <div style="font-size:20px;margin-bottom:4px">👥</div>
                        <div style="font-size:13px;font-weight:700;color:#10b981">F.931</div>
                        <div style="font-size:11px;color:var(--text-secondary)">Cargas Sociales</div>
                    </button>
                    <button type="button" class="tipo-btn" data-tipo="portal"
                        style="padding:14px 10px;border-radius:8px;border:2px solid rgba(139,92,246,0.3);background:rgba(139,92,246,0.08);cursor:pointer;font-family:inherit;transition:all 0.15s">
                        <div style="font-size:20px;margin-bottom:4px">🧾</div>
                        <div style="font-size:13px;font-weight:700;color:#a78bfa">Portal IVA</div>
                        <div style="font-size:11px;color:var(--text-secondary)">DDJJ IVA</div>
                    </button>
                </div>
                <input type="hidden" id="inp-tipo" value="">
            </div>

            <!-- Período -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
                <div>
                    <label class="form-label">Mes *</label>
                    <select id="inp-mes" class="form-input" style="width:100%">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m === $mesActual ? 'selected' : '' ?>><?= $mesesNombres[$m] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Año *</label>
                    <select id="inp-anio" class="form-input" style="width:100%">
                        <?php for ($a = $anioActual; $a >= $anioActual - 5; $a--): ?>
                        <option value="<?= $a ?>" <?= $a === $anioActual ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <!-- Upload zone -->
            <div style="margin-bottom:16px">
                <label class="form-label">Archivo PDF *</label>
                <div class="upload-zone" id="upload-zone" style="padding:24px">
                    <input type="file" id="file-input" accept=".pdf,application/pdf">
                    <div class="upload-icon">📄</div>
                    <div class="upload-title" style="font-size:14px">Arrastrar el PDF aquí</div>
                    <div class="upload-sub">o hacer click para seleccionar — solo .pdf</div>
                </div>
                <div id="file-selected" style="display:none;margin-top:8px" class="alert alert-success">
                    <span>✓</span>
                    <span id="file-name">—</span>
                </div>
            </div>

            <div style="display:flex;gap:10px;align-items:center">
                <button class="btn btn-danger btn-lg" id="btn-guardar" disabled>
                    <span id="btn-txt">Guardar Archivo</span>
                    <span id="btn-spinner" class="spinner" style="display:none"></span>
                </button>
                <button class="btn btn-secondary" id="btn-limpiar" style="display:none">✕ Limpiar</button>
            </div>
        </div>

        <!-- Info -->
        <div class="card" style="border-color:rgba(239,68,68,0.15)">
            <div class="card-title" style="color:var(--red)">Tipos de archivo</div>
            <div style="display:flex;flex-direction:column;gap:14px;font-size:13px">
                <div style="display:flex;gap:12px;align-items:flex-start">
                    <span class="tipo-badge tipo-iibb">IIBB</span>
                    <div>
                        <div style="font-weight:600">Declaración ARBA (R-606M)</div>
                        <div style="color:var(--text-secondary);font-size:12px">Ingresos Brutos mensual. Archivo del portal ARBA con datos de ingresos, alícuotas y deducciones.</div>
                    </div>
                </div>
                <div style="display:flex;gap:12px;align-items:flex-start">
                    <span class="tipo-badge tipo-931">F.931</span>
                    <div>
                        <div style="font-weight:600">Formulario 931 SUSS</div>
                        <div style="color:var(--text-secondary);font-size:12px">Cargas sociales mensuales (ARCA/ANSES). Declaración de aportes, contribuciones y LRT.</div>
                    </div>
                </div>
                <div style="display:flex;gap:12px;align-items:flex-start">
                    <span class="tipo-badge tipo-portal">Portal IVA</span>
                    <div>
                        <div style="font-weight:600">Portal IVA — DDJJ</div>
                        <div style="color:var(--text-secondary);font-size:12px">Vista previa de la DDJJ de IVA del Portal ARCA con débito fiscal, crédito fiscal y saldo.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── DERECHA: Historial ── -->
    <div>
        <div class="card">
            <div class="flex-between mb-16">
                <div class="card-title" style="margin:0">Archivos cargados</div>
                <div style="display:flex;gap:8px;align-items:center">
                    <select id="fil-tipo" class="form-input" style="width:130px">
                        <option value="">Todos los tipos</option>
                        <option value="iibb">IIBB</option>
                        <option value="931">F.931</option>
                        <option value="portal">Portal IVA</option>
                    </select>
                    <select id="fil-anio" class="form-input" style="width:90px">
                        <option value="">Todos</option>
                        <?php for ($a = $anioActual; $a >= $anioActual - 5; $a--): ?>
                        <option value="<?= $a ?>" <?= $a === $anioActual ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endfor; ?>
                    </select>
                    <button class="btn btn-secondary btn-sm" id="btn-refresh">↺</button>
                    <button class="btn btn-danger btn-sm" onclick="openModal('modal-reset-imp')">⚠ Eliminar todo</button>
                </div>
            </div>

            <!-- Mapa visual de períodos -->
            <div id="mapa-periodos" style="margin-bottom:20px"></div>

            <!-- Tabla -->
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Período</th>
                            <th>Tipo</th>
                            <th>Archivo</th>
                            <th style="text-align:right">Tamaño</th>
                            <th>Cargado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="archivos-body">
                        <tr><td colspan="6"><div class="empty-state"><div class="spinner" style="margin:0 auto"></div></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal eliminar -->
<div class="modal-overlay" id="modal-eliminar">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--red)">⚠ Eliminar archivo</div>
            <button class="modal-close">✕</button>
        </div>
        <p style="color:var(--text-secondary);font-size:14px;margin-bottom:24px">
            El archivo será eliminado permanentemente. Esta acción no se puede deshacer.
        </p>
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

.tipo-btn:hover { filter:brightness(1.2); }
.tipo-btn.selected { outline:3px solid currentColor; outline-offset:2px; }

.periodo-chip {
    display:inline-flex;align-items:center;gap:4px;padding:4px 8px;border-radius:6px;
    font-size:11px;font-family:'Space Mono',monospace;font-weight:600;margin:3px;
    background:var(--bg-hover);border:1px solid var(--border);color:var(--text-secondary);cursor:default;
}
.periodo-chip.has-iibb   { border-color:rgba(245,158,11,0.4); }
.periodo-chip.has-931    { border-color:rgba(16,185,129,0.4); }
.periodo-chip.has-portal { border-color:rgba(139,92,246,0.4); }
.chip-dot { width:6px;height:6px;border-radius:50%;display:inline-block; }
</style>

<script>
const CLIENTE_ID    = <?= $clienteId ?>;
const MESES_NOMBRES = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const TIPOS = {
    'iibb':   { label:'IIBB',      color:'#f59e0b', bg:'rgba(245,158,11,0.15)'  },
    '931':    { label:'F.931',     color:'#10b981', bg:'rgba(16,185,129,0.15)'  },
    'portal': { label:'Portal IVA',color:'#a78bfa', bg:'rgba(139,92,246,0.15)' },
};
let currentFile = null;
let archivoAEliminar = null;

// ── Tipo selector ─────────────────────────────────────────────────────────
document.querySelectorAll('.tipo-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tipo-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        document.getElementById('inp-tipo').value = btn.dataset.tipo;
        checkCanSubmit();
    });
});

// ── Upload zone ───────────────────────────────────────────────────────────
initDropZone('upload-zone', file => {
    if (!file.name.toLowerCase().endsWith('.pdf') && file.type !== 'application/pdf') {
        toast('Solo se aceptan archivos PDF', 'error'); return;
    }
    setFile(file);
});

function setFile(file) {
    currentFile = file;
    const kb = (file.size / 1024).toFixed(1);
    document.getElementById('file-name').textContent = file.name + ' (' + kb + ' KB)';
    document.getElementById('file-selected').style.display = 'flex';
    document.getElementById('btn-limpiar').style.display   = 'inline-flex';
    checkCanSubmit();
}

function checkCanSubmit() {
    const tipo = document.getElementById('inp-tipo').value;
    document.getElementById('btn-guardar').disabled = !(tipo && currentFile);
}

document.getElementById('btn-limpiar').addEventListener('click', () => {
    currentFile = null;
    document.getElementById('file-input').value = '';
    document.getElementById('file-selected').style.display = 'none';
    document.getElementById('btn-limpiar').style.display   = 'none';
    checkCanSubmit();
});

// ── Guardar ───────────────────────────────────────────────────────────────
document.getElementById('btn-guardar').addEventListener('click', async () => {
    const tipo = document.getElementById('inp-tipo').value;
    const mes  = document.getElementById('inp-mes').value;
    const anio = document.getElementById('inp-anio').value;
    if (!tipo || !currentFile) return;

    const btn = document.getElementById('btn-guardar');
    btn.disabled = true;
    document.getElementById('btn-txt').style.display    = 'none';
    document.getElementById('btn-spinner').style.display = 'inline-block';

    const form = new FormData();
    form.append('cliente_id', CLIENTE_ID);
    form.append('tipo',       tipo);
    form.append('mes',        mes);
    form.append('anio',       anio);
    form.append('archivo',    currentFile);

    try {
        const res  = await fetch('/api/impuestos.php?action=archivos_guardar', { method: 'POST', body: form });
        const data = await res.json();
        if (data.error) { toast(data.error, 'error'); return; }
        if (data.reemplazado)
            toast('⚠ Se reemplazó el archivo anterior para este período', 'warning');
        else
            toast('✓ Archivo guardado correctamente', 'success');
        // Reset file only
        currentFile = null;
        document.getElementById('file-input').value = '';
        document.getElementById('file-selected').style.display = 'none';
        document.getElementById('btn-limpiar').style.display   = 'none';
        checkCanSubmit();
        cargarArchivos();
    } catch (e) {
        toast('Error: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        document.getElementById('btn-txt').style.display    = 'inline';
        document.getElementById('btn-spinner').style.display = 'none';
        checkCanSubmit();
    }
});

// ── Cargar archivos ───────────────────────────────────────────────────────
async function cargarArchivos() {
    const anio = document.getElementById('fil-anio').value;
    const tipo = document.getElementById('fil-tipo').value;
    let url = `/api/impuestos.php?action=archivos_listar&cliente_id=${CLIENTE_ID}`;
    if (anio) url += `&anio=${anio}`;
    if (tipo) url += `&tipo=${tipo}`;

    const res  = await fetch(url);
    const data = await res.json();
    renderArchivos(data.data || []);
    renderMapa(data.data || []);
}

function renderArchivos(lista) {
    const tbody = document.getElementById('archivos-body');
    if (!lista.length) {
        tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state">
            <div class="empty-state-icon">📁</div>
            <div class="empty-state-text">No hay archivos cargados para este cliente</div>
        </div></td></tr>`;
        return;
    }
    tbody.innerHTML = lista.map(a => {
        const t = TIPOS[a.tipo] || { label: a.tipo, color:'#888', bg:'rgba(136,136,136,0.1)' };
        const kb = (parseInt(a.tamano)/1024).toFixed(0) + ' KB';
        const fecha = new Date(a.created_at).toLocaleDateString('es-AR');
        return `<tr>
            <td class="mono" style="font-weight:600;font-size:12px">${MESES_NOMBRES[parseInt(a.mes)]} ${a.anio}</td>
            <td><span class="tipo-badge tipo-${a.tipo}">${t.label}</span></td>
            <td style="font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(a.nombre_original)}">${escHtml(a.nombre_original)}</td>
            <td class="mono" style="text-align:right;font-size:11px;color:var(--text-secondary)">${kb}</td>
            <td class="mono" style="font-size:11px;color:var(--text-secondary)">${fecha}</td>
            <td style="display:flex;gap:6px">
                <a href="/api/impuestos.php?action=archivos_descargar&id=${a.id}" target="_blank"
                   class="btn btn-success btn-sm">⬇ Ver</a>
                <button class="btn btn-danger btn-sm" onclick="pedirEliminar(${a.id})">✕</button>
            </td>
        </tr>`;
    }).join('');
}

function renderMapa(lista) {
    // Group by period to show which types are covered
    const map = {};
    lista.forEach(a => {
        const key = `${a.anio}-${String(a.mes).padStart(2,'0')}`;
        if (!map[key]) map[key] = { anio: a.anio, mes: parseInt(a.mes), tipos: [] };
        map[key].tipos.push(a.tipo);
    });
    const sorted = Object.values(map).sort((a,b) => b.anio - a.anio || b.mes - a.mes);
    const cont = document.getElementById('mapa-periodos');
    if (!sorted.length) { cont.innerHTML = ''; return; }
    cont.innerHTML = `
        <div style="font-size:11px;color:var(--text-secondary);margin-bottom:8px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px">Períodos con archivos</div>
        <div style="display:flex;flex-wrap:wrap;gap:4px">` +
        sorted.map(p => {
            const classes = p.tipos.map(t => 'has-' + t).join(' ');
            const dots = p.tipos.map(t => {
                const col = TIPOS[t]?.color || '#888';
                return `<span class="chip-dot" style="background:${col}"></span>`;
            }).join('');
            return `<span class="periodo-chip ${classes}">${dots}${MESES_NOMBRES[p.mes].slice(0,3)} ${p.anio}</span>`;
        }).join('') +
        `</div>`;
}

function pedirEliminar(id) { archivoAEliminar = id; openModal('modal-eliminar'); }

document.getElementById('btn-confirmar-elim').addEventListener('click', async () => {
    if (!archivoAEliminar) return;
    const btn = document.getElementById('btn-confirmar-elim');
    btn.disabled = true; btn.textContent = 'Eliminando…';
    const res  = await fetch('/api/impuestos.php?action=archivos_eliminar', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ id: archivoAEliminar })
    });
    const data = await res.json();
    closeModal('modal-eliminar');
    if (data.success) { toast('Archivo eliminado', 'success'); cargarArchivos(); }
    else toast(data.error || 'Error', 'error');
    btn.disabled = false; btn.textContent = 'Eliminar';
    archivoAEliminar = null;
});

document.getElementById('btn-refresh').addEventListener('click', cargarArchivos);
document.getElementById('fil-anio').addEventListener('change', cargarArchivos);
document.getElementById('fil-tipo').addEventListener('change', cargarArchivos);

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

cargarArchivos();

document.getElementById('btn-confirmar-reset-imp')?.addEventListener('click', async () => {
    const btn = document.getElementById('btn-confirmar-reset-imp');
    btn.disabled = true; btn.textContent = 'Eliminando...';
    const res  = await fetch('/api/impuestos.php?action=eliminar_todo', { method: 'POST' });
    const data = await res.json();
    closeModal('modal-reset-imp');
    btn.disabled = false; btn.textContent = 'Sí, eliminar todo';
    if (data.success) { toast('Todos los archivos eliminados', 'success'); cargarArchivos(); }
    else toast(data.error || 'Error', 'error');
});
</script>

<div class="modal-overlay" id="modal-reset-imp">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--red)">⚠ Eliminar todos los archivos de Impuestos</div>
            <button class="modal-close">✕</button>
        </div>
        <p style="color:var(--sub);font-size:14px;margin-bottom:16px">
            Se van a eliminar <strong style="color:var(--text)">todos los archivos de declaraciones juradas</strong> cargados.
        </p>
        <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);border-radius:8px;padding:12px 16px;margin-bottom:24px;font-size:13px;color:var(--sub)">
            Esta acción <strong style="color:var(--red)">no se puede deshacer</strong>.
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end">
            <button class="btn btn-secondary" onclick="closeModal('modal-reset-imp')">Cancelar</button>
            <button class="btn btn-danger" id="btn-confirmar-reset-imp">Sí, eliminar todo</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
