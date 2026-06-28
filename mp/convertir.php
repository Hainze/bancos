<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Convertir Excel';
require_once __DIR__ . '/includes/header.php';
?>

<div class="grid-2" style="gap:24px">
    <div>
        <div class="card mb-24">
            <div class="card-title">Convertir Extracto Mercado Pago</div>
            <div class="alert alert-info">
                <span>ℹ</span>
                <div>
                    Subí el Excel tal como lo descargás de Mercado Pago y te devuelvo un archivo limpio con:<br>
                    <strong>Fecha · Descripción · Monto Bruto · Comisión · Retención IIBB · Medio de Pago · Plataforma · Tipo de Medio</strong><br>
                    <small style="opacity:.7">Acepta .xls y .xlsx. El archivo no se sube al servidor.</small>
                </div>
            </div>
            <div class="upload-zone" id="upload-zone">
                <input type="file" id="file-input" accept=".xlsx,.xls,.csv">
                <div class="upload-icon">💳</div>
                <div class="upload-title">Arrastrá el Excel de Mercado Pago aquí</div>
                <div class="upload-sub">o hacé click para seleccionar · .xls .xlsx .csv</div>
            </div>
            <div id="file-selected" style="display:none;margin-top:12px" class="alert alert-success">
                <span>✓</span><span id="file-name">—</span>
            </div>
            <div style="margin-top:16px;display:flex;gap:10px;align-items:center">
                <button class="btn btn-primary btn-lg" id="btn-convertir" disabled>
                    ⬇ Convertir y Descargar
                </button>
                <button class="btn btn-secondary" id="btn-limpiar" style="display:none">✕ Limpiar</button>
            </div>
            <div id="status-ok" style="display:none;margin-top:12px" class="alert alert-success">
                <span>✓</span><span id="status-msg">Archivo descargado.</span>
            </div>
            <div id="status-err" style="display:none;margin-top:12px" class="alert alert-danger">
                <span>✕</span><pre id="err-msg" style="margin:0;white-space:pre-wrap;font-size:12px;font-family:monospace">—</pre>
            </div>
        </div>

        <div class="card">
            <div class="card-title" style="font-size:13px;margin-bottom:12px">Columnas del archivo de salida</div>
            <table style="font-size:13px;width:100%">
                <thead><tr><th>Col</th><th>Origen en el Excel MP</th></tr></thead>
                <tbody>
                    <tr><td class="mono">A · Fecha</td><td>FECHA DE LIBERACIÓN (solo fecha)</td></tr>
                    <tr><td class="mono">B · Descripción</td><td>DESCRIPCIÓN</td></tr>
                    <tr><td class="mono">C · Monto Bruto</td><td>MONTO BRUTO DE LA OPERACIÓN</td></tr>
                    <tr><td class="mono">D · Comisión MP</td><td>COMISIÓN DE MERCADO PAGO (incluye IVA)</td></tr>
                    <tr><td class="mono">E · Retención IIBB</td><td>IMPUESTOS COBRADOS POR RETENCIONES IIBB</td></tr>
                    <tr><td class="mono">F · Medio de Pago</td><td>MEDIO DE PAGO</td></tr>
                    <tr><td class="mono">G · Plataforma</td><td>PLATAFORMA DE COBRO</td></tr>
                    <tr><td class="mono">H · Tipo de Medio de Pago</td><td>TIPO DE MEDIO DE PAGO</td></tr>
                </tbody>
            </table>
            <div style="margin-top:10px;font-size:12px;color:var(--sub)">
                Se eliminan: ID Operación, Montos Neto, Saldo, Fecha Aprobación, Canal de Venta, Purchase ID.
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-title">Vista Previa</div>
            <div id="preview-empty" class="empty-state">
                <div class="empty-state-icon">📋</div>
                <div class="empty-state-text">Subí un archivo para ver la vista previa</div>
            </div>
            <div id="preview-table" style="display:none">
                <div class="flex-between mb-16">
                    <span id="preview-count" class="badge badge-blue">0 filas</span>
                </div>
                <div class="table-wrap" style="max-height:560px;overflow-y:auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Descripción</th>
                                <th style="text-align:right">Monto Bruto</th>
                                <th style="text-align:right">Comisión</th>
                                <th style="text-align:right">Ret. IIBB</th>
                                <th>Medio de Pago</th>
                                <th>Plataforma</th>
                                <th>Tipo Medio</th>
                            </tr>
                        </thead>
                        <tbody id="preview-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentFile = null;
let parsedRows  = null;

initDropZone('upload-zone', f => setFile(f));

function setFile(f) {
    currentFile = f;
    parsedRows  = null;
    document.getElementById('file-name').textContent = f.name + ' (' + (f.size / 1024).toFixed(1) + ' KB)';
    document.getElementById('file-selected').style.display  = 'flex';
    document.getElementById('btn-convertir').disabled = true;
    document.getElementById('btn-limpiar').style.display    = 'inline-flex';
    document.getElementById('status-ok').style.display      = 'none';
    document.getElementById('status-err').style.display     = 'none';
    parseFile(f);
}

