<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Procesar ARCA';
require_once __DIR__ . '/includes/header.php';
?>

<div class="grid-2" style="gap:24px">

    <!-- ── COLUMNA IZQUIERDA: upload + resultado ── -->
    <div>
        <div class="card mb-24">
            <div class="card-title">Subir Libro de Compras ARCA</div>

            <div class="alert alert-info">
                <span>ℹ</span>
                <div>El archivo debe ser el <strong>Libro de Compras</strong> descargado desde ARCA/AFIP.
                Columnas esperadas: Fecha · Tipo · Pto. Venta · Nro. Desde · Nro. Doc. Vendedor · Denominación · Tipo Cambio · Neto Gravado · No Gravado · Exento · IVA · Total</div>
            </div>

            <div class="upload-zone" id="upload-zone">
                <input type="file" id="file-input" accept=".xlsx,.xls">
                <div class="upload-icon">📋</div>
                <div class="upload-title">Arrastrar el Excel de ARCA aquí</div>
                <div class="upload-sub">o hacer click para seleccionar — .xlsx / .xls</div>
            </div>

            <div id="file-selected" style="display:none;margin-top:12px" class="alert alert-success">
                <span>✓</span>
                <span id="file-name">—</span>
            </div>

            <div style="margin-top:16px;display:flex;gap:10px;align-items:center">
                <button class="btn btn-primary btn-lg" id="btn-procesar" disabled>
                    <span id="btn-txt">⚙ Procesar</span>
                    <span id="btn-spinner" class="spinner" style="display:none"></span>
                </button>
                <button class="btn btn-secondary" id="btn-limpiar" style="display:none">✕ Limpiar</button>
            </div>
        </div>

        <!-- Resultado -->
        <div class="card" id="resultado-card" style="display:none">
            <div class="card-title">Resultado</div>
            <div class="stats-grid" style="grid-template-columns:1fr 1fr 1fr;margin-bottom:0">
                <div class="stat-card blue">
                    <div class="stat-label">Total filas</div>
                    <div class="stat-value" id="res-total">—</div>
                </div>
                <div class="stat-card amber">
                    <div class="stat-label">Notas de Crédito</div>
                    <div class="stat-value" id="res-nc">—</div>
                    <div class="stat-sub">Tipo Cambio negado</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-label">Período</div>
                    <div class="stat-value" style="font-size:18px" id="res-periodo">—</div>
                </div>
            </div>
            <div style="margin-top:20px;padding:14px 16px;background:rgba(16,185,129,0.07);border:1px solid rgba(16,185,129,0.2);border-radius:8px;font-size:13px;color:var(--sub)">
                El Excel incluye <strong style="color:var(--text)">3 columnas vacías</strong> (Perc IIBB · Perc IVA · Imp Int) para completar manualmente.
                <strong style="color:var(--text)">Diferencia</strong>, columnas <strong style="color:var(--text)">*2 en pesos</strong>
                y <strong style="color:var(--text)">Porcentaje</strong> (IVA/Neto×100) usan fórmulas automáticas.
                Las celdas de <strong style="color:#f59e0b">Diferencia ≠ 0</strong> se marcan en amarillo
                y las de <strong style="color:#ef4444">Porcentaje fuera de 10,5 / 21 / 27</strong> en rojo.
            </div>
            <div style="margin-top:16px">
                <button class="btn btn-success btn-lg" id="btn-descargar">⬇ Descargar Excel Procesado</button>
            </div>
        </div>
    </div>

    <!-- ── COLUMNA DERECHA: preview ── -->
    <div>
        <div class="card">
            <div class="card-title">Vista Previa</div>
            <div id="preview-empty" class="empty-state">
                <div class="empty-state-icon">📋</div>
                <div class="empty-state-text">La vista previa aparecerá aquí después de procesar</div>
            </div>
            <div id="preview-table" style="display:none">
                <div class="flex-between mb-16">
                    <span id="preview-count" class="badge badge-blue">0 filas</span>
                    <span id="preview-nc" class="badge badge-amber">0 notas de crédito</span>
                </div>
                <div class="table-wrap" style="max-height:520px;overflow-y:auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Pto. Venta</th>
                                <th>Tipo Cambio</th>
                                <th>Neto Gravado</th>
                                <th>IVA</th>
                                <th>Total</th>
                                <th>Vendedor</th>
                            </tr>
                        </thead>
                        <tbody id="preview-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── HISTORIAL DE LOTES ── -->
