<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Subir PDF Fiserv';
require_once __DIR__ . '/includes/header.php';
?>

<!-- PDF.js: parsea el PDF en el navegador, sin necesitar nada instalado en el servidor -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
</script>

<div class="card" style="max-width:680px;margin:0 auto">
    <div class="card-title">Subir liquidación Fiserv</div>
    <p style="color:var(--sub);font-size:14px;margin-bottom:24px">
        Seleccioná el PDF de liquidación mensual de Fiserv (Visa, Mastercard, etc.).
        El sistema va a extraer todas las liquidaciones y guardarlas automáticamente.
    </p>

    <!-- Zona de drop -->
    <div class="upload-zone" id="upload-zone">
        <div class="upload-icon">📄</div>
        <div class="upload-text">Arrastrá el PDF acá</div>
        <div class="upload-sub">o hacé clic para seleccionar</div>
        <input type="file" id="file-input" accept=".pdf" style="display:none">
    </div>

    <!-- Info del archivo seleccionado -->
    <div id="file-info" style="display:none;margin-top:16px">
        <div style="display:flex;align-items:center;gap:10px;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px 16px">
            <span style="font-size:20px">📄</span>
            <div style="flex:1;min-width:0">
                <div id="file-name" style="font-size:14px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></div>
                <div id="file-size" style="font-size:12px;color:var(--sub)"></div>
            </div>
            <button onclick="clearFile()" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;padding:4px">✕</button>
        </div>
    </div>

    <!-- Barra de progreso -->
    <div id="progress-wrap" style="display:none;margin-top:16px">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px">
            <span style="font-size:13px;color:var(--sub)" id="progress-label">Procesando PDF…</span>
            <span style="font-size:13px;color:var(--sub)" id="progress-pct">0%</span>
        </div>
        <div style="background:var(--surface);border-radius:4px;height:6px;overflow:hidden">
            <div id="progress-bar" style="height:100%;background:var(--accent);width:0%;transition:width 0.3s"></div>
        </div>
    </div>

    <!-- Botón subir -->
    <div style="margin-top:24px;display:flex;gap:12px">
        <button class="btn btn-primary" id="btn-subir" disabled style="flex:1">
            <span id="btn-text">⬆ Subir y procesar</span>
        </button>
    </div>
</div>

<!-- Resultado -->
<div id="resultado" style="display:none;margin-top:24px;max-width:1200px;margin-left:auto;margin-right:auto">
    <div class="card">
        <div class="flex-between mb-16">
            <div class="card-title" style="margin:0" id="res-titulo">Resultado</div>
            <button class="btn btn-success btn-sm" id="btn-exportar">⬇ Descargar Excel</button>
        </div>
        <div id="res-header" style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px"></div>
        <div class="table-wrap" style="overflow-x:auto">
            <table id="res-tabla" style="font-size:12px;min-width:1600px">
                <thead>
                    <tr>
                        <th>Nro. Liq.</th>
                        <th>F. Pago</th>
                        <th>F. Pres.</th>
                        <th style="color:#10b981">VENTAS C/DSCTO CONTADO</th>
                        <th style="color:#ef4444">ARANCEL</th>
                        <th style="color:#ef4444">IVA S/ARANC 21%</th>
                        <th style="color:#ef4444">ARANCEL CUOTAS</th>
                        <th style="color:#ef4444">IVA ARANC CUOTAS 21%</th>
                        <th style="color:#ef4444">PROMO CUOTA AHORA</th>
                        <th style="color:#ef4444">DTO FINANC CUOTAS</th>
                        <th style="color:#ef4444">IVA RI DTO F.OTORG 10,5%</th>
                        <th style="color:#ef4444">DTO VENTAS FIN ADQ</th>
                        <th style="color:#ef4444">PER B.A.I. BRDN</th>
                        <th style="color:#ef4444">RET IIBB SIRTAC</th>
                        <th style="color:#ef4444">IVA PROMO CUOTA 21%</th>
                        <th style="color:#ef4444">IVA DTO FIN ADQ 21%</th>
                        <th style="color:#ef4444">PERC IVA 1,50%</th>
                        <th style="color:#ef4444">PERC IVA 3%</th>
                        <th style="color:#ef4444">CARGO TERMINAL</th>
                        <th style="color:#ef4444">CARGO SIST CUOTAS</th>
                        <th style="color:#ef4444">IVA RI SIST CUOTAS</th>
                        <th style="color:#ef4444">QR PERC IVA</th>
                        <th style="color:#ef4444">QR RET IIBB</th>
                        <th style="color:#f59e0b;font-weight:700">TOTAL DSCTOS</th>
                        <th style="color:#10b981;font-weight:700">ACREDITADO</th>
                    </tr>
                </thead>
                <tbody id="res-tbody"></tbody>
                <tfoot id="res-tfoot"></tfoot>
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
    border-color: var(--accent);
    background: rgba(37,99,235,0.06);
}
.upload-icon { font-size: 40px; margin-bottom: 12px; }
.upload-text { font-size: 16px; font-weight: 600; margin-bottom: 4px; }
.upload-sub  { font-size: 13px; color: var(--sub); }
.stat-pill {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 5px 12px;
    font-size: 13px;
    color: var(--sub);
}
.stat-pill.green { color: var(--green); border-color: rgba(16,185,129,0.3); }
.stat-pill.blue  { color: var(--accent-light); border-color: rgba(37,99,235,0.3); }
</style>

