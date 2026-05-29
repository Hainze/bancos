<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Subir Excel de Cobranza';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:680px;margin:0 auto">
    <div class="card-title">Subir cartera de cobranza</div>
    <p style="color:var(--sub);font-size:14px;margin-bottom:4px">
        Seleccioná el Excel con las columnas: <strong>Sistema · Código · Nombre · 1-30 días · 31-60 días · 61-90 días · 91-120 días · Más de 120 · TOTAL</strong>
    </p>
    <p style="color:var(--sub);font-size:13px;margin-bottom:24px">
        Los valores negativos (saldo a favor) se respetan. Clientes con total ≤ 0 quedan como "Sin acción".
    </p>

    <div class="upload-zone" id="upload-zone">
        <div class="upload-icon">📊</div>
        <div class="upload-text">Arrastrá el Excel acá</div>
        <div class="upload-sub">o hacé clic para seleccionar (.xlsx, .xls)</div>
        <input type="file" id="file-input" accept=".xlsx,.xls" style="display:none">
    </div>

    <div id="file-info" style="display:none;margin-top:16px">
        <div style="display:flex;align-items:center;gap:10px;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px 16px">
            <span style="font-size:20px">📊</span>
            <div style="flex:1;min-width:0">
                <div id="file-name" style="font-size:14px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></div>
                <div id="file-size" style="font-size:12px;color:var(--sub)"></div>
            </div>
            <button onclick="clearFile()" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;padding:4px">✕</button>
        </div>
    </div>

    <div id="progress-wrap" style="display:none;margin-top:16px">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px">
            <span style="font-size:13px;color:var(--sub)" id="progress-label">Procesando…</span>
            <span style="font-size:13px;color:var(--sub)" id="progress-pct">0%</span>
        </div>
        <div style="background:var(--surface);border-radius:4px;height:6px;overflow:hidden">
            <div id="progress-bar" style="height:100%;background:var(--accent);width:0%;transition:width 0.3s"></div>
        </div>
    </div>

    <div style="margin-top:24px">
        <button class="btn btn-primary" id="btn-procesar" disabled style="width:100%">
            <span id="btn-text">⬆ Procesar y guardar</span>
        </button>
    </div>
</div>

<!-- Resultado -->
<div id="resultado" style="display:none;margin-top:24px">

    <!-- Resumen rápido -->
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr)" id="res-stats"></div>

    <!-- Tabla de resultados -->
    <div class="card" style="margin-top:24px">
        <div class="flex-between mb-16">
            <div class="card-title" style="margin:0" id="res-titulo">Vista previa</div>
            <div style="display:flex;gap:8px;align-items:center">
                <!-- Filtro por prioridad -->
                <select class="form-control" id="filtro-prioridad" style="width:auto;font-size:13px" onchange="filtrarTabla()">
                    <option value="">Todas las prioridades</option>
                    <option value="URGENCIA">Urgencia</option>
                    <option value="LLAMAR">Llamar</option>
                    <option value="COBRAR AL FINAL">Cobrar al final</option>
                    <option value="SIN ACCIÓN">Sin acción</option>
                </select>
                <button class="btn btn-success" id="btn-exportar">⬇ Descargar Excel</button>
            </div>
        </div>

        <!-- Leyenda de prioridades -->
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
            <span style="font-size:12px;display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;background:#ef4444;border-radius:2px;display:inline-block"></span> Urgencia</span>
            <span style="font-size:12px;display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;background:#f97316;border-radius:2px;display:inline-block"></span> Llamar</span>
            <span style="font-size:12px;display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;background:#f59e0b;border-radius:2px;display:inline-block"></span> Cobrar al final</span>
            <span style="font-size:12px;display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;background:#4a5f7a;border-radius:2px;display:inline-block"></span> Sin acción</span>
        </div>

        <div class="table-wrap" style="overflow-x:auto">
            <table id="res-tabla" style="font-size:12px;min-width:1100px">
                <thead>
                    <tr>
                        <th>Sis.</th>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th style="text-align:right">1–30d</th>
                        <th style="text-align:right">31–60d</th>
                        <th style="text-align:right">61–90d</th>
                        <th style="text-align:right">91–120d</th>
                        <th style="text-align:right">+120d</th>
                        <th style="text-align:right">Total</th>
                        <th>Prioridad</th>
                        <th>Observación</th>
                    </tr>
                </thead>
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
    transition: all 0.2s;
    background: var(--surface);
}
.upload-zone:hover, .upload-zone.dragover {
    border-color: var(--accent);
    background: rgba(37,99,235,0.06);
}
.upload-icon { font-size: 40px; margin-bottom: 12px; }
.upload-text { font-size: 16px; font-weight: 600; margin-bottom: 4px; }
.upload-sub  { font-size: 13px; color: var(--sub); }

