<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Subir PDF Fiserv';
require_once __DIR__ . '/includes/header.php';
?>

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
        <button class="btn btn-secondary" id="btn-debug" disabled title="Ver texto extraído del PDF (diagnóstico)" style="min-width:90px">
            🔍 Debug
        </button>
    </div>
    <div id="debug-output" style="display:none;margin-top:16px">
        <div style="font-size:12px;color:var(--sub);margin-bottom:6px">Texto extraído del PDF (primeros 4000 caracteres):</div>
        <textarea id="debug-text" readonly style="width:100%;height:220px;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:10px;font-family:monospace;font-size:11px;color:var(--text);resize:vertical"></textarea>
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
</style>

<script>
let parsedData = null;

const zone      = document.getElementById('upload-zone');
const fileInput = document.getElementById('file-input');
const fileInfo  = document.getElementById('file-info');
const btnSubir  = document.getElementById('btn-subir');

zone.addEventListener('click', () => fileInput.click());
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
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
    document.getElementById('btn-debug').disabled = false;
    fileInput._file = f;
}

function clearFile() {
    fileInfo.style.display = 'none';
    btnSubir.disabled = true;
    document.getElementById('btn-debug').disabled = true;
    document.getElementById('debug-output').style.display = 'none';
    fileInput.value = '';
    fileInput._file = null;
}

