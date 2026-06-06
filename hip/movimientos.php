<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Movimientos';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card mb-16">
    <div class="flex-between" style="flex-wrap:wrap;gap:12px">
        <div class="flex-gap" style="flex-wrap:wrap;gap:10px">
            <div class="form-group" style="margin:0"><label class="form-label">Desde</label><input type="date" class="form-control" id="filtro-desde" value="<?= date('Y-m-01') ?>"></div>
            <div class="form-group" style="margin:0"><label class="form-label">Hasta</label><input type="date" class="form-control" id="filtro-hasta" value="<?= date('Y-m-d') ?>"></div>
            <div class="form-group" style="margin:0"><label class="form-label">Tipo</label><select class="form-control" id="filtro-tipo"><option value="">Todos</option><option value="ingreso">Ingresos</option><option value="gasto">Gastos</option></select></div>
            <div class="form-group" style="margin:0"><label class="form-label">Categoría</label><select class="form-control" id="filtro-cat"><option value="">Todas</option></select></div>
        </div>
        <div class="flex-gap gap-8" style="align-self:flex-end">
            <button class="btn btn-primary" id="btn-filtrar">Filtrar</button>
            <button class="btn btn-success" id="btn-exportar">⬇ Exportar Excel</button>
        </div>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px">
    <div class="stat-card green"><div class="stat-label">Total Ingresos</div><div class="stat-value green" id="mov-total-ing">—</div></div>
    <div class="stat-card red"><div class="stat-label">Total Gastos</div><div class="stat-value red" id="mov-total-gasto">—</div></div>
    <div class="stat-card blue"><div class="stat-label">Registros encontrados</div><div class="stat-value" id="mov-total-count">—</div></div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Fecha</th><th>Descripción</th><th>Importe</th><th>Tipo</th><th>Categoría</th><th>Código</th><th>CUIT</th><th>DNI</th><th>N° Cuenta</th><th>Nombre</th></tr></thead>
            <tbody id="mov-tbody"><tr><td colspan="10"><div class="empty-state"><div class="spinner"></div></div></td></tr></tbody>
        </table>
    </div>
    <div class="pagination" id="paginacion"></div>
</div>

<script>
let currentPage=1,currentFilters={};
async function loadCategorias(){const res=await fetch('/hip/api/padrones.php?action=listar_categorias');const data=await res.json();const sel=document.getElementById('filtro-cat');(data.data||[]).forEach(c=>{const o=document.createElement('option');o.value=c.nombre;o.textContent=c.nombre;sel.appendChild(o);});}
async function loadMovimientos(page=1){currentPage=page;const de=document.getElementById('filtro-desde').value,ha=document.getElementById('filtro-hasta').value,ti=document.getElementById('filtro-tipo').value,ca=document.getElementById('filtro-cat').value,lo=new URLSearchParams(location.search).get('lote')||'';currentFilters={desde:de,hasta:ha,tipo:ti,cat:ca,lote:lo};const params=new URLSearchParams({action:'listar_movimientos',page,desde:de,hasta:ha,tipo:ti,cat:ca,lote:lo});const res=await fetch('/hip/api/padrones.php?'+params);const data=await res.json();if(data.error){toast(data.error,'error');return;}document.getElementById('mov-total-ing').textContent=formatMoney(data.total_ingresos);document.getElementById('mov-total-gasto').textContent=formatMoney(data.total_gastos);document.getElementById('mov-total-count').textContent=data.total;const tbody=document.getElementById('mov-tbody');if(!data.data.length){tbody.innerHTML=`<tr><td colspan="10"><div class="empty-state"><div class="empty-state-icon">◈</div><div class="empty-state-text">No se encontraron movimientos</div></div></td></tr>`;document.getElementById('paginacion').innerHTML='';return;}tbody.innerHTML=data.data.map(r=>`<tr><td class="mono">${r.fecha?r.fecha.split(' ')[0].split('-').reverse().join('/'):'—'}</td><td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${r.descripcion}">${r.descripcion}</td><td class="mono" style="color:${r.tipo==='ingreso'?'var(--green)':'var(--red)'}">${formatMoney(parseFloat(r.importe))}</td><td><span class="badge ${r.tipo==='ingreso'?'badge-green':'badge-red'}">${r.tipo}</span></td><td>${r.categoria_nombre?`<span class="badge badge-blue">${r.categoria_nombre}</span>`:'<span class="badge badge-muted">—</span>'}</td><td class="mono">${r.codigo||'—'}</td><td class="mono" style="font-size:11px">${r.cuit||'—'}</td><td class="mono" style="font-size:11px">${r.dni||'—'}</td><td class="mono" style="font-size:11px">${r.numero_cuenta||'—'}</td><td style="font-size:12px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${r.nombre_cliente||'—'}</td></tr>`).join('');const tp=Math.ceil(data.total/data.limit);if(tp>1){let h='';if(page>1)h+=`<a class="page-btn" onclick="loadMovimientos(${page-1})">‹</a>`;for(let p=Math.max(1,page-2);p<=Math.min(tp,page+2);p++)h+=`<a class="page-btn ${p===page?'active':''}" onclick="loadMovimientos(${p})">${p}</a>`;if(page<tp)h+=`<a class="page-btn" onclick="loadMovimientos(${page+1})">›</a>`;document.getElementById('paginacion').innerHTML=h;}else document.getElementById('paginacion').innerHTML='';}
document.getElementById('btn-filtrar').addEventListener('click',()=>loadMovimientos(1));
document.getElementById('btn-exportar').addEventListener('click',()=>{const p=new URLSearchParams({tipo:'movimientos',desde:currentFilters.desde||'',hasta:currentFilters.hasta||'',lote:currentFilters.lote||''});window.location.href='/hip/api/exportar_excel.php?'+p;});
loadCategorias();loadMovimientos();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