document.getElementById('btn-limpiar').addEventListener('click', () => {
    currentFile = null; parsedRows = null;
    document.getElementById('file-input').value = '';
    document.getElementById('file-selected').style.display  = 'none';
    document.getElementById('btn-convertir').disabled = true;
    document.getElementById('btn-limpiar').style.display    = 'none';
    document.getElementById('status-ok').style.display      = 'none';
    document.getElementById('status-err').style.display     = 'none';
    document.getElementById('preview-table').style.display  = 'none';
    document.getElementById('preview-empty').style.display  = 'block';
});

// ── Parseo con SheetJS ─────────────────────────────────────────────────────────
function parseFile(file) {
    function doRead() {
        const reader = new FileReader();
        reader.onload = e => {
            try {
                const wb  = XLSX.read(e.target.result, { type: 'array', cellDates: true });
                const ws  = wb.Sheets[wb.SheetNames[0]];
                const raw = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });

                if (!raw || raw.length < 2) {
                    showErr('El archivo está vacío o no tiene filas de datos.');
                    return;
                }

                // Detectar columnas por nombre normalizado
                const hdrs = raw[0].map(h => norm(String(h)));
                const cols = detectarColumnas(hdrs);

                if (cols.fecha < 0) {
                    showErr(
                        'No se detectó la columna de fecha.\n' +
                        'Encabezados encontrados:\n' + raw[0].slice(0, 20).join(' | ')
                    );
                    return;
                }

                // Transformar filas
                const rows = [];
                for (let i = 1; i < raw.length; i++) {
                    const r = raw[i];

                    // Saltar filas completamente vacías
                    const tieneAlgo = r.some(v => String(v).trim() !== '');
                    if (!tieneAlgo) continue;

                    const fecha      = fmtFecha(r[cols.fecha]);
                    const desc       = String(r[cols.desc]      || '').trim();
                    const montoBruto = toNum(r[cols.montoBruto]);
                    const comision   = toNum(r[cols.comision]);
                    const iibb       = toNum(r[cols.iibb]);
                    const medioPago  = String(r[cols.medioPago]  || '').trim();
                    const plataforma = String(r[cols.plataforma] || '').trim();
                    const tipoMedio  = String(r[cols.tipoMedio]  || '').trim();

                    // Saltar si no hay fecha ni descripción
                    if (!fecha && !desc) continue;

                    rows.push({ fecha, desc, montoBruto, comision, iibb, medioPago, plataforma, tipoMedio });
                }

                parsedRows = rows;
                renderPreview(rows);
                document.getElementById('btn-convertir').disabled = (rows.length === 0);

                if (rows.length === 0) {
                    showErr(
                        'No se encontraron filas con datos.\n' +
                        'Encabezados detectados: ' + JSON.stringify(raw[0].slice(0, 15)) + '\n' +
                        'Columnas usadas: ' + JSON.stringify(cols)
                    );
                }
            } catch (ex) {
                showErr('Error al leer el archivo: ' + ex.message);
            }
        };
        reader.readAsArrayBuffer(file);
    }

    if (typeof XLSX !== 'undefined') { doRead(); return; }
    const t = setInterval(() => { if (typeof XLSX !== 'undefined') { clearInterval(t); doRead(); } }, 100);
}

// ── Detección de columnas ──────────────────────────────────────────────────────
function detectarColumnas(hdrs) {
    const cols = {
        fecha: -1, desc: -1, montoBruto: -1,
        comision: -1, iibb: -1, medioPago: -1,
        plataforma: -1, tipoMedio: -1,
    };

    hdrs.forEach((h, i) => {
        // Fecha de liberación (no la de aprobación)
        if      (cols.fecha < 0      && /fecha.*libera/.test(h))               cols.fecha      = i;
        // Descripción
        else if (cols.desc < 0       && /^descripci/.test(h))                  cols.desc       = i;
        // Monto bruto de la operación
        else if (cols.montoBruto < 0 && /monto.*bruto/.test(h))                cols.montoBruto = i;
        // Comisión (incluye IVA)
        else if (cols.comision < 0   && /comisi/.test(h))                      cols.comision   = i;
        // Retenciones IIBB
        else if (cols.iibb < 0       && /iibb/.test(h))                        cols.iibb       = i;
        // Medio de pago (no "tipo de medio")
        else if (cols.medioPago < 0  && /^medio.*pago/.test(h))                cols.medioPago  = i;
        // Plataforma de cobro
        else if (cols.plataforma < 0 && /plataforma/.test(h))                  cols.plataforma = i;
        // Tipo de medio de pago
        else if (cols.tipoMedio < 0  && /tipo.*medio/.test(h))                 cols.tipoMedio  = i;
    });

    // Fallback por posición conocida del Excel MP estándar:
    // A=FechaLiberacion B=IdOperacion C=Descripcion D=MontoNetoAcreditado
    // E=MontoNetoDebitado F=MontoBruto G=Comision H=IIBB I=MedioPago
    // J=FechaAprobacion K=CanalVenta L=Plataforma M=Saldo N=TipoMedio O=PurchaseId
    if (cols.fecha      < 0) cols.fecha      = 0;
    if (cols.desc       < 0) cols.desc       = 2;
    if (cols.montoBruto < 0) cols.montoBruto = 5;
    if (cols.comision   < 0) cols.comision   = 6;
    if (cols.iibb       < 0) cols.iibb       = 7;
    if (cols.medioPago  < 0) cols.medioPago  = 8;
    if (cols.plataforma < 0) cols.plataforma = 11;
    if (cols.tipoMedio  < 0) cols.tipoMedio  = 13;

    return cols;
}