.pri-urgencia   { color: #ef4444; font-weight: 700; }
.pri-llamar     { color: #f97316; font-weight: 600; }
.pri-final      { color: #f59e0b; }
.pri-sinaccion  { color: #4a5f7a; }
</style>

<script>
// ──────────────────────────────────────────────────────
// Estado global
// ──────────────────────────────────────────────────────
let allRows   = [];   // filas procesadas con prioridades
let loteId    = null; // ID guardado en DB
let fileName  = '';

const PAGE_SIZE = 100;
let currentPage = 1;

// ──────────────────────────────────────────────────────
// Upload zone
// ──────────────────────────────────────────────────────
const zone      = document.getElementById('upload-zone');
const fileInput = document.getElementById('file-input');

zone.addEventListener('click',    () => fileInput.click());
zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => {
    e.preventDefault(); zone.classList.remove('dragover');
    const f = e.dataTransfer.files[0];
    if (f) setFile(f);
});
fileInput.addEventListener('change', () => { if (fileInput.files[0]) setFile(fileInput.files[0]); });

function setFile(f) {
    const ext = f.name.split('.').pop().toLowerCase();
    if (!['xlsx','xls'].includes(ext)) { toast('Solo se aceptan archivos .xlsx o .xls', 'warning'); return; }
    fileName = f.name;
    document.getElementById('file-name').textContent = f.name;
    document.getElementById('file-size').textContent = (f.size / 1024).toFixed(1) + ' KB';
    document.getElementById('file-info').style.display = 'block';
    document.getElementById('btn-procesar').disabled = false;
    fileInput._file = f;
}

function clearFile() {
    document.getElementById('file-info').style.display = 'none';
    document.getElementById('btn-procesar').disabled = true;
    fileInput.value = '';
    fileInput._file = null;
    allRows = [];
    loteId = null;
    document.getElementById('resultado').style.display = 'none';
    setProgress(false);
}

function setProgress(on, label = '', pct = 0) {
    document.getElementById('progress-wrap').style.display = on ? 'block' : 'none';
    if (on) {
        document.getElementById('progress-label').textContent = label;
        document.getElementById('progress-pct').textContent   = pct + '%';
        document.getElementById('progress-bar').style.width   = pct + '%';
    }
}

// ──────────────────────────────────────────────────────
// Parsear Excel con SheetJS
// ──────────────────────────────────────────────────────
function parseExcelFile(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = e => {
            try {
                const wb   = XLSX.read(e.target.result, { type: 'array' });
                const ws   = wb.Sheets[wb.SheetNames[0]];
                const rows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });
                resolve(rows);
            } catch(err) { reject(err); }
        };
        reader.onerror = reject;
        reader.readAsArrayBuffer(file);
    });
}

function parseNum(v) {
    if (v === '' || v === null || v === undefined) return 0;
    // SheetJS ya devuelve números JS para celdas numéricas — usarlos directo
    if (typeof v === 'number') return v;
    // Para celdas de texto con formato argentino: "1.234,56" → 1234.56
    const s = String(v).trim().replace(/\./g,'').replace(',','.');
    const n = parseFloat(s);
    return isNaN(n) ? 0 : n;
}

function parseSistema(v) {
    // Acepta: 2, "2", 2.0, "Sistema 2", "negro", cualquier cosa que empiece con 2
    const n = parseFloat(v);
    if (n === 2) return 2;
    const s = String(v).trim().toLowerCase();
    if (s.startsWith('2') || s === 'sistema 2' || s === 'negro') return 2;
    return 1;
}

// Detecta la fila de encabezados (contiene "Sistema" o "Código" etc.)
function findHeaderRow(rows) {
    for (let i = 0; i < Math.min(rows.length, 10); i++) {
        const row = rows[i];
        const joined = row.join(' ').toLowerCase();
        if (joined.includes('sistema') || joined.includes('codigo') || joined.includes('código')) {
            return i;
        }
    }
    return 0; // primera fila si no se encuentra
}

