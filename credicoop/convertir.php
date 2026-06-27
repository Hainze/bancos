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
                    <small style="opacity:.7">Acepta .xls y .xlsx. El archivo no se sube al servidor.</small>
                </div>
            </div>
            <div class="upload-zone" id="upload-zone">
                <input type="file" id="file-input" accept=".xlsx,.xls,.csv">
                <div class="upload-icon">📄</div>
                <div class="upload-title">Arrastrá el Excel del Credicoop aquí</div>
                <div class="upload-sub">o hacé click para seleccionar · .xls .xlsx .csv</div>
            </div>
            <div id="file-selected" style="display:none;margin-top:12px" class="alert alert-success">
                <span>✓</span><span id="file-name">—</span>
            </div>
            <div style="margin-top:16px;display:flex;gap:10px;align-items:center">
                <button class="btn btn-primary btn-lg" id="btn-convertir" disabled>
                    ⬇ Convertir y Descargar
                </button>
                <button class="btn btn-secondary" id="btn-limpiar" style="display:none">✕ Limpiar</button>
            </div>
            <div id="status-ok" style="display:none;margin-top:12px" class="alert alert-success">
                <span>✓</span><span id="status-msg">Archivo descargado.</span>
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
let parsedRows  = null; // Array de { fecha, desc, importe, tipo }

initDropZone('upload-zone', f => setFile(f));

function setFile(f) {
    currentFile = f;
    parsedRows  = null;
    document.getElementById('file-name').textContent = f.name + ' (' + (f.size / 1024).toFixed(1) + ' KB)';
    document.getElementById('file-selected').style.display = 'flex';
    document.getElementById('btn-convertir').disabled = true;
    document.getElementById('btn-limpiar').style.display = 'inline-flex';
    document.getElementById('status-ok').style.display  = 'none';
    document.getElementById('status-err').style.display = 'none';
    parseFile(f);
}

document.getElementById('btn-limpiar').addEventListener('click', () => {
    currentFile = null; parsedRows = null;
    document.getElementById('file-input').value = '';
    document.getElementById('file-selected').style.display = 'none';
    document.getElementById('btn-convertir').disabled = true;
    document.getElementById('btn-limpiar').style.display = 'none';
    document.getElementById('status-ok').style.display  = 'none';
    document.getElementById('status-err').style.display = 'none';
    document.getElementById('preview-table').style.display = 'none';
    document.getElementById('preview-empty').style.display = 'block';
});

// ── Parseo con SheetJS (soporta .xls y .xlsx) ─────────────────────────────────
function parseFile(file) {
    function doRead() {
        const reader = new FileReader();
        reader.onload = e => {
            try {
                const wb  = XLSX.read(e.target.result, { type: 'array', cellDates: true });
                const ws  = wb.Sheets[wb.SheetNames[0]];
                const raw = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });

                if (!raw || raw.length < 2) {
                    showErr('El archivo está vacío o no tiene filas de datos.');
                    return;
                }

                // Detectar columnas por encabezado
                const hdrs = raw[0].map(h => norm(String(h)));
                let cFecha=-1, cConc=-1, cDebe=-1, cHaber=-1;
                hdrs.forEach((h, i) => {
                    if      (/^fecha/.test(h))                       cFecha = i;
                    else if (/concepto|^desc|detalle/.test(h))       cConc  = i;
                    else if (/debito|debe|cargo|extraccion/.test(h)) cDebe  = i;
                    else if (/credito|haber|abono|deposito/.test(h)) cHaber = i;
                });
                // Fallback posición conocida Credicoop: A vacía, B=Fecha, C=Concepto, D=Nro, E=Débito, F=Crédito
                if (cFecha < 0) cFecha = 1;
                if (cConc  < 0) cConc  = 2;
                if (cDebe  < 0) cDebe  = 4;
                if (cHaber < 0) cHaber = 5;

                // Transformar filas
                const rows = [];
                for (let i = 1; i < raw.length; i++) {
                    const r     = raw[i];
                    const desc  = String(r[cConc] || '').trim();
                    const debe  = toNum(r[cDebe]);
                    const haber = toNum(r[cHaber]);
                    if (!desc && debe === 0 && haber === 0) continue;

                    const importe = haber > 0 ? haber : debe;
                    const tipo    = haber > 0 ? 'Ingreso' : 'Gasto';
                    rows.push({ fecha: fmtFecha(r[cFecha]), desc, importe, tipo });
                }

                parsedRows = rows;
                renderPreview(rows);
                document.getElementById('btn-convertir').disabled = (rows.length === 0);

                if (rows.length === 0) {
                    showErr('No se encontraron filas con datos.\n' +
                        'Encabezados detectados: ' + JSON.stringify(raw[0].slice(0,10)) + '\n' +
                        'Cols usadas: Fecha=' + cFecha + ' Desc=' + cConc + ' Debe=' + cDebe + ' Haber=' + cHaber);
                }
            } catch(ex) {
                showErr('Error al leer el archivo: ' + ex.message);
            }
        };
        reader.readAsArrayBuffer(file);
    }

    if (typeof XLSX !== 'undefined') { doRead(); return; }
    const t = setInterval(() => { if (typeof XLSX !== 'undefined') { clearInterval(t); doRead(); } }, 100);
}

