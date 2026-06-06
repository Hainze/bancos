<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo      = 'Cuentas y Credenciales';
$clienteId   = (int)($_SESSION['fact_cliente_id']     ?? 0);
$clienteNombre = $_SESSION['fact_cliente_nombre'] ?? '';
require_once __DIR__ . '/includes/header.php';
?>

<?php if (!$clienteId): ?>
<div style="background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);border-radius:10px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px">
    <span style="font-size:20px">⚠</span>
    <div>
        <strong style="color:#f59e0b">Sin cliente seleccionado</strong>
        <div style="font-size:13px;color:var(--sub);margin-top:2px">
            <a href="/facturacion/index.php" style="color:var(--accent-light)">Seleccioná un cliente</a> para gestionar sus cuentas.
        </div>
    </div>
</div>
<?php else: ?>

<div class="flex-between mb-16">
    <div>
        <p style="color:var(--sub);font-size:13px;margin:0">
            Cuentas y servicios de <strong><?= htmlspecialchars($clienteNombre) ?></strong> — credenciales de acceso, URLs y datos de pago.
            Las contraseñas se guardan cifradas.
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <select class="form-control" id="filtro-tipo" style="width:auto" onchange="cargarCuentas()">
            <option value="">Todos los tipos</option>
            <option value="fiscal">Solo Fiscal</option>
            <option value="servicio">Solo Servicios</option>
        </select>
        <button class="btn btn-primary" onclick="abrirModal()">+ Nueva cuenta</button>
    </div>
</div>

<!-- Tabla de cuentas -->
<div class="card">
    <div class="table-wrap">
        <table style="font-size:13px">
            <thead>
                <tr>
                    <th style="width:70px">Tipo</th>
                    <th>Categoría / Nombre</th>
                    <th>Descripción</th>
                    <th style="width:120px">Sitio web</th>
                    <th>Usuario</th>
                    <th>Contraseña</th>
                    <th style="width:130px"></th>
                </tr>
            </thead>
            <tbody id="tbody-cuentas">
                <tr><td colspan="7"><div class="empty-state"><div class="spinner"></div></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Modal: Nueva/editar cuenta ── -->
<div class="modal-overlay" id="modal-cuenta">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <span class="modal-title" id="modal-titulo">Nueva cuenta</span>
            <button class="modal-close">✕</button>
        </div>
        <input type="hidden" id="cuenta-id">

        <div class="grid-2">
            <div class="form-group">
                <label class="form-label">Tipo *</label>
                <select class="form-control" id="cuenta-tipo" onchange="actualizarCategorias()">
                    <option value="fiscal">📊 Fiscal</option>
                    <option value="servicio">⚡ Servicio</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Categoría *</label>
                <select class="form-control" id="cuenta-categoria"></select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control" id="cuenta-nombre" placeholder="Ej: IVA Mensual · Luz Depto 3A · EDESUR Comercial">
        </div>

        <div class="form-group">
            <label class="form-label">Descripción</label>
            <input type="text" class="form-control" id="cuenta-desc" placeholder="Datos adicionales (Nro. de cuenta, CUIT, dirección del inmueble…)">
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label class="form-label">Sitio web / URL</label>
                <input type="url" class="form-control" id="cuenta-url" placeholder="https://…">
            </div>
            <div class="form-group">
                <label class="form-label">Usuario / Email</label>
                <input type="text" class="form-control" id="cuenta-usuario" placeholder="usuario@ejemplo.com">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Contraseña
                <span style="font-size:11px;color:var(--muted);margin-left:8px">Dejar vacío para no cambiarla</span>
            </label>
            <div style="position:relative">
                <input type="password" class="form-control" id="cuenta-clave" placeholder="Contraseña de acceso" style="padding-right:44px">
                <button type="button" onclick="togglePwd('cuenta-clave',this)"
                    style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:14px">👁</button>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Notas</label>
            <textarea class="form-control" id="cuenta-notas" rows="2" placeholder="Vence el 10 de cada mes · Pagar por homebanking · Nro. cliente 12345…" style="resize:vertical"></textarea>
        </div>

        <div style="display:flex;gap:10px;justify-content:space-between;margin-top:8px;align-items:center">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                <input type="checkbox" id="cuenta-activo" checked style="width:16px;height:16px">
                Cuenta activa
            </label>
            <div style="display:flex;gap:8px">
                <button class="btn btn-secondary" onclick="closeModal('modal-cuenta')">Cancelar</button>
                <button class="btn btn-primary" onclick="guardarCuenta()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Generar vencimientos periódicos ── -->