<div class="card mt-24">
    <div class="flex-between mb-16">
        <div class="card-title" style="margin:0">Historial de archivos procesados</div>
        <div style="display:flex;gap:8px">
            <button class="btn btn-secondary btn-sm" id="btn-refresh-historial">↺ Actualizar</button>
            <button class="btn btn-danger btn-sm" onclick="openModal('modal-reset-iva')">⚠ Eliminar todo</button>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Archivo</th>
                    <th>Período</th>
                    <th>Filas</th>
                    <th>Notas C.</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="historial-body">
                <tr><td colspan="7"><div class="empty-state"><div class="spinner" style="margin:0 auto"></div></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal confirmar eliminación -->
<div class="modal-overlay" id="modal-eliminar">
    <div class="modal" style="max-width:400px">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--red)">⚠ Eliminar lote</div>
            <button class="modal-close" onclick="closeModal('modal-eliminar')">✕</button>
        </div>
        <p style="color:var(--sub);font-size:14px;margin-bottom:24px">
            Esto eliminará el lote y todos sus registros. <strong style="color:var(--red)">No se puede deshacer.</strong>
        </p>
        <div style="display:flex;gap:10px;justify-content:flex-end">
            <button class="btn btn-secondary" onclick="closeModal('modal-eliminar')">Cancelar</button>
            <button class="btn btn-danger" id="btn-confirmar-eliminar">Eliminar</button>
        </div>
    </div>
</div>

<script>
let currentFile = null;
let loteActual  = null;
let loteAEliminar = null;

initDropZone('upload-zone', file => setFile(file));

function setFile(file) {
    currentFile = file;
    document.getElementById('file-name').textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
    document.getElementById('file-selected').style.display = 'flex';
    document.getElementById('btn-procesar').disabled = false;
    document.getElementById('btn-limpiar').style.display = 'inline-flex';
    document.getElementById('resultado-card').style.display = 'none';
    loteActual = null;
}

document.getElementById('btn-limpiar').addEventListener('click', () => {
    currentFile = null; loteActual = null;
    document.getElementById('file-input').value = '';
    document.getElementById('file-selected').style.display = 'none';
    document.getElementById('btn-procesar').disabled = true;
    document.getElementById('btn-limpiar').style.display = 'none';
    document.getElementById('resultado-card').style.display = 'none';
    document.getElementById('preview-table').style.display = 'none';
    document.getElementById('preview-empty').style.display = 'block';
});

