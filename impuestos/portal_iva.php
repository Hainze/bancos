<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Parsear Portal IVA';
require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
</script>

<div class="card" style="max-width:680px;margin:0 auto 24px">
    <div class="card-title">Importar Vista Previa — Portal IVA (ARCA)</div>
    <p style="color:var(--sub);font-size:14px;margin-bottom:6px">
        Subí los PDF de "Vista Previa" del Portal IVA de ARCA. El sistema extrae el período, los datos de
        <strong>Ventas</strong> (facturado menos restitución) y de <strong>Compras</strong> (facturas y notas de crédito).
    </p>
    <p style="color:var(--sub);font-size:13px;margin-bottom:20px">
        El Excel final tiene <strong>dos hojas</strong>: Ventas y Compras. Podés acumular varios meses.
    </p>

    <div class="upload-zone" id="upload-zone">
        <div class="upload-icon">🧾</div>
        <div class="upload-text">Arrastrá los PDF acá</div>
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

<!-- Resultados -->
<div id="resultado" style="display:none">

    <div class="flex-between mb-16">
        <div class="card-title" id="res-titulo" style="margin:0"></div>
        <div style="display:flex;gap:8px">
            <button class="btn btn-secondary btn-sm" onclick="limpiar()">✕ Limpiar</button>
            <button class="btn btn-success" onclick="exportarExcel()">⬇ Descargar Excel</button>
        </div>
    </div>

    <!-- Tabla VENTAS -->
    <div class="card mb-24">
        <div class="card-title" style="color:#10b981">Ventas (Facturado − Restitución)</div>
        <div style="overflow-x:auto">
            <table style="font-size:12px;min-width:900px">
                <thead>
                    <tr>
                        <th rowspan="2" style="vertical-align:middle">Período</th>
                        <th colspan="4" style="text-align:center;background:rgba(16,185,129,.1);color:#10b981">Operaciones con RI y Monotributistas</th>
                        <th colspan="4" style="text-align:center;background:rgba(37,99,235,.1);color:var(--accent-light)">Operaciones con CF, Exentos y No Alcanzados</th>
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

    <!-- Tabla COMPRAS -->
    <div class="card">
        <div class="card-title" style="color:#f59e0b">Compras</div>
        <div style="overflow-x:auto">
            <table style="font-size:12px;min-width:1100px">
                <thead>
                    <tr>
                        <th rowspan="2" style="vertical-align:middle">Período</th>
                        <th colspan="6" style="text-align:center;background:rgba(16,185,129,.1);color:#10b981">Facturas de Compra</th>
                        <th colspan="6" style="text-align:center;background:rgba(239,68,68,.1);color:var(--red)">Notas de Crédito</th>
                    </tr>
                    <tr>
                        <th style="text-align:right">IVA 10,5%</th><th style="text-align:right">Neto 10,5%</th>
                        <th style="text-align:right">IVA 21%</th><th style="text-align:right">Neto 21%</th>
                        <th style="text-align:right">IVA 27%</th><th style="text-align:right">Neto 27%</th>
                        <th style="text-align:right">IVA 10,5%</th><th style="text-align:right">Neto 10,5%</th>
                        <th style="text-align:right">IVA 21%</th><th style="text-align:right">Neto 21%</th>
                        <th style="text-align:right">IVA 27%</th><th style="text-align:right">Neto 27%</th>
                    </tr>
                </thead>
                <tbody id="tbody-compras"></tbody>
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
</style>

<script>
// ── Estado ────────────────────────────────────────────────
let filas = [];

// ── Drop zone ─────────────────────────────────────────────
const zone      = document.getElementById('upload-zone');
const fileInput = document.getElementById('file-input');
zone.addEventListener('click',    () => fileInput.click());
zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => { e.preventDefault(); zone.classList.remove('dragover'); procesarArchivos(Array.from(e.dataTransfer.files)); });
fileInput.addEventListener('change', () => procesarArchivos(Array.from(fileInput.files)));

// ── Extraer texto de PDF ──────────────────────────────────
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
        for (const y of ys) {
            text += lineMap[y].sort((a,b)=>a.x-b.x).map(i=>i.str).join(' ') + '\n';
        }
        text += '\n';
    }
    return text;
}

function parseNum(s) {
    if (!s) return 0;
    const n = parseFloat(String(s).trim().replace(/\./g,'').replace(',','.'));
    return isNaN(n) ? 0 : n;
}

