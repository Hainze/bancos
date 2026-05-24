<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Gestión de Clientes';
require_once __DIR__ . '/includes/header.php';
?>

<div class="grid-2" style="gap:24px">

    <!-- ── IZQUIERDA: Form ── -->
    <div>
        <div class="card">
            <div class="card-title" id="form-titulo">Nuevo Cliente</div>

            <div style="display:flex;flex-direction:column;gap:16px">
                <input type="hidden" id="cliente-id" value="0">

                <div>
                    <label style="display:block;font-size:13px;color:var(--text-secondary);margin-bottom:6px">Nombre / Razón Social *</label>
                    <input type="text" id="inp-nombre" class="form-input" placeholder="Nombre del cliente o empresa" style="width:100%">
                </div>

                <div>
                    <label style="display:block;font-size:13px;color:var(--text-secondary);margin-bottom:6px">CUIT</label>
                    <input type="text" id="inp-cuit" class="form-input" placeholder="20-12345678-9" style="width:100%">
                </div>

                <div style="display:flex;gap:10px;margin-top:8px">
                    <button class="btn btn-primary" id="btn-guardar">Guardar cliente</button>
                    <button class="btn btn-secondary" id="btn-cancelar" style="display:none">Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── DERECHA: Lista ── -->
    <div>
        <div class="card">
            <div class="flex-between mb-16">
                <div class="card-title" style="margin:0">Clientes registrados</div>
                <button class="btn btn-secondary btn-sm" id="btn-refresh">↺ Actualizar</button>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>CUIT</th>
                            <th>Desde</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="clientes-body">
                        <tr><td colspan="4"><div class="empty-state"><div class="spinner" style="margin:0 auto"></div></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal eliminar -->
<div class="modal-overlay" id="modal-eliminar">
    <div class="modal" style="max-width:400px">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--red)">⚠ Eliminar cliente</div>
            <button class="modal-close">✕</button>
        </div>
        <p style="color:var(--text-secondary);font-size:14px;margin-bottom:24px">
            Esto eliminará el cliente y <strong style="color:var(--red)">todos sus comprobantes de compra y venta</strong>. No se puede deshacer.
        </p>
        <div style="display:flex;gap:10px;justify-content:flex-end">
            <button class="btn btn-secondary" onclick="closeModal('modal-eliminar')">Cancelar</button>
            <button class="btn btn-danger" id="btn-confirmar-eliminar">Eliminar</button>
        </div>
    </div>
</div>

<script>
let clienteAEliminar = null;

async function cargarClientes() {
    const res  = await fetch('/api/facturacion.php?action=clientes_listar');
    const data = await res.json();
    renderClientes(data.data || []);
}

function renderClientes(lista) {
    const tbody = document.getElementById('clientes-body');
    if (!lista.length) {
        tbody.innerHTML = `<tr><td colspan="4"><div class="empty-state">
            <div class="empty-state-icon">👥</div>
            <div class="empty-state-text">No hay clientes registrados</div>
        </div></td></tr>`;
        return;
    }
    tbody.innerHTML = lista.map(c => `<tr>
        <td style="font-weight:500">${escHtml(c.nombre)}</td>
        <td class="mono" style="font-size:12px">${escHtml(c.cuit || '—')}</td>
        <td class="mono" style="font-size:11px">${new Date(c.created_at).toLocaleDateString('es-AR')}</td>
        <td style="display:flex;gap:6px">
            <button class="btn btn-secondary btn-sm" onclick="editarCliente(${c.id},'${escJs(c.nombre)}','${escJs(c.cuit)}')">✎ Editar</button>
            <button class="btn btn-danger btn-sm" onclick="pedirEliminar(${c.id})">✕</button>
        </td>
    </tr>`).join('');
}

function editarCliente(id, nombre, cuit) {
    document.getElementById('cliente-id').value = id;
    document.getElementById('inp-nombre').value = nombre;
    document.getElementById('inp-cuit').value   = cuit;
    document.getElementById('form-titulo').textContent = 'Editar Cliente';
    document.getElementById('btn-cancelar').style.display = 'inline-flex';
    document.getElementById('inp-nombre').focus();
}

function pedirEliminar(id) {
    clienteAEliminar = id;
    openModal('modal-eliminar');
}

function resetForm() {
    document.getElementById('cliente-id').value = 0;
    document.getElementById('inp-nombre').value = '';
    document.getElementById('inp-cuit').value   = '';
    document.getElementById('form-titulo').textContent = 'Nuevo Cliente';
    document.getElementById('btn-cancelar').style.display = 'none';
}

document.getElementById('btn-guardar').addEventListener('click', async () => {
    const nombre = document.getElementById('inp-nombre').value.trim();
    const cuit   = document.getElementById('inp-cuit').value.trim();
    const id     = parseInt(document.getElementById('cliente-id').value);
    if (!nombre) { toast('El nombre es requerido', 'error'); return; }

    const res  = await fetch('/api/facturacion.php?action=clientes_guardar', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id, nombre, cuit})
    });
    const data = await res.json();
    if (data.error) { toast(data.error, 'error'); return; }
    toast(id > 0 ? '✓ Cliente actualizado' : '✓ Cliente creado', 'success');
    resetForm();
    cargarClientes();
});

document.getElementById('btn-cancelar').addEventListener('click', resetForm);
document.getElementById('btn-refresh').addEventListener('click', cargarClientes);

document.getElementById('btn-confirmar-eliminar').addEventListener('click', async () => {
    if (!clienteAEliminar) return;
    const btn = document.getElementById('btn-confirmar-eliminar');
    btn.disabled = true; btn.textContent = 'Eliminando...';
    const res  = await fetch('/api/facturacion.php?action=clientes_eliminar', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id: clienteAEliminar})
    });
    const data = await res.json();
    closeModal('modal-eliminar');
    if (data.success) { toast('Cliente eliminado', 'success'); cargarClientes(); }
    else toast(data.error || 'Error', 'error');
    btn.disabled = false; btn.textContent = 'Eliminar';
    clienteAEliminar = null;
});

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escJs(s)   { return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }

cargarClientes();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