document.getElementById('btn-procesar').addEventListener('click', async () => {
    if (!currentFile) return;
    const btn = document.getElementById('btn-procesar');
    btn.disabled = true;
    document.getElementById('btn-txt').style.display = 'none';
    document.getElementById('btn-spinner').style.display = 'inline-block';

    const form = new FormData();
    form.append('excel', currentFile);

    try {
        const res  = await fetch('/api/libro_iva.php?action=procesar', { method: 'POST', body: form });
        const data = await res.json();

        if (data.error) { toast(data.error, 'error'); return; }

        loteActual = data.lote_id;

        document.getElementById('res-total').textContent   = data.total;
        document.getElementById('res-nc').textContent      = data.notas_credito;
        document.getElementById('res-periodo').textContent = data.periodo || '—';
        document.getElementById('resultado-card').style.display = 'block';

        renderPreview(data.preview, data.total, data.notas_credito);
        cargarHistorial();
        toast(`✓ ${data.total} filas procesadas`, 'success');

    } catch(e) {
        toast('Error: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        document.getElementById('btn-txt').style.display = 'inline';
        document.getElementById('btn-spinner').style.display = 'none';
    }
});

document.getElementById('btn-descargar').addEventListener('click', () => {
    if (!loteActual) return;
    window.location.href = '/api/libro_iva.php?action=descargar&lote_id=' + loteActual;
});

function renderPreview(rows, total, nc) {
    document.getElementById('preview-count').textContent = total + ' filas';
    document.getElementById('preview-nc').textContent    = nc + ' notas de crédito';

    const tbody = document.getElementById('preview-body');
    tbody.innerHTML = rows.map(r => {
        const esNC   = r.es_nota_credito;
        const tcColor = esNC ? 'color:var(--red)' : '';
        const tc = parseFloat(r.tipo_cambio);
        return `<tr style="${esNC ? 'background:rgba(239,68,68,0.04)' : ''}">
            <td class="mono" style="font-size:11px">${r.fecha || '—'}</td>
            <td style="font-size:11px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${r.tipo}">${r.tipo}</td>
            <td class="mono">${r.punto_venta}</td>
            <td class="mono" style="${tcColor}">${tc}</td>
            <td class="mono">${formatMoney(r.neto_gravado)}</td>
            <td class="mono">${formatMoney(r.iva_monto)}</td>
            <td class="mono">${formatMoney(r.total)}</td>
            <td style="font-size:11px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${r.denominacion_vendedor}">${r.denominacion_vendedor || '—'}</td>
        </tr>`;
    }).join('');

    if (total > rows.length) {
        tbody.innerHTML += `<tr><td colspan="8" style="text-align:center;color:var(--sub);font-size:12px;padding:12px">
            … y ${total - rows.length} filas más en el Excel descargado
        </td></tr>`;
    }

    document.getElementById('preview-empty').style.display = 'none';
    document.getElementById('preview-table').style.display = 'block';
}

async function cargarHistorial() {
    try {
        const res  = await fetch('/api/libro_iva.php?action=listar_lotes');
        const data = await res.json();
        renderHistorial(data.data || []);
    } catch(e) {
        document.getElementById('historial-body').innerHTML =
            '<tr><td colspan="7" style="color:var(--red);text-align:center">Error al cargar historial</td></tr>';
    }
}

function renderHistorial(lotes) {
    const tbody = document.getElementById('historial-body');
    if (!lotes.length) {
        tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">Aún no procesaste ningún archivo</div>
        </div></td></tr>`;
        return;
    }
    tbody.innerHTML = lotes.map(l => `<tr>
        <td class="mono" style="font-size:11px">${l.codigo}</td>
        <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px">${l.archivo_nombre || '—'}</td>
        <td class="mono">${l.periodo || '—'}</td>
        <td class="mono">${l.total_filas}</td>
        <td>${parseInt(l.notas_credito) > 0
            ? `<span class="badge badge-amber">${l.notas_credito}</span>`
            : '<span class="badge badge-muted">0</span>'}</td>
        <td class="mono" style="font-size:11px">${new Date(l.created_at).toLocaleDateString('es-AR')}</td>
        <td style="display:flex;gap:6px">
            <a href="/api/libro_iva.php?action=descargar&lote_id=${l.id}" class="btn btn-success btn-sm">⬇ Descargar</a>
            <button class="btn btn-danger btn-sm" onclick="confirmarEliminar(${l.id})">✕</button>
        </td>
    </tr>`).join('');
}

function confirmarEliminar(id) {
    loteAEliminar = id;
    openModal('modal-eliminar');
}

document.getElementById('btn-confirmar-eliminar').addEventListener('click', async () => {
    if (!loteAEliminar) return;
    const btn = document.getElementById('btn-confirmar-eliminar');
    btn.disabled = true; btn.textContent = 'Eliminando...';
    try {
        const res  = await fetch('/api/libro_iva.php?action=eliminar_lote', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({id: loteAEliminar})
        });
        const data = await res.json();
        closeModal('modal-eliminar');
        if (data.success) { toast('Lote eliminado', 'success'); cargarHistorial(); }
        else toast(data.error || 'Error', 'error');
    } catch(e) { toast('Error: ' + e.message, 'error'); }
    finally { btn.disabled = false; btn.textContent = 'Eliminar'; loteAEliminar = null; }
});

document.getElementById('btn-refresh-historial').addEventListener('click', cargarHistorial);

cargarHistorial();

document.getElementById('btn-confirmar-reset-iva')?.addEventListener('click', async () => {
    const btn = document.getElementById('btn-confirmar-reset-iva');
    btn.disabled = true; btn.textContent = 'Eliminando...';
    const res  = await fetch('/api/libro_iva.php?action=eliminar_todo', { method: 'POST' });
    const data = await res.json();
    closeModal('modal-reset-iva');
    btn.disabled = false; btn.textContent = 'Sí, eliminar todo';
    if (data.success) { toast('Todos los archivos IVA eliminados', 'success'); cargarHistorial(); }
    else toast(data.error || 'Error', 'error');
});
</script>

<div class="modal-overlay" id="modal-reset-iva">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--red)">⚠ Eliminar todos los archivos IVA</div>
            <button class="modal-close">✕</button>
        </div>
        <p style="color:var(--sub);font-size:14px;margin-bottom:16px">
            Se van a eliminar <strong style="color:var(--text)">todos los libros de compras procesados</strong>.
        </p>
        <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);border-radius:8px;padding:12px 16px;margin-bottom:24px;font-size:13px;color:var(--sub)">
            Esta acción <strong style="color:var(--red)">no se puede deshacer</strong>.
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end">
            <button class="btn btn-secondary" onclick="closeModal('modal-reset-iva')">Cancelar</button>
            <button class="btn btn-danger" id="btn-confirmar-reset-iva">Sí, eliminar todo</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
