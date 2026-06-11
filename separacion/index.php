<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Separar Comprobantes';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Upload card -->
<div class="card" style="max-width:680px;margin:0 auto 24px">
    <div class="card-title">Separar columna Comprobante</div>
    <p style="color:var(--sub);font-size:13px;margin-bottom:16px">
        El sistema busca la columna <strong>Comprobante</strong> y la reemplaza por tres columnas:
        <strong style="color:#f59e0b">Tipo</strong> ·
        <strong style="color:#f59e0b">Punto</strong> ·
        <strong style="color:#f59e0b">Factura</strong>.
        El resto del Excel queda exactamente igual.
    </p>
    <p style="color:var(--muted);font-size:12px;margin-bottom:16px;font-family:'Space Mono',monospace;background:var(--surface);padding:10px 14px;border-radius:8px;border:1px solid var(--border)">
        FAC A0007-00060132 → Tipo: <strong>FAC A</strong> &nbsp;·&nbsp; Punto: <strong>7</strong> &nbsp;·&nbsp; Factura: <strong>60132</strong>
    </p>

    <div class="upload-zone" id="upload-zone">
        <input type="file" id="file-input" accept=".xlsx,.xls">
        <div class="upload-icon">📑</div>
        <div class="upload-title">Arrastrá el Excel acá</div>
        <div class="upload-sub">o hacé clic para seleccionar — .xlsx / .xls</div>
    </div>

    <div id="file-info" style="display:none;margin-top:12px" class="alert alert-success">
        <span>✓</span>
        <span id="file-name">—</span>
    </div>

    <div style="margin-top:16px;display:flex;gap:10px">
        <button class="btn btn-primary btn-lg" id="btn-procesar" disabled>
            <span id="btn-txt">⚙ Procesar</span>
            <span id="btn-spinner" class="spinner" style="display:none"></span>
        </button>
        <button class="btn btn-secondary" id="btn-limpiar" style="display:none">✕ Limpiar</button>
    </div>
</div>

<!-- Resultado -->
<div id="resultado" style="display:none">

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px" id="stats-cards"></div>

    <!-- Preview tabla -->
    <div class="card">
        <div class="flex-between mb-16">
            <div>
                <div class="card-title" style="margin:0" id="res-titulo">Vista previa</div>
                <div style="font-size:12px;color:var(--muted);margin-top:4px">
                    Columnas <span style="color:#f59e0b;font-weight:600">Tipo / Punto / Factura</span> reemplazan a Comprobante
                </div>
            </div>
            <button class="btn btn-success" id="btn-exportar">⬇ Descargar Excel</button>
        </div>

        <div class="table-wrap" style="overflow-x:auto">
            <table id="res-tabla" style="font-size:12px">
                <thead id="res-thead"></thead>
                <tbody id="res-tbody"></tbody>
            </table>
        </div>
        <div id="pag-wrap" style="display:flex;align-items:center;gap:8px;margin-top:12px;font-size:13px;color:var(--sub)"></div>
    </div>
</div>

<style>
.upload-zone {
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 48px 24px;
    text-align: center;
    cursor: pointer;
    transition: all .2s;
    background: var(--surface);
}
.upload-zone:hover, .upload-zone.dragover {
    border-color: #f59e0b;
    background: rgba(245,158,11,.06);
}
.upload-zone input[type=file] { display: none; }
.upload-icon  { font-size: 40px; margin-bottom: 12px; }
.upload-title { font-size: 16px; font-weight: 600; margin-bottom: 4px; }
.upload-sub   { font-size: 13px; color: var(--sub); }

