<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Analizar Echeqs';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Upload card -->
<div class="card" style="max-width:700px;margin:0 auto 24px">
    <div class="card-title">Subir listado de echeqs</div>
    <p style="color:var(--sub);font-size:13px;margin-bottom:16px">
        El sistema detecta automáticamente las columnas. Columnas reconocidas:
        <strong>número, monto/importe, fecha vencimiento, fecha emisión, estado, emisor, CUIT, beneficiario, banco, tipo.</strong>
        Si alguna columna tiene otro nombre, mañana lo ajustamos.
    </p>

    <div class="upload-zone" id="upload-zone">
        <input type="file" id="file-input" accept=".xlsx,.xls,.csv">
        <div class="upload-icon">🧾</div>
        <div class="upload-title">Arrastrá el Excel de echeqs acá</div>
        <div class="upload-sub">o hacé clic para seleccionar — .xlsx / .xls / .csv</div>
    </div>

    <div id="file-info" style="display:none;margin-top:12px" class="alert alert-success">
        <span>✓</span>
        <span id="file-name">—</span>
    </div>

    <!-- Columnas detectadas -->
    <div id="cols-detectadas" style="display:none;margin-top:12px;padding:10px 14px;background:var(--surface);border:1px solid var(--border);border-radius:8px;font-size:12px;color:var(--sub)">
        <strong style="color:var(--text)">Columnas detectadas:</strong>
        <span id="cols-lista"></span>
    </div>

    <div style="margin-top:16px;display:flex;gap:10px">
        <button class="btn btn-primary btn-lg" id="btn-analizar" disabled>
            <span id="btn-txt">⚙ Analizar</span>
            <span id="btn-spinner" class="spinner" style="display:none"></span>
        </button>
        <button class="btn btn-secondary" id="btn-limpiar" style="display:none">✕ Limpiar</button>
    </div>
</div>

