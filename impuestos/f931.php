<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo      = 'Parsear F.931';
$clienteId   = (int)($_SESSION['fact_cliente_id']     ?? 0);
$clienteNombre = $_SESSION['fact_cliente_nombre'] ?? '';
require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
const CLIENTE_ID = <?= $clienteId ?>;
const CLIENTE_NOMBRE = <?= json_encode($clienteNombre) ?>;
</script>

<?php if (!$clienteId): ?>
<div style="background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);border-radius:10px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px">
    <span style="font-size:20px">⚠</span>
    <div>
        <strong style="color:#f59e0b">Sin cliente seleccionado</strong>
        <div style="font-size:13px;color:var(--sub);margin-top:2px">
            Los PDFs se van a procesar pero <strong>no se guardarán</strong>.
            <a href="/facturacion/index.php" style="color:var(--accent-light)">Seleccioná un cliente</a> para guardar cada período automáticamente.
        </div>
    </div>
</div>
<?php else: ?>
<div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);border-radius:10px;padding:12px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px">
    <span style="color:#10b981;font-size:16px">●</span>
    <span style="font-size:13px">Los PDFs se guardarán automáticamente para <strong><?= htmlspecialchars($clienteNombre) ?></strong></span>
</div>
<?php endif; ?>

<div class="grid-2" style="gap:20px;margin-bottom:24px">

    <!-- ── Subir nuevos PDFs ── -->
    <div class="card">
        <div class="card-title">Subir PDFs</div>
        <div class="upload-zone" id="upload-zone">
            <div class="upload-icon">👥</div>
            <div class="upload-text">Arrastrá los F.931 acá</div>
            <div class="upload-sub">o hacé clic — podés elegir varios a la vez</div>
            <input type="file" id="file-input" accept=".pdf" multiple style="display:none">
        </div>
        <div id="progress-wrap" style="display:none;margin-top:12px">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                <span style="font-size:12px;color:var(--sub)" id="progress-label">Procesando…</span>
                <span style="font-size:12px;color:var(--sub)" id="progress-pct">0%</span>
            </div>
            <div style="background:var(--surface);border-radius:4px;height:5px;overflow:hidden">
                <div id="progress-bar" style="height:100%;background:#10b981;width:0%;transition:width 0.3s"></div>
            </div>
        </div>
    </div>

    <!-- ── Cargar período guardado ── -->
    <div class="card">
        <div class="card-title">Cargar período guardado</div>
        <?php if (!$clienteId): ?>
        <p style="color:var(--sub);font-size:13px">Seleccioná un cliente para cargar datos guardados.</p>
        <?php else: ?>
        <p style="color:var(--sub);font-size:13px;margin-bottom:16px">
            Cargá todos los F.931 ya guardados de <strong><?= htmlspecialchars($clienteNombre) ?></strong> en un rango de meses.
        </p>
        <div class="grid-2" style="gap:12px;margin-bottom:12px">
            <div class="form-group" style="margin:0">
                <label class="form-label">Desde (mes/año)</label>
                <input type="month" class="form-control" id="periodo-desde">
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label">Hasta (mes/año)</label>
                <input type="month" class="form-control" id="periodo-hasta">
            </div>
        </div>
        <div style="display:flex;gap:8px">
            <button class="btn btn-primary" style="flex:1" onclick="cargarGuardados()">⬇ Cargar período</button>
            <button class="btn btn-secondary" onclick="cargarTodo()">Todo</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tabla de resultados -->
