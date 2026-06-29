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

        reader.onerror = () => showErr(
            'FileReader no pudo leer el archivo.\n' +
            'Nombre: ' + file.name + '\nTamaño: ' + file.size + ' bytes'
        );

        reader.onload = e => {
            try {
                const data = e.target.result;

                // Detectar formato por magic bytes para manejar HTML disfrazado de XLSX
                const bytes = new Uint8Array(data.slice(0, 4));
                const hex4  = Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
                const isHtml = hex4.startsWith('3c') || hex4.startsWith('efbb');

                let wb;
                if (isHtml) {
                    const text = new TextDecoder('utf-8').decode(data);
                    wb = XLSX.read(text, { type: 'string', cellDates: true });
                } else {
                    wb = XLSX.read(data, { type: 'array', cellDates: true });
                }

                if (!wb.SheetNames || wb.SheetNames.length === 0) {
                    showErr('El archivo no contiene hojas de cálculo.\nFormato: ' + hex4);
                    return;
                }

                // Buscar la hoja con encabezados MP válidos (el primer sheet puede ser portada)
                let raw = null;
                let sheetUsada = '';
                for (const name of wb.SheetNames) {
                    const candidate = wb.Sheets[name];
                    if (!candidate || !candidate['!ref']) continue;
                    const candidateRows = XLSX.utils.sheet_to_json(candidate, { header: 1, defval: '' });
                    if (candidateRows.length < 2) continue;

                    // Verificar si esta hoja tiene encabezados de MP
                    const hIdx  = findHeaderRow(candidateRows);
                    const hdrs  = candidateRows[hIdx].map(h => norm(String(h)));
                    const mpHits = hdrs.filter(h =>
                        /fecha.*libera/.test(h) || /^descripci/.test(h) ||
                        /monto.*bruto/.test(h)  || /comisi/.test(h) ||
                        /iibb/.test(h)           || /medio.*pago/.test(h)
                    ).length;

                    if (mpHits >= 2) {
                        raw = candidateRows;
                        sheetUsada = name;
                        break; // Hoja correcta encontrada
                    }
                    // Como fallback guardar la hoja con más filas
                    if (!raw || candidateRows.length > raw.length) {
                        raw = candidateRows;
                        sheetUsada = name;
                    }
                }

                if (!raw || raw.length < 2) {
                    showErr(
                        'El archivo no tiene filas con datos.\n' +
                        'Hojas encontradas: ' + wb.SheetNames.join(', ') + '\n' +
                        'Formato (hex): ' + hex4
                    );
                    return;
                }

                // Buscar la fila real de encabezados (puede no ser la primera)
                const headerIdx = findHeaderRow(raw);
                const hdrs = raw[headerIdx].map(h => norm(String(h)));
                const cols = detectarColumnas(hdrs);

                if (cols.fecha < 0) {
                    showErr(
                        'No se detectó la columna de fecha.\n\n' +
                        'Hoja usada: "' + sheetUsada + '"\n' +
                        'Fila de encabezado (fila ' + (headerIdx + 1) + '):\n  ' +
                        raw[headerIdx].slice(0, 15).filter(h => h !== '').join(' | ') + '\n\n' +
                        'Primeras 3 filas:\n' +
                        raw.slice(0, 3).map((r, i) =>
                            'F' + (i+1) + ': ' + r.slice(0, 8).map(v => String(v).substring(0, 20)).join(' | ')
                        ).join('\n')
                    );
                    return;
                }

                // Transformar filas (saltear todo lo anterior al encabezado)
                const rows = [];
                for (let i = headerIdx + 1; i < raw.length; i++) {
                    const r = raw[i];

                    // Saltar filas completamente vacías
                    if (!r.some(v => String(v).trim() !== '')) continue;

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
                        'Se leyó el archivo pero no se encontraron filas válidas.\n\n' +
                        'Hoja: "' + sheetUsada + '" | Filas totales: ' + raw.length + '\n' +
                        'Encabezado detectado (fila ' + (headerIdx + 1) + '):\n  ' +
                        raw[headerIdx].slice(0, 15).filter(h => h !== '').join(' | ') + '\n\n' +
                        'Columnas mapeadas:\n' + JSON.stringify(cols) + '\n\n' +
                        'Primera fila de datos:\n  ' +
                        (raw[headerIdx + 1] || []).slice(0, 8).map((v, i) => i + ':' + String(v).substring(0, 15)).join(' | ')
                    );
                }
            } catch (ex) {
                showErr(
                    'Error al procesar el archivo: ' + ex.message + '\n\n' +
                    'Nombre: ' + file.name + '\nTamaño: ' + file.size + ' bytes\nTipo MIME: ' + (file.type || 'desconocido')
                );
            }
        };
        reader.readAsArrayBuffer(file);
    }

    if (typeof XLSX !== 'undefined') { doRead(); return; }
    const t = setInterval(() => { if (typeof XLSX !== 'undefined') { clearInterval(t); doRead(); } }, 100);
}