// ── Conversión y descarga ──────────────────────────────────────────────────────
document.getElementById('btn-convertir').addEventListener('click', () => {
    if (!parsedRows || parsedRows.length === 0) return;
    try {
        const aoa = [[
            'Fecha', 'Descripción', 'Monto Bruto',
            'Comisión Mercado Pago', 'Retención IIBB',
            'Medio de Pago', 'Plataforma', 'Tipo de Medio de Pago',
        ]];
        parsedRows.forEach(r => aoa.push([
            r.fecha, r.desc, r.montoBruto,
            r.comision, r.iibb,
            r.medioPago, r.plataforma, r.tipoMedio,
        ]));

        const wsOut = XLSX.utils.aoa_to_sheet(aoa);
        wsOut['!cols'] = [
            { wch: 13 },  // Fecha
            { wch: 45 },  // Descripción
            { wch: 16 },  // Monto Bruto
            { wch: 18 },  // Comisión
            { wch: 16 },  // IIBB
            { wch: 18 },  // Medio de Pago
            { wch: 22 },  // Plataforma
            { wch: 22 },  // Tipo de Medio
        ];

        const wbOut = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wbOut, wsOut, 'MP');

        const fname = 'mercadopago_' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + '.xlsx';
        XLSX.writeFile(wbOut, fname);

        document.getElementById('status-msg').textContent  = fname + ' descargado (' + parsedRows.length + ' filas).';
        document.getElementById('status-ok').style.display  = 'flex';
        document.getElementById('status-err').style.display = 'none';
    } catch (ex) {
        showErr('Error al generar el archivo: ' + ex.message);
    }
});

// ── Preview ────────────────────────────────────────────────────────────────────
function renderPreview(rows) {
    document.getElementById('preview-count').textContent = rows.length + ' filas';

    const MAX = 200;
    document.getElementById('preview-body').innerHTML =
        rows.slice(0, MAX).map(r =>
            `<tr>
                <td class="mono" style="white-space:nowrap">${r.fecha}</td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(r.desc)}">${r.desc}</td>
                <td class="mono" style="text-align:right">${fmtNum(r.montoBruto)}</td>
                <td class="mono" style="text-align:right;color:var(--red)">${fmtNum(r.comision)}</td>
                <td class="mono" style="text-align:right;color:var(--red)">${fmtNum(r.iibb)}</td>
                <td style="font-size:12px">${r.medioPago}</td>
                <td style="font-size:12px">${r.plataforma}</td>
                <td style="font-size:12px">${r.tipoMedio}</td>
            </tr>`
        ).join('') +
        (rows.length > MAX
            ? `<tr><td colspan="8" style="text-align:center;color:var(--sub);font-size:12px;padding:10px">… y ${rows.length - MAX} filas más</td></tr>`
            : '');

    document.getElementById('preview-empty').style.display = 'none';
    document.getElementById('preview-table').style.display = 'block';
}

// ── Helpers ────────────────────────────────────────────────────────────────────
function norm(s) {
    return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
}

function fmtFecha(v) {
    if (!v) return '';
    // Date object de SheetJS (cellDates: true)
    if (v instanceof Date) {
        return String(v.getDate()).padStart(2, '0') + '/' +
               String(v.getMonth() + 1).padStart(2, '0') + '/' +
               v.getFullYear();
    }
    let s = String(v).trim();
    if (!s || s === 'Invalid Date') return '';
    // Quitar la parte de hora si viene con ella: "2026-05-29 10:35:00" o "2026-05-29T10:35"
    s = s.split(/[\sT]/)[0];
    // yyyy-mm-dd
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
        const [y, m, d] = s.split('-');
        return d + '/' + m + '/' + y;
    }
    // dd/mm/yyyy
    if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(s)) {
        const [d, m, y] = s.split('/');
        return d.padStart(2, '0') + '/' + m.padStart(2, '0') + '/' + y;
    }
    return s;
}

function toNum(v) {
    if (v === '' || v == null) return 0;
    if (typeof v === 'number') return v;
    const s = String(v).replace(/[$\s ]/g, '').replace(/\./g, '').replace(',', '.');
    return parseFloat(s) || 0;
}

function fmtNum(n) {
    if (!n && n !== 0) return '—';
    return new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
}

function esc(s) {
    return String(s).replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

function showErr(msg) {
    document.getElementById('err-msg').textContent      = msg;
    document.getElementById('status-err').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
