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
    <p style="color:var(--sub);font-size:14px;margin-bottom:20px">
        Subí los PDF de "Vista Previa" del Portal IVA de ARCA. El Excel descarga un solo archivo
        con <strong>Compras</strong> y <strong>Ventas</strong> en el mismo cuadro.
        Compras = facturas − notas de crédito. Ventas = facturado − restitución.
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

    <!-- Tabla COMPRAS (neto = facturas - NC) -->
    <div class="card mb-24">
        <div class="card-title" style="color:#f59e0b">Compras (Facturas − Notas de Crédito)</div>
        <div style="overflow-x:auto">
            <table style="font-size:12px;min-width:700px">
                <thead>
                    <tr>
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

    <!-- Tabla VENTAS (neto = facturado - restitución) -->
    <div class="card">
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
                        <th style="text-align:right">IVA 10,5%</th><th style="text-align:right">Neto 10,5%</th>
                        <th style="text-align:right">IVA 21%</th><th style="text-align:right">Neto 21%</th>
                        <th style="text-align:right">IVA 10,5%</th><th style="text-align:right">Neto 10,5%</th>
                        <th style="text-align:right">IVA 21%</th><th style="text-align:right">Neto 21%</th>
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

function parseNum(s) {
    if (!s) return 0;
    const n = parseFloat(String(s).trim().replace(/\./g,'').replace(',','.'));
    return isNaN(n) ? 0 : n;
}

const ARG = '\\d{1,3}(?:\\.\\d{3})*,\\d{2}';

// Extrae sección de texto entre dos patrones
function seccion(text, desdeRe, hastaRe) {
    const ini = text.search(desdeRe);
    if (ini < 0) return '';
    const rest = text.slice(ini);
    if (!hastaRe) return rest;
    const fin = rest.search(hastaRe);
    return fin > 0 ? rest.slice(0, fin) : rest;
}

// Extrae {neto, iva} de la primera coincidencia de la tasa en el texto
function tasa(texto, rate) {
    const esc = rate.replace(',','[,.]');
    const re  = new RegExp(`${esc}\\s*%\\s+(${ARG})(?:\\s+(${ARG}))?`, 'i');
    const m   = texto.match(re);
    if (!m) return { neto:0, iva:0 };
    return { neto: parseNum(m[1]), iva: parseNum(m[2]||'0') };
}

// Para compras: todas las ocurrencias de una tasa → primera=facturas, segunda=NC
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
        archivo: fileName, cuit: '', mes: '', anio: '', parseOk: true,
        // Ventas netas (facturado − restituido)
        vRI_iva105:0, vRI_neto105:0, vRI_iva21:0, vRI_neto21:0,
        vCF_iva105:0, vCF_neto105:0, vCF_iva21:0, vCF_neto21:0,
        // Compras netas (facturas − NC)
        c_iva105:0, c_neto105:0, c_iva21:0, c_neto21:0, c_iva27:0, c_neto27:0,
    };

    // Período
    const mPer = text.match(/Vista\s+Previa\s*\|\s*0?(\d{1,2})\s*\/\s*(\d{4})/i)
              || text.match(/Presentaci[oó]n\s+0?(\d{1,2})\/(\d{4})/i)
              || text.match(/\b(\d{2})\/(\d{4})\b/);
    if (mPer) { r.mes = String(mPer[1]).padStart(2,'0'); r.anio = mPer[2]; }

    // CUIT
    const mCuit = text.match(/\[(\d{2}-\d{7,8}-\d)\]/);
    if (mCuit) r.cuit = mCuit[1];

    // ── VENTAS — sección facturado ──────────────────────────────
    const secVC = seccion(text,
        /Información\s+Consolidada\s+de\s+Ventas/i,
        /generan\s+Restitu[ci][oó]n|Restitución\s+del\s+Déb/i
    );

    if (secVC) {
        // Primera subsección = RI y Monotributistas
        const riEnd = secVC.search(/Consumidores\s+Finales/i);
        const riTxt = riEnd > 0 ? secVC.slice(0, riEnd) : secVC;
        // Segunda subsección = CF, Exentos y No Alcanzados
        const cfTxt = riEnd > 0 ? secVC.slice(riEnd) : '';

        const ri05 = tasa(riTxt,'10,5'); r.vRI_iva105 = ri05.iva; r.vRI_neto105 = ri05.neto;
        const ri21 = tasa(riTxt,'21');   r.vRI_iva21  = ri21.iva; r.vRI_neto21  = ri21.neto;

        if (cfTxt) {
            const cf05 = tasa(cfTxt,'10,5'); r.vCF_iva105 = cf05.iva; r.vCF_neto105 = cf05.neto;
            const cf21 = tasa(cfTxt,'21');   r.vCF_iva21  = cf21.iva; r.vCF_neto21  = cf21.neto;
        }
    }

    // ── VENTAS — sección restitución (a restar) ─────────────────
    const secVR = seccion(text,
        /generan\s+Restitu[ci][oó]n\s+del\s+D[eé]b|Restitución\s+del\s+D[eé]b/i,
        /Información\s+Consolidada\s+de\s+Compras/i
    );

    if (secVR) {
        // Primera subsección = RI restituido
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

    // ── COMPRAS — primera ocurrencia=facturas, segunda=NC → neto directo ──
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
        document.getElementById('progress-label').textContent = `(${i+1}/${pdfs.length}) ${f.name}`;
        document.getElementById('progress-pct').textContent   = Math.round(i/pdfs.length*90) + '%';
        document.getElementById('progress-bar').style.width   = Math.round(i/pdfs.length*90) + '%';
        try {
            const text = await extractText(f);
            const fila = parsearPortalIVA(text, f.name);
            const dup  = filas.findIndex(x => x.mes === fila.mes && x.anio === fila.anio);
            if (dup >= 0) filas[dup] = fila; else filas.push(fila);
        } catch(e) { toast(`Error: ${f.name} — ${e.message}`, 'error'); }
    }

    filas.sort((a,b) => (a.anio-b.anio)||(a.mes-b.mes));
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
    return n.toLocaleString('es-AR',{minimumFractionDigits:2,maximumFractionDigits:2});
}