// ── Helpers de sección ────────────────────────────────────
function seccion(text, desdeRegex, hastaRegex) {
    const ini = text.search(desdeRegex);
    if (ini < 0) return '';
    const resto = text.slice(ini);
    if (!hastaRegex) return resto;
    const fin = resto.search(hastaRegex);
    return fin > 0 ? resto.slice(0, fin) : resto;
}

const ARG = '\\d{1,3}(?:\\.\\d{3})*,\\d{2}';

// Extrae {neto, iva} de la primer línea con "10,5 %" o "21 %" o "27 %"
function tasa(texto, rate) {
    const esc = rate.replace(',','[,.]');
    const re  = new RegExp(`${esc}\\s*%\\s+(${ARG})(?:\\s+(${ARG}))?`, 'i');
    const m   = texto.match(re);
    if (!m) return { neto: 0, iva: 0 };
    return { neto: parseNum(m[1]), iva: parseNum(m[2] || '0') };
}

// ── Parser principal ──────────────────────────────────────
function parsearPortalIVA(text, fileName) {
    const r = {
        archivo: fileName, cuit: '', mes: '', anio: '', parseOk: true,
        // Ventas RI facturado
        vRI_iva105: 0, vRI_neto105: 0, vRI_iva21: 0, vRI_neto21: 0,
        // Ventas CF facturado
        vCF_iva105: 0, vCF_neto105: 0, vCF_iva21: 0, vCF_neto21: 0,
        // Ventas RI restituido
        vrRI_iva105: 0, vrRI_neto105: 0, vrRI_iva21: 0, vrRI_neto21: 0,
        // Ventas CF restituido
        vrCF_iva105: 0, vrCF_neto105: 0, vrCF_iva21: 0, vrCF_neto21: 0,
        // Compras facturas
        cF_iva105: 0, cF_neto105: 0, cF_iva21: 0, cF_neto21: 0, cF_iva27: 0, cF_neto27: 0,
        // Compras NC
        cNC_iva105: 0, cNC_neto105: 0, cNC_iva21: 0, cNC_neto21: 0, cNC_iva27: 0, cNC_neto27: 0,
    };

    // ── Período ──────────────────────────────────────────
    const mPer = text.match(/Vista\s+Previa\s*\|\s*0?(\d{1,2})\s*\/\s*(\d{4})/i)
              || text.match(/Presentaci[oó]n\s+0?(\d{1,2})\/(\d{4})/i)
              || text.match(/(\d{2})\/(\d{4})/);
    if (mPer) { r.mes = String(mPer[1]).padStart(2,'0'); r.anio = mPer[2]; }

    // ── CUIT ─────────────────────────────────────────────
    const mCuit = text.match(/\[(\d{2}-\d{7,8}-\d)\]/);
    if (mCuit) r.cuit = mCuit[1];

    // ── VENTAS — Información Consolidada ─────────────────
    const secVentas = seccion(text,
        /Información Consolidada de Ventas/i,
        /Restitu[ci]ón del Déb|Restitución del Deb|generan Restituc/i
    );

    if (secVentas) {
        // RI y Monotributistas = primera parte (antes de CF)
        const riText = seccion(secVentas, /Responsables\s+Inscriptos\s+y\s+Monotrib/i, /Consumidores\s+Finales/i);
        const cfText = seccion(secVentas, /Consumidores\s+Finales[^R]*(?:Exentos|No\s+Alc)/i, null);

        if (riText) {
            const t1 = tasa(riText, '10,5'); r.vRI_iva105 = t1.iva; r.vRI_neto105 = t1.neto;
            const t2 = tasa(riText, '21');   r.vRI_iva21  = t2.iva; r.vRI_neto21  = t2.neto;
        }
        if (cfText) {
            const t1 = tasa(cfText, '10,5'); r.vCF_iva105 = t1.iva; r.vCF_neto105 = t1.neto;
            const t2 = tasa(cfText, '21');   r.vCF_iva21  = t2.iva; r.vCF_neto21  = t2.neto;
        }
        // Fallback: si no encontró secciones, tomar todas las tasas en orden
        if (!riText && !cfText) {
            const allRates = [...secVentas.matchAll(new RegExp(`(10[,.]5|21)\\s*%\\s+(${ARG})(?:\\s+(${ARG}))?`,'gi'))];
            if (allRates[0]) { r.vRI_neto105 = parseNum(allRates[0][2]); r.vRI_iva105 = parseNum(allRates[0][3]||'0'); }
            if (allRates[1]) { r.vRI_neto21  = parseNum(allRates[1][2]); r.vRI_iva21  = parseNum(allRates[1][3]||'0'); }
            if (allRates[2]) { r.vCF_neto105 = parseNum(allRates[2][2]); r.vCF_iva105 = parseNum(allRates[2][3]||'0'); }
            if (allRates[3]) { r.vCF_neto21  = parseNum(allRates[3][2]); r.vCF_iva21  = parseNum(allRates[3][3]||'0'); }
        }
    }

    // ── VENTAS — Restitución ──────────────────────────────
    const secRest = seccion(text,
        /generan\s+Restitu[ci][oó]n\s+del\s+D[eé]b|Restitu[ci][oó]n\s+del\s+D[eé]b/i,
        /Informaci[oó]n\s+Consolidada\s+de\s+Compras/i
    );

    if (secRest) {
        // Primera tabla: RI restituido
        const riRText = seccion(secRest, /Responsables\s+Inscriptos|Inscriptos.*?Otros.*?Restitu/i, /Sujetos\s+Exentos|No\s+Alcanzados.*?Restitu/i);
        // Segunda tabla: CF restituido
        const cfRText = seccion(secRest, /Sujetos\s+Exentos|No\s+Alcanzados.*?Restitu|Consumidores\s+Finales.*?Restitu/i, null);

        if (riRText) {
            const t1 = tasa(riRText, '10,5'); r.vrRI_iva105 = t1.iva; r.vrRI_neto105 = t1.neto;
            const t2 = tasa(riRText, '21');   r.vrRI_iva21  = t2.iva; r.vrRI_neto21  = t2.neto;
        }
        if (cfRText) {
            const t1 = tasa(cfRText, '10,5'); r.vrCF_iva105 = t1.iva; r.vrCF_neto105 = t1.neto;
            const t2 = tasa(cfRText, '21');   r.vrCF_iva21  = t2.iva; r.vrCF_neto21  = t2.neto;
        }
    }

    // ── COMPRAS — Información Consolidada ────────────────
    const secCompras = seccion(text, /Informaci[oó]n\s+Consolidada\s+de\s+Compras/i, null);

    if (secCompras) {
        // Facturas: "Compras de Bienes" (antes de "Mercado Local" o "a Restituir")
        const facText = seccion(secCompras, /Compras\s+de\s+Bienes|Compras.*?Mercado.*?Interior/i, /Mercado\s+Local.*?Restituir|Crédito\s+Fiscal\s+a\s+Restituir/i);
        // NC: "Crédito Fiscal a Restituir"
        const ncText  = seccion(secCompras, /Mercado\s+Local.*?Restituir|Crédito\s+Fiscal\s+a\s+Restituir/i, null);

        if (facText) {
            const t1 = tasa(facText, '10,5'); r.cF_iva105 = t1.iva; r.cF_neto105 = t1.neto;
            const t2 = tasa(facText, '21');   r.cF_iva21  = t2.iva; r.cF_neto21  = t2.neto;
            const t3 = tasa(facText, '27');   r.cF_iva27  = t3.iva; r.cF_neto27  = t3.neto;
        }
        // Fallback si no hay subsección clara: tomar las primeras 3 tasas de la sección
        if (!facText && secCompras) {
            const allC = [...secCompras.matchAll(new RegExp(`(10[,.]5|21|27)\\s*%\\s+(${ARG})(?:\\s+(${ARG}))?`,'gi'))];
            if (allC[0]) { r.cF_neto105 = parseNum(allC[0][2]); r.cF_iva105 = parseNum(allC[0][3]||'0'); }
            if (allC[1]) { r.cF_neto21  = parseNum(allC[1][2]); r.cF_iva21  = parseNum(allC[1][3]||'0'); }
            if (allC[2]) { r.cF_neto27  = parseNum(allC[2][2]); r.cF_iva27  = parseNum(allC[2][3]||'0'); }
        }

        if (ncText) {
            const t1 = tasa(ncText, '10,5'); r.cNC_iva105 = t1.iva; r.cNC_neto105 = t1.neto;
            const t2 = tasa(ncText, '21');   r.cNC_iva21  = t2.iva; r.cNC_neto21  = t2.neto;
            const t3 = tasa(ncText, '27');   r.cNC_iva27  = t3.iva; r.cNC_neto27  = t3.neto;
        }
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
        const pct = Math.round(i / pdfs.length * 90);
        document.getElementById('progress-label').textContent = `(${i+1}/${pdfs.length}) ${f.name}`;
        document.getElementById('progress-pct').textContent   = pct + '%';
        document.getElementById('progress-bar').style.width   = pct + '%';

        try {
            const text = await extractText(f);
            const fila = parsearPortalIVA(text, f.name);
            // Evitar duplicados por mes/año
            const dup = filas.findIndex(x => x.mes === fila.mes && x.anio === fila.anio);
            if (dup >= 0) filas[dup] = fila; else filas.push(fila);
        } catch(e) {
            toast(`Error procesando ${f.name}: ${e.message}`, 'error');
        }
    }

    filas.sort((a,b) => (a.anio - b.anio) || (a.mes - b.mes));
    document.getElementById('progress-pct').textContent = '100%';
    document.getElementById('progress-bar').style.width = '100%';
    document.getElementById('progress-label').textContent = '¡Listo!';
    setTimeout(() => { document.getElementById('progress-wrap').style.display='none'; }, 600);
    fileInput.value = '';
    renderTablas();
    toast(`${pdfs.length} PDF${pdfs.length>1?'s':''} procesado${pdfs.length>1?'s':''}`, 'success');
}

