<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Convertir Excel';
require_once __DIR__ . '/includes/header.php';
?>

<div class="grid-2" style="gap:24px">
    <div>
        <div class="card mb-24">
            <div class="card-title">Convertir Extracto Credicoop</div>
            <div class="alert alert-info">
                <span>ℹ</span>
                <div>
                    Subí el Excel tal como lo descargás del banco y te devuelvo un archivo limpio con:<br>
                    <strong>Fecha · Descripción · Importe · Tipo</strong><br>
                    <small style="opacity:.7">El archivo no se guarda en el sistema.</small>
                </div>
            </div>
            <div class="upload-zone" id="upload-zone">
                <input type="file" id="file-input" accept=".xlsx,.xls,.csv">
                <div class="upload-icon">📄</div>
                <div class="upload-title">Arrastrá el Excel del Credicoop aquí</div>
                <div class="upload-sub">o hacé click para seleccionar · .xlsx .xls .csv</div>
            </div>
            <div id="file-selected" style="display:none;margin-top:12px" class="alert alert-success">
                <span>✓</span><span id="file-name">—</span>
            </div>
            <div style="margin-top:16px;display:flex;gap:10px;align-items:center">
                <button class="btn btn-primary btn-lg" id="btn-convertir" disabled>
                    <span id="btn-txt">⬇ Convertir y Descargar</span>
                    <span id="btn-spinner" class="spinner" style="display:none"></span>
                </button>
                <button class="btn btn-secondary" id="btn-limpiar" style="display:none">✕ Limpiar</button>
            </div>
            <div id="status-ok" style="display:none;margin-top:12px" class="alert alert-success">
                <span>✓</span><span id="status-msg">Archivo generado y descargado.</span>
            </div>
            <div id="status-err" style="display:none;margin-top:12px" class="alert alert-danger">
                <span>✕</span><pre id="err-msg" style="margin:0;white-space:pre-wrap;font-size:12px;font-family:monospace">—</pre>
            </div>
        </div>

        <div class="card">
            <div class="card-title" style="font-size:13px;margin-bottom:12px">Columnas del archivo de salida</div>
            <table style="font-size:13px;width:100%">
                <thead><tr><th>Col</th><th>Contenido</th></tr></thead>
                <tbody>
                    <tr><td class="mono">A · Fecha</td><td>Fecha de la operación (dd/mm/aaaa)</td></tr>
                    <tr><td class="mono">B · Descripción</td><td>Concepto del movimiento</td></tr>
                    <tr><td class="mono">C · Importe</td><td>Monto (siempre positivo)</td></tr>
                    <tr><td class="mono">D · Tipo</td><td><span class="badge badge-green">Ingreso</span> o <span class="badge badge-red">Gasto</span></td></tr>
                </tbody>
            </table>
            <div style="margin-top:10px;font-size:12px;color:var(--sub)">
                Se eliminan: Nro. Comprobante, Saldo y Código.
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-title">Vista Previa</div>
            <div id="preview-empty" class="empty-state">
                <div class="empty-state-icon">📋</div>
                <div class="empty-state-text">Subí un archivo para ver la vista previa</div>
            </div>
            <div id="preview-table" style="display:none">
                <div class="flex-between mb-16">
                    <span id="preview-count" class="badge badge-blue">0 filas</span>
                    <div class="flex-gap gap-8">
                        <span class="badge badge-green" id="prev-ing">0 ingresos</span>
                        <span class="badge badge-red"   id="prev-gasto">0 gastos</span>
                    </div>
                </div>
                <div class="table-wrap" style="max-height:520px;overflow-y:auto">
                    <table>
                        <thead><tr><th>Fecha</th><th>Descripción</th><th>Importe</th><th>Tipo</th></tr></thead>
                        <tbody id="preview-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentFile = null;
initDropZone('upload-zone', f => setFile(f));

function setFile(f) {
    currentFile = f;
    document.getElementById('file-name').textContent = f.name + ' (' + (f.size / 1024).toFixed(1) + ' KB)';
    document.getElementById('file-selected').style.display = 'flex';
    document.getElementById('btn-convertir').disabled = false;
    document.getElementById('btn-limpiar').style.display = 'inline-flex';
    document.getElementById('status-ok').style.display = 'none';
    document.getElementById('status-err').style.display = 'none';
    readPreview(f);
}

document.getElementById('btn-limpiar').addEventListener('click', () => {
    currentFile = null;
    document.getElementById('file-input').value = '';
    document.getElementById('file-selected').style.display = 'none';
    document.getElementById('btn-convertir').disabled = true;
    document.getElementById('btn-limpiar').style.display = 'none';
    document.getElementById('status-ok').style.display = 'none';
    document.getElementById('status-err').style.display = 'none';
    document.getElementById('preview-table').style.display = 'none';
    document.getElementById('preview-empty').style.display = 'block';
});

