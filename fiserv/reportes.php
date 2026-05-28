<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Reportes Fiserv';
require_once __DIR__ . '/includes/header.php';

$lote_id_filtro = isset($_GET['lote']) ? (int)$_GET['lote'] : 0;
?>

<!-- Lista de lotes -->
<div class="card mb-24" id="seccion-lotes">
    <div class="flex-between mb-16">
        <div class="card-title" style="margin:0">PDFs importados</div>
        <a href="/fiserv/procesar.php" class="btn btn-success btn-sm">⬆ Subir nuevo PDF</a>
    </div>
    <div class="table-wrap">
        <table id="tabla-lotes">
            <thead>
                <tr>
                    <th>Código</th><th>Archivo</th><th>Tarjeta</th><th>Período</th>
                    <th>Nro Comercio</th><th>Presentado</th><th>Neto Pagos</th>
                    <th>Liq.</th><th>Importado</th><th></th>
                </tr>
            </thead>
            <tbody id="lotes-tbody">
                <tr><td colspan="10"><div class="empty-state"><div class="spinner" style="margin:0 auto"></div></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Detalle de un lote -->
<div class="card" id="seccion-detalle" style="display:none">
    <div class="flex-between mb-16">
        <div>
            <div class="card-title" style="margin:0" id="detalle-titulo">Detalle</div>
            <div style="font-size:13px;color:var(--sub);margin-top:4px" id="detalle-sub"></div>
        </div>
        <div style="display:flex;gap:8px">
            <button class="btn btn-success btn-sm" id="btn-excel">⬇ Descargar Excel</button>
            <button class="btn btn-secondary btn-sm" onclick="cerrarDetalle()">✕ Cerrar</button>
        </div>
    </div>
    <div class="table-wrap" style="overflow-x:auto">
        <table id="tabla-detalle" style="font-size:12px;min-width:1600px">
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
                    <th style="color:#ef4444">IVA RI DTO 10,5%</th>
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
            <tbody id="detalle-tbody"></tbody>
            <tfoot id="detalle-tfoot"></tfoot>
        </table>
    </div>
</div>

<!-- Modal eliminar -->
<div class="modal-overlay" id="modal-eliminar">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--red)">⚠ Eliminar lote</div>
            <button class="modal-close" onclick="closeModal('modal-eliminar')">✕</button>
        </div>
        <p style="color:var(--sub);font-size:14px;margin-bottom:20px">
            Esto va a eliminar el lote y todas sus liquidaciones.<br>
            <strong style="color:var(--text)">Esta acción no se puede deshacer.</strong>
        </p>
        <input type="hidden" id="del-lote-id">
        <div style="display:flex;gap:10px;justify-content:flex-end">
            <button class="btn btn-secondary" onclick="closeModal('modal-eliminar')">Cancelar</button>
            <button class="btn btn-danger" id="btn-confirmar-del">Sí, eliminar</button>
        </div>
    </div>
</div>

<script>
const colFields = [
    'ventas_contado','arancel','iva_arancel','arancel_cuotas','iva_arancel_cuotas',
    'promo_cuota_ahora','dto_financ_cuotas','iva_ri_dto_financ','dto_ventas_fin_adq',
    'per_bai_brdn','ret_iibb_sirtac','iva_promo_cuota','iva_dto_fin_adq',
    'perc_iva_1_5','perc_iva_3','cargo_terminal','cargo_sist_cuotas','iva_ri_sist_cuotas',
    'qr_perc_iva','qr_ret_iibb','total_descuentos','acreditado'
];
const colHeaders = [
    'VENTAS C/DESCUENTO CONTADO','ARANCEL','IVA CRED FISC COMERCIO S/ARANC 21%',
    'ARANCEL CUOTAS','IVA ARANCEL CUOTAS 21%','PROMO CUOTA AHORA SIMPLE',
    'DESCUENTO FINANC OTORG. CUOTAS','IVA RI CRED FISC COMERCIO S/DTO F.OTORG 10,5%',
    'DTO S/VENTAS FIN ADQ CONT','PER B.A.I. BRDN 01/04','RETENCION ING. BRUTOS BSAS SIRTAC',
    'IVA PROMO CUOTA AHORA SIMPLE 21%','IVA S/DTO FIN ADQ CONT 21%',
    'PERCEPCION IVA R.G. 2408 1,50%','PERCEPCION IVA R.G. 2408 3%',
    'CARGO TERMINAL FISERV','CARGO SISTEMA CUOTAS MENS','IVA RI SIST CUOTAS',
    'QR PERCEPCION IVA','QR RETENCION IIBB BS.AS.','TOTAL','ACREDITADO'
];