<script>
// ─────────────────────────────────────────────────────────────────────────────
// EXTRACCIÓN DE TEXTO CON PDF.JS (navegador — no requiere nada en el servidor)
// ─────────────────────────────────────────────────────────────────────────────
async function extractPdfText(file) {
    const arrayBuffer = await file.arrayBuffer();
    const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
    let fullText = '';

    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
        const page = await pdf.getPage(pageNum);
        const content = await page.getTextContent();

        // Agrupar ítems por línea (misma posición Y, tolerancia 3px)
        const lineMap = {};
        for (const item of content.items) {
            if (!item.str) continue;
            const y = Math.round(item.transform[5] / 3) * 3;
            if (!lineMap[y]) lineMap[y] = [];
            lineMap[y].push({ x: item.transform[4], str: item.str });
        }

        // Ordenar líneas de arriba a abajo (Y decreciente en coordenadas PDF)
        const ys = Object.keys(lineMap).map(Number).sort((a, b) => b - a);
        for (const y of ys) {
            const sorted = lineMap[y].sort((a, b) => a.x - b.x);
            fullText += sorted.map(i => i.str).join(' ') + '\n';
        }
        fullText += '\n';
    }
    return fullText;
}

// ─────────────────────────────────────────────────────────────────────────────
// PARSER DE LIQUIDACIONES FISERV (idéntico a la lógica PHP, pero en JS)
// ─────────────────────────────────────────────────────────────────────────────
function parseAmount(raw) {
    return parseFloat(String(raw).replace(/\./g, '').replace(',', '.')) || 0;
}

function parseDate(raw) {
    const m = String(raw).match(/(\d{2})\/(\d{2})\/(\d{4})/);
    return m ? `${m[3]}-${m[2]}-${m[1]}` : null;
}

