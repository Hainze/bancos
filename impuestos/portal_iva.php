<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo        = 'Parsear Portal IVA';
$clienteId     = (int)($_SESSION['fact_cliente_id']     ?? 0);
$clienteNombre = $_SESSION['fact_cliente_nombre'] ?? '';
require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
const CLIENTE_ID     = <?= $clienteId ?>;
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
            <div class="upload-icon">🧾</div>
            <div class="upload-text">Arrastrá los PDF de Vista Previa acá</div>
            <div class="upload-sub">o hacé clic — podés elegir varios a la vez</div>
            <input type="file" id="file-input" accept=".pdf" multiple style="display:none">
        </div>
        <div id="progress-wrap" style="display:none;margin-top:12px">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                <span style="font-size:12px;color:var(--sub)" id="progress-label">Procesando…</span>
                <span style="font-size:12px;color:var(--sub)" id="progress-pct">0%</span>
            </div>
            <div style="background:var(--surface);border-radius:4px;height:5px;overflow:hidden">
                <div id="progress-bar" style="height:100%;background:#8b5cf6;width:0%;transition:width 0.3s"></div>
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
            Cargá todos los Portal IVA ya guardados de <strong><?= htmlspecialchars($clienteNombre) ?></strong> en un rango de meses.
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

<!-- Resultados -->
<div id="resultado" style="display:none">

    <div class="flex-between mb-16">
        <div class="card-title" id="res-titulo" style="margin:0"></div>
        <div style="display:flex;gap:8px">
            <button class="btn btn-secondary btn-sm" onclick="limpiar()">✕ Limpiar</button>
            <button class="btn btn-success" onclick="exportarExcel()">⬇ Descargar Excel</button>
        </div>
    </div>

    <!-- Tabla COMPRAS -->
    <div class="card mb-24">
        <div class="card-title" style="color:#f59e0b">Compras (Facturas − Notas de Crédito)</div>
        <div style="overflow-x:auto">
            <table style="font-size:12px;min-width:750px">
                <thead>
                    <tr>
                        <th>Estado</th>
                        <th>Período</th>
                        <th style="text-align:right">IVA 10,5%</th>
                        <th style="text-align:right">Neto 10,5%</th>
                        <th style="text-align:right">IVA 21%</th>
                        <th style="text-align:right">Neto 21%</th>
                        <th style="text-align:right">IVA 27%</th>
                        <th style="text-align:right">Neto 27%</th>
                    </tr>
                </thead>
                <tbody id="tbody-compras"></tbody>
            </table>
        </div>
    </div>

    <!-- Tabla VENTAS -->
    <div class="card">
        <div class="card-title" style="color:#10b981">Ventas (Facturado − Restitución)</div>
        <div style="overflow-x:auto">
            <table style="font-size:12px;min-width:950px">
                <thead>
                    <tr>
                        <th rowspan="2" style="vertical-align:middle">Estado</th>
                        <th rowspan="2" style="vertical-align:middle">Período</th>
                        <th colspan="4" style="text-align:center;background:rgba(16,185,129,.1);color:#10b981">Operaciones con RI y Monotributistas</th>
                        <th colspan="4" style="text-align:center;background:rgba(37,99,235,.1);color:var(--accent-light)">Operaciones con CF, Exentos y No Alc</th>
                    </tr>
                    <tr>
                        <th style="text-align:right">IVA 10,5%</th>
                        <th style="text-align:right">Neto 10,5%</th>
                        <th style="text-align:right">IVA 21%</th>
                        <th style="text-align:right">Neto 21%</th>
                        <th style="text-align:right">IVA 10,5%</th>
                        <th style="text-align:right">Neto 10,5%</th>
                        <th style="text-align:right">IVA 21%</th>
                        <th style="text-align:right">Neto 21%</th>
                    </tr>
                </thead>
                <tbody id="tbody-ventas"></tbody>
            </table>
        </div>
    </div>
</div>

