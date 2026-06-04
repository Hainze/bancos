<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Subir Excel de Proveedores';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:680px;margin:0 auto">
    <div class="card-title">Subir cartera de proveedores</div>
    <p style="color:var(--sub);font-size:14px;margin-bottom:4px">
        Seleccioná el Excel con las columnas: <strong>Sistema · Código · Nombre del proveedor · Saldo Cta. Cte.</strong>
    </p>
    <p style="color:var(--sub);font-size:13px;margin-bottom:24px">
        Saldo <strong style="color:var(--red)">negativo</strong> = debemos nosotros &nbsp;·&nbsp;
        Saldo <strong style="color:var(--green)">positivo</strong> = tenemos crédito a favor.
        Sistema 1 = blanco, Sistema 2 = negro.
    </p>

    <div class="upload-zone" id="upload-zone">
        <div class="upload-icon">🏭</div>
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
            <div id="progress-bar" style="height:100%;background:#ef4444;width:0%;transition:width 0.3s"></div>
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

    <!-- Tabla -->
    <div class="card" style="margin-top:24px">
        <div class="flex-between mb-16">
            <div class="card-title" style="margin:0" id="res-titulo">Vista previa</div>
            <div style="display:flex;gap:8px;align-items:center">
                <select class="form-control" id="filtro-prioridad" style="width:auto;font-size:13px" onchange="filtrarTabla()">
                    <option value="">Todas las prioridades</option>
                    <option value="URGENTE">Urgente</option>
                    <option value="REVISAR">Revisar</option>
                    <option value="A COBRAR">A cobrar</option>
                    <option value="DEUDA MENOR">Deuda menor</option>
                    <option value="FAVOR MENOR">Favor menor</option>
                    <option value="SIN SALDO">Sin saldo</option>
                </select>
                <button class="btn btn-success" id="btn-exportar">⬇ Descargar Excel</button>
            </div>
        </div>

        <!-- Leyenda -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px">
            <span style="font-size:12px;display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;background:#ef4444;border-radius:2px;display:inline-block"></span> Urgente (debe &gt;$1M)</span>
            <span style="font-size:12px;display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;background:#f97316;border-radius:2px;display:inline-block"></span> Revisar (debe $100K–$1M)</span>
            <span style="font-size:12px;display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;background:#10b981;border-radius:2px;display:inline-block"></span> A cobrar (favor &gt;$100K)</span>
            <span style="font-size:12px;display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;background:#f59e0b;border-radius:2px;display:inline-block"></span> Deuda menor</span>
            <span style="font-size:12px;display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;background:#06b6d4;border-radius:2px;display:inline-block"></span> Favor menor</span>
        </div>

        <div class="table-wrap" style="overflow-x:auto">
            <table id="res-tabla" style="font-size:12px;min-width:700px">
                <thead>
                    <tr>
                        <th>Sis.</th>
                        <th>Código</th>
                        <th>Nombre del proveedor</th>
                        <th style="text-align:right">Saldo Cta. Cte.</th>
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
    border: 2px dashed var(--border); border-radius: 12px; padding: 48px 24px;
    text-align: center; cursor: pointer; transition: all 0.2s; background: var(--surface);
}
.upload-zone:hover, .upload-zone.dragover { border-color:#ef4444; background:rgba(239,68,68,0.06); }
.upload-icon { font-size: 40px; margin-bottom: 12px; }
.upload-text { font-size: 16px; font-weight: 600; margin-bottom: 4px; }
.upload-sub  { font-size: 13px; color: var(--sub); }

.pri-urgente    { color:#ef4444; font-weight:700; }
.pri-revisar    { color:#f97316; font-weight:600; }
.pri-acobrar    { color:#10b981; font-weight:600; }
.pri-deudamenor { color:#f59e0b; }
.pri-favormenor { color:#06b6d4; }
.pri-sinsaldo   { color:var(--muted); }
</style>

<script>
let allRows   = [];
let loteId    = null;
let fileName  = '';
const PAGE_SIZE = 100;
let currentPage = 1;

// ── Upload zone ───────────────────────────────────────────
const zone      = document.getElementById('upload-zone');
const fileInput = document.getElementById('file-input');
zone.addEventListener('click',    () => fileInput.click());
zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => { e.preventDefault(); zone.classList.remove('dragover'); const f = e.dataTransfer.files[0]; if (f) setFile(f); });
fileInput.addEventListener('change', () => { if (fileInput.files[0]) setFile(fileInput.files[0]); });

function setFile(f) {
    const ext = f.name.split('.').pop().toLowerCase();
    if (!['xlsx','xls'].includes(ext)) { toast('Solo se aceptan .xlsx o .xls', 'warning'); return; }
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
    loteId  = null;
    document.getElementById('resultado').style.display = 'none';
    setProgress(false);
}

function setProgress(on, label='', pct=0) {
    document.getElementById('progress-wrap').style.display = on ? 'block' : 'none';
    if (on) {
        document.getElementById('progress-label').textContent = label;
        document.getElementById('progress-pct').textContent   = pct + '%';
        document.getElementById('progress-bar').style.width   = pct + '%';
    }
}

// ── Parsear Excel ─────────────────────────────────────────
function parseExcelFile(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = e => {
            try {
                const wb   = XLSX.read(e.target.result, { type:'array' });
                const ws   = wb.Sheets[wb.SheetNames[0]];
                const rows = XLSX.utils.sheet_to_json(ws, { header:1, defval:'' });
                resolve(rows);
            } catch(err) { reject(err); }
        };
        reader.onerror = reject;
        reader.readAsArrayBuffer(file);
    });
}

function parseNum(v) {
    if (v === '' || v === null || v === undefined) return 0;
    if (typeof v === 'number') return v;
    const s = String(v).trim().replace(/\./g,'').replace(',','.');
    const n = parseFloat(s);
    return isNaN(n) ? 0 : n;
}

function parseSistema(v) {
    const n = parseFloat(v);
    if (n === 2) return 2;
    const s = String(v).trim().toLowerCase();
    if (s.startsWith('2') || s === 'sistema 2' || s === 'negro') return 2;
    return 1;
}

function findHeaderRow(rows) {
    for (let i = 0; i < Math.min(rows.length, 10); i++) {
        const joined = rows[i].join(' ').toLowerCase();
        if (joined.includes('sistema') || joined.includes('codigo') || joined.includes('nombre')) return i;
    }
    return 0;
}

function mapColumns(headerRow) {
    const map = { sistema:-1, codigo:-1, nombre:-1, saldo:-1 };
    headerRow.forEach((cell, idx) => {
        const h = String(cell).trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');
        if (/^sistema/.test(h))             map.sistema = idx;
        else if (/^c[oo]digo|^cod/.test(h)) map.codigo  = idx;
        else if (/^nombre/.test(h))         map.nombre  = idx;
        else if (/saldo/.test(h))           map.saldo   = idx;
    });
    return map;
}

// ── Lógica de prioridad ───────────────────────────────────
function calcPriority(saldo) {
    if (saldo === 0) return 'SIN SALDO';
    const abs = Math.abs(saldo);
    if (saldo < 0) {
        if (abs >= 1000000) return 'URGENTE';
        if (abs >= 100000)  return 'REVISAR';
        return 'DEUDA MENOR';
    }
    // saldo > 0 (a favor nuestro)
    if (abs >= 100000) return 'A COBRAR';
    return 'FAVOR MENOR';
}

const PRI_ORDER = { 'URGENTE':0, 'REVISAR':1, 'A COBRAR':2, 'DEUDA MENOR':3, 'FAVOR MENOR':4, 'SIN SALDO':5 };

// ── Procesar ──────────────────────────────────────────────
document.getElementById('btn-procesar').addEventListener('click', async () => {
    const f = fileInput._file || fileInput.files[0];
    if (!f) return;

    const btn = document.getElementById('btn-procesar');
    btn.disabled = true;
    document.getElementById('btn-text').textContent = 'Procesando…';
    setProgress(true, 'Leyendo Excel…', 20);

    try {
        const rawRows = await parseExcelFile(f);
        setProgress(true, 'Analizando datos…', 50);

        const headerIdx = findHeaderRow(rawRows);
        const colMap    = mapColumns(rawRows[headerIdx]);

        if (colMap.nombre < 0 || colMap.saldo < 0) {
            toast('No se detectaron las columnas requeridas (Nombre, Saldo). Verificá el formato.', 'error');
            return;
        }

        const parsed = [];
        for (let i = headerIdx + 1; i < rawRows.length; i++) {
            const row = rawRows[i];
            if (!row || row.every(c => c === '' || c === null || c === undefined)) continue;
            const nombre = String(row[colMap.nombre] ?? '').trim();
            if (!nombre) continue;

            const sistemaRaw = colMap.sistema >= 0 ? row[colMap.sistema] : row[0];
            parsed.push({
                sistema: parseSistema(sistemaRaw),
                codigo:  colMap.codigo >= 0 ? String(row[colMap.codigo] ?? '').trim() : '',
                nombre,
                saldo:   parseNum(row[colMap.saldo]),
            });
        }

        if (!parsed.length) { toast('No se encontraron filas de datos válidas.', 'error'); return; }

        // Cargar observaciones
        setProgress(true, 'Cargando observaciones…', 65);
        const padMap = {};
        try {
            const padRes = await fetch('/proveedores/api/padrones.php?action=listar_todos');
            if (padRes.ok) {
                const padData = await padRes.json();
                (padData.data || []).forEach(p => { padMap[p.sistema + '_' + p.codigo] = p.observacion; });
            }
        } catch(e) { /* continúa sin observaciones */ }

        // Calcular prioridades
        allRows = parsed.map(r => {
            const prioridad   = calcPriority(r.saldo);
            const observacion = padMap[r.sistema + '_' + r.codigo] || '';
            return { ...r, prioridad, observacion };
        });

        // Ordenar: por prioridad, luego por |saldo| descendente
        allRows.sort((a, b) => {
            const po = PRI_ORDER[a.prioridad] - PRI_ORDER[b.prioridad];
            if (po !== 0) return po;
            return Math.abs(b.saldo) - Math.abs(a.saldo);
        });

        // Guardar en DB
        setProgress(true, 'Guardando en base de datos…', 85);
        const saveRes  = await fetch('/proveedores/api/cartera.php?action=guardar_lote', {
            method: 'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ archivo_nombre: f.name, filas: allRows }),
        });
        const saveData = await saveRes.json();
        if (saveData.error) { toast(saveData.error, 'error'); return; }
        loteId = saveData.lote_id;

        setProgress(true, 'Listo', 100);
        setTimeout(() => setProgress(false), 400);
        renderResultado();
        toast(`${allRows.length} proveedores procesados`, 'success');

    } catch(e) {
        toast('Error: ' + e.message, 'error');
        console.error(e);
    } finally {
        btn.disabled = false;
        document.getElementById('btn-text').textContent = '⬆ Procesar y guardar';
    }
});

// ── Render resultado ──────────────────────────────────────
function getFilteredRows() {
    const f = document.getElementById('filtro-prioridad').value;
    return f ? allRows.filter(r => r.prioridad === f) : allRows;
}

function filtrarTabla() { currentPage = 1; renderTabla(); }

function renderResultado() {
    document.getElementById('resultado').style.display = 'block';
    document.getElementById('resultado').scrollIntoView({ behavior:'smooth', block:'start' });
    document.getElementById('res-titulo').textContent = `${allRows.length} proveedores — ${fileName}`;

    const counts  = { 'URGENTE':0,'REVISAR':0,'A COBRAR':0,'DEUDA MENOR':0,'FAVOR MENOR':0,'SIN SALDO':0 };
    let totalDeuda = 0, totalFavor = 0;
    allRows.forEach(r => {
        counts[r.prioridad]++;
        if (r.saldo < 0) totalDeuda += Math.abs(r.saldo);
        if (r.saldo > 0) totalFavor += r.saldo;
    });

    document.getElementById('res-stats').innerHTML = `
        <div class="stat-card red">
            <div class="stat-label">Urgente</div>
            <div class="stat-value red">${counts['URGENTE']}</div>
            <div class="stat-sub">proveedores</div>
        </div>
        <div class="stat-card" style="border-color:rgba(249,115,22,.3)">
            <div class="stat-label">Revisar</div>
            <div class="stat-value" style="color:#f97316">${counts['REVISAR']}</div>
            <div class="stat-sub">proveedores</div>
        </div>
        <div class="stat-card red">
            <div class="stat-label">Total a pagar</div>
            <div class="stat-value red" style="font-size:16px">${fmt(-totalDeuda)}</div>
            <div class="stat-sub">saldo negativo</div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Total a favor</div>
            <div class="stat-value green" style="font-size:16px">${fmt(totalFavor)}</div>
            <div class="stat-sub">saldo positivo</div>
        </div>
    `;

    currentPage = 1;
    renderTabla();
}

function renderTabla() {
    const rows  = getFilteredRows();
    const total = rows.length;
    const start = (currentPage - 1) * PAGE_SIZE;
    const slice = rows.slice(start, start + PAGE_SIZE);

    const priClass = {
        'URGENTE':    'pri-urgente',
        'REVISAR':    'pri-revisar',
        'A COBRAR':   'pri-acobrar',
        'DEUDA MENOR':'pri-deudamenor',
        'FAVOR MENOR':'pri-favormenor',
        'SIN SALDO':  'pri-sinsaldo',
    };

    document.getElementById('res-tbody').innerHTML = slice.map(r => {
        const saldoFmt = r.saldo === 0
            ? '<span style="color:var(--muted)">$ 0</span>'
            : `<span style="color:${r.saldo < 0 ? 'var(--red)' : 'var(--green)';font-weight:600}">${fmt(r.saldo)}</span>`;
        return `<tr>
            <td><span class="badge ${r.sistema===1?'badge-blue':'badge-red'}" style="font-size:10px">S${r.sistema}</span></td>
            <td class="mono" style="font-size:11px">${r.codigo || '—'}</td>
            <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(r.nombre)}">${escHtml(r.nombre)}</td>
            <td class="mono" style="text-align:right;font-weight:600;color:${r.saldo<0?'var(--red)':'var(--green)'}">${fmt(r.saldo)}</td>
            <td class="${priClass[r.prioridad]||''}" style="white-space:nowrap">${r.prioridad}</td>
            <td style="font-size:11px;color:var(--sub);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(r.observacion)}">${r.observacion || '—'}</td>
        </tr>`;
    }).join('');

    // Paginación
    const totalPages = Math.ceil(total / PAGE_SIZE);
    let pag = `<span>${total} registros${getFilteredRows().length < allRows.length ? ' filtrados' : ''}</span>`;
    if (totalPages > 1) {
        if (currentPage > 1) pag += `<a class="page-btn" onclick="goPage(${currentPage-1})">‹</a>`;
        const from = Math.max(1, currentPage-2);
        const to   = Math.min(totalPages, currentPage+2);
        for (let p = from; p <= to; p++) pag += `<a class="page-btn ${p===currentPage?'active':''}" onclick="goPage(${p})">${p}</a>`;
        if (currentPage < totalPages) pag += `<a class="page-btn" onclick="goPage(${currentPage+1})">›</a>`;
    }
    document.getElementById('pag-wrap').innerHTML = pag;
}

function goPage(p) {
    currentPage = p;
    renderTabla();
    document.getElementById('res-tabla')?.scrollIntoView({ behavior:'smooth', block:'start' });
}

// ── Exportar Excel ────────────────────────────────────────
document.getElementById('btn-exportar').addEventListener('click', () => {
    if (!allRows.length) return;

    const headers = ['Sistema','Código','Nombre del proveedor','Saldo Cta. Cte.','Prioridad','Observación'];
    const sysLabel = s => s === 1 ? 'Sistema 1 (blanco)' : 'Sistema 2 (negro)';

    const dataRows = allRows.map(r => [
        sysLabel(r.sistema),
        r.codigo,
        r.nombre,
        r.saldo,
        r.prioridad,
        r.observacion || '',
    ]);

    const ws = XLSX.utils.aoa_to_sheet([headers, ...dataRows]);
    ws['!cols'] = [{ wch:22 },{ wch:12 },{ wch:40 },{ wch:18 },{ wch:16 },{ wch:35 }];

    const wb   = XLSX.utils.book_new();
    const name = fileName.replace(/\.[^.]+$/, '').substring(0, 25);
    XLSX.utils.book_append_sheet(wb, ws, 'Proveedores');
    const fname = `Proveedores_${name}_prioridades.xlsx`.replace(/\s+/g,'_').replace(/[^\w._-]/g,'');
    XLSX.writeFile(wb, fname);
    toast('Excel descargado', 'success');
});

function fmt(v) {
    const n = parseFloat(v || 0);
    if (n === 0) return '$ 0';
    const abs = Math.abs(n);
    const s = abs.toLocaleString('es-AR', { minimumFractionDigits:2, maximumFractionDigits:2 });
    return (n < 0 ? '-$ ' : '$ ') + s;
}

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