function parseFiservHeader(text) {
    const h = {};
    const mP = text.match(/(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)\s+(\d{4})/i);
    if (mP) h.periodo = mP[1].toUpperCase() + ' ' + mP[2];

    const isDebito = /TARJETA\s+DE\s+D[EÉ]BITO/i.test(text);
    const mT = text.match(/\b(MASTERCARD|VISA|AMERICAN\s+EXPRESS|AMEX|CABAL)\b/i);
    if (mT) {
        const brand = mT[1].charAt(0).toUpperCase() + mT[1].slice(1).toLowerCase();
        h.tarjeta = brand + (isDebito ? ' Débito' : ' Crédito');
    } else if (isDebito) {
        h.tarjeta = 'Tarjeta Débito';
    } else if (/TARJETA\s+DE\s+CR[EÉ]DITO/i.test(text)) {
        h.tarjeta = 'Tarjeta Crédito';
    }

    const mC = text.match(/N[°º]?\s*Comercio[\s:]+([\d]+)/i) || text.match(/(\d{8,10})\s*\/\s*\d/);
    if (mC) h.nro_comercio = mC[1];

    const mTP = text.match(/Total\s+presentado[\s:]+([\d.,]+)/i);
    if (mTP) h.total_presentado = parseAmount(mTP[1]);
    const mN = text.match(/Neto\s+de\s+pagos[\s:]+([\d.,]+)/i);
    if (mN) h.neto_pagos = parseAmount(mN[1]);

    return h;
}

// Extrae los ítems de una sección de texto (líneas +/- y IMPORTE NETO)
function parseLineItems(chunk) {
    const v = {
        ventas_contado:0, arancel:0, iva_arancel:0, arancel_cuotas:0,
        iva_arancel_cuotas:0, promo_cuota_ahora:0, dto_financ_cuotas:0,
        iva_ri_dto_financ:0, dto_ventas_fin_adq:0, per_bai_brdn:0,
        ret_iibb_sirtac:0, iva_promo_cuota:0, iva_dto_fin_adq:0,
        perc_iva_1_5:0, perc_iva_3:0, cargo_terminal:0,
        cargo_sist_cuotas:0, iva_ri_sist_cuotas:0, qr_perc_iva:0, qr_ret_iibb:0
    };
    let acreditado = null;

    for (const line of chunk.split('\n')) {
        const t = line.trim();

        const mNeto = t.match(/IMPORTE\s+NETO\s+DE\s+PAGOS\s+\$\s*([\d.,]+)\s*(-?)/i);
        if (mNeto) {
            const val = parseAmount(mNeto[1]);
            acreditado = mNeto[2] === '-' ? -val : val;
            continue;
        }

        const mL = t.match(/^([-+])\s+(.+?)\s+\$\s*([\d.,]+)\s*$/);
        if (!mL) continue;

        const sign  = mL[1];
        const desc  = mL[2];
        const amt   = parseAmount(mL[3]);
        const delta = sign === '-' ? amt : -amt;

        if      (/VENTAS\s+C\/DESCUENTO\s+CONTADO/i.test(desc))
            v.ventas_contado     += sign === '+' ? amt : -amt;
        // Ventas con financiación de cuotas (ej: "VENTAS C/DTO CUOTAS FINANC. OTORG.")
        else if (/VENTAS\s+C\/DTO\s+CUOTAS/i.test(desc))
            v.ventas_contado     += sign === '+' ? amt : -amt;
        else if (/IVA\s+ARANCEL\s+CUOTAS/i.test(desc))
            v.iva_arancel_cuotas += delta;
        else if (/ARANCEL\s+CUOTAS/i.test(desc))
            v.arancel_cuotas     += delta;
        // IVA sobre arancel (contado) — debe ir ANTES del genérico S/DTO
        else if (/IVA\s+CRED\.?FISC.*S\/ARANC/i.test(desc))
            v.iva_arancel        += delta;
        else if (/^ARANCEL\s*$/i.test(desc))
            v.arancel            += delta;
        else if (/IVA\s+PROMO\s+CUOTA\s+AHORA/i.test(desc))
            v.iva_promo_cuota    += delta;
        else if (/PROMO\s+CUOTA\s+AHORA/i.test(desc))
            v.promo_cuota_ahora  += delta;
        else if (/DESCUENTO\s+FINANC[\s.]OTORG/i.test(desc))
            v.dto_financ_cuotas  += delta;
        // IVA sobre descuento financiero de cuotas — con o sin "RI" (ej: "IVA CRED.FISC.COM.L 25063 S/DTO F.OTOR 10,50%")
        else if (/IVA\s+(RI\s+)?CRED\.?FISC.*S\/DTO/i.test(desc))
            v.iva_ri_dto_financ  += delta;
        else if (/DTO\s+S\/VENTAS\s+FIN\s+ADQ/i.test(desc))
            v.dto_ventas_fin_adq += delta;
        else if (/IVA\s+S\/DTO\s+FIN\s+ADQ/i.test(desc))
            v.iva_dto_fin_adq    += delta;
        // PER B.A.I — flexible para puntos y espacios variables entre letras
        else if (/PER\s+B[\s.]*A[\s.]*I/i.test(desc))
            v.per_bai_brdn       += delta;
        else if (/RETENCION\s+ING\.?\s*BRUTOS.*SIRTAC/i.test(desc))
            v.ret_iibb_sirtac    += delta;
        else if (/PERCEPCION\s+IVA\s+R\.?G\.?\s*2408\s+1[,.]?5/i.test(desc))
            v.perc_iva_1_5       += delta;
        else if (/PERCEPCION\s+IVA\s+R\.?G\.?\s*2408\s+3/i.test(desc))
            v.perc_iva_3         += delta;
        else if (/CARGO\s+TERMINAL\s+FISERV/i.test(desc))
            v.cargo_terminal     += delta;
        else if (/CARGO\s+SISTEMA\s+CUOTAS\s+MENS/i.test(desc))
            v.cargo_sist_cuotas  += delta;
        else if (/IVA\s+RI\s+SIST\s+CUOTAS/i.test(desc))
            v.iva_ri_sist_cuotas += delta;
        else if (/QR\s+PERCEPCION\s+IVA/i.test(desc))
            v.qr_perc_iva        += delta;
        else if (/QR\s+RETENCION\s+IIBB/i.test(desc))
            v.qr_ret_iibb        += delta;
    }
    return { v, acreditado };
}

