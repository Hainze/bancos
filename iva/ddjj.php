<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Generar DDJJ IVA';
require_once __DIR__ . '/includes/header.php';
?>

<div class="grid-2" style="gap:24px">

    <!-- ── COLUMNA IZQUIERDA: upload + resultado ── -->
    <div>
        <div class="card mb-24">
            <div class="card-title">Subir Excel Procesado</div>

            <div class="alert alert-info">
                <span>ℹ</span>
                <div>
                    Subí el Excel que descargaste del sistema, completaste y guardaste en Excel/LibreOffice.<br>
                    El sistema genera los dos archivos TXT listos para cargar en <strong>Portal IVA → DDJJ</strong>:
                    <ul style="margin:8px 0 0 16px;line-height:1.8">
                        <li><strong>*_IVAVentasDigital_Alic.txt</strong> — detalle de alícuotas</li>
                        <li><strong>*_IVAVentasDigital_Cbte.txt</strong> — detalle de comprobantes</li>
                    </ul>
                </div>
            </div>

            <div class="upload-zone" id="upload-zone">
                <input type="file" id="file-input" accept=".xlsx">
                <div class="upload-icon">📄</div>
                <div class="upload-title">Arrastrar el Excel procesado aquí</div>
                <div class="upload-sub">o hacer click para seleccionar — solo .xlsx</div>
            </div>

            <div id="file-selected" style="display:none;margin-top:12px" class="alert alert-success">
                <span>✓</span>
                <span id="file-name">—</span>
            </div>

            <div style="margin-top:16px;display:flex;gap:10px;align-items:center">
                <button class="btn btn-primary btn-lg" id="btn-generar" disabled>
                    <span id="btn-txt">⚙ Generar TXT</span>
                    <span id="btn-spinner" class="spinner" style="display:none"></span>
                </button>
                <button class="btn btn-secondary" id="btn-limpiar" style="display:none">✕ Limpiar</button>
            </div>
        </div>

        <!-- Resultado -->
        <div class="card" id="resultado-card" style="display:none">
            <div class="card-title">Archivos generados</div>

            <div class="stats-grid" style="grid-template-columns:1fr 1fr 1fr;margin-bottom:20px">
                <div class="stat-card green">
                    <div class="stat-label">Período</div>
                    <div class="stat-value" style="font-size:18px" id="res-periodo">—</div>
                </div>
                <div class="stat-card blue">
                    <div class="stat-label">Líneas Alic.txt</div>
                    <div class="stat-value" id="res-alic">—</div>
                </div>
                <div class="stat-card amber">
                    <div class="stat-label">Líneas Cbte.txt</div>
                    <div class="stat-value" id="res-cbte">—</div>
                </div>
            </div>

            <div style="display:flex;gap:12px;flex-wrap:wrap">
                <button class="btn btn-success btn-lg" id="btn-dl-alic">
                    ⬇ Descargar Alic.txt
                </button>
                <button class="btn btn-success btn-lg" id="btn-dl-cbte">
                    ⬇ Descargar Cbte.txt
                </button>
                <button class="btn btn-primary btn-lg" id="btn-dl-zip">
                    ⬇ Descargar ZIP (ambos)
                </button>
            </div>

            <div style="margin-top:16px;padding:12px 14px;background:rgba(16,185,129,0.07);border:1px solid rgba(16,185,129,0.2);border-radius:8px;font-size:13px;color:var(--sub)">
                Los archivos están listos para cargar en <strong style="color:var(--text)">ARCA → Portal IVA → DDJJ de IVA</strong>.
                Renombralos con el nombre de tu empresa y el período antes de cargarlos.
            </div>
        </div>
    </div>

    <!-- ── COLUMNA DERECHA: instrucciones ── -->
    <div>
        <div class="card">
            <div class="card-title">¿Cómo usar esta sección?</div>

            <div style="display:flex;flex-direction:column;gap:16px">

                <div style="display:flex;gap:14px;align-items:flex-start">
                    <div style="min-width:32px;height:32px;border-radius:50%;background:var(--purple,#8b5cf6);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px">1</div>
                    <div>
                        <div style="font-weight:600;font-size:14px;margin-bottom:4px">Procesá el Excel de ARCA</div>
                        <div style="font-size:13px;color:var(--sub)">En la sección <a href="/iva/index.php" style="color:var(--purple,#8b5cf6)">Procesar ARCA</a> subís el Libro de Compras y descargás el Excel procesado.</div>
                    </div>
                </div>

                <div style="display:flex;gap:14px;align-items:flex-start">
                    <div style="min-width:32px;height:32px;border-radius:50%;background:var(--purple,#8b5cf6);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px">2</div>
                    <div>
                        <div style="font-weight:600;font-size:14px;margin-bottom:4px">Revisá y completá el Excel</div>
                        <div style="font-size:13px;color:var(--sub)">Abrís el Excel en Excel/LibreOffice, completás las columnas <strong>Perc IIBB</strong>, <strong>Perc IVA</strong> e <strong>Imp Int</strong> donde corresponda, y corregís cualquier diferencia. Guardás el archivo.</div>
                    </div>
                </div>

                <div style="display:flex;gap:14px;align-items:flex-start">
                    <div style="min-width:32px;height:32px;border-radius:50%;background:var(--purple,#8b5cf6);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px">3</div>
                    <div>
                        <div style="font-weight:600;font-size:14px;margin-bottom:4px">Subí el Excel corregido aquí</div>
                        <div style="font-size:13px;color:var(--sub)">Subís el mismo Excel (guardado desde Excel/LibreOffice) en esta sección.</div>
                    </div>
                </div>

                <div style="display:flex;gap:14px;align-items:flex-start">
                    <div style="min-width:32px;height:32px;border-radius:50%;background:var(--purple,#8b5cf6);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px">4</div>
                    <div>
                        <div style="font-weight:600;font-size:14px;margin-bottom:4px">Descargá los TXT y cargalos en ARCA</div>
                        <div style="font-size:13px;color:var(--sub)">El sistema genera los dos archivos TXT. Los descargás y los cargás en <strong>Portal IVA → Mi DDJJ de IVA</strong>.</div>
                    </div>
                </div>

            </div>

            <div style="margin-top:20px;padding:12px 14px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.25);border-radius:8px;font-size:13px">
                <strong style="color:#f59e0b">⚠ Importante:</strong>
                <span style="color:var(--sub)"> El Excel debe estar abierto y guardado en Excel o LibreOffice Calc para que las fórmulas estén calculadas correctamente. Si subís el Excel tal como lo descargaste del sistema sin abrirlo, puede haber errores en los valores.</span>
            </div>
        </div>

        <!-- Formato esperado -->
        <div class="card mt-24">
            <div class="card-title">Formato de los TXT generados</div>
            <div style="font-size:12px;color:var(--sub);line-height:1.8">
                <div style="margin-bottom:10px"><strong style="color:var(--text)">Alic.txt</strong> — 62 caracteres por línea:</div>
                <div class="mono" style="background:var(--surface2,#1e1e2e);padding:10px;border-radius:6px;font-size:11px;overflow-x:auto;white-space:nowrap">
                    tipo(3) + pto_vta(5) + nro(20) + neto×100(15) + cod_alic(4) + iva×100(15)
                </div>
                <div style="margin-top:12px;margin-bottom:10px"><strong style="color:var(--text)">Cbte.txt</strong> — 266 caracteres por línea:</div>
                <div class="mono" style="background:var(--surface2,#1e1e2e);padding:10px;border-radius:6px;font-size:11px;overflow-x:auto;white-space:nowrap">
                    fecha(8) + tipo(3) + pto(5) + nroDesde(20) + nroHasta(20) + tipoDoc(2) + cuit(20) + razon(30) + total(15) + noGrav(15) + exento(15) + percIVA(15) + percNac(15) + percIIBB(15) + percMun(15) + impInt(15) + moneda(3) + tc(10) + cantAlic(1) + codOp(1) + credFisc(15) + fechaVto(8)
                </div>
                <div style="margin-top:10px">
                    Códigos alícuota: <span class="badge badge-blue">0003</span>=0% &nbsp;
                    <span class="badge badge-amber">0004</span>=10,5% &nbsp;
                    <span class="badge badge-blue">0005</span>=21% &nbsp;
                    <span class="badge badge-muted">0006</span>=27%
                </div>
            </div>
        </div>
    </div>