// Mapea columnas del Excel a índices
function mapColumns(headerRow) {
    const map = { sistema:-1, codigo:-1, nombre:-1, d30:-1, d60:-1, d90:-1, d120:-1, d120plus:-1, total:-1 };
    headerRow.forEach((cell, idx) => {
        const h = String(cell).trim().toLowerCase()
            .normalize('NFD').replace(/[̀-ͯ]/g,'');

        if (/^sistema/.test(h))              map.sistema   = idx;
        else if (/^c[oó]digo|^cod/.test(h))  map.codigo    = idx;
        else if (/^nombre/.test(h))          map.nombre    = idx;
        else if (/^1[-–]30|1 a 30/.test(h))  map.d30       = idx;
        else if (/31[-–]60|31 a 60/.test(h)) map.d60       = idx;
        else if (/61[-–]90|61 a 90/.test(h)) map.d90       = idx;
        else if (/91[-–]120|91 a 120/.test(h)) map.d120    = idx;
        else if (/m[aá]s de 120|120\+|\+120/.test(h)) map.d120plus = idx;
        else if (/^total$/.test(h))          map.total     = idx;
    });
    return map;
}

// ──────────────────────────────────────────────────────
// Lógica de prioridad
// ──────────────────────────────────────────────────────
function calcPriority(d30, d60, d90, d120, d120plus, total) {
    if (total <= 0) return 'SIN ACCIÓN';

    // Urgencia: mucho tiempo sin pagar o deuda muy grande
    if (d120plus >= 50000 || total >= 1000000) return 'URGENCIA';
    if (d120plus > 0 && total >= 100000)       return 'URGENCIA';

    // Llamar: deuda antigua moderada o monto relevante
    if (d120 >= 10000 || d90 >= 10000)         return 'LLAMAR';
    if (total >= 100000)                        return 'LLAMAR';
    if (d120 > 0 || d90 > 0)                   return 'LLAMAR';

    // Cobrar al final: solo deuda reciente o montos chicos
    if (total >= 1)                             return 'COBRAR AL FINAL';

    return 'SIN ACCIÓN';
}

// ──────────────────────────────────────────────────────
// Procesar
// ──────────────────────────────────────────────────────
document.getElementById('btn-procesar').addEventListener('click', async () => {
    const f = fileInput._file || fileInput.files[0];
    if (!f) return;

    const btn = document.getElementById('btn-procesar');
    btn.disabled = true;
    document.getElementById('btn-text').textContent = 'Procesando…';
    setProgress(true, 'Leyendo Excel…', 20);

    try {
        // 1. Leer Excel
        const rawRows = await parseExcelFile(f);
        setProgress(true, 'Analizando datos…', 50);

        const headerIdx = findHeaderRow(rawRows);
        const colMap    = mapColumns(rawRows[headerIdx]);

        if (colMap.nombre < 0 || colMap.total < 0) {
            toast('No se detectaron las columnas requeridas (Nombre, Total). Verificá el formato.', 'error');
            return;
        }

        // 2. Construir filas
        const parsed = [];
        for (let i = headerIdx + 1; i < rawRows.length; i++) {
            const row = rawRows[i];
            if (!row || row.every(c => c === '' || c === null || c === undefined)) continue;

            const nombre = String(row[colMap.nombre] ?? '').trim();
            if (!nombre) continue;

            // Sistema: usa columna detectada por nombre, sino cae a la primera columna (índice 0)
            const sistemaRaw = colMap.sistema >= 0 ? row[colMap.sistema] : row[0];
            const sistema  = parseSistema(sistemaRaw);
            const codigo   = colMap.codigo   >= 0 ? String(row[colMap.codigo] ?? '').trim() : '';
            // Montos: el Excel guarda en centavos enteros → dividir por 100
            const d30      = colMap.d30      >= 0 ? parseNum(row[colMap.d30])      / 100 : 0;
            const d60      = colMap.d60      >= 0 ? parseNum(row[colMap.d60])      / 100 : 0;
            const d90      = colMap.d90      >= 0 ? parseNum(row[colMap.d90])      / 100 : 0;
            const d120     = colMap.d120     >= 0 ? parseNum(row[colMap.d120])     / 100 : 0;
            const d120plus = colMap.d120plus >= 0 ? parseNum(row[colMap.d120plus]) / 100 : 0;
            const total    = colMap.total    >= 0 ? parseNum(row[colMap.total])    / 100 : (d30 + d60 + d90 + d120 + d120plus);

            parsed.push({ sistema, codigo, nombre, d30, d60, d90, d120, d120plus, total });
        }

        if (!parsed.length) {
            toast('No se encontraron filas de datos válidas en el Excel.', 'error');
            return;
        }

        // 3. Cargar padrones para enriquecer (falla silenciosa — no es bloqueante)
        setProgress(true, 'Cargando padrones…', 65);
        const padMap = {};
        try {
            const padRes  = await fetch('/cobranza/api/padrones.php?action=listar_todos');
            if (padRes.ok) {
                const padData = await padRes.json();
                (padData.data || []).forEach(p => { padMap[p.sistema + '_' + p.codigo] = p.observacion; });
            }
        } catch(e) {
            // Padrones no disponibles — se continúa sin observaciones
        }

        // 4. Calcular prioridades y observaciones
        allRows = parsed.map(r => {
            const priority = calcPriority(r.d30, r.d60, r.d90, r.d120, r.d120plus, r.total);
            const obs      = padMap[r.sistema + '_' + r.codigo] || '';
            return { ...r, priority, observacion: obs };
        });

        // Ordenar: primero urgencia, luego llamar, luego cobrar al final, luego sin acción
        const priOrder = { 'URGENCIA': 0, 'LLAMAR': 1, 'COBRAR AL FINAL': 2, 'SIN ACCIÓN': 3 };
        allRows.sort((a, b) => {
            const po = priOrder[a.priority] - priOrder[b.priority];
            if (po !== 0) return po;
            return b.total - a.total; // mismo nivel: mayor deuda primero
        });

        // 5. Guardar en DB
        setProgress(true, 'Guardando en base de datos…', 85);
        const saveRes  = await fetch('/cobranza/api/cartera.php?action=guardar_lote', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ archivo_nombre: f.name, filas: allRows }),
        });
        const saveData = await saveRes.json();
        if (saveData.error) { toast(saveData.error, 'error'); return; }
        loteId = saveData.lote_id;

        setProgress(true, 'Listo', 100);
        setTimeout(() => setProgress(false), 400);

        renderResultado();
        toast(`${allRows.length} clientes procesados`, 'success');

    } catch(e) {
        toast('Error: ' + e.message, 'error');
        console.error(e);
    } finally {
        btn.disabled = false;
        document.getElementById('btn-text').textContent = '⬆ Procesar y guardar';
    }
});