function parseFiservLiquidaciones(text) {
    const liquidaciones = [];
    text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');

    const parts = text.split(/(?=F\.?\s*de\s+Pago\s*:)/i);

    // REGLA CLAVE: En todos los PDFs Fiserv, los ítems de la liquidación N
    // aparecen ANTES de la línea "F.de Pago:" que la identifica.
    // Por lo tanto, los ítems de cada bloque pertenecen a la SIGUIENTE liquidación.
    // Usamos pendingItems para guardar los ítems del bloque anterior.
    let pendingItems = null;

    for (const chunk of parts) {
        if (!/^F\.?\s*de\s+Pago\s*:/i.test(chunk.trimStart())) continue;

        const flat = chunk.replace(/\s+/g, ' ');

        const mNro = flat.match(/Nro\.?\s*Liq[\s:.]*(\d+)/i);
        const mFp  = flat.match(/el\s+d[íi]a\s+(\d{2}\/\d{2}\/\d{4})/i);
        const mFpr = flat.match(/F\.?\s*Pres\.?\s+(\d{2}\/\d{2}\/\d{4})/i);

        const nro_liq    = mNro ? mNro[1] : '';
        const fecha_pago = mFp  ? parseDate(mFp[1])  : null;
        const fecha_pres = mFpr ? parseDate(mFpr[1]) : null;

        const items    = parseLineItems(chunk);
        const hasItems = items.acreditado !== null || Object.values(items.v).some(x => x !== 0);

        if (!nro_liq && !fecha_pago) {
            // Bloque sin liquidación (encabezado de tabla, etc.)
            // Sus ítems pertenecen a la primera liq real → guardar como pending
            if (hasItems) pendingItems = items;
            continue;
        }

        // Bloque con liquidación identificada:
        // - pendingItems = ítems que le corresponden a ESTA liq (vienen del bloque anterior)
        // - items del chunk actual = ítems de la SIGUIENTE liq → guardar como pending
        const use = pendingItems;
        pendingItems = hasItems ? items : null;

        if (!use) continue; // sin ítems para esta liquidación, ignorar

        const { v, acreditado: rawAcred } = use;
        let total_descuentos = 0;
        for (const [k, val] of Object.entries(v)) {
            if (k !== 'ventas_contado' && val > 0) total_descuentos += val;
        }
        const acreditado = rawAcred !== null ? rawAcred : v.ventas_contado - total_descuentos;

        const r2 = n => Math.round(n * 100) / 100;
        liquidaciones.push({
            nro_liq, fecha_pago, fecha_pres,
            ventas_contado:    r2(v.ventas_contado),
            arancel:           r2(v.arancel),
            iva_arancel:       r2(v.iva_arancel),
            arancel_cuotas:    r2(v.arancel_cuotas),
            iva_arancel_cuotas:r2(v.iva_arancel_cuotas),
            promo_cuota_ahora: r2(v.promo_cuota_ahora),
            dto_financ_cuotas: r2(v.dto_financ_cuotas),
            iva_ri_dto_financ: r2(v.iva_ri_dto_financ),
            dto_ventas_fin_adq:r2(v.dto_ventas_fin_adq),
            per_bai_brdn:      r2(v.per_bai_brdn),
            ret_iibb_sirtac:   r2(v.ret_iibb_sirtac),
            iva_promo_cuota:   r2(v.iva_promo_cuota),
            iva_dto_fin_adq:   r2(v.iva_dto_fin_adq),
            perc_iva_1_5:      r2(v.perc_iva_1_5),
            perc_iva_3:        r2(v.perc_iva_3),
            cargo_terminal:    r2(v.cargo_terminal),
            cargo_sist_cuotas: r2(v.cargo_sist_cuotas),
            iva_ri_sist_cuotas:r2(v.iva_ri_sist_cuotas),
            qr_perc_iva:       r2(v.qr_perc_iva),
            qr_ret_iibb:       r2(v.qr_ret_iibb),
            total_descuentos:  r2(total_descuentos),
            acreditado:        r2(acreditado),
        });
    }
    return liquidaciones;
}

