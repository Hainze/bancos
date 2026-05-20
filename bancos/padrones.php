<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Padrones';
$tab = $_GET['tab'] ?? 'categorias';
require_once __DIR__ . '/includes/header.php';
?>

<div class="tabs" style="width:100%">
    <button class="tab-btn <?= $tab==='categorias'?'active':'' ?>" onclick="switchTab('categorias')">▦ Categorías</button>
    <button class="tab-btn <?= $tab==='palabras'?'active':'' ?>"    onclick="switchTab('palabras')">◉ Palabras Clave</button>
    <button class="tab-btn <?= $tab==='clientes'?'active':'' ?>"    onclick="switchTab('clientes')">◷ Clientes</button>
</div>

<!-- ══════════ CATEGORÍAS ══════════ -->
<div id="tab-categorias" class="tab-content <?= $tab==='categorias'?'active':'' ?>">
    <div class="flex-between mb-16">
        <div></div>
        <button class="btn btn-primary" onclick="openModal('modal-cat')">+ Nueva Categoría</button>
    </div>
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Nombre</th><th>Código</th><th>Tipo</th><th>Palabras clave</th><th></th></tr>
                </thead>
                <tbody id="tbody-cats">
                    <tr><td colspan="5"><div class="empty-state"><div class="spinner"></div></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ══════════ PALABRAS CLAVE ══════════ -->
<div id="tab-palabras" class="tab-content <?= $tab==='palabras'?'active':'' ?>">
    <div class="flex-between mb-16">
        <div class="form-group" style="margin:0;min-width:220px">
            <label class="form-label">Filtrar por categoría</label>
            <select class="form-control" id="sel-cat-palabras" onchange="loadPalabras()">
                <option value="">Todas las categorías</option>
            </select>
        </div>
        <button class="btn btn-primary" onclick="openModal('modal-pal')">+ Nueva Palabra Clave</button>
    </div>
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Palabra / Frase</th><th>Categoría</th><th>Código</th><th></th></tr>
                </thead>
                <tbody id="tbody-palabras">
                    <tr><td colspan="4"><div class="empty-state"><div class="spinner"></div></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ══════════ CLIENTES ══════════ -->
<div id="tab-clientes" class="tab-content <?= $tab==='clientes'?'active':'' ?>">
    <div class="flex-between mb-16">
        <div class="flex-gap gap-8">
            <input type="text" class="form-control" id="search-clientes" placeholder="Buscar por nombre, CUIT, DNI..." style="width:280px" oninput="debounceClientes()">
        </div>
        <div class="flex-gap gap-8">
            <button class="btn btn-secondary" onclick="openModal('modal-import-clientes')">⬆ Importar CSV</button>
            <button class="btn btn-primary" onclick="abrirModalCliente()">+ Nuevo Cliente</button>
        </div>
    </div>
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Nombre / Razón Social</th><th>CUIT</th><th>DNI</th><th>N° Cuenta</th><th></th></tr>
                </thead>
                <tbody id="tbody-clientes">
                    <tr><td colspan="5"><div class="empty-state"><div class="spinner"></div></div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="pagination" id="pag-clientes"></div>
    </div>
</div>

<!-- ══════════ MODALS ══════════ -->

<!-- Modal Categoría -->
<div class="modal-overlay" id="modal-cat">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modal-cat-title">Nueva Categoría</span>
            <button class="modal-close">✕</button>
        </div>
        <input type="hidden" id="cat-id">
        <div class="form-group">
            <label class="form-label">Nombre</label>
            <input type="text" class="form-control" id="cat-nombre" placeholder="Ej: Ingresos Brutos">
        </div>
        <div class="form-group">
            <label class="form-label">Código</label>
            <input type="text" class="form-control" id="cat-codigo" placeholder="Ej: 83">
        </div>
        <div class="form-group">
            <label class="form-label">Aplica a</label>
            <select class="form-control" id="cat-tipo">
                <option value="ambos">Ingresos y Gastos</option>
                <option value="ingreso">Solo Ingresos</option>
                <option value="gasto">Solo Gastos</option>
            </select>
        </div>
        <div class="flex-gap gap-8" style="justify-content:flex-end;margin-top:8px">
            <button class="btn btn-secondary" onclick="closeModal('modal-cat')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarCategoria()">Guardar</button>
        </div>
    </div>