<style>
.upload-zone { border:2px dashed var(--border);border-radius:10px;padding:40px 20px;text-align:center;cursor:pointer;transition:all .2s;background:var(--surface); }
.upload-zone:hover,.upload-zone.dragover { border-color:#8b5cf6;background:rgba(139,92,246,.05); }
.upload-icon { font-size:36px;margin-bottom:8px; }
.upload-text { font-size:15px;font-weight:600;margin-bottom:4px; }
.upload-sub  { font-size:12px;color:var(--sub); }
.z { color:var(--muted); }
.st-guardando { color:#f59e0b; }
.st-guardado  { color:#10b981; }
.st-error     { color:var(--red); }
.st-cargado   { color:#6366f1; }
.st-sincliente{ color:var(--muted); }
</style>

<script>
let filas = [];

// ── Drop zone ─────────────────────────────────────────────
const zone      = document.getElementById('upload-zone');
const fileInput = document.getElementById('file-input');
zone.addEventListener('click',    () => fileInput.click());
zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => { e.preventDefault(); zone.classList.remove('dragover'); procesarArchivos(Array.from(e.dataTransfer.files)); });
fileInput.addEventListener('change', () => procesarArchivos(Array.from(fileInput.files)));

// ── Extraer texto ─────────────────────────────────────────
async function extractText(file) {
    const buf = await file.arrayBuffer();
    const pdf = await pdfjsLib.getDocument({ data: buf }).promise;
    let text = '';
    for (let i = 1; i <= pdf.numPages; i++) {
        const page    = await pdf.getPage(i);
        const content = await page.getTextContent();
        const lineMap = {};
        for (const item of content.items) {
            if (!item.str?.trim()) continue;
            const y = Math.round(item.transform[5] / 2) * 2;
            if (!lineMap[y]) lineMap[y] = [];
            lineMap[y].push({ x: item.transform[4], str: item.str });
        }
        const ys = Object.keys(lineMap).map(Number).sort((a, b) => b - a);
        for (const y of ys)
            text += lineMap[y].sort((a,b) => a.x-b.x).map(i => i.str).join(' ') + '\n';
        text += '\n';
    }
    return text;
}

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

const ARG = '\\d{1,3}(?:\\.\\d{3})*,\\d{2}';

function seccion(text, desdeRe, hastaRe) {
    const ini = text.search(desdeRe);
    if (ini < 0) return '';
    const rest = text.slice(ini);
    if (!hastaRe) return rest;
    const fin = rest.search(hastaRe);
    return fin > 0 ? rest.slice(0, fin) : rest;
}

function tasa(texto, rate) {
    const esc = rate.replace(',','[,.]');
    const re  = new RegExp(`${esc}\\s*%\\s+(${ARG})(?:\\s+(${ARG}))?`, 'i');
    const m   = texto.match(re);
    if (!m) return { neto:0, iva:0 };
    return { neto: parseNum(m[1]), iva: parseNum(m[2]||'0') };
}

function tasaCompras(texto, rate) {
    const esc = rate.replace(',','[,.]');
    const re  = new RegExp(`${esc}\\s*%\\s+(${ARG})(?:\\s+(${ARG}))?`, 'gi');
    const all = [...texto.matchAll(re)];
    const fac = all[0] ? { neto: parseNum(all[0][1]), iva: parseNum(all[0][2]||'0') } : {neto:0,iva:0};
    const nc  = all[1] ? { neto: parseNum(all[1][1]), iva: parseNum(all[1][2]||'0') } : {neto:0,iva:0};
    return { iva: fac.iva - nc.iva, neto: fac.neto - nc.neto };
}

// ── Parser ────────────────────────────────────────────────
function parsearPortalIVA(text, fileName) {
    const r = {
        archivo: fileName, cuit: '', mes: '', anio: '', parseOk: true, estado: 'nuevo', desde_db: false,
        vRI_iva105:0, vRI_neto105:0, vRI_iva21:0, vRI_neto21:0,
        vCF_iva105:0, vCF_neto105:0, vCF_iva21:0, vCF_neto21:0,
        c_iva105:0, c_neto105:0, c_iva21:0, c_neto21:0, c_iva27:0, c_neto27:0,
    };

    const mPer = text.match(/Vista\s+Previa\s*\|\s*0?(\d{1,2})\s*\/\s*(\d{4})/i)
              || text.match(/Presentaci[oó]n\s+0?(\d{1,2})\/(\d{4})/i)
              || text.match(/\b(\d{2})\/(\d{4})\b/);
    if (mPer) { r.mes = String(mPer[1]).padStart(2,'0'); r.anio = mPer[2]; }

    const mCuit = text.match(/\[(\d{2}-\d{7,8}-\d)\]/);
    if (mCuit) r.cuit = mCuit[1];

    const secVC = seccion(text,
        /Información\s+Consolidada\s+de\s+Ventas/i,
        /generan\s+Restitu[ci][oó]n|Restitución\s+del\s+Déb/i
    );
    if (secVC) {
        const riEnd = secVC.search(/Consumidores\s+Finales/i);
        const riTxt = riEnd > 0 ? secVC.slice(0, riEnd) : secVC;
        const cfTxt = riEnd > 0 ? secVC.slice(riEnd) : '';
        const ri05 = tasa(riTxt,'10,5'); r.vRI_iva105 = ri05.iva; r.vRI_neto105 = ri05.neto;
        const ri21 = tasa(riTxt,'21');   r.vRI_iva21  = ri21.iva; r.vRI_neto21  = ri21.neto;
        if (cfTxt) {
            const cf05 = tasa(cfTxt,'10,5'); r.vCF_iva105 = cf05.iva; r.vCF_neto105 = cf05.neto;
            const cf21 = tasa(cfTxt,'21');   r.vCF_iva21  = cf21.iva; r.vCF_neto21  = cf21.neto;
        }
    }

    const secVR = seccion(text,
        /generan\s+Restitu[ci][oó]n\s+del\s+D[eé]b|Restitución\s+del\s+D[eé]b/i,
        /Información\s+Consolidada\s+de\s+Compras/i
    );
    if (secVR) {
        const riREnd = secVR.search(/Sujetos\s+Exentos|No\s+Alcanzados.*?Restitu|Consumidores\s+Finales.*?Restitu/i);
        const riRTxt = riREnd > 0 ? secVR.slice(0, riREnd) : secVR;
        const cfRTxt = riREnd > 0 ? secVR.slice(riREnd) : '';
        const rr05 = tasa(riRTxt,'10,5'); r.vRI_iva105 -= rr05.iva; r.vRI_neto105 -= rr05.neto;
        const rr21 = tasa(riRTxt,'21');   r.vRI_iva21  -= rr21.iva; r.vRI_neto21  -= rr21.neto;
        if (cfRTxt) {
            const cr05 = tasa(cfRTxt,'10,5'); r.vCF_iva105 -= cr05.iva; r.vCF_neto105 -= cr05.neto;
            const cr21 = tasa(cfRTxt,'21');   r.vCF_iva21  -= cr21.iva; r.vCF_neto21  -= cr21.neto;
        }
    }

    const secCC = seccion(text, /Información\s+Consolidada\s+de\s+Compras/i, null);
    if (secCC) {
        const c05 = tasaCompras(secCC,'10,5'); r.c_iva105 = c05.iva; r.c_neto105 = c05.neto;
        const c21 = tasaCompras(secCC,'21');   r.c_iva21  = c21.iva; r.c_neto21  = c21.neto;
        const c27 = tasaCompras(secCC,'27');   r.c_iva27  = c27.iva; r.c_neto27  = c27.neto;
    }

    return r;
}

// ── Procesar archivos ─────────────────────────────────────
async function procesarArchivos(files) {
    const pdfs = files.filter(f => f.name.toLowerCase().endsWith('.pdf'));
    if (!pdfs.length) { toast('No hay PDFs válidos','warning'); return; }

    document.getElementById('progress-wrap').style.display = 'block';

    for (let i = 0; i < pdfs.length; i++) {
        const f = pdfs[i];
        setProgress(`(${i+1}/${pdfs.length}) Procesando: ${f.name}`, Math.round(i/pdfs.length*80));

        try {
            const text = await extractText(f);
            const fila = parsearPortalIVA(text, f.name);
            const dup  = filas.findIndex(x => x.mes === fila.mes && x.anio === fila.anio);

            if (CLIENTE_ID && fila.mes && fila.anio) {
                fila.estado = 'guardando';
                if (dup >= 0) filas[dup] = fila; else filas.push(fila);
                renderTablas();

                try {
                    const b64 = await fileToBase64(f);
                    const res = await fetch('/api/portal_iva.php?action=guardar', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ...fila, pdf_base64: b64, archivo_nombre: f.name }),
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
                if (dup >= 0) filas[dup] = fila; else filas.push(fila);
            }

            renderTablas();
        } catch(e) {
            filas.push({ archivo:f.name, parseOk:false, error:e.message, estado:'error_parse' });
            renderTablas();
        }
    }

    filas.sort((a,b) => (a.anio-b.anio)||(a.mes-b.mes));
    setProgress('¡Listo!', 100);
    setTimeout(() => { document.getElementById('progress-wrap').style.display='none'; }, 600);
    fileInput.value = '';
    renderTablas();
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
        const res  = await fetch('/api/portal_iva.php?' + params);
        const data = await res.json();
        if (data.error) { toast(data.error,'error'); return; }

        const rows = data.data || [];
        if (!rows.length) { toast('No hay datos guardados en ese período','warning'); return; }

        let agregados = 0;
        for (const r of rows) {
            const mes  = String(r.mes).padStart(2,'0');
            const anio = String(r.anio);
            const exists = filas.some(f => f.mes === mes && f.anio === anio);
            if (exists) continue;

            filas.push({
                archivo: r.archivo_nombre || `PortalIVA ${mes}/${anio}`,
                cuit: r.cuit, mes, anio,
                vRI_iva105: parseFloat(r.vRI_iva105||0), vRI_neto105: parseFloat(r.vRI_neto105||0),
                vRI_iva21:  parseFloat(r.vRI_iva21||0),  vRI_neto21:  parseFloat(r.vRI_neto21||0),
                vCF_iva105: parseFloat(r.vCF_iva105||0), vCF_neto105: parseFloat(r.vCF_neto105||0),
                vCF_iva21:  parseFloat(r.vCF_iva21||0),  vCF_neto21:  parseFloat(r.vCF_neto21||0),
                c_iva105:   parseFloat(r.c_iva105||0),   c_neto105:   parseFloat(r.c_neto105||0),
                c_iva21:    parseFloat(r.c_iva21||0),    c_neto21:    parseFloat(r.c_neto21||0),
                c_iva27:    parseFloat(r.c_iva27||0),    c_neto27:    parseFloat(r.c_neto27||0),
                parseOk: true, desde_db: true, estado: 'cargado',
            });
            agregados++;
        }

        filas.sort((a,b) => (a.anio-b.anio)||(a.mes-b.mes));
        renderTablas();
        toast(`${agregados} período${agregados!==1?'s':''} cargado${agregados!==1?'s':''}`, 'success');
    } catch(e) {
        toast('Error al cargar: ' + e.message, 'error');
    }
}

// ── Render ────────────────────────────────────────────────
function fmt(n) {
    if (n === 0) return '<span class="z">—</span>';
    return n.toLocaleString('es-AR',{minimumFractionDigits:2,maximumFractionDigits:2});
}

function periodo(f) {
    return f.anio && f.mes ? `${f.anio} ${f.mes}` : f.archivo.slice(0,12);
}

function estadoBadge(f) {
    switch(f.estado) {
        case 'guardando':    return '<span class="st-guardando">⟳ Guardando…</span>';
        case 'guardado':     return '<span class="st-guardado">✓ Guardado</span>';
        case 'cargado':      return '<span class="st-cargado">● Guardado</span>';
        case 'sin_cliente':  return '<span class="st-sincliente">— Sin cliente</span>';
        case 'sin_periodo':  return '<span class="st-error">⚠ Sin período</span>';
        case 'error_guardar':return `<span class="st-error" title="${escHtml(f.errorMsg||'')}">✕ Error</span>`;
        case 'error_parse':  return '<span class="st-error">✕ Error lectura</span>';
        default:             return '<span class="st-sincliente">—</span>';
    }
}

function renderTablas() {
    const ok = filas.filter(f => f.parseOk);
    if (!filas.length) { document.getElementById('resultado').style.display='none'; return; }
    document.getElementById('resultado').style.display = 'block';
    document.getElementById('res-titulo').textContent = `${filas.length} período${filas.length>1?'s':''} procesado${filas.length>1?'s':''}`;

    document.getElementById('tbody-compras').innerHTML = filas.map(f => {
        if (!f.parseOk) return `<tr><td>${estadoBadge(f)}</td><td colspan="7" style="color:var(--red)">⚠ ${escHtml(f.archivo)} — ${escHtml(f.error||'')}</td></tr>`;
        return `<tr>
            <td style="white-space:nowrap">${estadoBadge(f)}</td>
            <td class="mono" style="font-weight:600">${periodo(f)}</td>
            <td class="mono" style="text-align:right">${fmt(f.c_iva105)}</td>
            <td class="mono" style="text-align:right">${fmt(f.c_neto105)}</td>
            <td class="mono" style="text-align:right">${fmt(f.c_iva21)}</td>
            <td class="mono" style="text-align:right">${fmt(f.c_neto21)}</td>
            <td class="mono" style="text-align:right">${fmt(f.c_iva27)}</td>
            <td class="mono" style="text-align:right">${fmt(f.c_neto27)}</td>
        </tr>`;
    }).join('');

    document.getElementById('tbody-ventas').innerHTML = filas.map(f => {
        if (!f.parseOk) return `<tr><td>${estadoBadge(f)}</td><td colspan="9" style="color:var(--red)">⚠ ${escHtml(f.archivo)}</td></tr>`;
        return `<tr>
            <td style="white-space:nowrap">${estadoBadge(f)}</td>
            <td class="mono" style="font-weight:600">${periodo(f)}</td>
            <td class="mono" style="text-align:right">${fmt(f.vRI_iva105)}</td>
            <td class="mono" style="text-align:right">${fmt(f.vRI_neto105)}</td>
            <td class="mono" style="text-align:right">${fmt(f.vRI_iva21)}</td>
            <td class="mono" style="text-align:right">${fmt(f.vRI_neto21)}</td>
            <td class="mono" style="text-align:right;border-left:1px solid var(--border)">${fmt(f.vCF_iva105)}</td>
            <td class="mono" style="text-align:right">${fmt(f.vCF_neto105)}</td>
            <td class="mono" style="text-align:right">${fmt(f.vCF_iva21)}</td>
            <td class="mono" style="text-align:right">${fmt(f.vCF_neto21)}</td>
        </tr>`;
    }).join('');
}

function limpiar() { filas = []; renderTablas(); }

// ── Exportar Excel ────────────────────────────────────────
function exportarExcel() {
    if (!filas.length) return;
    const ok = filas.filter(f => f.parseOk);

    const rows = [];

    rows.push(['Compras','Iva al 10,5%','Neto al 10,5%','Iva al 21%','Neto al 21%','Iva al 27%','Neto al 27%']);
    for (const f of ok) {
        rows.push([periodo(f), f.c_iva105, f.c_neto105, f.c_iva21, f.c_neto21, f.c_iva27, f.c_neto27]);
    }

    rows.push([]);

    rows.push(['Ventas','Operaciones con RI y Monotributistas','','','','Operaciones con CF, Ex y No Alc','','','']);
    rows.push(['Período','Iva al 10,5%','Neto al 10,5%','Iva al 21%','Neto al 21%','Iva al 10,5%','Neto al 10,5%','Iva al 21%','Neto al 21%']);
    for (const f of ok) {
        rows.push([
            periodo(f),
            f.vRI_iva105, f.vRI_neto105, f.vRI_iva21, f.vRI_neto21,
            f.vCF_iva105, f.vCF_neto105, f.vCF_iva21, f.vCF_neto21,
        ]);
    }

    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = [
        {wch:12},{wch:16},{wch:16},{wch:16},{wch:16},
        {wch:16},{wch:16},{wch:16},{wch:16},
    ];

    const vR = ok.length + 2;
    ws['!merges'] = [
        { s:{r:vR,c:1}, e:{r:vR,c:4} },
        { s:{r:vR,c:5}, e:{r:vR,c:8} },
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Compras y Ventas');
    const fname = `PortalIVA_${(CLIENTE_NOMBRE||'').replace(/\s+/g,'_').substring(0,15)}_${new Date().toISOString().slice(0,10)}.xlsx`;
    XLSX.writeFile(wb, fname);
    toast('Excel descargado', 'success');
}

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

(function() {
    const now  = new Date();
    const curr = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
    const desde = document.getElementById('periodo-desde');
    const hasta  = document.getElementById('periodo-hasta');
    if (desde) desde.value = `${now.getFullYear()}-01`;
    if (hasta)  hasta.value = curr;
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