.col-nueva { background: rgba(245,158,11,.12); color: #f59e0b; font-weight: 600; }
th.col-nueva { background: rgba(245,158,11,.18); }
.col-sin-match { color: var(--red); font-style: italic; }
</style>

<script>
let processedData = [];   // { headers: [], rows: [] } — ya transformado
let originalName  = '';
let colCompIdx    = -1;   // índice original de la columna Comprobante
const PAGE_SIZE   = 100;
let currentPage   = 1;

// ── Upload zone ───────────────────────────────────────
initDropZone('upload-zone', f => setFile(f));

function setFile(f) {
    const ext = f.name.split('.').pop().toLowerCase();
    if (!['xlsx','xls'].includes(ext)) { toast('Solo .xlsx o .xls', 'warning'); return; }
    originalName = f.name;
    document.getElementById('file-name').textContent = f.name + ' (' + (f.size / 1024).toFixed(1) + ' KB)';
    document.getElementById('file-info').style.display = 'flex';
    document.getElementById('btn-procesar').disabled = false;
    document.getElementById('btn-limpiar').style.display = 'inline-flex';
    document.getElementById('file-input')._file = f;
    document.getElementById('resultado').style.display = 'none';
    processedData = [];
}

document.getElementById('btn-limpiar').addEventListener('click', () => {
    document.getElementById('file-input').value = '';
    document.getElementById('file-input')._file = null;
    document.getElementById('file-info').style.display = 'none';
    document.getElementById('btn-procesar').disabled = true;
    document.getElementById('btn-limpiar').style.display = 'none';
    document.getElementById('resultado').style.display = 'none';
    processedData = [];
});

// ── Parseo del comprobante ────────────────────────────
function parseComprobante(v) {
    const s = String(v ?? '').trim();
    if (!s) return { tipo: '', punto: '', factura: '', ok: false };
    // Formato: LETRAS_MAYUS[espacio] DIGITS - DIGITS
    // Ej: "FAC A0007-00060132", "NCR A0700-00030047"
    const m = s.match(/^([A-Z][A-Z\s]*?)(\d+)-(\d+)$/);
    if (!m) return { tipo: s, punto: '', factura: '', ok: false };
    return {
        tipo:    m[1].trim(),
        punto:   String(parseInt(m[2], 10)),
        factura: String(parseInt(m[3], 10)),
        ok:      true,
    };
}

function normCol(s) {
    return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]/g, '');
}

// ── Procesar ──────────────────────────────────────────
document.getElementById('btn-procesar').addEventListener('click', async () => {
    const f = document.getElementById('file-input')._file;
    if (!f) return;

    const btn = document.getElementById('btn-procesar');
    btn.disabled = true;
    document.getElementById('btn-txt').style.display    = 'none';
    document.getElementById('btn-spinner').style.display = 'inline-block';

    try {
        // 1. Leer Excel
        const raw = await readExcel(f);
        if (!raw.length) { toast('El archivo está vacío', 'error'); return; }

        // 2. Buscar fila de encabezados (primer fila que tenga "comprobante")
        let headerIdx = -1;
        for (let i = 0; i < Math.min(raw.length, 10); i++) {
            if (raw[i].some(c => normCol(String(c)) === 'comprobante')) {
                headerIdx = i; break;
            }
        }
        if (headerIdx < 0) {
            toast('No se encontró la columna "Comprobante" en el archivo.', 'error');
            return;
        }

        const origHeaders = raw[headerIdx].map(c => String(c ?? ''));
        colCompIdx = origHeaders.findIndex(h => normCol(h) === 'comprobante');

        // 3. Construir nuevos encabezados: reemplazar "Comprobante" por Tipo / Punto / Factura
        const newHeaders = [];
        for (let i = 0; i < origHeaders.length; i++) {
            if (i === colCompIdx) {
                newHeaders.push('Tipo', 'Punto', 'Factura');
            } else {
                newHeaders.push(origHeaders[i]);
            }
        }

        // 4. Transformar filas de datos
        let okCount = 0, nokCount = 0;
        const newRows = [];
        for (let r = headerIdx + 1; r < raw.length; r++) {
            const row = raw[r];
            // Saltar filas completamente vacías
            if (!row || row.every(c => c === '' || c === null || c === undefined)) continue;

            const newRow = [];
            for (let i = 0; i < origHeaders.length; i++) {
                if (i === colCompIdx) {
                    const parsed = parseComprobante(row[i]);
                    if (parsed.ok) okCount++; else if (String(row[i] ?? '').trim()) nokCount++;
                    newRow.push(parsed.tipo, parsed.punto ? Number(parsed.punto) : '', parsed.factura ? Number(parsed.factura) : '');
                } else {
                    newRow.push(row[i] ?? '');
                }
            }
            newRows.push(newRow);
        }

        if (!newRows.length) { toast('No se encontraron filas de datos.', 'error'); return; }

        processedData = { headers: newHeaders, rows: newRows, origCompIdx: colCompIdx };

        renderStats(newRows.length, okCount, nokCount);
        currentPage = 1;
        renderTabla();
        document.getElementById('resultado').style.display = 'block';
        document.getElementById('resultado').scrollIntoView({ behavior: 'smooth', block: 'start' });
        document.getElementById('res-titulo').textContent = `${newRows.length} filas procesadas — ${f.name}`;
        toast(`${newRows.length} filas procesadas`, 'success');

    } catch(e) {
        toast('Error al procesar: ' + e.message, 'error');
        console.error(e);
    } finally {
        btn.disabled = false;
        document.getElementById('btn-txt').style.display     = 'inline';
        document.getElementById('btn-spinner').style.display = 'none';
    }
});