document.getElementById('btn-debug').addEventListener('click', async () => {
    const f = fileInput._file || fileInput.files[0];
    if (!f) return;
    const btn = document.getElementById('btn-debug');
    btn.disabled = true; btn.textContent = '…';
    const form = new FormData();
    form.append('pdf', f);
    form.append('debug', '1');
    try {
        const res  = await fetch('/fiserv/api/subir_pdf.php', { method: 'POST', body: form });
        const data = await res.json();
        const out  = document.getElementById('debug-output');
        const ta   = document.getElementById('debug-text');
        out.style.display = 'block';
        ta.value = data.debug_text || data.error || JSON.stringify(data);
        out.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch(e) { toast('Error: ' + e.message, 'error'); }
    finally { btn.disabled = false; btn.textContent = '🔍 Debug'; }
});

document.getElementById('btn-subir').addEventListener('click', async () => {
    const f = fileInput._file || fileInput.files[0];
    if (!f) return;

    btnSubir.disabled = true;
    document.getElementById('btn-text').textContent = 'Procesando…';
    setProgress(true, 'Leyendo PDF…', 20);

    const form = new FormData();
    form.append('pdf', f);

    try {
        setProgress(true, 'Extrayendo datos…', 60);
        const res  = await fetch('/fiserv/api/subir_pdf.php', { method: 'POST', body: form });
        setProgress(true, 'Guardando…', 85);
        const data = await res.json();

        setProgress(true, 'Listo', 100);
        setTimeout(() => setProgress(false), 600);

        if (data.error) {
            toast(data.error, 'error');
            return;
        }

        parsedData = data;
        renderResultado(data);
        toast(`${data.total} liquidaciones importadas`, 'success');

    } catch (e) {
        toast('Error de conexión: ' + e.message, 'error');
    } finally {
        btnSubir.disabled = false;
        document.getElementById('btn-text').textContent = '⬆ Subir y procesar';
        setProgress(false);
    }
});

function setProgress(on, label = '', pct = 0) {
    document.getElementById('progress-wrap').style.display = on ? 'block' : 'none';
    if (on) {
        document.getElementById('progress-label').textContent = label;
        document.getElementById('progress-pct').textContent   = pct + '%';
        document.getElementById('progress-bar').style.width   = pct + '%';
    }
}

function fmt(v) {
    const n = parseFloat(v || 0);
    const abs = Math.abs(n);
    const s = abs.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return n < 0 ? '-$ ' + s : '$ ' + s;
}

function cellColor(c, n) {
    if (n === 0) return 'var(--muted)';
    if (c === 'ventas_contado') return n > 0 ? 'var(--green)' : 'var(--muted)';
    if (c === 'acreditado')     return n >= 0 ? 'var(--green)' : 'var(--red)';
    if (c === 'total_descuentos') return n > 0 ? 'var(--amber)' : 'var(--muted)';
    // columnas de descuento: positivo=rojo (débito), negativo=verde (crédito devuelto)
    return n > 0 ? 'var(--red)' : 'var(--green)';
}

function fmtDate(d) {
    if (!d) return '—';
    const [y, m, dia] = d.split('-');
    return `${dia}/${m}/${y}`;
}

function renderResultado(data) {
    const res = document.getElementById('resultado');
    res.style.display = 'block';
    res.scrollIntoView({ behavior: 'smooth', block: 'start' });

    document.getElementById('res-titulo').textContent = `${data.total} liquidaciones — ${data.header?.periodo ?? ''} ${data.header?.tarjeta ?? ''}`;

    // Header info
    const h = data.header || {};
    document.getElementById('res-header').innerHTML = [
        h.tarjeta      && `<div class="stat-pill">💳 ${h.tarjeta}</div>`,
        h.periodo      && `<div class="stat-pill">📅 ${h.periodo}</div>`,
        h.nro_comercio && `<div class="stat-pill">🏪 Comercio: ${h.nro_comercio}</div>`,
        h.total_presentado && `<div class="stat-pill green">Presentado: ${fmt(h.total_presentado)}</div>`,
        h.neto_pagos   && `<div class="stat-pill blue">Neto pagos: ${fmt(h.neto_pagos)}</div>`,
    ].filter(Boolean).join('');

    const cols = [
        'ventas_contado','arancel','iva_arancel','arancel_cuotas','iva_arancel_cuotas',
        'promo_cuota_ahora','dto_financ_cuotas','iva_ri_dto_financ','dto_ventas_fin_adq',
        'per_bai_brdn','ret_iibb_sirtac','iva_promo_cuota','iva_dto_fin_adq',
        'perc_iva_1_5','perc_iva_3','cargo_terminal','cargo_sist_cuotas','iva_ri_sist_cuotas',
        'qr_perc_iva','qr_ret_iibb','total_descuentos','acreditado'
    ];
    const totals = {};
    cols.forEach(c => totals[c] = 0);

    const tbody = document.getElementById('res-tbody');
    tbody.innerHTML = data.liquidaciones.map(l => {
        cols.forEach(c => totals[c] += parseFloat(l[c] || 0));
        return `<tr>
            <td class="mono" style="font-size:11px">${l.nro_liq || '—'}</td>
            <td class="mono">${fmtDate(l.fecha_pago)}</td>
            <td class="mono">${fmtDate(l.fecha_pres)}</td>
            ${cols.map(c => { const n = parseFloat(l[c]||0); return `<td class="mono" style="text-align:right;color:${cellColor(c,n)}">${n !== 0 ? fmt(n) : '—'}</td>`; }).join('')}
        </tr>`;
    }).join('');

    // Fila de totales
    document.getElementById('res-tfoot').innerHTML = `<tr style="background:var(--surface);font-weight:700">
        <td colspan="3" style="font-size:12px;color:var(--sub)">TOTALES</td>
        ${cols.map(c => { const n = totals[c]; return `<td class="mono" style="text-align:right;font-size:12px;color:${cellColor(c,n)}">${n !== 0 ? fmt(n) : '—'}</td>`; }).join('')}
    </tr>`;
}

// ── Exportar Excel con SheetJS ───────────────────────────────────────────────
document.getElementById('btn-exportar').addEventListener('click', () => {
    if (!parsedData) return;
    exportarExcel(parsedData);
});

function exportarExcel(data) {
    const h = data.header || {};
    const colHeaders = [
        'Nro. Liq.', 'Fecha Pago', 'Fecha Pres.',
        'VENTAS C/DESCUENTO CONTADO',
        'ARANCEL',
        'IVA CRED FISC COMERCIO S/ARANC 21%',
        'ARANCEL CUOTAS',
        'IVA ARANCEL CUOTAS 21%',
        'PROMO CUOTA AHORA SIMPLE',
        'DESCUENTO FINANC OTORG. CUOTAS',
        'IVA RI CRED FISC COMERCIO S/DTO F.OTORG 10,5%',
        'DTO S/VENTAS FIN ADQ CONT',
        'PER B.A.I. BRDN 01/04',
        'RETENCION ING. BRUTOS BSAS SIRTAC',
        'IVA PROMO CUOTA AHORA SIMPLE 21%',
        'IVA S/DTO FIN ADQ CONT 21%',
        'PERCEPCION IVA R.G. 2408 1,50%',
        'PERCEPCION IVA R.G. 2408 3%',
        'CARGO TERMINAL FISERV',
        'CARGO SISTEMA CUOTAS MENS',
        'IVA RI SIST CUOTAS',
        'QR PERCEPCION IVA',
        'QR RETENCION IIBB BS.AS.',
        'TOTAL',
        'ACREDITADO',
    ];

    const fieldMap = [
        'nro_liq', 'fecha_pago', 'fecha_pres',
        'ventas_contado', 'arancel', 'iva_arancel', 'arancel_cuotas', 'iva_arancel_cuotas',
        'promo_cuota_ahora', 'dto_financ_cuotas', 'iva_ri_dto_financ', 'dto_ventas_fin_adq',
        'per_bai_brdn', 'ret_iibb_sirtac', 'iva_promo_cuota', 'iva_dto_fin_adq',
        'perc_iva_1_5', 'perc_iva_3', 'cargo_terminal', 'cargo_sist_cuotas', 'iva_ri_sist_cuotas',
        'qr_perc_iva', 'qr_ret_iibb', 'total_descuentos', 'acreditado',
    ];

    const rows = [colHeaders];
    const totals = new Array(fieldMap.length).fill(0);

    data.liquidaciones.forEach(l => {
        const row = fieldMap.map((f, i) => {
            const v = l[f];
            if (f === 'nro_liq') return v || '';
            if (f === 'fecha_pago' || f === 'fecha_pres') return fmtDate(v);
            const n = parseFloat(v || 0);
            if (i >= 3) totals[i] += n;
            return n;
        });
        rows.push(row);
    });

    // Fila totales
    const totRow = fieldMap.map((f, i) => {
        if (i === 0) return 'TOTALES';
        if (i === 1 || i === 2) return '';
        return totals[i];
    });
    rows.push(totRow);

    const ws = XLSX.utils.aoa_to_sheet(rows);

    // Ancho de columnas
    ws['!cols'] = colHeaders.map((c, i) => ({ wch: i < 3 ? 14 : Math.max(c.length, 12) }));

    const wb = XLSX.utils.book_new();
    const sheetName = (h.periodo || 'Fiserv').replace(/\s+/g, '_').substring(0, 31);
    XLSX.utils.book_append_sheet(wb, ws, sheetName);

    const filename = `Fiserv_${h.periodo || 'liquidacion'}_${h.tarjeta || ''}.xlsx`
        .replace(/\s+/g, '_').replace(/[^\w._-]/g, '');
    XLSX.writeFile(wb, filename);
}
</script>

<style>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
