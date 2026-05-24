<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Seleccionar Cliente';
require_once __DIR__ . '/includes/header.php';
?>

<div class="grid-2" style="gap:24px">

    <!-- ── IZQUIERDA: Seleccionar cliente ── -->
    <div>
        <div class="card">
            <div class="card-title">¿Con qué cliente vas a trabajar?</div>

            <div class="alert alert-info" style="margin-bottom:20px">
                <span>ℹ</span>
                <div>Cada cliente tiene su propio registro de compras, ventas e informes. Seleccioná un cliente antes de ingresar comprobantes.</div>
            </div>

            <div id="selector-lista">
                <div class="empty-state"><div class="spinner" style="margin:0 auto"></div></div>
            </div>
        </div>
    </div>

    <!-- ── DERECHA: Accesos rápidos ── -->
    <div>
        <div class="card">
            <div class="card-title">Accesos rápidos</div>
            <div style="display:flex;flex-direction:column;gap:12px">

                <a href="/facturacion/clientes.php" class="btn btn-secondary" style="justify-content:flex-start;gap:12px;padding:14px 16px">
                    <span style="font-size:18px">👥</span>
                    <div style="text-align:left">
                        <div style="font-weight:600;font-size:14px">Gestión de Clientes</div>
                        <div style="font-size:12px;color:var(--text-secondary)">Agregar, editar o eliminar clientes</div>
                    </div>
                </a>

                <a href="/facturacion/compras.php" class="btn btn-secondary" id="link-compras"
                   style="justify-content:flex-start;gap:12px;padding:14px 16px;<?= empty($_SESSION['fact_cliente_id']) ? 'opacity:0.4;pointer-events:none' : '' ?>">
                    <span style="font-size:18px">⬇</span>
                    <div style="text-align:left">
                        <div style="font-weight:600;font-size:14px">Compras</div>
                        <div style="font-size:12px;color:var(--text-secondary)">Registrar facturas de compra, NC, ND, recibos</div>
                    </div>
                </a>

                <a href="/facturacion/ventas.php" class="btn btn-secondary" id="link-ventas"
                   style="justify-content:flex-start;gap:12px;padding:14px 16px;<?= empty($_SESSION['fact_cliente_id']) ? 'opacity:0.4;pointer-events:none' : '' ?>">
                    <span style="font-size:18px">⬆</span>
                    <div style="text-align:left">
                        <div style="font-weight:600;font-size:14px">Ventas</div>
                        <div style="font-size:12px;color:var(--text-secondary)">Registrar facturas de venta, NC, ND, recibos</div>
                    </div>
                </a>

                <a href="/facturacion/informes.php" class="btn btn-secondary" id="link-informes"
                   style="justify-content:flex-start;gap:12px;padding:14px 16px;<?= empty($_SESSION['fact_cliente_id']) ? 'opacity:0.4;pointer-events:none' : '' ?>">
                    <span style="font-size:18px">📊</span>
                    <div style="text-align:left">
                        <div style="font-weight:600;font-size:14px">Informes</div>
                        <div style="font-size:12px;color:var(--text-secondary)">Informe mensual e informe por rango</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
const currentClienteId = <?= (int)($_SESSION['fact_cliente_id'] ?? 0) ?>;

async function cargarClientes() {
    const res  = await fetch('/api/facturacion.php?action=clientes_listar');
    const data = await res.json();
    const lista = data.data || [];
    const cont = document.getElementById('selector-lista');

    if (lista.length === 0) {
        cont.innerHTML = `<div class="empty-state">
            <div class="empty-state-icon">👥</div>
            <div class="empty-state-text">No hay clientes registrados</div>
            <a href="/facturacion/clientes.php" class="btn btn-primary" style="margin-top:16px">+ Agregar primer cliente</a>
        </div>`;
        return;
    }

    cont.innerHTML = `<div style="display:flex;flex-direction:column;gap:8px">` +
        lista.map(c => `
        <div class="cliente-row" style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;
             background:${c.id == currentClienteId ? 'rgba(16,185,129,0.1)' : 'var(--bg-hover)'};
             border:1px solid ${c.id == currentClienteId ? 'rgba(16,185,129,0.3)' : 'var(--border)'};
             border-radius:8px;cursor:pointer;transition:all 0.15s" data-id="${c.id}" data-nombre="${escHtml(c.nombre)}">
            <div>
                <div style="font-weight:600;font-size:14px">${escHtml(c.nombre)}</div>
                <div style="font-size:12px;color:var(--text-secondary)">CUIT: ${escHtml(c.cuit || '—')}</div>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
                ${c.id == currentClienteId ? '<span style="color:var(--green);font-size:12px;font-weight:600">● Activo</span>' : ''}
                <button class="btn btn-primary btn-sm btn-seleccionar" data-id="${c.id}" data-nombre="${escHtml(c.nombre)}">
                    ${c.id == currentClienteId ? 'Seleccionado' : 'Seleccionar →'}
                </button>
            </div>
        </div>`).join('') + `</div>`;

    document.querySelectorAll('.btn-seleccionar').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            await seleccionarCliente(parseInt(btn.dataset.id), btn.dataset.nombre);
        });
    });
    document.querySelectorAll('.cliente-row').forEach(row => {
        row.addEventListener('click', async () => {
            await seleccionarCliente(parseInt(row.dataset.id), row.dataset.nombre);
        });
    });
}

async function seleccionarCliente(id, nombre) {
    const res  = await fetch('/api/facturacion.php?action=set_cliente', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id})
    });
    const data = await res.json();
    if (data.error) { toast(data.error, 'error'); return; }
    toast(`✓ Cliente seleccionado: ${nombre}`, 'success');
    // Enable quick-access links
    ['link-compras','link-ventas','link-informes'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.style.opacity = '1'; el.style.pointerEvents = ''; }
    });
    setTimeout(() => { window.location.href = '/facturacion/compras.php'; }, 800);
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

cargarClientes();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
