<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo      = 'Dashboard Vencimientos';
$clienteId   = (int)($_SESSION['fact_cliente_id']     ?? 0);
$clienteNombre = $_SESSION['fact_cliente_nombre'] ?? '';
require_once __DIR__ . '/includes/header.php';
?>

<script>
const CLIENTE_ID = <?= $clienteId ?>;
</script>

<?php if (!$clienteId): ?>
<div style="background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);border-radius:10px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px">
    <span style="font-size:20px">⚠</span>
    <div>
        <strong style="color:#f59e0b">Sin cliente seleccionado</strong>
        <div style="font-size:13px;color:var(--sub);margin-top:2px">
            <a href="/facturacion/index.php" style="color:var(--accent-light)">Seleccioná un cliente</a> para ver y gestionar sus vencimientos.
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
    <div class="stat-card red">
        <div class="stat-label">Vencidos</div>
        <div class="stat-value red" id="stat-vencidos">—</div>
        <div class="stat-sub">sin pagar</div>
    </div>
    <div class="stat-card amber">
        <div class="stat-label">Esta semana</div>
        <div class="stat-value amber" id="stat-semana">—</div>
        <div class="stat-sub">próximos 7 días</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Próximos 30 días</div>
        <div class="stat-value" style="color:var(--accent-light)" id="stat-mes">—</div>
        <div class="stat-sub">pendientes</div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Pagados este mes</div>
        <div class="stat-value green" id="stat-pagados">—</div>
        <div class="stat-sub">este mes</div>
    </div>
</div>

<!-- Tabla principal -->
<div class="card">
    <div class="flex-between mb-16">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <div class="card-title" style="margin:0">Vencimientos</div>
            <div class="section-filters" style="display:flex;gap:6px">
                <button class="filter-pill active" data-vista="pendientes" onclick="cambiarVista('pendientes',this)">Pendientes</button>
                <button class="filter-pill" data-vista="pagados" onclick="cambiarVista('pagados',this)">Pagados</button>
                <button class="filter-pill" data-vista="todos" onclick="cambiarVista('todos',this)">Todos</button>
            </div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="openModal('modal-agregar')">+ Agregar</button>
    </div>

    <div class="table-wrap">
        <table style="font-size:13px">
            <thead>
                <tr>
                    <th style="width:90px">Plazo</th>
                    <th style="width:100px">Fecha</th>
                    <th style="width:70px">Tipo</th>
                    <th>Cuenta / Servicio</th>
                    <th>Período</th>
                    <th style="text-align:right">Importe</th>
                    <th style="width:110px">Estado</th>
                    <th style="width:110px"></th>
                </tr>
            </thead>
            <tbody id="tbody-venc">
                <tr><td colspan="8"><div class="empty-state"><div class="spinner"></div></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Modal: Agregar vencimiento ── -->
<div class="modal-overlay" id="modal-agregar">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modal-agregar-titulo">Nuevo vencimiento</span>
            <button class="modal-close">✕</button>
        </div>
        <input type="hidden" id="venc-id">

        <div class="form-group">
            <label class="form-label">Cuenta / Servicio *</label>
            <select class="form-control" id="venc-cuenta">
                <option value="">— Seleccioná una cuenta —</option>
            </select>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label class="form-label">Período / Descripción</label>
                <input type="text" class="form-control" id="venc-desc" placeholder="Ej: Mayo 2026">
            </div>
            <div class="form-group">
                <label class="form-label">Fecha de vencimiento *</label>
                <input type="date" class="form-control" id="venc-fecha">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Importe</label>
            <input type="number" class="form-control" id="venc-importe" placeholder="0.00" step="0.01" min="0">
        </div>

        <div class="form-group">
            <label class="form-label">Notas</label>
            <input type="text" class="form-control" id="venc-notas" placeholder="Observaciones opcionales">
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
            <button class="btn btn-secondary" onclick="closeModal('modal-agregar')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarVencimiento()">Guardar</button>
        </div>
    </div>
</div>

