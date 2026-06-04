<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Observaciones de Proveedores';
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex-between mb-16">
    <div>
        <p style="color:var(--sub);font-size:14px;margin:0">
            Asigná observaciones a proveedores específicos: forma de pago, cuenta bancaria, notas especiales, etc.
            Se agregan automáticamente al exportar el Excel.
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <button class="btn btn-secondary btn-sm" onclick="openModal('modal-import')">⬆ Importar Excel</button>
        <button class="btn btn-primary" onclick="abrirModal()">+ Nueva observación</button>
    </div>
</div>

<!-- Filtros -->
<div class="filter-bar" style="margin-bottom:16px">
    <div class="filter-bar-body">
        <div class="filter-field">
            <label>Sistema</label>
            <select class="form-control" id="filtro-sistema" onchange="loadPadrones()">
                <option value="">Todos</option>
                <option value="1">Sistema 1 (blanco)</option>
                <option value="2">Sistema 2 (negro)</option>
            </select>
        </div>
        <div class="filter-field" style="flex:1">
            <label>Buscar por código o observación</label>
            <input type="text" class="form-control" id="filtro-buscar" placeholder="Código, observación..." oninput="debounce()">
        </div>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Sistema</th>
                    <th>Código</th>
                    <th>Observación</th>
                    <th>Creado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-padrones">
                <tr><td colspan="5"><div class="empty-state"><div class="spinner"></div></div></td></tr>
            </tbody>
        </table>
    </div>
    <div id="pag-padrones" style="display:flex;align-items:center;gap:8px;margin-top:12px;font-size:13px;color:var(--sub)"></div>
</div>

<!-- Modal alta/edición -->
<div class="modal-overlay" id="modal-padron">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modal-title">Nueva observación</span>
            <button class="modal-close">✕</button>
        </div>
        <input type="hidden" id="padron-id">

        <div class="grid-2">
            <div class="form-group">
                <label class="form-label">Sistema *</label>
                <select class="form-control" id="padron-sistema">
                    <option value="1">Sistema 1 (blanco)</option>
                    <option value="2">Sistema 2 (negro)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Código del proveedor *</label>
                <input type="text" class="form-control" id="padron-codigo" placeholder="Ej: P-4230">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Observación *</label>
            <input type="text" class="form-control" id="padron-obs" placeholder="Ej: Se paga por Credicoop · Pagar los 5 del mes">
            <small style="color:var(--sub);font-size:11px;margin-top:4px;display:block">
                Ejemplos: "Se paga por Credicoop", "Pagar antes del 10", "Contactar a Juan", "En disputa legal"
            </small>
        </div>

        <div class="flex-gap gap-8" style="justify-content:flex-end;margin-top:8px">
            <button class="btn btn-secondary" onclick="closeModal('modal-padron')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarPadron()">Guardar</button>
        </div>
    </div>
</div>

<!-- Modal import masivo -->
<div class="modal-overlay" id="modal-import">
    <div class="modal" style="width:540px">
        <div class="modal-header">
            <span class="modal-title">Importar observaciones desde Excel</span>
            <button class="modal-close">✕</button>
        </div>
        <div class="alert alert-info" style="background:rgba(37,99,235,.08);border:1px solid rgba(37,99,235,.3);border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:16px">
            El Excel debe tener estas columnas: <strong>sistema · codigo · observacion</strong><br>
            Primera fila = encabezado. Si el código ya existe, se actualiza la observación.
        </div>
        <div class="upload-zone" id="upload-import" style="padding:24px">
            <input type="file" id="import-input" accept=".xlsx,.xls">
            <div class="upload-icon" style="font-size:28px">📋</div>
            <div class="upload-text" style="font-size:14px">Seleccionar Excel</div>
        </div>
        <div id="import-preview" style="display:none;margin-top:12px;font-size:13px;color:var(--green)"></div>
        <div class="flex-gap gap-8" style="justify-content:flex-end;margin-top:12px">
            <button class="btn btn-secondary" onclick="closeModal('modal-import')">Cancelar</button>
            <button class="btn btn-success" id="btn-confirmar-import" disabled onclick="confirmarImport()">Importar</button>
        </div>
    </div>
</div>

<style>
.upload-zone { border:2px dashed var(--border);border-radius:8px;padding:24px;text-align:center;cursor:pointer;background:var(--surface);transition:all .2s; }
.upload-zone:hover { border-color:var(--accent); }
</style>

<script>
let padronesPage = 1;
let debounceTimer = null;
let importData = null;

function debounce() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadPadrones(1), 350);
}