</div>

<!-- Modal Palabra Clave -->
<div class="modal-overlay" id="modal-pal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modal-pal-title">Nueva Palabra Clave</span>
            <button class="modal-close">✕</button>
        </div>
        <input type="hidden" id="pal-id">
        <div class="form-group">
            <label class="form-label">Categoría</label>
            <select class="form-control" id="pal-cat"></select>
        </div>
        <div class="form-group">
            <label class="form-label">Palabra o frase clave</label>
            <input type="text" class="form-control" id="pal-palabra" placeholder="Ej: ingresos brutos">
            <small style="color:var(--text-muted);font-size:11px;margin-top:4px">Se buscará esta frase en la descripción del movimiento (sin distinción de mayúsculas)</small>
        </div>
        <div class="flex-gap gap-8" style="justify-content:flex-end;margin-top:8px">
            <button class="btn btn-secondary" onclick="closeModal('modal-pal')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarPalabra()">Guardar</button>
        </div>
    </div>
</div>

<!-- Modal Cliente -->
<div class="modal-overlay" id="modal-cliente">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modal-cli-title">Nuevo Cliente</span>
            <button class="modal-close">✕</button>
        </div>
        <input type="hidden" id="cli-id">
        <div class="form-group">
            <label class="form-label">Nombre / Razón Social *</label>
            <input type="text" class="form-control" id="cli-nombre" placeholder="Ej: Juan Pérez">
        </div>
        <div class="grid-2">
            <div class="form-group">
                <label class="form-label">CUIT (11 dígitos)</label>
                <input type="text" class="form-control" id="cli-cuit" placeholder="20123456789" maxlength="11">
            </div>
            <div class="form-group">
                <label class="form-label">DNI (7-8 dígitos)</label>
                <input type="text" class="form-control" id="cli-dni" placeholder="12345678" maxlength="8">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Número de Cuenta</label>
            <input type="text" class="form-control" id="cli-cuenta" placeholder="Ej: 640100025786">
        </div>
        <div class="flex-gap gap-8" style="justify-content:flex-end;margin-top:8px">
            <button class="btn btn-secondary" onclick="closeModal('modal-cliente')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarCliente()">Guardar</button>
        </div>
    </div>
</div>

<!-- Modal Import Clientes -->
<div class="modal-overlay" id="modal-import-clientes">
    <div class="modal" style="width:540px">
        <div class="modal-header">
            <span class="modal-title">Importar Clientes desde CSV</span>
            <button class="modal-close">✕</button>
        </div>
        <div class="alert alert-info">
            <span>ℹ</span>
            <div>El CSV debe tener estas columnas: <strong>nombre, cuit, dni, numero_cuenta</strong><br>
            La primera fila es el encabezado. CUIT, DNI y cuenta son opcionales.</div>
        </div>
        <div class="upload-zone" id="upload-csv" style="padding:24px">
            <input type="file" id="csv-input" accept=".csv,.xlsx,.xls">
            <div class="upload-icon" style="font-size:32px">📋</div>
            <div class="upload-title" style="font-size:14px">Seleccionar archivo CSV</div>
        </div>
        <div id="csv-preview" style="display:none;margin-top:12px">
            <div class="alert alert-success"><span>✓</span><span id="csv-preview-msg">—</span></div>
        </div>
        <div class="flex-gap gap-8" style="justify-content:flex-end;margin-top:12px">
            <button class="btn btn-secondary" onclick="closeModal('modal-import-clientes')">Cancelar</button>
            <button class="btn btn-success" id="btn-confirmar-import" disabled onclick="confirmarImportClientes()">Importar</button>
        </div>
    </div>
</div>

<script>
let categoriasCache = [];
let clientesPage = 1;
let csvClientesData = null;
let debounceTimer = null;