// ──────────────────────────────────────────────────────
// Render resultado
// ──────────────────────────────────────────────────────
function getFilteredRows() {
    const f = document.getElementById('filtro-prioridad').value;
    return f ? allRows.filter(r => r.priority === f) : allRows;
}

function filtrarTabla() {
    currentPage = 1;
    renderTabla();
}

function renderResultado() {
    document.getElementById('resultado').style.display = 'block';
    document.getElementById('resultado').scrollIntoView({ behavior:'smooth', block:'start' });
    document.getElementById('res-titulo').textContent = `${allRows.length} clientes — ${fileName}`;

    // Stats rápidos
    const counts = { 'URGENCIA':0,'LLAMAR':0,'COBRAR AL FINAL':0,'SIN ACCIÓN':0 };
    allRows.forEach(r => counts[r.priority]++);
    document.getElementById('res-stats').innerHTML = `
        <div class="stat-card" style="border-color:rgba(239,68,68,.3)">
            <div class="stat-label">Urgencia</div>
            <div class="stat-value red">${counts['URGENCIA']}</div>
            <div class="stat-sub">clientes</div>
        </div>
        <div class="stat-card" style="border-color:rgba(249,115,22,.3)">
            <div class="stat-label">Llamar</div>
            <div class="stat-value" style="color:#f97316">${counts['LLAMAR']}</div>
            <div class="stat-sub">clientes</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-label">Cobrar al final</div>
            <div class="stat-value amber">${counts['COBRAR AL FINAL']}</div>
            <div class="stat-sub">clientes</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Sin acción</div>
            <div class="stat-value" style="color:var(--muted)">${counts['SIN ACCIÓN']}</div>
            <div class="stat-sub">saldo ≤ 0</div>
        </div>
    `;

    currentPage = 1;
    renderTabla();
}