// ── Busca la fila real de encabezados (MP pone títulos antes de los headers) ───
function findHeaderRow(raw) {
    // Palabras clave que deben aparecer en la fila de encabezados
    const marcas = [
        /fecha.*libera/, /^descripci/, /monto.*bruto/, /monto.*neto/,
        /comisi/, /iibb/, /medio.*pago/, /plataforma/, /tipo.*medio/,
    ];
    for (let i = 0; i < Math.min(10, raw.length); i++) {
        const rowNorm = raw[i].map(h => norm(String(h)));
        let hits = 0;
        for (const h of rowNorm) {
            for (const re of marcas) { if (re.test(h)) { hits++; break; } }
        }
        if (hits >= 2) return i; // encontró al menos 2 columnas conocidas
    }
    return 0; // fallback: usar fila 0
}

// ── Detección de columnas ──────────────────────────────────────────────────────
// Nombres exactos del Excel de Mercado Pago (normalizados)
const MP_COLS = {
    'fecha de liberacion':                                     'fecha',
    'descripcion':                                             'desc',
    'monto bruto de la operacion':                            'montoBruto',
    'comision de mercado pago o mercado libre (incluye iva)': 'comision',
    'impuestos cobrados por retenciones iibb':                'iibb',
    'medio de pago':                                          'medioPago',
    'plataforma de cobro':                                    'plataforma',
    'tipo de medio de pago':                                  'tipoMedio',
};

function detectarColumnas(hdrs) {
    const cols = { fecha:-1, desc:-1, montoBruto:-1, comision:-1, iibb:-1, medioPago:-1, plataforma:-1, tipoMedio:-1 };

    hdrs.forEach((h, i) => {
        // 1) Match exacto por nombre completo
        const exactKey = MP_COLS[h];
        if (exactKey && cols[exactKey] < 0) { cols[exactKey] = i; return; }

        // 2) Regex fallback
        if      (cols.fecha      < 0 && /fecha.*libera/.test(h))   cols.fecha      = i;
        else if (cols.desc       < 0 && /^descripci/.test(h))      cols.desc       = i;
        else if (cols.montoBruto < 0 && /monto.*bruto/.test(h))    cols.montoBruto = i;
        else if (cols.comision   < 0 && /comisi/.test(h))          cols.comision   = i;
        else if (cols.iibb       < 0 && /iibb/.test(h))            cols.iibb       = i;
        else if (cols.medioPago  < 0 && /^medio.*pago$/.test(h))   cols.medioPago  = i;
        else if (cols.plataforma < 0 && /plataforma/.test(h))      cols.plataforma = i;
        else if (cols.tipoMedio  < 0 && /tipo.*medio/.test(h))     cols.tipoMedio  = i;
    });

    // Fallback posicional (Excel MP estándar: A B C D E F G H I J K L M N O)
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
    // Date object: SheetJS crea fechas en UTC, usar getUTC* para evitar desfase de zona horaria
    if (v instanceof Date) {
        if (isNaN(v.getTime())) return '';
        return String(v.getUTCDate()).padStart(2, '0') + '/' +
               String(v.getUTCMonth() + 1).padStart(2, '0') + '/' +
               v.getUTCFullYear();
    }
    const s = String(v).trim();
    if (!s || s === 'Invalid Date') return '';
    // Extraer yyyy-mm-dd del inicio (maneja ISO con timezone: "2026-01-01T00:00:00.000-03:00")
    const mISO = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (mISO) return mISO[3] + '/' + mISO[2] + '/' + mISO[1];
    // dd/mm/yyyy
    const mDMY = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (mDMY) return mDMY[1].padStart(2,'0') + '/' + mDMY[2].padStart(2,'0') + '/' + mDMY[3];
    return s;
}

function toNum(v) {
    if (v === '' || v == null) return 0;
    if (typeof v === 'number') return v;
    let s = String(v).trim().replace(/[$\s ]/g, '');
    if (!s) return 0;
    if (s.includes(',') && s.includes('.')) {
        if (s.lastIndexOf(',') > s.lastIndexOf('.')) {
            s = s.replace(/\./g, '').replace(',', '.'); // argentino 1.234,56
        } else {
            s = s.replace(/,/g, '');                    // US 1,234.56
        }
    } else if (s.includes(',')) {
        s = s.replace(',', '.');
    }
    // solo punto = decimal estandar (12531215.48) no modificar
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