// Vista previa del lado cliente usando SheetJS
function readPreview(file) {
    function doRead() {
        const reader = new FileReader();
        reader.onload = e => {
            try {
                const wb   = XLSX.read(e.target.result, { type: 'array', cellDates: true });
                const ws   = wb.Sheets[wb.SheetNames[0]];
                const data = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });
                if (data.length < 2) return;

                const hdrs = data[0].map(h => (h||'').toString()
                    .normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim());
                let cFecha=-1, cConc=-1, cDebe=-1, cHaber=-1;
                hdrs.forEach((h, i) => {
                    if (/^fecha/.test(h))                           cFecha = i;
                    else if (/concepto|^desc|detalle/.test(h))      cConc  = i;
                    else if (/debito|debe|cargo/.test(h))           cDebe  = i;
                    else if (/credito|haber|abono/.test(h))         cHaber = i;
                });
                if (cFecha < 0) cFecha = 1;
                if (cConc  < 0) cConc  = 2;
                if (cDebe  < 0) cDebe  = 4;
                if (cHaber < 0) cHaber = 5;

                const rows = [];
                for (let i = 1; i < data.length; i++) {
                    const r    = data[i];
                    const desc = (r[cConc] || '').toString().trim();
                    const debe  = toNum(r[cDebe]);
                    const haber = toNum(r[cHaber]);
                    if (!desc && debe === 0 && haber === 0) continue;
                    let fecha = '';
                    const fv = r[cFecha];
                    if (fv instanceof Date) fecha = fv.toLocaleDateString('es-AR');
                    else if (fv) fecha = fv.toString();
                    const importe = haber > 0 ? haber : debe;
                    const tipo    = haber > 0 ? 'Ingreso' : 'Gasto';
                    rows.push({ fecha, desc, importe, tipo });
                }
                renderPreview(rows);
            } catch(e) { /* silencioso */ }
        };
        reader.readAsArrayBuffer(file);
    }

    if (typeof XLSX !== 'undefined') { doRead(); return; }
    // Esperar a que SheetJS cargue (está en defer)
    const t = setInterval(() => { if (typeof XLSX !== 'undefined') { clearInterval(t); doRead(); } }, 100);
}

function toNum(v) {
    if (v === '' || v === null || v === undefined) return 0;
    if (typeof v === 'number') return v;
    const s = v.toString().replace(/[$\s]/g, '').replace(/\./g, '').replace(',', '.');
    return parseFloat(s) || 0;
}

function renderPreview(rows) {
    const ing   = rows.filter(r => r.tipo === 'Ingreso').length;
    const gasto = rows.filter(r => r.tipo === 'Gasto').length;
    document.getElementById('preview-count').textContent  = rows.length + ' filas';
    document.getElementById('prev-ing').textContent       = ing   + ' ingresos';
    document.getElementById('prev-gasto').textContent     = gasto + ' gastos';

    const maxPreview = 200;
    document.getElementById('preview-body').innerHTML = rows.slice(0, maxPreview).map(r =>
        `<tr>
            <td class="mono" style="white-space:nowrap">${r.fecha}</td>
            <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${r.desc.replace(/"/g,'&quot;')}">${r.desc}</td>
            <td class="mono" style="text-align:right;color:${r.tipo==='Ingreso'?'var(--green)':'var(--red)'}">${formatMoney(r.importe)}</td>
            <td><span class="badge ${r.tipo==='Ingreso'?'badge-green':'badge-red'}">${r.tipo}</span></td>
        </tr>`
    ).join('') + (rows.length > maxPreview
        ? `<tr><td colspan="4" style="text-align:center;color:var(--sub);font-size:12px;padding:10px">… y ${rows.length - maxPreview} filas más</td></tr>`
        : '');

    document.getElementById('preview-empty').style.display = 'none';
    document.getElementById('preview-table').style.display = 'block';
}

// Envío al servidor y descarga del XLSX resultante
document.getElementById('btn-convertir').addEventListener('click', async () => {
    if (!currentFile) return;
    const btn = document.getElementById('btn-convertir');
    btn.disabled = true;
    document.getElementById('btn-txt').style.display    = 'none';
    document.getElementById('btn-spinner').style.display = 'inline-block';
    document.getElementById('status-ok').style.display  = 'none';
    document.getElementById('status-err').style.display = 'none';

    try {
        const form = new FormData();
        form.append('excel', currentFile);
        const res = await fetch('/credicoop/api/convertir_excel.php', { method: 'POST', body: form });
        const ct  = res.headers.get('Content-Type') || '';

        if (ct.includes('json')) {
            const data = await res.json();
            let msg = data.error || 'Error desconocido';
            if (data.debug) {
                msg += '\n\n[DEBUG] Encabezado detectado: ' + JSON.stringify(data.debug.header_raw) +
                       '\nColumnas detectadas: ' + JSON.stringify(data.debug.cols_detectados) +
                       '\nTotal filas: ' + data.debug.total_rawRows +
                       '\nMuestra fila 2: ' + JSON.stringify(data.debug.muestra_fila2);
            }
            document.getElementById('err-msg').textContent  = msg;
            document.getElementById('status-err').style.display = 'flex';
        } else {
            const blob  = await res.blob();
            const url   = URL.createObjectURL(blob);
            const a     = document.createElement('a');
            const fecha = new Date().toISOString().slice(0,10).replace(/-/g,'');
            a.href     = url;
            a.download = 'credicoop_' + fecha + '.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            document.getElementById('status-msg').textContent  = 'Archivo descargado correctamente.';
            document.getElementById('status-ok').style.display = 'flex';
        }
    } catch (e) {
        document.getElementById('err-msg').textContent  = 'Error: ' + e.message;
        document.getElementById('status-err').style.display = 'flex';
    } finally {
        btn.disabled = false;
        document.getElementById('btn-txt').style.display    = 'inline';
        document.getElementById('btn-spinner').style.display = 'none';
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