// ── Stats ─────────────────────────────────────────────
function renderStats(total, ok, nok) {
    document.getElementById('stats-cards').innerHTML = `
        <div class="stat-card amber">
            <div class="stat-label">Total filas</div>
            <div class="stat-value amber">${total}</div>
            <div class="stat-sub">en el archivo</div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Separadas OK</div>
            <div class="stat-value green">${ok}</div>
            <div class="stat-sub">con formato reconocido</div>
        </div>
        <div class="stat-card ${nok > 0 ? 'red' : ''}">
            <div class="stat-label">Sin separar</div>
            <div class="stat-value ${nok > 0 ? 'red' : 'green'}">${nok}</div>
            <div class="stat-sub">${nok > 0 ? 'formato no reconocido' : 'todo OK'}</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-label">Columnas nuevas</div>
            <div class="stat-value" style="color:var(--accent-light)">3</div>
            <div class="stat-sub">Tipo · Punto · Factura</div>
        </div>
    `;
}

// ── Tabla preview ─────────────────────────────────────
function renderTabla() {
    if (!processedData.headers) return;
    const { headers, rows } = processedData;

    // Índices de las 3 columnas nuevas
    const newColStart = colCompIdx;          // Tipo está aquí
    const newColEnd   = colCompIdx + 2;      // Factura está aquí

    // Encabezados
    document.getElementById('res-thead').innerHTML = '<tr>' +
        headers.map((h, i) => {
            const isNew = i >= newColStart && i <= newColEnd;
            return `<th class="${isNew ? 'col-nueva' : ''}" style="white-space:nowrap">${escHtml(h)}</th>`;
        }).join('') + '</tr>';

    // Filas paginadas
    const total = rows.length;
    const start = (currentPage - 1) * PAGE_SIZE;
    const slice = rows.slice(start, start + PAGE_SIZE);

    document.getElementById('res-tbody').innerHTML = slice.map(row => {
        const celdas = row.map((v, i) => {
            const isNew = i >= newColStart && i <= newColEnd;
            const disp  = v === '' || v === null || v === undefined ? '' : String(v);
            const noMatch = isNew && i === newColStart && disp && !processedData.rows.some(r => r[i] === v);
            return `<td class="${isNew ? 'col-nueva' : ''}" style="white-space:nowrap">${escHtml(disp)}</td>`;
        }).join('');
        return `<tr>${celdas}</tr>`;
    }).join('');

    // Paginación
    const totalPages = Math.ceil(total / PAGE_SIZE);
    let pag = `<span>${total} filas</span>`;
    if (totalPages > 1) {
        if (currentPage > 1) pag += `<a class="page-btn" onclick="goPage(${currentPage - 1})">‹</a>`;
        const from = Math.max(1, currentPage - 2), to = Math.min(totalPages, currentPage + 2);
        for (let p = from; p <= to; p++) pag += `<a class="page-btn ${p === currentPage ? 'active' : ''}" onclick="goPage(${p})">${p}</a>`;
        if (currentPage < totalPages) pag += `<a class="page-btn" onclick="goPage(${currentPage + 1})">›</a>`;
    }
    document.getElementById('pag-wrap').innerHTML = pag;
}

function goPage(p) { currentPage = p; renderTabla(); document.getElementById('res-tabla').scrollIntoView({ behavior: 'smooth', block: 'start' }); }

// ── Exportar Excel ────────────────────────────────────
document.getElementById('btn-exportar').addEventListener('click', () => {
    if (!processedData.headers) return;
    const { headers, rows } = processedData;

    const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);

    // Ancho de columnas
    ws['!cols'] = headers.map(() => ({ wch: 18 }));

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Datos');

    const base  = originalName.replace(/\.(xlsx|xls)$/i, '');
    const fname = `${base}_separado.xlsx`;
    XLSX.writeFile(wb, fname);
    toast('Excel descargado', 'success');
});

// ── Helpers ───────────────────────────────────────────
function readExcel(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = e => {
            try {
                const wb  = XLSX.read(e.target.result, { type: 'array', cellDates: false });
                const ws  = wb.Sheets[wb.SheetNames[0]];
                const raw = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '', raw: true });
                resolve(raw);
            } catch(err) { reject(err); }
        };
        reader.onerror = reject;
        reader.readAsArrayBuffer(file);
    });
}

function escHtml(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
