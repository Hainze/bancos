<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Importar Excel';
require_once __DIR__ . '/includes/header.php';
?>

<div class="grid-2" style="gap:24px">
    <div>
        <div class="card mb-24">
            <div class="card-title">Subir Archivo</div>

            <div class="alert alert-info">
                <span>ℹ</span>
                <div>
                    El sistema detecta automáticamente el formato del extracto de Mercado Pago.<br>
                    <strong>Columnas reconocidas:</strong> Fecha · Tipo de movimiento · Descripción / Detalle · Monto / Importe · Crédito · Débito.<br>
                    Formatos aceptados: <strong>.xlsx, .xls, .csv</strong>
                </div>
            </div>

            <div class="upload-zone" id="upload-zone">
                <input type="file" id="file-input" accept=".xlsx,.xls,.csv">
                <div class="upload-icon">💳</div>
                <div class="upload-title">Arrastrar el extracto aquí</div>
                <div class="upload-sub">o hacer click para seleccionar — .xlsx / .xls / .csv</div>
            </div>

            <div id="file-selected" style="display:none;margin-top:12px" class="alert alert-success">
                <span>✓</span>
                <span id="file-name">—</span>
            </div>

            <div style="margin-top:16px;display:flex;gap:10px;align-items:center">
                <button class="btn btn-primary btn-lg" id="btn-procesar" disabled>
                    <span id="btn-txt">⚙ Procesar Excel</span>
                    <span id="btn-spinner" class="spinner" style="display:none"></span>
                </button>
                <button class="btn btn-secondary" id="btn-limpiar" style="display:none">✕ Limpiar</button>
            </div>
        </div>

        <div class="card" id="resultado-card" style="display:none">
            <div class="card-title">Resultado del Procesamiento</div>
            <div class="stats-grid" style="grid-template-columns:1fr 1fr 1fr;margin-bottom:0">
                <div class="stat-card blue">
                    <div class="stat-label">Total filas</div>
                    <div class="stat-value" id="res-total">—</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-label">Clasificadas</div>
                    <div class="stat-value green" id="res-clas">—</div>
                </div>
                <div class="stat-card amber">
                    <div class="stat-label">Sin clasificar</div>
                    <div class="stat-value" id="res-sin">—</div>
                </div>
            </div>
            <div id="res-formato" style="margin-top:12px;font-size:12px;color:var(--sub)"></div>
            <div style="margin-top:16px;display:flex;gap:10px">
                <button class="btn btn-success" id="btn-descargar">⬇ Descargar Excel Procesado</button>
                <a href="movimientos.php" class="btn btn-secondary">≡ Ver movimientos</a>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-title">Vista Previa</div>
            <div id="preview-empty" class="empty-state">
                <div class="empty-state-icon">💳</div>
                <div class="empty-state-text">La vista previa aparecerá aquí después de procesar</div>
            </div>
            <div id="preview-table" style="display:none">
                <div class="flex-between mb-16">
                    <span id="preview-count" class="badge badge-blue">0 filas</span>
                    <div class="flex-gap gap-8">
                        <span class="badge badge-green" id="prev-ing">0 ingresos</span>
                        <span class="badge badge-red" id="prev-gasto">0 egresos</span>
                    </div>
                </div>
                <div class="table-wrap" style="max-height:500px;overflow-y:auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Descripción</th>
                                <th>Importe</th>
                                <th>Tipo mov.</th>
                                <th>Categoría</th>
                            </tr>
                        </thead>
                        <tbody id="preview-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentFile = null;
let processedData = null;

initDropZone('upload-zone', file => setFile(file));

function setFile(file) {
    currentFile = file;
    document.getElementById('file-name').textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
    document.getElementById('file-selected').style.display = 'flex';
    document.getElementById('btn-procesar').disabled = false;
    document.getElementById('btn-limpiar').style.display = 'inline-flex';
    document.getElementById('resultado-card').style.display = 'none';
    processedData = null;
}

document.getElementById('btn-limpiar').addEventListener('click', () => {
    currentFile = null;
    processedData = null;
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
        const res  = await fetch('/mp/api/procesar_excel.php', { method: 'POST', body: form });
        const data = await res.json();

        if (data.error) { toast(data.error, 'error'); return; }

        processedData = data;

        document.getElementById('res-total').textContent = data.total;
        document.getElementById('res-clas').textContent  = data.clasificadas;
        document.getElementById('res-sin').textContent   = data.sin_clasificar;
        document.getElementById('res-formato').textContent = data.formato ? 'Formato detectado: ' + data.formato : '';
        document.getElementById('resultado-card').style.display = 'block';

        renderPreview(data.rows);
        toast(`✓ ${data.total} movimientos procesados`, 'success');

    } catch(e) {
        toast('Error al procesar: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        document.getElementById('btn-txt').style.display = 'inline';
        document.getElementById('btn-spinner').style.display = 'none';
    }
});

function renderPreview(rows) {
    const ingresos = rows.filter(r => r.tipo === 'ingreso').length;
    const gastos   = rows.filter(r => r.tipo === 'gasto').length;

    document.getElementById('preview-count').textContent = rows.length + ' filas';
    document.getElementById('prev-ing').textContent   = ingresos + ' ingresos';
    document.getElementById('prev-gasto').textContent = gastos + ' egresos';

    const tbody = document.getElementById('preview-body');
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td class="mono">${r.fecha || '—'}</td>
            <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${r.descripcion}">${r.descripcion}</td>
            <td class="mono" style="color:${r.tipo==='ingreso'?'var(--green)':'var(--red)'}">${formatMoney(r.importe)}</td>
            <td style="font-size:11px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${r.tipo_movimiento || '—'}</td>
            <td>${r.categoria ? `<span class="badge badge-blue">${r.categoria}</span>` : '<span class="badge badge-muted">—</span>'}</td>
        </tr>
    `).join('');

    document.getElementById('preview-empty').style.display = 'none';
    document.getElementById('preview-table').style.display = 'block';
}

document.getElementById('btn-descargar').addEventListener('click', () => {
    if (!processedData) return;
    const lote = processedData.lote;
    window.location.href = `/mp/api/exportar_excel.php?tipo=movimientos&lote=${encodeURIComponent(lote)}`;
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