// ── Conversión y descarga (100% client-side, SheetJS) ─────────────────────────
document.getElementById('btn-convertir').addEventListener('click', () => {
    if (!parsedRows || parsedRows.length === 0) return;
    try {
        const aoa = [['Fecha', 'Descripción', 'Importe', 'Tipo']];
        parsedRows.forEach(r => aoa.push([r.fecha, r.desc, r.importe, r.tipo]));

        const wsOut = XLSX.utils.aoa_to_sheet(aoa);
        wsOut['!cols'] = [{ wch: 13 }, { wch: 52 }, { wch: 16 }, { wch: 10 }];

        const wbOut = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wbOut, wsOut, 'Movimientos');

        const fname = 'credicoop_' + new Date().toISOString().slice(0,10).replace(/-/g,'') + '.xlsx';
        XLSX.writeFile(wbOut, fname);

        document.getElementById('status-msg').textContent = fname + ' descargado (' + parsedRows.length + ' filas).';
        document.getElementById('status-ok').style.display  = 'flex';
        document.getElementById('status-err').style.display = 'none';
    } catch(ex) {
        showErr('Error al generar el archivo: ' + ex.message);
    }
});

// ── Preview ────────────────────────────────────────────────────────────────────
function renderPreview(rows) {
    const ing   = rows.filter(r => r.tipo === 'Ingreso').length;
    const gasto = rows.filter(r => r.tipo === 'Gasto').length;
    document.getElementById('preview-count').textContent = rows.length + ' filas';
    document.getElementById('prev-ing').textContent      = ing   + ' ingresos';
    document.getElementById('prev-gasto').textContent    = gasto + ' gastos';

    const MAX = 200;
    document.getElementById('preview-body').innerHTML =
        rows.slice(0, MAX).map(r =>
            `<tr>
                <td class="mono" style="white-space:nowrap">${r.fecha}</td>
                <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(r.desc)}">${r.desc}</td>
                <td class="mono" style="text-align:right;color:${r.tipo==='Ingreso'?'var(--green)':'var(--red)'}">${formatMoney(r.importe)}</td>
                <td><span class="badge ${r.tipo==='Ingreso'?'badge-green':'badge-red'}">${r.tipo}</span></td>
            </tr>`
        ).join('') +
        (rows.length > MAX ? `<tr><td colspan="4" style="text-align:center;color:var(--sub);font-size:12px;padding:10px">… y ${rows.length - MAX} filas más</td></tr>` : '');

    document.getElementById('preview-empty').style.display = 'none';
    document.getElementById('preview-table').style.display = 'block';
}

// ── Helpers ────────────────────────────────────────────────────────────────────
function norm(s) {
    return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
}

function fmtFecha(v) {
    if (!v) return '';
    if (v instanceof Date) {
        return String(v.getDate()).padStart(2,'0') + '/' +
               String(v.getMonth()+1).padStart(2,'0') + '/' +
               v.getFullYear();
    }
    const s = String(v).trim();
    if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(s)) return s;
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
        const [y, m, d] = s.split('-');
        return d + '/' + m + '/' + y;
    }
    return s;
}

function toNum(v) {
    if (v === '' || v == null) return 0;
    if (typeof v === 'number') return v;
    // Formato argentino: 1.234,56 (punto=miles, coma=decimal)
    const s = String(v).replace(/[$\s ]/g, '').replace(/\./g, '').replace(',', '.');
    return parseFloat(s) || 0;
}

function esc(s) { return String(s).replace(/"/g, '&quot;').replace(/</g,'&lt;'); }
function showErr(msg) {
    document.getElementById('err-msg').textContent  = msg;
    document.getElementById('status-err').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