<div class="modal-overlay" id="modal-generar">
    <div class="modal" style="max-width:520px">
        <div class="modal-header">
            <span class="modal-title">Generar vencimientos mensuales</span>
            <button class="modal-close">✕</button>
        </div>
        <input type="hidden" id="gen-cuenta-id">
        <p id="gen-cuenta-nombre" style="color:var(--accent-light);font-weight:600;margin-bottom:16px"></p>

        <div class="grid-2">
            <div class="form-group">
                <label class="form-label">Año</label>
                <select class="form-control" id="gen-anio">
                    <?php for ($y = (int)date('Y') - 1; $y <= (int)date('Y') + 2; $y++): ?>
                    <option value="<?= $y ?>" <?= $y == (int)date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Día del mes</label>
                <input type="number" class="form-control" id="gen-dia" value="10" min="1" max="28">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Importe por período (opcional)</label>
            <input type="number" class="form-control" id="gen-importe" placeholder="0.00" step="0.01" min="0">
        </div>

        <div class="form-group">
            <label class="form-label">Meses a generar</label>
            <div id="gen-meses" style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-top:4px">
                <?php $meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                foreach ($meses as $i => $m): $n = $i + 1; ?>
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;padding:4px 0">
                    <input type="checkbox" class="mes-check" value="<?= $n ?>" checked style="width:14px;height:14px">
                    <?= $m ?>
                </label>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:8px;margin-top:8px">
                <button class="btn btn-secondary btn-sm" onclick="toggleMeses(true)">Todos</button>
                <button class="btn btn-secondary btn-sm" onclick="toggleMeses(false)">Ninguno</button>
            </div>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
            <button class="btn btn-secondary" onclick="closeModal('modal-generar')">Cancelar</button>
            <button class="btn btn-primary" onclick="confirmarGenerar()">⚡ Generar</button>
        </div>
    </div>
</div>

