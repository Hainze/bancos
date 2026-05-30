<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Parsear F.931';
require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
</script>

<div class="card" style="max-width:680px;margin:0 auto 24px">
    <div class="card-title">Importar F.931 — Cargas Sociales</div>
    <p style="color:var(--sub);font-size:14px;margin-bottom:24px">
        Subí uno o varios PDFs del formulario F.931 de AFIP. El sistema extrae el período, CUIT,
        Suma de Rem. 9 y todos los montos de la sección VIII. Podés acumular varios meses y descargar un solo Excel.
    </p>

    <div class="upload-zone" id="upload-zone">
        <div class="upload-icon">👥</div>
        <div class="upload-text">Arrastrá los PDF acá</div>
        <div class="upload-sub">o hacé clic para seleccionar — podés elegir varios a la vez</div>
        <input type="file" id="file-input" accept=".pdf" multiple style="display:none">
    </div>

    <div id="progress-wrap" style="display:none;margin-top:16px">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px">
            <span style="font-size:13px;color:var(--sub)" id="progress-label">Procesando…</span>
            <span style="font-size:13px;color:var(--sub)" id="progress-pct">0%</span>
        </div>
        <div style="background:var(--surface);border-radius:4px;height:6px;overflow:hidden">
            <div id="progress-bar" style="height:100%;background:#10b981;width:0%;transition:width 0.3s"></div>
        </div>
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
                <button class="btn btn-secondary btn-sm" onclick="limpiarTabla()">✕ Limpiar</button>
                <button class="btn btn-success" onclick="exportarExcel()">⬇ Descargar Excel</button>
            </div>
        </div>

        <div style="overflow-x:auto">
            <table id="res-tabla" style="font-size:12px;min-width:1400px">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>CUIT</th>
                        <th>Mes</th>
                        <th>Año</th>
                        <th style="text-align:right">Suma Rem. 9</th>
                        <th style="text-align:right;color:#f59e0b">Total Retenciones</th>
                        <th style="text-align:right;color:#10b981">351 Contrib. Seg. Social</th>
                        <th style="text-align:right;color:#10b981">301 Aportes Seg. Social</th>
                        <th style="text-align:right;color:#10b981">360 RENATRE</th>
                        <th style="text-align:right;color:#10b981">352 Contrib. Obra Social</th>
                        <th style="text-align:right;color:#10b981">935 Sepelio UATRE</th>
                        <th style="text-align:right;color:#10b981">302 Aportes Obra Social</th>
                        <th style="text-align:right;color:#10b981">270 Vales Aliment.</th>
                        <th style="text-align:right;color:#10b981">312 L.R.T.</th>
                        <th style="text-align:right;color:#10b981">028 Seg. Vida Oblig.</th>
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
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 48px 24px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: var(--surface);
}
.upload-zone:hover, .upload-zone.dragover {
    border-color: #10b981;
    background: rgba(16,185,129,0.05);
}
.upload-icon { font-size: 40px; margin-bottom: 12px; }
.upload-text { font-size: 16px; font-weight: 600; margin-bottom: 4px; }
.upload-sub  { font-size: 13px; color: var(--sub); }
.val-ok  { color: var(--text); }
.val-zero{ color: var(--muted); }
.val-err { color: var(--red); font-style: italic; }
</style>

<script>
// ── Estado ────────────────────────────────────────────────
let filas = [];  // array de objetos con los datos extraídos

// ── Drop zone ─────────────────────────────────────────────
const zone      = document.getElementById('upload-zone');
const fileInput = document.getElementById('file-input');

zone.addEventListener('click',    () => fileInput.click());
zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => {
    e.preventDefault(); zone.classList.remove('dragover');
    procesarArchivos(Array.from(e.dataTransfer.files));
});
fileInput.addEventListener('change', () => procesarArchivos(Array.from(fileInput.files)));