let currentLote = null;
let currentRows = [];

function fmt(v) {
    const n = parseFloat(v || 0);
    if (n === 0) return '—';
    return '$ ' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function fmtDate(d) {
    if (!d) return '—';
    const [y, m, dia] = d.split('-');
    return `${dia}/${m}/${y}`;
}

// Cargar lista de lotes
async function cargarLotes() {
    const res  = await fetch('/fiserv/api/stats.php?action=lotes');
    const data = await res.json();
    const tbody = document.getElementById('lotes-tbody');
    if (!data.lotes?.length) {
        tbody.innerHTML = `<tr><td colspan="10"><div class="empty-state">
            <div class="empty-state-icon">◈</div>
            <div class="empty-state-text">No hay PDFs importados — <a href="/fiserv/procesar.php" style="color:var(--accent-light)">Subir el primero</a></div>
        </div></td></tr>`;
        return;
    }
    tbody.innerHTML = data.lotes.map(l => `<tr>
        <td class="mono" style="font-size:11px">${l.codigo}</td>
        <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${l.archivo_nombre||''}">${l.archivo_nombre||'—'}</td>
        <td><span class="badge badge-muted">${l.tarjeta||'—'}</span></td>
        <td>${l.periodo||'—'}</td>
        <td class="mono">${l.nro_comercio||'—'}</td>
        <td class="mono">${fmt(l.total_presentado)}</td>
        <td class="mono green">${fmt(l.neto_pagos)}</td>
        <td class="mono">${l.total_filas}</td>
        <td class="mono" style="font-size:11px">${new Date(l.created_at).toLocaleDateString('es-AR')}</td>
        <td>
            <div style="display:flex;gap:6px">
                <button class="btn btn-secondary btn-sm" onclick="verDetalle(${l.id})">Ver</button>
                <button class="btn btn-danger btn-sm" onclick="confirmarEliminar(${l.id})">✕</button>
            </div>
        </td>
    </tr>`).join('');

    // Auto-abrir si viene ?lote=X
    <?php if ($lote_id_filtro): ?>
    verDetalle(<?= $lote_id_filtro ?>);
    <?php endif; ?>
}

async function verDetalle(lote_id) {
    document.getElementById('seccion-detalle').style.display = 'block';
    document.getElementById('detalle-tbody').innerHTML = `<tr><td colspan="25"><div class="empty-state"><div class="spinner" style="margin:0 auto"></div></div></td></tr>`;
    document.getElementById('seccion-detalle').scrollIntoView({ behavior:'smooth', block:'start' });

    const res  = await fetch('/fiserv/api/stats.php?action=detalle_lote&lote_id=' + lote_id);
    const data = await res.json();
    if (data.error) { toast(data.error, 'error'); return; }

    currentLote = data.lote;
    currentRows = data.rows;

    document.getElementById('detalle-titulo').textContent = `${currentLote.archivo_nombre}`;
    document.getElementById('detalle-sub').textContent    = `${currentLote.tarjeta||''} · ${currentLote.periodo||''} · ${currentLote.total_filas} liquidaciones`;

    const totals = {};
    colFields.forEach(c => totals[c] = 0);

    document.getElementById('detalle-tbody').innerHTML = data.rows.map(r => {
        colFields.forEach(c => totals[c] += parseFloat(r[c] || 0));
        return `<tr>
            <td class="mono" style="font-size:11px">${r.nro_liq||'—'}</td>
            <td class="mono">${fmtDate(r.fecha_pago)}</td>
            <td class="mono">${fmtDate(r.fecha_pres)}</td>
            ${colFields.map(c => {
                const v = parseFloat(r[c] || 0);
                const color = v > 0 ? (c==='ventas_contado'||c==='acreditado' ? 'var(--green)' : 'var(--red)') : 'var(--muted)';
                return `<td class="mono" style="text-align:right;color:${color}">${v > 0 ? fmt(v) : '—'}</td>`;
            }).join('')}
        </tr>`;
    }).join('');

    document.getElementById('detalle-tfoot').innerHTML = `<tr style="background:var(--surface);font-weight:700">
        <td colspan="3" style="font-size:12px;color:var(--sub)">TOTALES</td>
        ${colFields.map(c => {
            const v = totals[c];
            const color = c==='ventas_contado'||c==='acreditado' ? 'var(--green)' : c==='total_descuentos' ? 'var(--amber)' : 'var(--red)';
            return `<td class="mono" style="text-align:right;font-size:12px;color:${color}">${v>0?fmt(v):'—'}</td>`;
        }).join('')}
    </tr>`;
}

function cerrarDetalle() {
    document.getElementById('seccion-detalle').style.display = 'none';
    currentLote = null; currentRows = [];
}

function confirmarEliminar(lote_id) {
    document.getElementById('del-lote-id').value = lote_id;
    openModal('modal-eliminar');
}

document.getElementById('btn-confirmar-del').addEventListener('click', async () => {
    const lote_id = document.getElementById('del-lote-id').value;
    const btn = document.getElementById('btn-confirmar-del');
    btn.disabled = true; btn.textContent = 'Eliminando…';
    try {
        const fd = new FormData();
        fd.append('lote_id', lote_id);
        const res  = await fetch('/fiserv/api/stats.php?action=eliminar_lote', { method:'POST', body:fd });
        const data = await res.json();
        closeModal('modal-eliminar');
        if (data.success) { toast('Lote eliminado', 'success'); cargarLotes(); cerrarDetalle(); }
        else toast(data.error || 'Error', 'error');
    } catch(e) { toast('Error: ' + e.message, 'error'); }
    finally { btn.disabled = false; btn.textContent = 'Sí, eliminar'; }
});

// Excel export
document.getElementById('btn-excel').addEventListener('click', () => {
    if (!currentLote || !currentRows.length) return;
    exportarExcel(currentLote, currentRows);
});

function exportarExcel(lote, rows) {
    const allHeaders = ['Nro. Liq.','Fecha Pago','Fecha Pres.', ...colHeaders];
    const allFields  = ['nro_liq','fecha_pago','fecha_pres', ...colFields];

    const data = [allHeaders];
    const totals = {};
    colFields.forEach(c => totals[c] = 0);

    rows.forEach(r => {
        data.push(allFields.map(f => {
            if (f === 'nro_liq') return r[f] || '';
            if (f === 'fecha_pago' || f === 'fecha_pres') return fmtDate(r[f]);
            const n = parseFloat(r[f] || 0);
            if (colFields.includes(f)) totals[f] += n;
            return n;
        }));
    });

    // Fila totales
    data.push(allFields.map((f, i) => {
        if (f === 'nro_liq') return 'TOTALES';
        if (f === 'fecha_pago' || f === 'fecha_pres') return '';
        return colFields.includes(f) ? totals[f] : '';
    }));

    const ws = XLSX.utils.aoa_to_sheet(data);
    ws['!cols'] = allHeaders.map((h, i) => ({ wch: i < 3 ? 14 : Math.max(h.length, 12) }));
    const wb = XLSX.utils.book_new();
    const sheetName = ((lote.periodo || 'Fiserv') + ' ' + (lote.tarjeta || '')).trim().replace(/\s+/g,'_').substring(0,31);
    XLSX.utils.book_append_sheet(wb, ws, sheetName);
    const fname = `Fiserv_${sheetName}.xlsx`;
    XLSX.writeFile(wb, fname);
}

cargarLotes();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