function renderTabla() {
    const rows   = getFilteredRows();
    const total  = rows.length;
    const start  = (currentPage - 1) * PAGE_SIZE;
    const slice  = rows.slice(start, start + PAGE_SIZE);

    const priClass = {
        'URGENCIA':      'pri-urgencia',
        'LLAMAR':        'pri-llamar',
        'COBRAR AL FINAL': 'pri-final',
        'SIN ACCIÓN':    'pri-sinaccion',
    };

    document.getElementById('res-tbody').innerHTML = slice.map(r => {
        const pc = priClass[r.priority] || '';
        const fmtC = (v) => {
            if (v === 0) return '<span style="color:var(--muted)">—</span>';
            const cls = v < 0 ? 'style="color:var(--green)"' : (v >= 50000 ? 'style="color:var(--red)"' : '');
            return `<span ${cls}>${fmt(v)}</span>`;
        };
        return `<tr>
            <td><span class="badge ${r.sistema===1?'badge-blue':'badge-red'}" style="font-size:10px">S${r.sistema}</span></td>
            <td class="mono" style="font-size:11px">${r.codigo || '—'}</td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(r.nombre)}">${escHtml(r.nombre)}</td>
            <td class="mono" style="text-align:right">${fmtC(r.d30)}</td>
            <td class="mono" style="text-align:right">${fmtC(r.d60)}</td>
            <td class="mono" style="text-align:right">${fmtC(r.d90)}</td>
            <td class="mono" style="text-align:right">${fmtC(r.d120)}</td>
            <td class="mono" style="text-align:right">${fmtC(r.d120plus)}</td>
            <td class="mono" style="text-align:right;font-weight:600">${fmt(r.total)}</td>
            <td class="${pc}" style="white-space:nowrap">${r.priority}</td>
            <td style="font-size:11px;color:var(--sub);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(r.observacion)}">${r.observacion || '—'}</td>
        </tr>`;
    }).join('');

    // Paginación
    const totalPages = Math.ceil(total / PAGE_SIZE);
    let pag = `<span>${total} registros${getFilteredRows().length < allRows.length ? ' filtrados' : ''}</span>`;
    if (totalPages > 1) {
        if (currentPage > 1) pag += `<a class="page-btn" onclick="goPage(${currentPage-1})">‹</a>`;
        const from = Math.max(1, currentPage - 2);
        const to   = Math.min(totalPages, currentPage + 2);
        for (let p = from; p <= to; p++) {
            pag += `<a class="page-btn ${p===currentPage?'active':''}" onclick="goPage(${p})">${p}</a>`;
        }
        if (currentPage < totalPages) pag += `<a class="page-btn" onclick="goPage(${currentPage+1})">›</a>`;
    }
    document.getElementById('pag-wrap').innerHTML = pag;
}

function goPage(p) {
    currentPage = p;
    renderTabla();
    document.getElementById('res-tabla')?.scrollIntoView({ behavior:'smooth', block:'start' });
}

function fmt(v) {
    const n = parseFloat(v || 0);
    if (n === 0) return '$ 0';
    const abs = Math.abs(n);
    const s = abs.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return (n < 0 ? '-$ ' : '$ ') + s;
}

function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ──────────────────────────────────────────────────────
// Exportar Excel con SheetJS
// ──────────────────────────────────────────────────────
document.getElementById('btn-exportar').addEventListener('click', () => {
    if (!allRows.length) return;
    exportarExcel();
});

function exportarExcel() {
    const headers = [
        'Sistema','Código','Nombre',
        '1-30 días','31-60 días','61-90 días','91-120 días','Más de 120','TOTAL',
        'Prioridad','Observación',
    ];

    const sysLabel = s => s === 1 ? 'Sistema 1 (blanco)' : 'Sistema 2 (negro)';

    const dataRows = allRows.map(r => [
        sysLabel(r.sistema),
        r.codigo,
        r.nombre,
        r.d30,
        r.d60,
        r.d90,
        r.d120,
        r.d120plus,
        r.total,
        r.priority,
        r.observacion || '',
    ]);

    const ws = XLSX.utils.aoa_to_sheet([headers, ...dataRows]);

    // Anchos de columna
    ws['!cols'] = [
        { wch: 22 }, { wch: 12 }, { wch: 35 },
        { wch: 14 }, { wch: 14 }, { wch: 14 }, { wch: 14 }, { wch: 14 }, { wch: 16 },
        { wch: 18 }, { wch: 30 },
    ];

    const wb   = XLSX.utils.book_new();
    const name = fileName.replace(/\.[^.]+$/, '').substring(0, 25);
    XLSX.utils.book_append_sheet(wb, ws, 'Cobranza');
    const fname = `Cobranza_${name}_prioridades.xlsx`.replace(/\s+/g,'_').replace(/[^\w._-]/g,'');
    XLSX.writeFile(wb, fname);
    toast('Excel descargado', 'success');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