// ── Extraer texto del PDF ─────────────────────────────────
async function extractText(file) {
    const buf = await file.arrayBuffer();
    const pdf = await pdfjsLib.getDocument({ data: buf }).promise;
    let text = '';
    for (let i = 1; i <= pdf.numPages; i++) {
        const page    = await pdf.getPage(i);
        const content = await page.getTextContent();
        // Agrupar por línea (posición Y)
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

// ── Parsear número argentino ──────────────────────────────
function parseNum(s) {
    if (!s) return 0;
    const clean = String(s).trim().replace(/\./g, '').replace(',', '.');
    const n = parseFloat(clean);
    return isNaN(n) ? 0 : n;
}

// ── Parser principal F.931 ────────────────────────────────
function parsearF931(text, fileName) {
    const resultado = {
        archivo: fileName,
        cuit:    '',
        mes:     '',
        anio:    '',
        rem9:       0,
        retenciones: 0,
        c351: 0, c301: 0, c360: 0, c352: 0, c935: 0,
        c302: 0, c270: 0, c312: 0, c028: 0,
        parseOk: true,
    };

    // CUIT — busca patrón XX-XXXXXXXX-X
    const mCuit = text.match(/(\d{2}[-\s]\d{7,8}[-\s]\d)/);
    if (mCuit) resultado.cuit = mCuit[1].replace(/\s/g, '-');

    // Período: busca "Mes - Año" o "Mes-Año" seguido de MM/YYYY o MM YYYY
    const mPer = text.match(/Mes\s*[-–]\s*A[ñn]o\s*:?\s*0?(\d{1,2})\s*[\/\s]\s*(\d{4})/i)
              || text.match(/(\d{1,2})\s*\/\s*(20\d{2})/);
    if (mPer) {
        resultado.mes  = String(mPer[1]).padStart(2, '0');
        resultado.anio = mPer[2];
    }

    // Suma de Rem. 9
    const mRem9 = text.match(/Suma\s+de\s+Rem\.?\s*9\s*:?\s*([\d.,]+)/i);
    if (mRem9) resultado.rem9 = parseNum(mRem9[1]);

    // Total retenciones (sección III)
    const mRet = text.match(/Total\s+retenciones\s+([\d.,]+)/i);
    if (mRet) resultado.retenciones = parseNum(mRet[1]);

    // Sección VIII — Montos que se ingresan
    // Estrategia: buscar el código seguido (en la misma línea o la próxima) del monto
    resultado.c351 = extraerCodigo(text, '351');
    resultado.c301 = extraerCodigo(text, '301');
    resultado.c360 = extraerCodigo(text, '360');
    resultado.c352 = extraerCodigo(text, '352');
    resultado.c935 = extraerCodigo(text, '935');
    resultado.c302 = extraerCodigo(text, '302');
    resultado.c270 = extraerCodigo(text, '270');
    resultado.c312 = extraerCodigo(text, '312');
    resultado.c028 = extraerCodigo(text, '028');

    return resultado;
}

function extraerCodigo(text, codigo) {
    // Busca "351 - texto ... monto" en la misma línea o hasta 2 líneas después
    // Monto argentino: dígitos con puntos de miles y coma decimal (ej: 780.997,47 o 780997,47)
    const reLinea = new RegExp(
        `\\b${codigo}\\s*[-–]\\s*.{0,80}?\\b(\\d{1,3}(?:\\.\\d{3})*,\\d{2})(?:\\s|$)`, 'i'
    );
    const m1 = text.match(reLinea);
    if (m1) return parseNum(m1[1]);

    // Fallback: el código está solo en una línea y el monto en la siguiente
    const reMultilinea = new RegExp(
        `\\b${codigo}\\b[^\\n]*\\n[^\\n]*(\\d{1,3}(?:\\.\\d{3})*,\\d{2})`, 'i'
    );
    const m2 = text.match(reMultilinea);
    if (m2) return parseNum(m2[1]);

    return 0;
}

// ── Procesar lista de archivos ────────────────────────────
async function procesarArchivos(files) {
    const pdfs = files.filter(f => f.name.toLowerCase().endsWith('.pdf'));
    if (!pdfs.length) { toast('No hay archivos PDF válidos', 'warning'); return; }

    document.getElementById('progress-wrap').style.display = 'block';

    for (let i = 0; i < pdfs.length; i++) {
        const f = pdfs[i];
        const pct = Math.round((i / pdfs.length) * 90);
        document.getElementById('progress-label').textContent = `Procesando: ${f.name}`;
        document.getElementById('progress-pct').textContent   = pct + '%';
        document.getElementById('progress-bar').style.width   = pct + '%';

        try {
            const text = await extractText(f);
            const fila = parsearF931(text, f.name);
            filas.push(fila);
        } catch(e) {
            filas.push({ archivo: f.name, parseOk: false, error: e.message });
            toast(`Error procesando ${f.name}: ${e.message}`, 'error');
        }
    }

    document.getElementById('progress-pct').textContent   = '100%';
    document.getElementById('progress-bar').style.width   = '100%';
    document.getElementById('progress-label').textContent = '¡Listo!';
    setTimeout(() => { document.getElementById('progress-wrap').style.display = 'none'; }, 600);

    fileInput.value = '';
    renderTabla();
    toast(`${pdfs.length} PDF${pdfs.length > 1 ? 's' : ''} procesado${pdfs.length > 1 ? 's' : ''}`, 'success');
}

// ── Render tabla ──────────────────────────────────────────
function renderTabla() {
    if (!filas.length) { document.getElementById('resultado').style.display = 'none'; return; }

    document.getElementById('resultado').style.display = 'block';
    document.getElementById('res-titulo').textContent  = `${filas.length} archivo${filas.length > 1 ? 's' : ''} procesado${filas.length > 1 ? 's' : ''}`;
    document.getElementById('res-sub').textContent     = 'Revisá los valores — si alguno aparece en rojo hubo un problema de lectura.';

    const fmt = (n) => {
        if (n === 0) return '<span class="val-zero">0,00</span>';
        return '<span class="val-ok">$ ' + n.toLocaleString('es-AR', { minimumFractionDigits:2, maximumFractionDigits:2 }) + '</span>';
    };

    document.getElementById('res-tbody').innerHTML = filas.map((f, idx) => {
        if (!f.parseOk) {
            return `<tr>
                <td colspan="15" style="color:var(--red)">⚠ ${escHtml(f.archivo)} — Error: ${escHtml(f.error||'')}</td>
            </tr>`;
        }
        const total = f.c351+f.c301+f.c360+f.c352+f.c935+f.c302+f.c270+f.c312+f.c028;
        const sinPer = !f.mes && !f.anio;
        return `<tr>
            <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px" title="${escHtml(f.archivo)}">${escHtml(f.archivo)}</td>
            <td class="mono" style="font-size:11px">${f.cuit || '<span class="val-err">—</span>'}</td>
            <td class="mono" style="text-align:center">${f.mes  || '<span class="val-err">?</span>'}</td>
            <td class="mono" style="text-align:center">${f.anio || '<span class="val-err">?</span>'}</td>
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

function limpiarTabla() {
    filas = [];
    renderTabla();
}

// ── Exportar Excel ────────────────────────────────────────
function exportarExcel() {
    if (!filas.length) return;

    const headers = [
        'Archivo','CUIT','Mes','Año','Suma Rem. 9','Total Retenciones',
        '351 - Contrib. Seg. Social',
        '301 - Aportes Seg. Social',
        '360 - RENATRE',
        '352 - Contrib. Obra Social',
        '935 - Sepelio UATRE',
        '302 - Aportes Obra Social',
        '270 - Vales Aliment.',
        '312 - L.R.T.',
        '028 - Seg. Vida Oblig.',
        'TOTAL Sec. VIII',
    ];

    const rows = [headers];
    for (const f of filas) {
        if (!f.parseOk) { rows.push([f.archivo, 'ERROR', '', '', '', '', '', '', '', '', '', '', '', '', '']); continue; }
        const total = f.c351+f.c301+f.c360+f.c352+f.c935+f.c302+f.c270+f.c312+f.c028;
        rows.push([
            f.archivo, f.cuit, f.mes, f.anio, f.rem9, f.retenciones,
            f.c351, f.c301, f.c360, f.c352, f.c935,
            f.c302, f.c270, f.c312, f.c028, total,
        ]);
    }

    // Totales si hay más de 1 fila
    if (filas.filter(f => f.parseOk).length > 1) {
        const tot = (col) => filas.filter(f => f.parseOk).reduce((s, f) => s + (f[col] || 0), 0);
        rows.push([
            'TOTALES', '', '', '',
            tot('rem9'), tot('retenciones'),
            tot('c351'), tot('c301'), tot('c360'), tot('c352'),
            tot('c935'), tot('c302'), tot('c270'), tot('c312'), tot('c028'),
            tot('c351')+tot('c301')+tot('c360')+tot('c352')+tot('c935')+tot('c302')+tot('c270')+tot('c312')+tot('c028'),
        ]);
    }

    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = [
        {wch:30},{wch:16},{wch:6},{wch:6},{wch:16},{wch:18},
        {wch:22},{wch:22},{wch:14},{wch:22},{wch:16},
        {wch:22},{wch:16},{wch:12},{wch:20},{wch:16},
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'F.931');
    XLSX.writeFile(wb, `F931_${new Date().toISOString().slice(0,10)}.xlsx`);
    toast('Excel descargado', 'success');
}

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