async function loadPadrones(page = 1) {
    padronesPage = page;
    const sistema = document.getElementById('filtro-sistema').value;
    const buscar  = document.getElementById('filtro-buscar').value;
    const params  = new URLSearchParams({ action:'listar', page, sistema, q:buscar });
    const res     = await fetch('/proveedores/api/padrones.php?' + params);
    const data    = await res.json();

    const tbody = document.getElementById('tbody-padrones');
    if (!data.data?.length) {
        tbody.innerHTML = `<tr><td colspan="5"><div class="empty-state">
            <div class="empty-state-icon">◎</div>
            <div class="empty-state-text">No hay observaciones. Creá la primera.</div>
        </div></td></tr>`;
        document.getElementById('pag-padrones').innerHTML = '';
        return;
    }

    tbody.innerHTML = data.data.map(p => `<tr>
        <td><span class="badge ${p.sistema==1?'badge-blue':'badge-red'}">${p.sistema==1?'Sistema 1':'Sistema 2'}</span></td>
        <td class="mono">${p.codigo}</td>
        <td style="max-width:320px">${escHtml(p.observacion)}</td>
        <td class="mono" style="font-size:11px">${new Date(p.created_at).toLocaleDateString('es-AR')}</td>
        <td>
            <div class="flex-gap gap-8">
                <button class="btn btn-secondary btn-sm" onclick="editarPadron(${p.id},${p.sistema},'${p.codigo}','${escAttr(p.observacion)}')">Editar</button>
                <button class="btn btn-danger btn-sm" onclick="eliminarPadron(${p.id})">Eliminar</button>
            </div>
        </td>
    </tr>`).join('');

    const totalPages = Math.ceil(data.total / data.limit);
    let pag = `<span>${data.total} registros</span>`;
    if (totalPages > 1) {
        if (page > 1) pag += `<a class="page-btn" onclick="loadPadrones(${page-1})">‹</a>`;
        for (let p = Math.max(1,page-2); p <= Math.min(totalPages,page+2); p++) {
            pag += `<a class="page-btn ${p===page?'active':''}" onclick="loadPadrones(${p})">${p}</a>`;
        }
        if (page < totalPages) pag += `<a class="page-btn" onclick="loadPadrones(${page+1})">›</a>`;
    }
    document.getElementById('pag-padrones').innerHTML = pag;
}

function abrirModal() {
    document.getElementById('padron-id').value      = '';
    document.getElementById('padron-sistema').value = '1';
    document.getElementById('padron-codigo').value  = '';
    document.getElementById('padron-obs').value     = '';
    document.getElementById('modal-title').textContent = 'Nueva observación';
    openModal('modal-padron');
}

function editarPadron(id, sistema, codigo, obs) {
    document.getElementById('padron-id').value      = id;
    document.getElementById('padron-sistema').value = sistema;
    document.getElementById('padron-codigo').value  = codigo;
    document.getElementById('padron-obs').value     = obs;
    document.getElementById('modal-title').textContent = 'Editar observación';
    openModal('modal-padron');
}

async function guardarPadron() {
    const body = {
        id:          document.getElementById('padron-id').value,
        sistema:     document.getElementById('padron-sistema').value,
        codigo:      document.getElementById('padron-codigo').value.trim(),
        observacion: document.getElementById('padron-obs').value.trim(),
    };
    if (!body.codigo || !body.observacion) { toast('Completá código y observación', 'warning'); return; }
    const res  = await fetch('/proveedores/api/padrones.php?action=guardar', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body),
    });
    const data = await res.json();
    if (data.error) { toast(data.error, 'error'); return; }
    toast(data.msg || 'Guardado', 'success');
    closeModal('modal-padron');
    loadPadrones(padronesPage);
}

async function eliminarPadron(id) {
    confirmAction('¿Eliminar esta observación?', async () => {
        const res  = await fetch('/proveedores/api/padrones.php?action=eliminar', {
            method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id}),
        });
        const data = await res.json();
        if (data.error) { toast(data.error, 'error'); return; }
        toast('Eliminado', 'success');
        loadPadrones(padronesPage);
    });
}

// Import masivo
initDropZone('upload-import', async file => {
    const reader = new FileReader();
    reader.onload = e => {
        const wb   = XLSX.read(e.target.result, { type:'array' });
        const ws   = wb.Sheets[wb.SheetNames[0]];
        const rows = XLSX.utils.sheet_to_json(ws, { header:1, defval:'' });
        if (rows.length < 2) { toast('El archivo está vacío', 'warning'); return; }

        const hdr  = rows[0].map(h => String(h).trim().toLowerCase());
        const iSis = hdr.findIndex(h => h.includes('sistema'));
        const iCod = hdr.findIndex(h => h.includes('codigo') || h.includes('código'));
        const iObs = hdr.findIndex(h => h.includes('obs'));
        if (iSis < 0 || iCod < 0 || iObs < 0) {
            toast('Faltan columnas: sistema, codigo, observacion', 'error'); return;
        }

        importData = [];
        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            const cod = String(row[iCod] ?? '').trim();
            const obs = String(row[iObs] ?? '').trim();
            if (!cod || !obs) continue;
            const sis = String(row[iSis] ?? '').includes('2') ? 2 : 1;
            importData.push({ sistema:sis, codigo:cod, observacion:obs });
        }

        document.getElementById('import-preview').style.display = 'block';
        document.getElementById('import-preview').textContent   = `✓ ${importData.length} registros listos para importar`;
        document.getElementById('btn-confirmar-import').disabled = false;
    };
    reader.readAsArrayBuffer(file);
});

async function confirmarImport() {
    if (!importData?.length) return;
    const res  = await fetch('/proveedores/api/padrones.php?action=importar', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({registros:importData}),
    });
    const data = await res.json();
    if (data.error) { toast(data.error, 'error'); return; }
    toast(`✓ ${data.insertados} registros importados / actualizados`, 'success');
    closeModal('modal-import');
    importData = null;
    document.getElementById('import-preview').style.display = 'none';
    document.getElementById('btn-confirmar-import').disabled = true;
    loadPadrones(1);
}

function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escAttr(s) { return String(s||'').replace(/'/g,"\\'").replace(/"/g,'&quot;'); }

loadPadrones();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