// ─────────────────────────────────────────────────────────────────────────────
// UI
// ─────────────────────────────────────────────────────────────────────────────
let parsedData = null;

const zone      = document.getElementById('upload-zone');
const fileInput = document.getElementById('file-input');
const fileInfo  = document.getElementById('file-info');
const btnSubir  = document.getElementById('btn-subir');

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
    if (!f.name.toLowerCase().endsWith('.pdf')) { toast('Solo se aceptan archivos PDF', 'warning'); return; }
    document.getElementById('file-name').textContent = f.name;
    document.getElementById('file-size').textContent = (f.size / 1024).toFixed(1) + ' KB';
    fileInfo.style.display = 'block';
    btnSubir.disabled = false;
    fileInput._file = f;
}

function clearFile() {
    fileInfo.style.display = 'none';
    btnSubir.disabled = true;
    fileInput.value = '';
    fileInput._file = null;
    parsedData = null;
    document.getElementById('resultado').style.display = 'none';
}

function setProgress(on, label = '', pct = 0) {
    document.getElementById('progress-wrap').style.display = on ? 'block' : 'none';
    if (on) {
        document.getElementById('progress-label').textContent = label;
        document.getElementById('progress-pct').textContent   = pct + '%';
        document.getElementById('progress-bar').style.width   = pct + '%';
    }
}