function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tab)?.classList.add('active');
    event.target.classList.add('active');
    history.replaceState(null,'',`?tab=${tab}`);
}

// ── CATEGORÍAS ──────────────────────────────────

async function loadCategorias() {
    const res  = await fetch('/api/padrones.php?action=listar_categorias');
    const data = await res.json();
    categoriasCache = data.data || [];

    const tbody = document.getElementById('tbody-cats');
    if (!categoriasCache.length) {
        tbody.innerHTML = `<tr><td colspan="5"><div class="empty-state"><div class="empty-state-icon">▦</div><div class="empty-state-text">No hay categorías. Creá la primera.</div></div></td></tr>`;
        return;
    }

    // Count keywords per category
    const kwRes  = await fetch('/api/padrones.php?action=listar_palabras');
    const kwData = await kwRes.json();
    const kwMap  = {};
    (kwData.data||[]).forEach(p => { kwMap[p.categoria_id] = (kwMap[p.categoria_id]||0)+1; });

    tbody.innerHTML = categoriasCache.map(c => `
        <tr>
            <td><strong>${c.nombre}</strong></td>
            <td class="mono">${c.codigo}</td>
            <td><span class="badge ${c.tipo==='ingreso'?'badge-green':c.tipo==='gasto'?'badge-red':'badge-blue'}">${c.tipo}</span></td>
            <td><span class="badge badge-muted">${kwMap[c.id]||0} palabras</span></td>
            <td>
                <div class="flex-gap gap-8">
                    <button class="btn btn-secondary btn-sm" onclick="editarCategoria(${c.id})">Editar</button>
                    <button class="btn btn-danger btn-sm" onclick="eliminarCategoria(${c.id})">Eliminar</button>
                </div>
            </td>
        </tr>
    `).join('');

    // Populate selects
    const opts = categoriasCache.map(c => `<option value="${c.id}">${c.nombre} (${c.codigo})</option>`).join('');
    document.getElementById('pal-cat').innerHTML = opts;
    document.getElementById('sel-cat-palabras').innerHTML = '<option value="">Todas las categorías</option>' + opts;
}

function editarCategoria(id) {
    const c = categoriasCache.find(x => x.id == id);
    if (!c) return;
    document.getElementById('cat-id').value     = c.id;
    document.getElementById('cat-nombre').value = c.nombre;
    document.getElementById('cat-codigo').value = c.codigo;
    document.getElementById('cat-tipo').value   = c.tipo;
    document.getElementById('modal-cat-title').textContent = 'Editar Categoría';
    openModal('modal-cat');
}

async function guardarCategoria() {
    const body = {
        id:     document.getElementById('cat-id').value,
        nombre: document.getElementById('cat-nombre').value,
        codigo: document.getElementById('cat-codigo').value,
        tipo:   document.getElementById('cat-tipo').value,
    };
    const res  = await fetch('/api/padrones.php?action=guardar_categoria', { method:'POST', body:JSON.stringify(body) });
    const data = await res.json();
    if (data.error) { toast(data.error,'error'); return; }
    toast(data.msg || 'Guardado','success');
    closeModal('modal-cat');
    document.getElementById('cat-id').value = '';
    document.getElementById('cat-nombre').value = '';
    document.getElementById('cat-codigo').value = '';
    document.getElementById('modal-cat-title').textContent = 'Nueva Categoría';
    loadCategorias();
}

async function eliminarCategoria(id) {
    confirmAction('¿Eliminar esta categoría? Se desasociarán los movimientos relacionados.', async () => {
        const res  = await fetch('/api/padrones.php?action=eliminar_categoria', { method:'POST', body:JSON.stringify({id}) });
        const data = await res.json();
        if (data.error) { toast(data.error,'error'); return; }
        toast('Categoría eliminada','success');
        loadCategorias();
    });
}

// ── PALABRAS CLAVE ──────────────────────────────