// ── Render ────────────────────────────────────────────────
function fmt(n) {
    if (n === 0) return '<span class="z">—</span>';
    return '$ ' + n.toLocaleString('es-AR',{minimumFractionDigits:2,maximumFractionDigits:2});
}

function renderTablas() {
    if (!filas.length) { document.getElementById('resultado').style.display='none'; return; }
    document.getElementById('resultado').style.display = 'block';
    document.getElementById('res-titulo').textContent  = `${filas.length} período${filas.length>1?'s':''} procesado${filas.length>1?'s':''}`;

    document.getElementById('tbody-ventas').innerHTML = filas.map(f => {
        if (!f.parseOk) return `<tr><td colspan="9" style="color:var(--red)">⚠ ${escHtml(f.archivo)}</td></tr>`;
        const periodo = f.anio && f.mes ? `${f.anio} ${f.mes}` : (f.archivo.slice(0,15));
        // Netos = facturado - restituido
        const riI05 = f.vRI_iva105 - f.vrRI_iva105,  riN05 = f.vRI_neto105 - f.vrRI_neto105;
        const riI21 = f.vRI_iva21  - f.vrRI_iva21,   riN21 = f.vRI_neto21  - f.vrRI_neto21;
        const cfI05 = f.vCF_iva105 - f.vrCF_iva105,  cfN05 = f.vCF_neto105 - f.vrCF_neto105;
        const cfI21 = f.vCF_iva21  - f.vrCF_iva21,   cfN21 = f.vCF_neto21  - f.vrCF_neto21;
        return `<tr>
            <td class="mono" style="font-weight:600">${periodo}</td>
            <td class="mono" style="text-align:right">${fmt(riI05)}</td>
            <td class="mono" style="text-align:right">${fmt(riN05)}</td>
            <td class="mono" style="text-align:right">${fmt(riI21)}</td>
            <td class="mono" style="text-align:right">${fmt(riN21)}</td>
            <td class="mono" style="text-align:right">${fmt(cfI05)}</td>
            <td class="mono" style="text-align:right">${fmt(cfN05)}</td>
            <td class="mono" style="text-align:right">${fmt(cfI21)}</td>
            <td class="mono" style="text-align:right">${fmt(cfN21)}</td>
        </tr>`;
    }).join('');

    document.getElementById('tbody-compras').innerHTML = filas.map(f => {
        if (!f.parseOk) return '';
        const periodo = f.anio && f.mes ? `${f.anio} ${f.mes}` : (f.archivo.slice(0,15));
        return `<tr>
            <td class="mono" style="font-weight:600">${periodo}</td>
            <td class="mono" style="text-align:right">${fmt(f.cF_iva105)}</td>
            <td class="mono" style="text-align:right">${fmt(f.cF_neto105)}</td>
            <td class="mono" style="text-align:right">${fmt(f.cF_iva21)}</td>
            <td class="mono" style="text-align:right">${fmt(f.cF_neto21)}</td>
            <td class="mono" style="text-align:right">${fmt(f.cF_iva27)}</td>
            <td class="mono" style="text-align:right">${fmt(f.cF_neto27)}</td>
            <td class="mono" style="text-align:right">${fmt(f.cNC_iva105)}</td>
            <td class="mono" style="text-align:right">${fmt(f.cNC_neto105)}</td>
            <td class="mono" style="text-align:right">${fmt(f.cNC_iva21)}</td>
            <td class="mono" style="text-align:right">${fmt(f.cNC_neto21)}</td>
            <td class="mono" style="text-align:right">${fmt(f.cNC_iva27)}</td>
            <td class="mono" style="text-align:right">${fmt(f.cNC_neto27)}</td>
        </tr>`;
    }).join('');
}