<div id="resultado" style="display:none">
    <div class="card">
        <div class="flex-between mb-16">
            <div>
                <div class="card-title" style="margin:0" id="res-titulo">Resultados</div>
                <div style="font-size:12px;color:var(--sub);margin-top:4px" id="res-sub"></div>
            </div>
            <div style="display:flex;gap:8px">
                <button class="btn btn-secondary btn-sm" onclick="limpiarTabla()">✕ Limpiar tabla</button>
                <button class="btn btn-success" onclick="exportarExcel()">⬇ Descargar Excel</button>
            </div>
        </div>

        <div style="overflow-x:auto">
            <table id="res-tabla" style="font-size:12px;min-width:1500px">
                <thead>
                    <tr>
                        <th>Estado</th>
                        <th>Archivo</th>
                        <th>CUIT</th>
                        <th>Mes</th>
                        <th>Año</th>
                        <th style="text-align:right">Suma Rem. 9</th>
                        <th style="text-align:right;color:#f59e0b">Retenciones</th>
                        <th style="text-align:right;color:#10b981">351 C.S.S.</th>
                        <th style="text-align:right;color:#10b981">301 A.S.S.</th>
                        <th style="text-align:right;color:#10b981">360 RENATRE</th>
                        <th style="text-align:right;color:#10b981">352 C.O.S.</th>
                        <th style="text-align:right;color:#10b981">935 Sepelio</th>
                        <th style="text-align:right;color:#10b981">302 A.O.S.</th>
                        <th style="text-align:right;color:#10b981">270 Vales</th>
                        <th style="text-align:right;color:#10b981">312 L.R.T.</th>
                        <th style="text-align:right;color:#10b981">028 Seg. Vida</th>
                        <th style="text-align:right;font-weight:700">TOTAL VIII</th>
                    </tr>
                </thead>
                <tbody id="res-tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<style>