<style>
.cat-badge { display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;letter-spacing:.5px;font-family:'Space Mono',monospace; }
.cat-fiscal   { background:rgba(139,92,246,.15);color:#a78bfa; }
.cat-servicio { background:rgba(16,185,129,.15); color:#10b981; }
.clave-wrap   { display:flex;align-items:center;gap:6px; }
.clave-dots   { font-size:14px;letter-spacing:2px;color:var(--muted); }
</style>

<script>
const CATEGORIAS = {
    fiscal: [
        'IVA','Ganancias','Bienes Personales','IIBB',
        'Monotributo','Autónomos','FAECYS','INACAP',
        'Sueldos / Cargas sociales','Otro fiscal'
    ],
    servicio: [
        'Luz','Gas','Agua','Teléfono','Internet',
        'Expensas','ABL','Patente','Seguro',
        'Alquiler','Otro servicio'
    ]
};

function actualizarCategorias(val) {
    const tipo = document.getElementById('cuenta-tipo').value;
    const sel  = document.getElementById('cuenta-categoria');
    const prev = val || sel.value;
    sel.innerHTML = CATEGORIAS[tipo].map(c =>
        `<option value="${c}" ${c === prev ? 'selected' : ''}>${c}</option>`
    ).join('');
}
actualizarCategorias();

// ── Cargar tabla ──────────────────────────────────────────
async function cargarCuentas() {
    const tipo = document.getElementById('filtro-tipo').value;
    const params = new URLSearchParams({ action:'listar' });
    if (tipo) params.set('tipo', tipo);
    const res  = await fetch('/vencimientos/api/cuentas.php?' + params);
    const data = await res.json();
    const rows = data.data || [];

    const tbody = document.getElementById('tbody-cuentas');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state">
            <div class="empty-state-icon">🔑</div>
            <div class="empty-state-text">Sin cuentas registradas. Creá la primera.</div>
        </div></td></tr>`;
        return;
    }

    tbody.innerHTML = rows.map(r => {
        const tipoBadge = `<span class="cat-badge cat-${r.tipo}">${r.tipo === 'fiscal' ? 'Fiscal' : 'Serv.'}</span>`;
        const urlHtml   = r.url_web
            ? `<a href="${escHtml(r.url_web)}" target="_blank" style="color:var(--accent-light);font-size:12px" title="${escHtml(r.url_web)}">🔗 Abrir</a>`
            : `<span style="color:var(--muted)">—</span>`;
        const userHtml  = r.usuario
            ? `<span class="mono" style="font-size:11px">${escHtml(r.usuario)}</span>`
            : `<span style="color:var(--muted)">—</span>`;
        const claveHtml = `<div class="clave-wrap">
            <span class="clave-dots" id="clave-txt-${r.id}">••••••••</span>
            <button onclick="toggleVerClave(${r.id},this)" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:13px;padding:2px 4px" title="Mostrar contraseña">👁</button>
        </div>`;
        const noActivo = !parseInt(r.activo) ? '<span style="font-size:10px;color:var(--muted);margin-left:4px">(inactiva)</span>' : '';

        return `<tr>
            <td>${tipoBadge}</td>
            <td>
                <div style="font-weight:600">${escHtml(r.nombre)}${noActivo}</div>
                <div style="font-size:11px;color:var(--muted)">${escHtml(r.categoria)}</div>
            </td>
            <td style="font-size:12px;color:var(--sub);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escHtml(r.descripcion || '—')}</td>
            <td>${urlHtml}</td>
            <td>${userHtml}</td>
            <td>${claveHtml}</td>
            <td style="white-space:nowrap">
                <button class="btn btn-secondary btn-sm" style="font-size:10px" onclick="editarCuenta(${r.id})">✏ Editar</button>
                <button class="btn btn-primary btn-sm" style="font-size:10px;margin-left:3px" onclick="abrirGenerar(${r.id},'${escAttr(r.nombre)}')">⚡ Generar</button>
                <button class="btn btn-danger btn-sm" style="font-size:10px;margin-left:3px" onclick="eliminarCuenta(${r.id})">×</button>
            </td>
        </tr>`;
    }).join('');
}

// ── Ver/ocultar contraseña ─────────────────────────────────
let claveVisible = {};
async function toggleVerClave(id, btn) {
    const el = document.getElementById('clave-txt-' + id);
    if (claveVisible[id]) {
        el.textContent = '••••••••';
        btn.textContent = '👁';
        claveVisible[id] = false;
        return;
    }
    btn.textContent = '…';
    const res  = await fetch('/vencimientos/api/cuentas.php?action=get_clave', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id})
    });
    const data = await res.json();
    if (data.error) { toast(data.error,'error'); btn.textContent='👁'; return; }
    el.textContent   = data.clave || '(sin contraseña)';
    el.style.fontFamily = 'Space Mono, monospace';
    btn.textContent  = '🙈';
    claveVisible[id] = true;
}

function togglePwd(inputId, btn) {
    const inp = document.getElementById(inputId);
    if (inp.type === 'password') { inp.type = 'text';     btn.textContent = '🙈'; }
    else                         { inp.type = 'password'; btn.textContent = '👁'; }
}

// ── Modal: Abrir nueva cuenta ─────────────────────────────
function abrirModal() {
    document.getElementById('cuenta-id').value    = '';
    document.getElementById('modal-titulo').textContent = 'Nueva cuenta';
    document.getElementById('cuenta-tipo').value  = 'servicio';
    actualizarCategorias();
    document.getElementById('cuenta-nombre').value  = '';
    document.getElementById('cuenta-desc').value    = '';
    document.getElementById('cuenta-url').value     = '';
    document.getElementById('cuenta-usuario').value = '';
    document.getElementById('cuenta-clave').value   = '';
    document.getElementById('cuenta-notas').value   = '';
    document.getElementById('cuenta-activo').checked = true;
    openModal('modal-cuenta');
}

// ── Modal: Editar cuenta ──────────────────────────────────
let cuentasCache = {};
async function editarCuenta(id) {
    // Fetch si no está en caché
    if (!cuentasCache[id]) {
        const res  = await fetch(`/vencimientos/api/cuentas.php?action=listar`);
        const data = await res.json();
        (data.data||[]).forEach(c => { cuentasCache[c.id] = c; });
    }
    const r = cuentasCache[id];
    if (!r) return;

    document.getElementById('cuenta-id').value          = r.id;
    document.getElementById('modal-titulo').textContent  = 'Editar cuenta';
    document.getElementById('cuenta-tipo').value         = r.tipo;
    actualizarCategorias(r.categoria);
    document.getElementById('cuenta-nombre').value       = r.nombre;
    document.getElementById('cuenta-desc').value         = r.descripcion || '';
    document.getElementById('cuenta-url').value          = r.url_web || '';
    document.getElementById('cuenta-usuario').value      = r.usuario || '';
    document.getElementById('cuenta-clave').value        = '';
    document.getElementById('cuenta-notas').value        = r.notas || '';
    document.getElementById('cuenta-activo').checked     = parseInt(r.activo) === 1;
    openModal('modal-cuenta');
}

// ── Guardar cuenta ────────────────────────────────────────
async function guardarCuenta() {
    const body = {
        id:          document.getElementById('cuenta-id').value || null,
        tipo:        document.getElementById('cuenta-tipo').value,
        categoria:   document.getElementById('cuenta-categoria').value,
        nombre:      document.getElementById('cuenta-nombre').value.trim(),
        descripcion: document.getElementById('cuenta-desc').value.trim(),
        url_web:     document.getElementById('cuenta-url').value.trim(),
        usuario:     document.getElementById('cuenta-usuario').value.trim(),
        clave:       document.getElementById('cuenta-clave').value,
        notas:       document.getElementById('cuenta-notas').value.trim(),
        activo:      document.getElementById('cuenta-activo').checked ? 1 : 0,
    };
    if (!body.nombre || !body.categoria) { toast('Nombre y categoría son requeridos', 'warning'); return; }

    const res  = await fetch('/vencimientos/api/cuentas.php?action=guardar', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)
    });
    const data = await res.json();
    if (data.error) { toast(data.error, 'error'); return; }
    cuentasCache = {};
    toast('Guardado', 'success');
    closeModal('modal-cuenta');
    cargarCuentas();
}

async function eliminarCuenta(id) {
    confirmAction('¿Eliminar esta cuenta? Se eliminarán también todos sus vencimientos.', async () => {
        const res  = await fetch('/vencimientos/api/cuentas.php?action=eliminar', {
            method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id})
        });
        const data = await res.json();
        if (data.error) { toast(data.error, 'error'); return; }
        cuentasCache = {};
        toast('Eliminado', 'success');
        cargarCuentas();
    });
}

// ── Generar periódico ─────────────────────────────────────
function abrirGenerar(id, nombre) {
    document.getElementById('gen-cuenta-id').value       = id;
    document.getElementById('gen-cuenta-nombre').textContent = nombre;
    document.getElementById('gen-importe').value         = '';
    document.querySelectorAll('.mes-check').forEach(c => c.checked = true);
    openModal('modal-generar');
}

function toggleMeses(val) {
    document.querySelectorAll('.mes-check').forEach(c => c.checked = val);
}

async function confirmarGenerar() {
    const meses = [...document.querySelectorAll('.mes-check:checked')].map(c => parseInt(c.value));
    if (!meses.length) { toast('Seleccioná al menos un mes', 'warning'); return; }

    const body = {
        cuenta_id: parseInt(document.getElementById('gen-cuenta-id').value),
        anio:      parseInt(document.getElementById('gen-anio').value),
        dia:       parseInt(document.getElementById('gen-dia').value) || 10,
        importe:   parseFloat(document.getElementById('gen-importe').value || 0),
        meses,
    };

    const res  = await fetch('/vencimientos/api/vencimientos.php?action=generar', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)
    });
    const data = await res.json();
    if (data.error) { toast(data.error, 'error'); return; }
    toast(`✓ ${data.creados} vencimientos generados`, 'success');
    closeModal('modal-generar');
}

function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escAttr(s) { return String(s||'').replace(/'/g,"\\'").replace(/"/g,'&quot;'); }

cargarCuentas();
</script>

<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