function limpiar() { filas = []; renderTablas(); }

// ── Exportar Excel ────────────────────────────────────────
function exportarExcel() {
    if (!filas.length) return;
    const wb = XLSX.utils.book_new();

    // ── Hoja Ventas ─────────────────────────────────────
    const vRows = [
        ['Ventas','','Operaciones con RI y Monotributistas','','','','Operaciones con CF, Exentos y No Alc','','',''],
        ['Período','Iva al 10,5%','Neto al 10,5%','Iva al 21%','Neto al 21%','','Iva al 10,5%','Neto al 10,5%','Iva al 21%','Neto al 21%'],
    ];
    for (const f of filas.filter(f => f.parseOk)) {
        const periodo = f.anio && f.mes ? `${f.anio} ${f.mes}` : f.archivo.slice(0,12);
        vRows.push([
            periodo,
            f.vRI_iva105 - f.vrRI_iva105, f.vRI_neto105 - f.vrRI_neto105,
            f.vRI_iva21  - f.vrRI_iva21,  f.vRI_neto21  - f.vrRI_neto21,
            '',
            f.vCF_iva105 - f.vrCF_iva105, f.vCF_neto105 - f.vrCF_neto105,
            f.vCF_iva21  - f.vrCF_iva21,  f.vCF_neto21  - f.vrCF_neto21,
        ]);
    }
    const wsV = XLSX.utils.aoa_to_sheet(vRows);
    wsV['!cols'] = [{wch:12},{wch:16},{wch:16},{wch:16},{wch:16},{wch:4},{wch:16},{wch:16},{wch:16},{wch:16}];
    wsV['!merges'] = [
        {s:{r:0,c:2},e:{r:0,c:5}}, // RI y Mono header
        {s:{r:0,c:6},e:{r:0,c:9}}, // CF header
    ];
    XLSX.utils.book_append_sheet(wb, wsV, 'Ventas');

    // ── Hoja Compras ────────────────────────────────────
    const cRows = [
        ['Compras','','Facturas de Compra','','','','','','Notas de Crédito','','','','',''],
        ['Período','Iva 10,5%','Neto 10,5%','Iva 21%','Neto 21%','Iva 27%','Neto 27%','','Iva 10,5%','Neto 10,5%','Iva 21%','Neto 21%','Iva 27%','Neto 27%'],
    ];
    for (const f of filas.filter(f => f.parseOk)) {
        const periodo = f.anio && f.mes ? `${f.anio} ${f.mes}` : f.archivo.slice(0,12);
        cRows.push([
            periodo,
            f.cF_iva105, f.cF_neto105, f.cF_iva21, f.cF_neto21, f.cF_iva27, f.cF_neto27,
            '',
            f.cNC_iva105, f.cNC_neto105, f.cNC_iva21, f.cNC_neto21, f.cNC_iva27, f.cNC_neto27,
        ]);
    }
    const wsC = XLSX.utils.aoa_to_sheet(cRows);
    wsC['!cols'] = [{wch:12},{wch:14},{wch:14},{wch:14},{wch:14},{wch:12},{wch:12},{wch:4},{wch:14},{wch:14},{wch:14},{wch:14},{wch:12},{wch:12}];
    wsC['!merges'] = [
        {s:{r:0,c:1},e:{r:0,c:6}},  // Facturas header
        {s:{r:0,c:8},e:{r:0,c:13}}, // NC header
    ];
    XLSX.utils.book_append_sheet(wb, wsC, 'Compras');

    XLSX.writeFile(wb, `PortalIVA_${new Date().toISOString().slice(0,10)}.xlsx`);
    toast('Excel descargado', 'success');
}

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