<!-- Resultados -->
<div id="resultado" style="display:none">

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(5,1fr)" id="stats-cards"></div>

    <!-- Tabla -->
    <div class="card" style="margin-top:24px">
        <div class="flex-between mb-16">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <div class="card-title" style="margin:0" id="res-titulo">Resultado</div>
                <div style="display:flex;gap:6px;flex-wrap:wrap" id="filtros-accion"></div>
            </div>
            <button class="btn btn-success" id="btn-exportar">⬇ Descargar Excel analizado</button>
        </div>

        <!-- Leyenda -->
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px" id="leyenda"></div>

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
.upload-zone { border:2px dashed var(--border);border-radius:12px;padding:48px 24px;text-align:center;cursor:pointer;transition:all .2s;background:var(--surface); }
.upload-zone:hover,.upload-zone.dragover { border-color:#8b5cf6;background:rgba(139,92,246,.06); }
.upload-icon { font-size:40px;margin-bottom:12px; }
.upload-title { font-size:16px;font-weight:600;margin-bottom:4px; }
.upload-sub   { font-size:13px;color:var(--sub); }

.accion-badge { display:inline-block;padding:3px 9px;border-radius:12px;font-size:11px;font-weight:700;font-family:'Space Mono',monospace;white-space:nowrap; }
.ac-vencido       { background:rgba(239,68,68,.18);  color:#ef4444; }
.ac-hoy           { background:rgba(239,68,68,.25);  color:#ef4444; animation:pulse 1.2s infinite; }
.ac-urgente       { background:rgba(249,115,22,.18); color:#f97316; }
.ac-proximo       { background:rgba(245,158,11,.18); color:#f59e0b; }
.ac-pendiente     { background:rgba(37,99,235,.15);  color:var(--accent-light); }
.ac-endosado      { background:rgba(16,185,129,.15); color:#10b981; }
.ac-depositado    { background:rgba(16,185,129,.2);  color:#059669; }
.ac-rechazado     { background:rgba(74,95,122,.2);   color:#4a5f7a; }
.ac-sin-fecha     { background:rgba(139,92,246,.15); color:#a78bfa; }

@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.5} }

.filter-pill { padding:4px 12px;border-radius:20px;border:1px solid var(--border);background:var(--surface);color:var(--sub);font-size:11px;cursor:pointer;transition:all .15s; }
.filter-pill.active,.filter-pill:hover { background:#8b5cf6;border-color:#8b5cf6;color:#fff; }
</style>

<script>
// ── Estado ────────────────────────────────────────────────
let allRows    = [];   // rows con análisis
let colMap     = {};   // columna → índice
let origHeaders= [];   // encabezados originales
let filtroActivo = '';
const PAGE_SIZE  = 150;
let currentPage  = 1;

// ── Upload zone ───────────────────────────────────────────
initDropZone('upload-zone', f => setFile(f));

function setFile(f) {
    const ext = f.name.split('.').pop().toLowerCase();
    if (!['xlsx','xls','csv'].includes(ext)) { toast('Solo .xlsx, .xls o .csv', 'warning'); return; }
    document.getElementById('file-name').textContent = f.name + ' (' + (f.size/1024).toFixed(1) + ' KB)';
    document.getElementById('file-info').style.display = 'flex';
    document.getElementById('btn-analizar').disabled = false;
    document.getElementById('btn-limpiar').style.display = 'inline-flex';
    document.getElementById('file-input')._file = f;
    // Pre-leer para mostrar columnas
    previewCols(f);
}

document.getElementById('btn-limpiar').addEventListener('click', () => {
    document.getElementById('file-input').value = '';
    document.getElementById('file-input')._file = null;
    document.getElementById('file-info').style.display = 'none';
    document.getElementById('cols-detectadas').style.display = 'none';
    document.getElementById('btn-analizar').disabled = true;
    document.getElementById('btn-limpiar').style.display = 'none';
    document.getElementById('resultado').style.display = 'none';
    allRows = [];
});

async function previewCols(file) {
    try {
        const raw = await readExcel(file);
        if (!raw.length) return;
        const { headers } = parseEcheqRows(raw);
        const map = detectColumns(headers);
        const detected = Object.entries(map).filter(([k,v]) => v >= 0).map(([k]) => k);
        document.getElementById('cols-lista').textContent = ' ' + detected.join(', ');
        document.getElementById('cols-detectadas').style.display = 'block';
    } catch(e) {}
}

// ── Leer Excel ────────────────────────────────────────────
function readExcel(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = e => {
            try {
                const wb  = XLSX.read(e.target.result, { type:'array', cellDates:true });
                const ws  = wb.Sheets[wb.SheetNames[0]];
                const raw = XLSX.utils.sheet_to_json(ws, { header:1, defval:'', raw:false });
                resolve(raw);
            } catch(err) { reject(err); }
        };
        reader.onerror = reject;
        reader.readAsArrayBuffer(file);
    });
}

// ── Parser multi-fila (formato banco: importe y estado en filas separadas) ──
function parseEcheqRows(raw) {
    if (!raw.length) return { headers: [], rows: [] };

    // Buscar fila de encabezados en las primeras 5 filas
    let headerIdx = 0;
    for (let i = 0; i < Math.min(raw.length, 5); i++) {
        if (raw[i].some(c => /echeq|nro|emisor|importe/i.test(String(c)))) {
            headerIdx = i; break;
        }
    }

    const headers = raw[headerIdx].map(String);
    const importeIdx = headers.findIndex(h => /importe|monto/i.test(h));
    const estadoIdx  = headers.findIndex(h => /^estado$/i.test(h));
    const dataStart  = headerIdx + 1;

    // Detectar formato: ¿tiene el importe en su propia columna?
    const firstData = raw.slice(dataStart).find(r => r.some(c => c !== '' && c !== null && c !== undefined));
    if (firstData && importeIdx >= 0 && firstData[importeIdx] !== '' && firstData[importeIdx] !== null) {
        // Formato estándar: una fila por echeq
        return { headers, rows: raw.slice(dataStart).filter(r => r.some(c => c !== '' && c !== null)) };
    }

    // Formato multi-fila: importe y estado vienen en filas adicionales (solo en col[0])
    const rows = [];
    let i = dataStart;

    while (i < raw.length) {
        const row = raw[i];
        if (!row || row.every(c => c === '' || c === null || c === undefined)) { i++; continue; }

        const col0 = String(row[0] || '').trim();

        // La fila principal empieza con el número de echeq (solo dígitos, 4+)
        if (!/^\d{4,}$/.test(col0)) { i++; continue; }

        const mainRow = row.map(c => (c === null || c === undefined) ? '' : c);
        i++;

        let importe = '';
        let estado  = '';

        // Recoger filas de continuación hasta fila vacía o nuevo echeq
        while (i < raw.length) {
            const next = raw[i];
            if (!next || next.every(c => c === '' || c === null || c === undefined)) break;

            const v0 = String(next[0] || '').trim();
            if (/^\d{4,}$/.test(v0)) break; // nuevo echeq

            // Si hay 2+ columnas con datos reales → nueva fila principal
            const extraFilled = next.slice(1).filter(c => c !== '' && c !== null && c !== undefined && c !== '-').length;
            if (extraFilled >= 2) break;

            if (!importe && /\$|^\d[\d.,]{3,}/.test(v0.replace(/\s/g, ''))) {
                importe = v0;
            } else if (!estado && v0) {
                estado = v0;
            }
            i++;
        }

        const merged = [...mainRow];
        if (importeIdx >= 0 && merged[importeIdx] === '') merged[importeIdx] = importe;
        if (estadoIdx  >= 0 && merged[estadoIdx]  === '') merged[estadoIdx]  = estado;

        rows.push(merged);
    }

    return { headers, rows };
}

// ── Detectar columnas ─────────────────────────────────────
function detectColumns(headerRow) {
    const map = {
        numero:-1, monto:-1, fecha_venc:-1, fecha_emis:-1,
        estado:-1, emisor:-1, cuit_emisor:-1,
        beneficiario:-1, banco:-1, tipo:-1,
    };
    headerRow.forEach((cell, i) => {
        const h = norm(String(cell));
        if      (/^num|^nro|^cheq|^id/.test(h))                          map.numero       = i;
        else if (/monto|importe|valor|impt/.test(h))                      map.monto        = i;
        else if (/venc|pago|cobro/.test(h) && !/emis/.test(h))           map.fecha_venc   = i;
        else if (/emis|origen|fecha emis/.test(h))                        map.fecha_emis   = i;
        else if (/estado|situac|condic/.test(h))                          map.estado       = i;
        else if (/emisor|librad|girad|razon|nombre/.test(h) && !/cuit/.test(h)) map.emisor = i;
        else if (/cuit/.test(h))                                          map.cuit_emisor  = i;
        else if (/benefic|tomad|portad/.test(h))                         map.beneficiario = i;
        else if (/banco/.test(h))                                         map.banco        = i;
        else if (/^tipo/.test(h))                                         map.tipo         = i;
    });
    return map;
}

function norm(s) {
    return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/[^a-z0-9 ]/g,' ').trim();
}

// ── Analizar ──────────────────────────────────────────────
document.getElementById('btn-analizar').addEventListener('click', async () => {
    const f = document.getElementById('file-input')._file;
    if (!f) return;
    const btn = document.getElementById('btn-analizar');
    btn.disabled = true;
    document.getElementById('btn-txt').style.display = 'none';
    document.getElementById('btn-spinner').style.display = 'inline-block';

    try {
        const raw = await readExcel(f);
        if (!raw.length) { toast('El archivo está vacío', 'error'); return; }

        const { headers, rows } = parseEcheqRows(raw);
        origHeaders = headers;
        colMap      = detectColumns(origHeaders);

        if (colMap.monto < 0 && colMap.fecha_venc < 0) {
            toast('No se detectaron columnas de monto ni fecha de vencimiento. Verificá el archivo.', 'warning');
        }

        const today = new Date(); today.setHours(0,0,0,0);

        allRows = rows
            .filter(r => r.some(c => c !== '' && c !== null))
            .map(r => {
                const obj = {};
                origHeaders.forEach((h, i) => obj[h] = r[i] ?? '');

                // Extraer campos clave
                const montoRaw  = colMap.monto    >= 0 ? r[colMap.monto]    : '';
                const vencRaw   = colMap.fecha_venc>= 0 ? r[colMap.fecha_venc]:'';
                const estadoRaw = colMap.estado   >= 0 ? r[colMap.estado]   : '';

                const monto    = parseMontoEcheq(montoRaw);
                const vencDate = parseFechaEcheq(vencRaw);
                const estado   = norm(String(estadoRaw));

                // Días al vencimiento
                let diasVenc = null;
                if (vencDate) {
                    diasVenc = Math.round((vencDate - today) / 86400000);
                }

                // Determinar acción
                const accion = determinarAccion(estado, diasVenc);

                return {
                    ...obj,
                    _monto:    monto,
                    _diasVenc: diasVenc,
                    _accion:   accion,
                    _vencDate: vencDate,
                };
            });

        // Ordenar por urgencia
        const orden = {'VENCIDO':0,'VENCE HOY':1,'ENDOSAR YA':2,'PRÓXIMO':3,'PENDIENTE':4,'ENDOSADO':5,'DEPOSITADO':6,'RECHAZADO':7,'SIN FECHA':8};
        allRows.sort((a, b) => {
            const oa = orden[a._accion] ?? 9;
            const ob = orden[b._accion] ?? 9;
            if (oa !== ob) return oa - ob;
            // Mismo nivel: más urgente primero (vencidos más atrasados, próximos más cercanos)
            if (a._diasVenc !== null && b._diasVenc !== null) return a._diasVenc - b._diasVenc;
            return 0;
        });

        renderResultado();
        toast(`${allRows.length} echeqs analizados`, 'success');

    } catch(e) {
        toast('Error al procesar: ' + e.message, 'error');
        console.error(e);
    } finally {
        btn.disabled = false;
        document.getElementById('btn-txt').style.display = 'inline';
        document.getElementById('btn-spinner').style.display = 'none';
    }
});

// ── Lógica de acción ──────────────────────────────────────
function determinarAccion(estadoNorm, diasVenc) {
    // Estado explícito tiene prioridad
    if (/deposit|acredit|cobrad|pagad/.test(estadoNorm)) return 'DEPOSITADO';
    if (/rechaz|devuelt|impagad|protestado/.test(estadoNorm)) return 'RECHAZADO';
    if (/endosad|cedid|transferid/.test(estadoNorm)) return 'ENDOSADO';

    // Por fecha de vencimiento
    if (diasVenc === null) return 'SIN FECHA';
    if (diasVenc < 0)  return 'VENCIDO';
    if (diasVenc === 0) return 'VENCE HOY';
    if (diasVenc <= 7)  return 'ENDOSAR YA';
    if (diasVenc <= 30) return 'PRÓXIMO';
    return 'PENDIENTE';
}

// ── Render resultado ──────────────────────────────────────
function renderResultado() {
    document.getElementById('resultado').style.display = 'block';
    document.getElementById('resultado').scrollIntoView({ behavior:'smooth', block:'start' });
    document.getElementById('res-titulo').textContent = `${allRows.length} echeqs`;

    // Stats
    const conteos = {};
    let montoTotal = 0;
    allRows.forEach(r => {
        conteos[r._accion] = (conteos[r._accion] || 0) + 1;
        montoTotal += r._monto || 0;
    });

    document.getElementById('stats-cards').innerHTML = `
        <div class="stat-card" style="border-color:rgba(139,92,246,.3)">
            <div class="stat-label">Total echeqs</div>
            <div class="stat-value" style="color:#a78bfa">${allRows.length}</div>
            <div class="stat-sub">${formatMoney(montoTotal)}</div>
        </div>
        <div class="stat-card red">
            <div class="stat-label">Vencidos</div>
            <div class="stat-value red">${(conteos['VENCIDO']||0) + (conteos['VENCE HOY']||0)}</div>
            <div class="stat-sub">atención inmediata</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-label">Endosar ya</div>
            <div class="stat-value amber">${conteos['ENDOSAR YA']||0}</div>
            <div class="stat-sub">vencen en 7 días</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-label">Pendientes</div>
            <div class="stat-value" style="color:var(--accent-light)">${(conteos['PENDIENTE']||0) + (conteos['PRÓXIMO']||0)}</div>
            <div class="stat-sub">más de 7 días</div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Endosados/Dep.</div>
            <div class="stat-value green">${(conteos['ENDOSADO']||0) + (conteos['DEPOSITADO']||0)}</div>
            <div class="stat-sub">finalizados</div>
        </div>
    `;

    // Filtros
    const acciones = [...new Set(allRows.map(r => r._accion))];
    document.getElementById('filtros-accion').innerHTML =
        `<button class="filter-pill active" onclick="setFiltro('',this)">Todos</button>` +
        acciones.map(a => `<button class="filter-pill" onclick="setFiltro('${a}',this)">${a} (${conteos[a]||0})</button>`).join('');

    // Leyenda
    const leyendaDef = [
        ['VENCIDO','ac-vencido'],['VENCE HOY','ac-hoy'],['ENDOSAR YA','ac-urgente'],
        ['PRÓXIMO','ac-proximo'],['PENDIENTE','ac-pendiente'],['ENDOSADO','ac-endosado'],
        ['DEPOSITADO','ac-depositado'],['RECHAZADO','ac-rechazado'],['SIN FECHA','ac-sin-fecha'],
    ];
    document.getElementById('leyenda').innerHTML = leyendaDef
        .filter(([a]) => conteos[a])
        .map(([a, cls]) => `<span class="accion-badge ${cls}">${a}</span>`)
        .join('');

    // Encabezados tabla
    const visibleCols = origHeaders.slice(0, 10); // mostrar hasta 10 cols originales
    document.getElementById('res-thead').innerHTML = `<tr>
        ${visibleCols.map(h => `<th style="white-space:nowrap">${escHtml(h)}</th>`).join('')}
        <th style="width:90px">Días al venc.</th>
        <th style="width:120px">Acción</th>
    </tr>`;

    currentPage = 1;
    renderTabla();
}

function setFiltro(accion, btn) {
    filtroActivo = accion;
    document.querySelectorAll('#filtros-accion .filter-pill').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentPage = 1;
    renderTabla();
}

function getFiltered() {
    return filtroActivo ? allRows.filter(r => r._accion === filtroActivo) : allRows;
}

function renderTabla() {
    const rows  = getFiltered();
    const total = rows.length;
    const start = (currentPage - 1) * PAGE_SIZE;
    const slice = rows.slice(start, start + PAGE_SIZE);

    const visibleCols = origHeaders.slice(0, 10);
    const accionClass = {
        'VENCIDO':'ac-vencido','VENCE HOY':'ac-hoy','ENDOSAR YA':'ac-urgente',
        'PRÓXIMO':'ac-proximo','PENDIENTE':'ac-pendiente','ENDOSADO':'ac-endosado',
        'DEPOSITADO':'ac-depositado','RECHAZADO':'ac-rechazado','SIN FECHA':'ac-sin-fecha',
    };

    document.getElementById('res-tbody').innerHTML = slice.map(r => {
        const celdas = visibleCols.map(h => {
            const v = r[h] ?? '';
            return `<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(String(v))}">${escHtml(String(v))}</td>`;
        }).join('');

        const dias = r._diasVenc === null ? '—'
            : r._diasVenc < 0 ? `<span style="color:var(--red);font-weight:700">${r._diasVenc}d</span>`
            : r._diasVenc === 0 ? `<span style="color:var(--red);font-weight:700">HOY</span>`
            : `<span style="color:${r._diasVenc <= 7 ? 'var(--amber)' : r._diasVenc <=30 ? 'var(--accent-light)' : 'var(--muted)'}">${r._diasVenc}d</span>`;

        const cls = accionClass[r._accion] || '';
        return `<tr>
            ${celdas}
            <td class="mono" style="text-align:center">${dias}</td>
            <td><span class="accion-badge ${cls}">${r._accion}</span></td>
        </tr>`;
    }).join('');

    // Paginación
    const totalPages = Math.ceil(total / PAGE_SIZE);
    let pag = `<span>${total} echeqs${filtroActivo ? ' filtrados' : ''}</span>`;
    if (totalPages > 1) {
        if (currentPage > 1) pag += `<a class="page-btn" onclick="goPage(${currentPage-1})">‹</a>`;
        const from = Math.max(1, currentPage-2), to = Math.min(totalPages, currentPage+2);
        for (let p = from; p <= to; p++) pag += `<a class="page-btn ${p===currentPage?'active':''}" onclick="goPage(${p})">${p}</a>`;
        if (currentPage < totalPages) pag += `<a class="page-btn" onclick="goPage(${currentPage+1})">›</a>`;
    }
    document.getElementById('pag-wrap').innerHTML = pag;
}

function goPage(p) { currentPage = p; renderTabla(); document.getElementById('res-tabla').scrollIntoView({behavior:'smooth',block:'start'}); }

// ── Exportar Excel ────────────────────────────────────────
document.getElementById('btn-exportar').addEventListener('click', () => {
    if (!allRows.length) return;

    const headers = [...origHeaders, 'Días al vencimiento', 'Acción sugerida'];

    const dataRows = allRows.map(r => [
        ...origHeaders.map(h => r[h] ?? ''),
        r._diasVenc !== null ? r._diasVenc : '',
        r._accion,
    ]);

    // Hoja resumen
    const resumen = [
        ['Resumen del análisis', ''],
        ['Total echeqs', allRows.length],
        ['Monto total', allRows.reduce((s,r) => s + (r._monto||0), 0)],
        [''],
    ];
    const conteos = {};
    allRows.forEach(r => { conteos[r._accion] = (conteos[r._accion] || 0) + 1; });
    Object.entries(conteos).sort((a,b) => a[0].localeCompare(b[0])).forEach(([accion, cant]) => {
        const monto = allRows.filter(r => r._accion === accion).reduce((s,r) => s + (r._monto||0), 0);
        resumen.push([accion, cant, monto]);
    });

    const wsDetalle = XLSX.utils.aoa_to_sheet([headers, ...dataRows]);
    wsDetalle['!cols'] = headers.map((_, i) => ({ wch: i >= headers.length - 2 ? 20 : 18 }));

    const wsResumen = XLSX.utils.aoa_to_sheet(resumen);

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, wsDetalle, 'Echeqs');
    XLSX.utils.book_append_sheet(wb, wsResumen, 'Resumen');

    const fname = `Echeqs_analisis_${new Date().toISOString().slice(0,10)}.xlsx`;
    XLSX.writeFile(wb, fname);
    toast('Excel descargado', 'success');
});

// ── Helpers ───────────────────────────────────────────────
function parseMontoEcheq(v) {
    if (!v && v !== 0) return 0;
    if (typeof v === 'number') return v;
    const s = String(v).replace(/[$\s]/g,'').replace(/\./g,'').replace(',','.');
    const n = parseFloat(s);
    return isNaN(n) ? 0 : n;
}

function parseFechaEcheq(v) {
    if (!v) return null;
    if (v instanceof Date) return isNaN(v) ? null : new Date(v.getFullYear(), v.getMonth(), v.getDate());
    const s = String(v).trim();
    // dd/mm/yyyy
    const m1 = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (m1) return new Date(+m1[3], +m1[2]-1, +m1[1]);
    // yyyy-mm-dd
    const m2 = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (m2) return new Date(+m2[1], +m2[2]-1, +m2[3]);
    // dd-mm-yyyy
    const m3 = s.match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/);
    if (m3) return new Date(+m3[3], +m3[2]-1, +m3[1]);
    const ts = Date.parse(s);
    if (!isNaN(ts)) { const d = new Date(ts); return new Date(d.getFullYear(), d.getMonth(), d.getDate()); }
    return null;
}

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