<!-- ── Modal: Marcar como pagado ── -->
<div class="modal-overlay" id="modal-pagar">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <span class="modal-title">Registrar pago</span>
            <button class="modal-close">✕</button>
        </div>
        <input type="hidden" id="pagar-id">
        <p id="pagar-desc" style="color:var(--sub);font-size:13px;margin-bottom:16px"></p>
        <div class="grid-2">
            <div class="form-group">
                <label class="form-label">Fecha de pago</label>
                <input type="date" class="form-control" id="pagar-fecha">
            </div>
            <div class="form-group">
                <label class="form-label">N° Comprobante</label>
                <input type="text" class="form-control" id="pagar-comp" placeholder="Opcional">
            </div>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
            <button class="btn btn-secondary" onclick="closeModal('modal-pagar')">Cancelar</button>
            <button class="btn btn-success" onclick="confirmarPago()">✓ Confirmar pago</button>
        </div>
    </div>
</div>

<style>
.filter-pill {
    padding:5px 14px;border-radius:20px;border:1px solid var(--border);
    background:var(--surface);color:var(--sub);font-size:12px;cursor:pointer;transition:all .15s;
}
.filter-pill.active, .filter-pill:hover {
    background:var(--accent);border-color:var(--accent);color:#fff;
}
.plazo-badge {
    display:inline-block;padding:3px 8px;border-radius:12px;font-size:11px;
    font-family:'Space Mono',monospace;font-weight:700;white-space:nowrap;
}
.plazo-vencido  { background:rgba(239,68,68,.15);  color:#ef4444; }
.plazo-hoy      { background:rgba(245,158,11,.2);  color:#f59e0b; animation:pulse 1.5s infinite; }
.plazo-semana   { background:rgba(249,115,22,.15); color:#f97316; }
.plazo-mes      { background:rgba(37,99,235,.15);  color:var(--accent-light); }
.plazo-futuro   { background:rgba(74,95,122,.15);  color:#4a5f7a; }
.plazo-pagado   { background:rgba(16,185,129,.15); color:#10b981; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.6} }
.estado-pendiente { color:var(--amber); }
.estado-vencido   { color:var(--red); font-weight:700; }
.estado-pagado    { color:var(--green); }
.cat-badge {
    display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;
    font-weight:700;letter-spacing:.5px;font-family:'Space Mono',monospace;
}
.cat-fiscal   { background:rgba(139,92,246,.15);color:#a78bfa; }
.cat-servicio { background:rgba(16,185,129,.15); color:#10b981; }
</style>

<script>
let vistaActual = 'pendientes';
let todasCuentas = [];

// ── Cargar stats ──────────────────────────────────────────
async function cargarStats() {
    if (!CLIENTE_ID) return;
    try {
        const res  = await fetch('/vencimientos/api/vencimientos.php?action=stats');
        const data = await res.json();
        document.getElementById('stat-vencidos').textContent = data.vencidos ?? 0;
        document.getElementById('stat-semana').textContent   = data.semana   ?? 0;
        document.getElementById('stat-mes').textContent      = data.mes      ?? 0;
        document.getElementById('stat-pagados').textContent  = data.pagados_mes ?? 0;
    } catch(e) {}
}

// ── Cargar cuentas para el select ─────────────────────────
async function cargarCuentas() {
    if (!CLIENTE_ID) return;
    try {
        const res  = await fetch('/vencimientos/api/cuentas.php?action=listar');
        const data = await res.json();
        todasCuentas = data.data || [];
        const sel = document.getElementById('venc-cuenta');
        sel.innerHTML = '<option value="">— Seleccioná una cuenta —</option>';
        const grupos = { fiscal: [], servicio: [] };
        todasCuentas.forEach(c => { if (grupos[c.tipo]) grupos[c.tipo].push(c); });

        ['fiscal','servicio'].forEach(tipo => {
            if (!grupos[tipo].length) return;
            const og = document.createElement('optgroup');
            og.label = tipo === 'fiscal' ? '📊 Fiscal' : '⚡ Servicios';
            grupos[tipo].forEach(c => {
                const o = document.createElement('option');
                o.value = c.id;
                o.textContent = c.nombre + (c.descripcion ? ` — ${c.descripcion}` : '');
                og.appendChild(o);
            });
            sel.appendChild(og);
        });

        if (!todasCuentas.length) {
            sel.innerHTML = '<option value="">No hay cuentas — <a href="/vencimientos/cuentas.php">Crear una</a></option>';
        }
    } catch(e) {}
}

// ── Cargar tabla ──────────────────────────────────────────
async function cargarTabla() {
    if (!CLIENTE_ID) {
        document.getElementById('tbody-venc').innerHTML = `<tr><td colspan="8">
            <div class="empty-state"><div class="empty-state-icon">📅</div>
            <div class="empty-state-text">Seleccioná un cliente para ver sus vencimientos</div></div></td></tr>`;
        return;
    }

    const tbody = document.getElementById('tbody-venc');
    tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state"><div class="spinner"></div></div></td></tr>`;

    const estado = vistaActual === 'pendientes' ? 'pendiente' : vistaActual === 'pagados' ? 'pagado' : '';
    const params = new URLSearchParams({ action:'listar', estado });
    if (vistaActual === 'pendientes') params.set('dias', 90);

    try {
        const res  = await fetch('/vencimientos/api/vencimientos.php?' + params);
        const data = await res.json();
        const rows = data.data || [];

        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state">
                <div class="empty-state-icon">✓</div>
                <div class="empty-state-text">${vistaActual === 'pagados' ? 'Sin pagos registrados' : 'Sin vencimientos pendientes'}</div>
            </div></td></tr>`;
            return;
        }

        tbody.innerHTML = rows.map(r => renderRow(r)).join('');
    } catch(e) {
        toast('Error al cargar vencimientos', 'error');
    }
}

function renderRow(r) {
    const hoy   = new Date(); hoy.setHours(0,0,0,0);
    const fVenc = new Date(r.fecha_venc + 'T00:00:00');
    const diff  = Math.round((fVenc - hoy) / 86400000);
    const pagado = r.estado === 'pagado';

    let plazoBadge, estadoHtml;
    if (pagado) {
        plazoBadge = `<span class="plazo-badge plazo-pagado">✓ Pagado</span>`;
        estadoHtml = `<span class="estado-pagado">✓ ${r.fecha_pago ? new Date(r.fecha_pago+'T00:00:00').toLocaleDateString('es-AR') : 'Pagado'}</span>`;
    } else if (diff < 0) {
        plazoBadge = `<span class="plazo-badge plazo-vencido">${Math.abs(diff)}d vencido</span>`;
        estadoHtml = `<span class="estado-vencido">⚠ Vencido</span>`;
    } else if (diff === 0) {
        plazoBadge = `<span class="plazo-badge plazo-hoy">¡HOY!</span>`;
        estadoHtml = `<span class="estado-vencido">¡Hoy!</span>`;
    } else if (diff <= 7) {
        plazoBadge = `<span class="plazo-badge plazo-semana">${diff}d</span>`;
        estadoHtml = `<span class="estado-pendiente">Esta semana</span>`;
    } else if (diff <= 30) {
        plazoBadge = `<span class="plazo-badge plazo-mes">${diff}d</span>`;
        estadoHtml = `<span class="estado-pendiente">Pendiente</span>`;
    } else {
        plazoBadge = `<span class="plazo-badge plazo-futuro">${diff}d</span>`;
        estadoHtml = `<span style="color:var(--muted)">Pendiente</span>`;
    }

    const imp = parseFloat(r.importe || 0);
    const impHtml = imp > 0
        ? `<strong>$ ${imp.toLocaleString('es-AR',{minimumFractionDigits:2,maximumFractionDigits:2})}</strong>`
        : `<span style="color:var(--muted)">—</span>`;

    const tipoHtml = `<span class="cat-badge cat-${r.tipo}">${r.tipo === 'fiscal' ? 'Fiscal' : 'Serv.'}</span>`;

    const cuentaHtml = r.url_web
        ? `<a href="${escHtml(r.url_web)}" target="_blank" style="color:var(--accent-light);text-decoration:none" title="Abrir sitio web">🔗 ${escHtml(r.cuenta_nombre)}</a>`
        : escHtml(r.cuenta_nombre);

    const acciones = pagado
        ? `<button class="btn btn-secondary btn-sm" style="font-size:10px" onclick="desmarcar(${r.id})">↩ Deshacer</button>
           <button class="btn btn-danger btn-sm" style="font-size:10px;margin-left:4px" onclick="eliminar(${r.id})">×</button>`
        : `<button class="btn btn-success btn-sm" style="font-size:10px" onclick="abrirPagar(${r.id},'${escHtml(r.cuenta_nombre)} — ${escHtml(r.descripcion || '')}')">✓ Pagar</button>
           <button class="btn btn-secondary btn-sm" style="font-size:10px;margin-left:4px" onclick="editarVenc(${r.id})">✏</button>
           <button class="btn btn-danger btn-sm" style="font-size:10px;margin-left:4px" onclick="eliminar(${r.id})">×</button>`;

    return `<tr data-id="${r.id}">
        <td>${plazoBadge}</td>
        <td class="mono" style="font-size:11px">${new Date(r.fecha_venc+'T00:00:00').toLocaleDateString('es-AR')}</td>
        <td>${tipoHtml}<br><span style="font-size:10px;color:var(--muted)">${escHtml(r.categoria)}</span></td>
        <td>${cuentaHtml}</td>
        <td style="font-size:12px;color:var(--sub)">${escHtml(r.descripcion || '—')}</td>
        <td class="mono" style="text-align:right">${impHtml}</td>
        <td>${estadoHtml}</td>
        <td style="white-space:nowrap">${acciones}</td>
    </tr>`;
}

// ── Cambiar vista ─────────────────────────────────────────
function cambiarVista(vista, btn) {
    vistaActual = vista;
    document.querySelectorAll('[data-vista]').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    cargarTabla();
}

// ── Guardar vencimiento ───────────────────────────────────
async function guardarVencimiento() {
    const body = {
        id:          document.getElementById('venc-id').value || null,
        cuenta_id:   document.getElementById('venc-cuenta').value,
        descripcion: document.getElementById('venc-desc').value.trim(),
        fecha_venc:  document.getElementById('venc-fecha').value,
        importe:     parseFloat(document.getElementById('venc-importe').value || 0),
        notas:       document.getElementById('venc-notas').value.trim(),
    };
    if (!body.cuenta_id || !body.fecha_venc) { toast('Seleccioná cuenta y fecha', 'warning'); return; }

    const res  = await fetch('/vencimientos/api/vencimientos.php?action=guardar', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)
    });
    const data = await res.json();
    if (data.error) { toast(data.error, 'error'); return; }
    toast('Vencimiento guardado', 'success');
    closeModal('modal-agregar');
    cargarTabla();
    cargarStats();
}

// ── Editar ────────────────────────────────────────────────
function editarVenc(id) {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (!row) return;
    // Popula el modal con datos existentes desde el DOM (simplificado)
    document.getElementById('venc-id').value = id;
    document.getElementById('modal-agregar-titulo').textContent = 'Editar vencimiento';
    openModal('modal-agregar');
}

// ── Pagar ─────────────────────────────────────────────────
function abrirPagar(id, desc) {
    document.getElementById('pagar-id').value   = id;
    document.getElementById('pagar-desc').textContent = desc;
    document.getElementById('pagar-fecha').value = new Date().toISOString().split('T')[0];
    document.getElementById('pagar-comp').value  = '';
    openModal('modal-pagar');
}

async function confirmarPago() {
    const body = {
        id:         parseInt(document.getElementById('pagar-id').value),
        fecha_pago: document.getElementById('pagar-fecha').value,
        nro_comp:   document.getElementById('pagar-comp').value.trim(),
    };
    const res  = await fetch('/vencimientos/api/vencimientos.php?action=pagar', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)
    });
    const data = await res.json();
    if (data.error) { toast(data.error, 'error'); return; }
    toast('¡Pago registrado!', 'success');
    closeModal('modal-pagar');
    cargarTabla();
    cargarStats();
}

async function desmarcar(id) {
    const res  = await fetch('/vencimientos/api/vencimientos.php?action=desmarcar', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id})
    });
    const data = await res.json();
    if (data.error) { toast(data.error, 'error'); return; }
    toast('Pago deshecho', 'success');
    cargarTabla(); cargarStats();
}

async function eliminar(id) {
    confirmAction('¿Eliminar este vencimiento?', async () => {
        const res  = await fetch('/vencimientos/api/vencimientos.php?action=eliminar', {
            method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id})
        });
        const data = await res.json();
        if (data.error) { toast(data.error, 'error'); return; }
        toast('Eliminado', 'success');
        cargarTabla(); cargarStats();
    });
}

// ── Modal agregar: limpiar al abrir ──────────────────────
document.getElementById('modal-agregar').addEventListener('click', function(e) {
    if (e.target === this) return;
});
document.getElementById('modal-agregar').querySelector('.modal-close').addEventListener('click', () => {
    document.getElementById('venc-id').value = '';
    document.getElementById('modal-agregar-titulo').textContent = 'Nuevo vencimiento';
});

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Init ──────────────────────────────────────────────────
cargarStats();
cargarCuentas();
cargarTabla();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