.upload-zone {
    border: 2px dashed var(--border); border-radius: 10px; padding: 32px 16px;
    text-align: center; cursor: pointer; transition: all .2s; background: var(--surface);
}
.upload-zone:hover, .upload-zone.dragover { border-color:#10b981; background:rgba(16,185,129,.05); }
.upload-icon { font-size:36px; margin-bottom:8px; }
.upload-text { font-size:15px; font-weight:600; margin-bottom:4px; }
.upload-sub  { font-size:12px; color:var(--sub); }
.st-guardando { color:#f59e0b; }
.st-guardado  { color:#10b981; }
.st-error     { color:var(--red); }
.st-cargado   { color:#6366f1; }
.st-sincliente{ color:var(--muted); }
.val-zero { color:var(--muted); }
</style>

<script>
// ── Estado ────────────────────────────────────────────────
let filas = [];   // {archivo, cuit, mes, anio, rem9, retenciones, c351..., estado, desde_db}

// ── Drop zone ─────────────────────────────────────────────
const zone      = document.getElementById('upload-zone');
const fileInput = document.getElementById('file-input');
zone.addEventListener('click',    () => fileInput.click());
zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => { e.preventDefault(); zone.classList.remove('dragover'); procesarArchivos(Array.from(e.dataTransfer.files)); });
fileInput.addEventListener('change', () => procesarArchivos(Array.from(fileInput.files)));

// ── Extraer texto del PDF ─────────────────────────────────
async function extractText(file) {
    const buf = await file.arrayBuffer();
    const pdf = await pdfjsLib.getDocument({ data: buf }).promise;
    let text = '';
    for (let i = 1; i <= pdf.numPages; i++) {
        const page = await pdf.getPage(i);
        const content = await page.getTextContent();
        const lineMap = {};
        for (const item of content.items) {
            if (!item.str?.trim()) continue;
            const y = Math.round(item.transform[5] / 2) * 2;
            if (!lineMap[y]) lineMap[y] = [];
            lineMap[y].push({ x: item.transform[4], str: item.str });
        }
        const ys = Object.keys(lineMap).map(Number).sort((a, b) => b - a);
        for (const y of ys) {
            const sorted = lineMap[y].sort((a, b) => a.x - b.x);
            text += sorted.map(i => i.str).join(' ') + '\n';
        }
        text += '\n';
    }
    return text;
}

// Leer PDF como base64 para enviar al servidor
function fileToBase64(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = e => resolve(e.target.result.split(',')[1]);
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

function parseNum(s) {
    if (!s) return 0;
    const n = parseFloat(String(s).trim().replace(/\./g,'').replace(',','.'));
    return isNaN(n) ? 0 : n;
}

// ── Parser F.931 ──────────────────────────────────────────
function parsearF931(text, fileName) {
    const r = {
        archivo: fileName, cuit: '', mes: '', anio: '',
        rem9: 0, retenciones: 0,
        c351:0, c301:0, c360:0, c352:0, c935:0,
        c302:0, c270:0, c312:0, c028:0,
        parseOk: true, desde_db: false, estado: 'nuevo',
    };

    const mCuit = text.match(/(\d{2}[-\s]\d{7,8}[-\s]\d)/);
    if (mCuit) r.cuit = mCuit[1].replace(/\s/g,'-');

    const mPer = text.match(/Mes\s*[-–]\s*A[ñn]o\s*:?\s*0?(\d{1,2})\s*[\/\s]\s*(\d{4})/i)
              || text.match(/(\d{1,2})\s*\/\s*(20\d{2})/);
    if (mPer) { r.mes = String(mPer[1]).padStart(2,'0'); r.anio = mPer[2]; }

    const mRem9 = text.match(/Suma\s+de\s+Rem\.?\s*9\s*:?\s*([\d.,]+)/i);
    if (mRem9) r.rem9 = parseNum(mRem9[1]);

    const mRet = text.match(/Total\s+retenciones\s+([\d.,]+)/i);
    if (mRet) r.retenciones = parseNum(mRet[1]);

    r.c351 = extraerCod(text,'351'); r.c301 = extraerCod(text,'301');
    r.c360 = extraerCod(text,'360'); r.c352 = extraerCod(text,'352');
    r.c935 = extraerCod(text,'935'); r.c302 = extraerCod(text,'302');
    r.c270 = extraerCod(text,'270'); r.c312 = extraerCod(text,'312');
    r.c028 = extraerCod(text,'028');

    return r;
}

function extraerCod(text, cod) {
    const re1 = new RegExp(`\\b${cod}\\s*[-–]\\s*.{0,80}?\\b(\\d{1,3}(?:\\.\\d{3})*,\\d{2})(?:\\s|$)`,'i');
    const m1  = text.match(re1);
    if (m1) return parseNum(m1[1]);
    const re2 = new RegExp(`\\b${cod}\\b[^\\n]*\\n[^\\n]*(\\d{1,3}(?:\\.\\d{3})*,\\d{2})`,'i');
    const m2  = text.match(re2);
    return m2 ? parseNum(m2[1]) : 0;
}

// ── Procesar archivos subidos ─────────────────────────────
async function procesarArchivos(files) {
    const pdfs = files.filter(f => f.name.toLowerCase().endsWith('.pdf'));
    if (!pdfs.length) { toast('No hay PDFs válidos','warning'); return; }

    document.getElementById('progress-wrap').style.display = 'block';

    for (let i = 0; i < pdfs.length; i++) {
        const f = pdfs[i];
        setProgress(`(${i+1}/${pdfs.length}) Procesando: ${f.name}`, Math.round(i/pdfs.length*80));

        try {
            const text = await extractText(f);
            const fila = parsearF931(text, f.name);

            if (CLIENTE_ID && fila.mes && fila.anio) {
                fila.estado = 'guardando';
                filas.push(fila);
                renderTabla();

                try {
                    const b64 = await fileToBase64(f);
                    const res = await fetch('/api/f931.php?action=guardar', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ...fila, pdf_base64: b64 }),
                    });
                    const data = await res.json();
                    fila.estado = data.success ? 'guardado' : 'error_guardar';
                    if (!data.success) fila.errorMsg = data.error;
                } catch(e) {
                    fila.estado = 'error_guardar';
                    fila.errorMsg = e.message;
                }
            } else {
                fila.estado = CLIENTE_ID ? 'sin_periodo' : 'sin_cliente';
                filas.push(fila);
            }
            renderTabla();

        } catch(e) {
            filas.push({ archivo:f.name, parseOk:false, error:e.message, estado:'error_parse' });
            renderTabla();
        }
    }

    setProgress('¡Listo!', 100);
    setTimeout(() => { document.getElementById('progress-wrap').style.display='none'; }, 600);
    fileInput.value = '';
    toast(`${pdfs.length} PDF${pdfs.length>1?'s':''} procesado${pdfs.length>1?'s':''}`, 'success');
}

function setProgress(label, pct) {
    document.getElementById('progress-label').textContent = label;
    document.getElementById('progress-pct').textContent   = pct + '%';
    document.getElementById('progress-bar').style.width   = pct + '%';
}

// ── Cargar datos guardados desde DB ──────────────────────
async function cargarGuardados() {
    const desde = document.getElementById('periodo-desde').value;
    const hasta = document.getElementById('periodo-hasta').value;
    if (!desde || !hasta) { toast('Seleccioná un rango de meses', 'warning'); return; }

    const [anioD, mesD] = desde.split('-').map(Number);
    const [anioH, mesH] = hasta.split('-').map(Number);
    await _cargar(mesD, anioD, mesH, anioH);
}

async function cargarTodo() {
    await _cargar(1, 2000, 12, 2099);
}

async function _cargar(mesD, anioD, mesH, anioH) {
    try {
        const params = new URLSearchParams({ action:'listar', mes_desde:mesD, anio_desde:anioD, mes_hasta:mesH, anio_hasta:anioH });
        const res  = await fetch('/api/f931.php?' + params);
        const data = await res.json();
        if (data.error) { toast(data.error,'error'); return; }

        const rows = data.data || [];
        if (!rows.length) { toast('No hay datos guardados en ese período','warning'); return; }

        let agregados = 0;
        for (const r of rows) {
            // Evitar duplicados por mes/año
            const exists = filas.some(f => f.mes == String(r.mes).padStart(2,'0') && f.anio == String(r.anio));
            if (exists) continue;

            filas.push({
                archivo: r.archivo_nombre || `F931 ${r.mes}/${r.anio}`,
                cuit: r.cuit, mes: String(r.mes).padStart(2,'0'), anio: String(r.anio),
                rem9: parseFloat(r.rem9||0), retenciones: parseFloat(r.retenciones||0),
                c351: parseFloat(r.c351||0), c301: parseFloat(r.c301||0),
                c360: parseFloat(r.c360||0), c352: parseFloat(r.c352||0),
                c935: parseFloat(r.c935||0), c302: parseFloat(r.c302||0),
                c270: parseFloat(r.c270||0), c312: parseFloat(r.c312||0),
                c028: parseFloat(r.c028||0),
                parseOk: true, desde_db: true, estado: 'cargado',
            });
            agregados++;
        }

        // Ordenar por año y mes
        filas.sort((a, b) => (a.anio - b.anio) || (a.mes - b.mes));
        renderTabla();
        toast(`${agregados} período${agregados!==1?'s':''} cargado${agregados!==1?'s':''}`, 'success');
    } catch(e) {
        toast('Error al cargar: ' + e.message, 'error');
    }
}

// ── Render tabla ──────────────────────────────────────────
function renderTabla() {
    if (!filas.length) { document.getElementById('resultado').style.display='none'; return; }
    document.getElementById('resultado').style.display = 'block';

    const ok = filas.filter(f => f.parseOk).length;
    document.getElementById('res-titulo').textContent = `${filas.length} archivo${filas.length>1?'s':''} en tabla`;
    document.getElementById('res-sub').textContent    = ok < filas.length ? `${filas.length-ok} con errores de lectura` : 'Todos procesados correctamente';

    const fmt = n => n === 0
        ? '<span class="val-zero">0,00</span>'
        : '$ ' + n.toLocaleString('es-AR',{minimumFractionDigits:2,maximumFractionDigits:2});

    const estadoBadge = (f) => {
        switch(f.estado) {
            case 'guardando':   return '<span class="st-guardando">⟳ Guardando…</span>';
            case 'guardado':    return '<span class="st-guardado">✓ Guardado</span>';
            case 'cargado':     return '<span class="st-cargado">● Guardado</span>';
            case 'sin_cliente': return '<span class="st-sincliente">— Sin cliente</span>';
            case 'sin_periodo': return '<span class="st-error">⚠ Sin período</span>';
            case 'error_guardar': return `<span class="st-error" title="${escHtml(f.errorMsg||'')}">✕ Error al guardar</span>`;
            case 'error_parse': return '<span class="st-error">✕ Error al leer</span>';
            default:            return '<span class="st-sincliente">—</span>';
        }
    };

    document.getElementById('res-tbody').innerHTML = filas.map((f, idx) => {
        if (!f.parseOk) {
            return `<tr><td>${estadoBadge(f)}</td><td colspan="16" style="color:var(--red)">⚠ ${escHtml(f.archivo)} — ${escHtml(f.error||'')}</td></tr>`;
        }
        const total = f.c351+f.c301+f.c360+f.c352+f.c935+f.c302+f.c270+f.c312+f.c028;
        return `<tr>
            <td style="white-space:nowrap">${estadoBadge(f)}</td>
            <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px" title="${escHtml(f.archivo)}">${escHtml(f.archivo)}</td>
            <td class="mono" style="font-size:11px">${f.cuit||'—'}</td>
            <td class="mono" style="text-align:center">${f.mes||'?'}</td>
            <td class="mono" style="text-align:center">${f.anio||'?'}</td>
            <td class="mono" style="text-align:right;font-weight:600">${fmt(f.rem9)}</td>
            <td class="mono" style="text-align:right">${fmt(f.retenciones)}</td>
            <td class="mono" style="text-align:right">${fmt(f.c351)}</td>
            <td class="mono" style="text-align:right">${fmt(f.c301)}</td>
            <td class="mono" style="text-align:right">${fmt(f.c360)}</td>
            <td class="mono" style="text-align:right">${fmt(f.c352)}</td>
            <td class="mono" style="text-align:right">${fmt(f.c935)}</td>
            <td class="mono" style="text-align:right">${fmt(f.c302)}</td>
            <td class="mono" style="text-align:right">${fmt(f.c270)}</td>
            <td class="mono" style="text-align:right">${fmt(f.c312)}</td>
            <td class="mono" style="text-align:right">${fmt(f.c028)}</td>
            <td class="mono" style="text-align:right;font-weight:700">$ ${total.toLocaleString('es-AR',{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
        </tr>`;
    }).join('');
}

function limpiarTabla() { filas = []; renderTabla(); }

// ── Exportar Excel ────────────────────────────────────────
function exportarExcel() {
    const ok = filas.filter(f => f.parseOk);
    if (!ok.length) return;

    const headers = [
        'Archivo','CUIT','Mes','Año','Suma Rem. 9','Total Retenciones',
        '351 Contrib.Seg.Social','301 Aportes Seg.Social','360 RENATRE',
        '352 Contrib.Obra Social','935 Sepelio UATRE','302 Aportes Obra Social',
        '270 Vales Aliment.','312 L.R.T.','028 Seg.Vida Oblig.','TOTAL Sec.VIII',
    ];

    const rows = [headers];
    for (const f of ok) {
        const total = f.c351+f.c301+f.c360+f.c352+f.c935+f.c302+f.c270+f.c312+f.c028;
        rows.push([f.archivo,f.cuit,f.mes,f.anio,f.rem9,f.retenciones,
            f.c351,f.c301,f.c360,f.c352,f.c935,f.c302,f.c270,f.c312,f.c028,total]);
    }

    if (ok.length > 1) {
        const tot = col => ok.reduce((s,f) => s+(f[col]||0), 0);
        const totVIII = tot('c351')+tot('c301')+tot('c360')+tot('c352')+tot('c935')+tot('c302')+tot('c270')+tot('c312')+tot('c028');
        rows.push(['TOTALES','','','',tot('rem9'),tot('retenciones'),
            tot('c351'),tot('c301'),tot('c360'),tot('c352'),tot('c935'),
            tot('c302'),tot('c270'),tot('c312'),tot('c028'),totVIII]);
    }

    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = [{wch:28},{wch:16},{wch:5},{wch:6},{wch:16},{wch:18},
        {wch:20},{wch:20},{wch:12},{wch:20},{wch:14},{wch:20},{wch:13},{wch:12},{wch:18},{wch:14}];

    const wb = XLSX.utils.book_new();
    const clienteSheet = CLIENTE_NOMBRE ? CLIENTE_NOMBRE.substring(0,20) : 'F931';
    XLSX.utils.book_append_sheet(wb, ws, clienteSheet);
    const fname = `F931_${(CLIENTE_NOMBRE||'').replace(/\s+/g,'_').substring(0,15)}_${new Date().toISOString().slice(0,10)}.xlsx`;
    XLSX.writeFile(wb, fname);
    toast('Excel descargado', 'success');
}

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Init: setear período hasta = mes actual por defecto
(function() {
    const now = new Date();
    const curr = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
    const desde = document.getElementById('periodo-desde');
    const hasta  = document.getElementById('periodo-hasta');
    if (desde) desde.value = `${now.getFullYear()}-01`;
    if (hasta)  hasta.value = curr;
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