</div>

<script>
let currentFile = null;
let alicB64 = null;
let cbteB64 = null;
let nombreBase = 'IVA';

initDropZone('upload-zone', file => setFile(file));

function setFile(file) {
    currentFile = file;
    document.getElementById('file-name').textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
    document.getElementById('file-selected').style.display = 'flex';
    document.getElementById('btn-generar').disabled = false;
    document.getElementById('btn-limpiar').style.display = 'inline-flex';
    document.getElementById('resultado-card').style.display = 'none';
    alicB64 = null; cbteB64 = null;
}

document.getElementById('btn-limpiar').addEventListener('click', () => {
    currentFile = null; alicB64 = null; cbteB64 = null;
    document.getElementById('file-input').value = '';
    document.getElementById('file-selected').style.display = 'none';
    document.getElementById('btn-generar').disabled = true;
    document.getElementById('btn-limpiar').style.display = 'none';
    document.getElementById('resultado-card').style.display = 'none';
});

document.getElementById('btn-generar').addEventListener('click', async () => {
    if (!currentFile) return;
    const btn = document.getElementById('btn-generar');
    btn.disabled = true;
    document.getElementById('btn-txt').style.display = 'none';
    document.getElementById('btn-spinner').style.display = 'inline-block';

    const form = new FormData();
    form.append('excel', currentFile);

    try {
        const res  = await fetch('/api/libro_iva_ddjj.php?action=generar', { method: 'POST', body: form });
        const data = await res.json();

        if (data.error) { toast(data.error, 'error'); return; }

        alicB64    = data.alic_b64;
        cbteB64    = data.cbte_b64;
        nombreBase = data.nombre_base || 'IVA';

        document.getElementById('res-periodo').textContent = data.periodo || '—';
        document.getElementById('res-alic').textContent    = data.alic_lineas;
        document.getElementById('res-cbte').textContent    = data.cbte_lineas;
        document.getElementById('resultado-card').style.display = 'block';

        toast(`✓ Archivos generados: ${data.alic_lineas} líneas Alic, ${data.cbte_lineas} líneas Cbte`, 'success');

    } catch(e) {
        toast('Error: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        document.getElementById('btn-txt').style.display = 'inline';
        document.getElementById('btn-spinner').style.display = 'none';
    }
});

document.getElementById('btn-dl-alic').addEventListener('click', () => {
    if (alicB64) downloadTxt(alicB64, nombreBase + '_IVAVentasDigital_Alic.txt');
});

document.getElementById('btn-dl-cbte').addEventListener('click', () => {
    if (cbteB64) downloadTxt(cbteB64, nombreBase + '_IVAVentasDigital_Cbte.txt');
});

document.getElementById('btn-dl-zip').addEventListener('click', () => {
    if (!alicB64 || !cbteB64) return;
    // Descargar ambos archivos secuencialmente
    downloadTxt(alicB64, nombreBase + '_IVAVentasDigital_Alic.txt');
    setTimeout(() => downloadTxt(cbteB64, nombreBase + '_IVAVentasDigital_Cbte.txt'), 300);
});

function downloadTxt(b64, filename) {
    const bytes = atob(b64);
    const arr   = new Uint8Array(bytes.length);
    for (let i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i);
    const blob  = new Blob([arr], { type: 'text/plain' });
    const url   = URL.createObjectURL(blob);
    const a     = document.createElement('a');
    a.href      = url;
    a.download  = filename;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 500);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