document.getElementById('btn-subir').addEventListener('click', async () => {
    const f = fileInput._file || fileInput.files[0];
    if (!f) return;

    btnSubir.disabled = true;
    document.getElementById('btn-text').textContent = 'Procesando…';
    setProgress(true, 'Leyendo PDF en el navegador…', 15);

    try {
        // 1. Extraer texto con PDF.js (en el navegador)
        setProgress(true, 'Extrayendo texto…', 35);
        const text = await extractPdfText(f);

        // 2. Parsear
        setProgress(true, 'Analizando liquidaciones…', 60);
        const header       = parseFiservHeader(text);
        const liquidaciones = parseFiservLiquidaciones(text);

        if (!liquidaciones.length) {
            toast('No se encontraron liquidaciones en el PDF. Verificá que sea un resumen Fiserv válido.', 'error');
            return;
        }

        // 3. Guardar en base de datos
        setProgress(true, 'Guardando en base de datos…', 80);
        const res  = await fetch('/fiserv/api/guardar_liquidacion.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ header, liquidaciones, archivo_nombre: f.name }),
        });
        const data = await res.json();

        setProgress(true, 'Listo', 100);
        setTimeout(() => setProgress(false), 500);

        if (data.error) { toast(data.error, 'error'); return; }

        parsedData = data;
        renderResultado(data);
        toast(`${data.total} liquidaciones importadas`, 'success');

    } catch(e) {
        toast('Error: ' + e.message, 'error');
    } finally {
        btnSubir.disabled = false;
        document.getElementById('btn-text').textContent = '⬆ Subir y procesar';
        setProgress(false);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// RENDER
// ─────────────────────────────────────────────────────────────────────────────
function fmt(v) {
    const n = parseFloat(v || 0);
    const abs = Math.abs(n);
    const s = abs.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return n < 0 ? '-$ ' + s : '$ ' + s;
}
function cellColor(c, n) {
    if (n === 0) return 'var(--muted)';
    if (c === 'ventas_contado')   return n > 0 ? 'var(--green)' : 'var(--muted)';
    if (c === 'acreditado')       return n >= 0 ? 'var(--green)' : 'var(--red)';
    if (c === 'total_descuentos') return n > 0 ? 'var(--amber)' : 'var(--muted)';
    return n > 0 ? 'var(--red)' : 'var(--green)';
}
function fmtDate(d) {
    if (!d) return '—';
    const [y, m, dia] = d.split('-');
    return `${dia}/${m}/${y}`;
}

const cols = [
    'ventas_contado','arancel','iva_arancel','arancel_cuotas','iva_arancel_cuotas',
    'promo_cuota_ahora','dto_financ_cuotas','iva_ri_dto_financ','dto_ventas_fin_adq',
    'per_bai_brdn','ret_iibb_sirtac','iva_promo_cuota','iva_dto_fin_adq',
    'perc_iva_1_5','perc_iva_3','cargo_terminal','cargo_sist_cuotas','iva_ri_sist_cuotas',
    'qr_perc_iva','qr_ret_iibb','total_descuentos','acreditado'
];

function renderResultado(data) {
    const res = document.getElementById('resultado');
    res.style.display = 'block';
    res.scrollIntoView({ behavior: 'smooth', block: 'start' });

    document.getElementById('res-titulo').textContent =
        `${data.total} liquidaciones — ${data.header?.periodo ?? ''} ${data.header?.tarjeta ?? ''}`;

    const h = data.header || {};
    document.getElementById('res-header').innerHTML = [
        h.tarjeta           && `<div class="stat-pill">💳 ${h.tarjeta}</div>`,
        h.periodo           && `<div class="stat-pill">📅 ${h.periodo}</div>`,
        h.nro_comercio      && `<div class="stat-pill">🏪 Comercio: ${h.nro_comercio}</div>`,
        h.total_presentado  && `<div class="stat-pill green">Presentado: ${fmt(h.total_presentado)}</div>`,
        h.neto_pagos        && `<div class="stat-pill blue">Neto pagos: ${fmt(h.neto_pagos)}</div>`,
    ].filter(Boolean).join('');

    const totals = {};
    cols.forEach(c => totals[c] = 0);

    document.getElementById('res-tbody').innerHTML = data.liquidaciones.map(l => {
        cols.forEach(c => totals[c] += parseFloat(l[c] || 0));
        return `<tr>
            <td class="mono" style="font-size:11px">${l.nro_liq || '—'}</td>
            <td class="mono">${fmtDate(l.fecha_pago)}</td>
            <td class="mono">${fmtDate(l.fecha_pres)}</td>
            ${cols.map(c => { const n = parseFloat(l[c]||0); return `<td class="mono" style="text-align:right;color:${cellColor(c,n)}">${n !== 0 ? fmt(n) : '—'}</td>`; }).join('')}
        </tr>`;
    }).join('');

    document.getElementById('res-tfoot').innerHTML = `<tr style="background:var(--surface);font-weight:700">
        <td colspan="3" style="font-size:12px;color:var(--sub)">TOTALES</td>
        ${cols.map(c => { const n = totals[c]; return `<td class="mono" style="text-align:right;font-size:12px;color:${cellColor(c,n)}">${n !== 0 ? fmt(n) : '—'}</td>`; }).join('')}
    </tr>`;
}

// ─────────────────────────────────────────────────────────────────────────────
// EXPORTAR EXCEL (SheetJS)
// ─────────────────────────────────────────────────────────────────────────────
document.getElementById('btn-exportar').addEventListener('click', () => {
    if (!parsedData) return;
    exportarExcel(parsedData);
});

function exportarExcel(data) {
    const h = data.header || {};
    const colHeaders = [
        'Nro. Liq.', 'Fecha Pago', 'Fecha Pres.',
        'VENTAS C/DESCUENTO CONTADO','ARANCEL','IVA CRED FISC COMERCIO S/ARANC 21%',
        'ARANCEL CUOTAS','IVA ARANCEL CUOTAS 21%','PROMO CUOTA AHORA SIMPLE',
        'DESCUENTO FINANC OTORG. CUOTAS','IVA RI CRED FISC COMERCIO S/DTO F.OTORG 10,5%',
        'DTO S/VENTAS FIN ADQ CONT','PER B.A.I. BRDN 01/04','RETENCION ING. BRUTOS BSAS SIRTAC',
        'IVA PROMO CUOTA AHORA SIMPLE 21%','IVA S/DTO FIN ADQ CONT 21%',
        'PERCEPCION IVA R.G. 2408 1,50%','PERCEPCION IVA R.G. 2408 3%',
        'CARGO TERMINAL FISERV','CARGO SISTEMA CUOTAS MENS','IVA RI SIST CUOTAS',
        'QR PERCEPCION IVA','QR RETENCION IIBB BS.AS.','TOTAL','ACREDITADO',
    ];
    const fieldMap = ['nro_liq','fecha_pago','fecha_pres', ...cols];
    const rows = [colHeaders];
    const totals = new Array(fieldMap.length).fill(0);

    data.liquidaciones.forEach(l => {
        rows.push(fieldMap.map((f, i) => {
            if (f === 'nro_liq') return l[f] || '';
            if (f === 'fecha_pago' || f === 'fecha_pres') return fmtDate(l[f]);
            const n = parseFloat(l[f] || 0);
            if (i >= 3) totals[i] += n;
            return n;
        }));
    });
    rows.push(fieldMap.map((f, i) => {
        if (i === 0) return 'TOTALES';
        if (i === 1 || i === 2) return '';
        return totals[i];
    }));

    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = colHeaders.map((c, i) => ({ wch: i < 3 ? 14 : Math.max(c.length, 12) }));
    const wb = XLSX.utils.book_new();
    const sheetName = (h.periodo || 'Fiserv').replace(/\s+/g,'_').substring(0,31);
    XLSX.utils.book_append_sheet(wb, ws, sheetName);
    const fname = `Fiserv_${h.periodo || 'liquidacion'}_${h.tarjeta || ''}.xlsx`
        .replace(/\s+/g,'_').replace(/[^\w._-]/g,'');
    XLSX.writeFile(wb, fname);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