async function loadPalabras() {
    const catId = document.getElementById('sel-cat-palabras').value;
    const params = new URLSearchParams({ action:'listar_palabras' });
    if (catId) params.set('categoria_id', catId);
    const res  = await fetch('/api/padrones.php?' + params);
    const data = await res.json();
    const rows = data.data || [];

    const tbody = document.getElementById('tbody-palabras');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="4"><div class="empty-state"><div class="empty-state-icon">◉</div><div class="empty-state-text">No hay palabras clave. Agregá la primera.</div></div></td></tr>`;
        return;
    }

    tbody.innerHTML = rows.map(p => `
        <tr>
            <td class="mono">"${p.palabra}"</td>
            <td>${p.cat_nombre}</td>
            <td class="mono">${p.cat_codigo}</td>
            <td>
                <div class="flex-gap gap-8">
                    <button class="btn btn-danger btn-sm" onclick="eliminarPalabra(${p.id})">Eliminar</button>
                </div>
            </td>
        </tr>
    `).join('');
}

async function guardarPalabra() {
    const body = {
        id:          document.getElementById('pal-id').value,
        categoria_id:document.getElementById('pal-cat').value,
        palabra:     document.getElementById('pal-palabra').value,
    };
    const res  = await fetch('/api/padrones.php?action=guardar_palabra', { method:'POST', body:JSON.stringify(body) });
    const data = await res.json();
    if (data.error) { toast(data.error,'error'); return; }
    toast('Palabra guardada','success');
    closeModal('modal-pal');
    document.getElementById('pal-id').value      = '';
    document.getElementById('pal-palabra').value = '';
    loadPalabras();
    loadCategorias(); // refresh keyword counts
}

async function eliminarPalabra(id) {
    confirmAction('¿Eliminar esta palabra clave?', async () => {
        const res = await fetch('/api/padrones.php?action=eliminar_palabra', { method:'POST', body:JSON.stringify({id}) });
        const data = await res.json();
        if (data.error) { toast(data.error,'error'); return; }
        toast('Eliminada','success');
        loadPalabras();
        loadCategorias();
    });
}

// ── CLIENTES ─────────────────────────────────────

function debounceClientes() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadClientes(1), 350);
}

async function loadClientes(page = 1) {
    clientesPage = page;
    const q = document.getElementById('search-clientes').value;
    const params = new URLSearchParams({ action:'listar_clientes', page, q });
    const res  = await fetch('/api/padrones.php?' + params);
    const data = await res.json();

    const tbody = document.getElementById('tbody-clientes');
    if (!data.data.length) {
        tbody.innerHTML = `<tr><td colspan="5"><div class="empty-state"><div class="empty-state-icon">◷</div><div class="empty-state-text">No se encontraron clientes</div></div></td></tr>`;
        document.getElementById('pag-clientes').innerHTML = '';
        return;
    }

    tbody.innerHTML = data.data.map(c => `
        <tr>
            <td><strong>${c.nombre}</strong></td>
            <td class="mono" style="font-size:12px">${c.cuit || '—'}</td>
            <td class="mono" style="font-size:12px">${c.dni || '—'}</td>
            <td class="mono" style="font-size:12px">${c.numero_cuenta || '—'}</td>
            <td>
                <div class="flex-gap gap-8">
                    <button class="btn btn-secondary btn-sm" onclick="editarCliente(${c.id},'${escHtml(c.nombre)}','${c.cuit||''}','${c.dni||''}','${c.numero_cuenta||''}')">Editar</button>
                    <button class="btn btn-danger btn-sm" onclick="eliminarCliente(${c.id})">Eliminar</button>
                </div>
            </td>
        </tr>
    `).join('');

    // Pagination
    const totalPages = Math.ceil(data.total / data.limit);
    let pagHtml = `<span style="font-size:12px;color:var(--text-muted);margin-right:8px">${data.total} registros</span>`;
    if (totalPages > 1) {
        if (page > 1) pagHtml += `<a class="page-btn" onclick="loadClientes(${page-1})">‹</a>`;
        for (let p = Math.max(1,page-2); p <= Math.min(totalPages,page+2); p++) {
            pagHtml += `<a class="page-btn ${p===page?'active':''}" onclick="loadClientes(${p})">${p}</a>`;
        }
        if (page < totalPages) pagHtml += `<a class="page-btn" onclick="loadClientes(${page+1})">›</a>`;
    }
    document.getElementById('pag-clientes').innerHTML = pagHtml;
}

function abrirModalCliente() {
    document.getElementById('cli-id').value     = '';
    document.getElementById('cli-nombre').value = '';
    document.getElementById('cli-cuit').value   = '';
    document.getElementById('cli-dni').value    = '';
    document.getElementById('cli-cuenta').value = '';
    document.getElementById('modal-cli-title').textContent = 'Nuevo Cliente';
    openModal('modal-cliente');
}

function editarCliente(id, nombre, cuit, dni, cuenta) {
    document.getElementById('cli-id').value     = id;
    document.getElementById('cli-nombre').value = nombre;
    document.getElementById('cli-cuit').value   = cuit;
    document.getElementById('cli-dni').value    = dni;
    document.getElementById('cli-cuenta').value = cuenta;
    document.getElementById('modal-cli-title').textContent = 'Editar Cliente';
    openModal('modal-cliente');
}

async function guardarCliente() {
    const body = {
        id:            document.getElementById('cli-id').value,
        nombre:        document.getElementById('cli-nombre').value,
        cuit:          document.getElementById('cli-cuit').value,
        dni:           document.getElementById('cli-dni').value,
        numero_cuenta: document.getElementById('cli-cuenta').value,
    };
    const res  = await fetch('/api/padrones.php?action=guardar_cliente', { method:'POST', body:JSON.stringify(body) });
    const data = await res.json();
    if (data.error) { toast(data.error,'error'); return; }
    toast(data.msg || 'Cliente guardado','success');
    closeModal('modal-cliente');
    loadClientes(clientesPage);
}

async function eliminarCliente(id) {
    confirmAction('¿Eliminar este cliente?', async () => {
        const res = await fetch('/api/padrones.php?action=eliminar_cliente', { method:'POST', body:JSON.stringify({id}) });
        const data = await res.json();
        if (data.error) { toast(data.error,'error'); return; }
        toast('Cliente eliminado','success');
        loadClientes(clientesPage);
    });
}

// CSV Import
initDropZone('upload-csv', async file => {
    csvClientesData = await parseCsvClientes(file);
    document.getElementById('csv-preview').style.display = 'block';
    document.getElementById('csv-preview-msg').textContent = `${csvClientesData.length} clientes listos para importar`;
    document.getElementById('btn-confirmar-import').disabled = false;
});

async function parseCsvClientes(file) {
    return new Promise(resolve => {
        const reader = new FileReader();
        reader.onload = e => {
            const lines = e.target.result.split('\n');
            const headers = lines[0].split(',').map(h => h.trim().toLowerCase().replace(/"/g,''));
            const rows = [];
            for (let i=1;i<lines.length;i++) {
                const cols = lines[i].split(',').map(c => c.trim().replace(/"/g,''));
                if (!cols[0]) continue;
                const obj = {};
                headers.forEach((h,idx) => obj[h] = cols[idx] || '');
                rows.push(obj);
            }
            resolve(rows);
        };
        reader.readAsText(file, 'UTF-8');
    });
}

async function confirmarImportClientes() {
    if (!csvClientesData) return;
    const res  = await fetch('/api/padrones.php?action=importar_clientes', {
        method: 'POST',
        body: JSON.stringify({ clientes: csvClientesData })
    });
    const data = await res.json();
    if (data.error) { toast(data.error,'error'); return; }
    toast(`✓ ${data.insertados} clientes importados`,'success');
    closeModal('modal-import-clientes');
    loadClientes(1);
    csvClientesData = null;
    document.getElementById('csv-preview').style.display = 'none';
    document.getElementById('btn-confirmar-import').disabled = true;
}

function escHtml(s) { return (s||'').replace(/'/g,"\\'"); }

// ── INIT ────────────────────────────────────────

loadCategorias().then(() => {
    loadPalabras();
    loadClientes();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