function periodo(f) {
    return f.anio && f.mes ? `${f.anio} ${f.mes}` : f.archivo.slice(0,12);
}

function renderTablas() {
    if (!filas.length) { document.getElementById('resultado').style.display='none'; return; }
    document.getElementById('resultado').style.display = 'block';
    document.getElementById('res-titulo').textContent = `${filas.length} período${filas.length>1?'s':''} procesado${filas.length>1?'s':''}`;

    document.getElementById('tbody-compras').innerHTML = filas.map(f => `<tr>
        <td class="mono" style="font-weight:600">${periodo(f)}</td>
        <td class="mono" style="text-align:right">${fmt(f.c_iva105)}</td>
        <td class="mono" style="text-align:right">${fmt(f.c_neto105)}</td>
        <td class="mono" style="text-align:right">${fmt(f.c_iva21)}</td>
        <td class="mono" style="text-align:right">${fmt(f.c_neto21)}</td>
        <td class="mono" style="text-align:right">${fmt(f.c_iva27)}</td>
        <td class="mono" style="text-align:right">${fmt(f.c_neto27)}</td>
    </tr>`).join('');

    document.getElementById('tbody-ventas').innerHTML = filas.map(f => `<tr>
        <td class="mono" style="font-weight:600">${periodo(f)}</td>
        <td class="mono" style="text-align:right">${fmt(f.vRI_iva105)}</td>
        <td class="mono" style="text-align:right">${fmt(f.vRI_neto105)}</td>
        <td class="mono" style="text-align:right">${fmt(f.vRI_iva21)}</td>
        <td class="mono" style="text-align:right">${fmt(f.vRI_neto21)}</td>
        <td class="mono" style="text-align:right">${fmt(f.vCF_iva105)}</td>
        <td class="mono" style="text-align:right">${fmt(f.vCF_neto105)}</td>
        <td class="mono" style="text-align:right">${fmt(f.vCF_iva21)}</td>
        <td class="mono" style="text-align:right">${fmt(f.vCF_neto21)}</td>
    </tr>`).join('');
}

function limpiar() { filas = []; renderTablas(); }

// ── Exportar Excel — UN solo sheet con Compras y Ventas ──
function exportarExcel() {
    if (!filas.length) return;
    const ok = filas.filter(f => f.parseOk);

    const rows = [];

    // ── Bloque Compras ─────────────────────────────────────
    rows.push(['Compras','Iva al 10,5%','Neto al 10,5%','Iva al 21%','Neto al 21%','Iva al 27%','Neto al 27%']);
    for (const f of ok) {
        rows.push([periodo(f), f.c_iva105, f.c_neto105, f.c_iva21, f.c_neto21, f.c_iva27, f.c_neto27]);
    }

    // Fila vacía separadora
    rows.push([]);

    // ── Bloque Ventas ──────────────────────────────────────
    rows.push(['Ventas','','Operaciones con RI y Monotributistas','','','','Operaciones con CF, Ex y No Alc','','','']);
    rows.push(['Período','Iva al 10,5%','Neto al 10,5%','Iva al 21%','Neto al 21%','','Iva al 10,5%','Neto al 10,5%','Iva al 21%','Neto al 21%']);
    for (const f of ok) {
        rows.push([
            periodo(f),
            f.vRI_iva105, f.vRI_neto105, f.vRI_iva21, f.vRI_neto21,
            '',
            f.vCF_iva105, f.vCF_neto105, f.vCF_iva21, f.vCF_neto21,
        ]);
    }

    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = [
        {wch:12},{wch:16},{wch:16},{wch:16},{wch:16},{wch:14},{wch:14},
        {wch:4},{wch:16},{wch:16},{wch:16},{wch:16},
    ];

    // Merge de encabezado ventas
    const vStartRow = ok.length + 3; // filas compras + separador + fila ventas header
    ws['!merges'] = [
        { s:{r:vStartRow,c:2}, e:{r:vStartRow,c:4} }, // RI y Mono
        { s:{r:vStartRow,c:6}, e:{r:vStartRow,c:9} }, // CF
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Compras y Ventas');
    XLSX.writeFile(wb, `PortalIVA_${new Date().toISOString().slice(0,10)}.xlsx`);
    toast('Excel descargado', 'success');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
