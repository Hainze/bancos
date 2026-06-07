<?php
require_once dirname(__DIR__, 2) . '/auth/check_api.php';

$tipo  = $_GET['tipo']  ?? 'movimientos';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$lote  = $_GET['lote']  ?? '';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="mp_' . $tipo . '_' . date('Ymd_His') . '.csv"');
header('Cache-Control: no-cache');
echo "\xEF\xBB\xBF"; // BOM UTF-8

$pdo = getDB();
$out = fopen('php://output', 'w');

if ($tipo === 'totales') {
    fputcsv($out, ['Categoría','Tipo','Total Ingresos','Total Egresos','Neto','Cantidad'], ';');
    $where='WHERE 1=1'; $params=[];
    if ($desde){$where.=" AND fecha >= ?";$params[]=$desde;}
    if ($hasta){$where.=" AND fecha <= ?";$params[]=$hasta;}
    $sql="SELECT
        COALESCE(categoria_nombre,'Sin clasificar') as categoria,
        tipo,
        SUM(CASE WHEN importe>0 THEN importe ELSE 0 END) as total_ing,
        SUM(CASE WHEN importe<0 THEN ABS(importe) ELSE 0 END) as total_gasto,
        SUM(importe) as neto,
        COUNT(*) as cantidad
        FROM mp_movimientos $where
        GROUP BY categoria_nombre,tipo ORDER BY categoria_nombre,tipo";
    $st=$pdo->prepare($sql); $st->execute($params);
    while ($r=$st->fetch()) {
        fputcsv($out,[
            $r['categoria'], ucfirst($r['tipo']),
            number_format($r['total_ing'],2,',','.'),
            number_format($r['total_gasto'],2,',','.'),
            number_format($r['neto'],2,',','.'),
            $r['cantidad'],
        ], ';');
    }
} else {
    fputcsv($out, ['Fecha','Descripción','Tipo de movimiento','Importe','Tipo','Categoría','Referencia','Nombre','Lote'], ';');
    $where='WHERE 1=1'; $params=[];
    if ($desde){$where.=" AND fecha >= ?";$params[]=$desde;}
    if ($hasta){$where.=" AND fecha <= ?";$params[]=$hasta;}
    if ($lote) {$where.=" AND lote_importacion = ?";$params[]=$lote;}
    $st=$pdo->prepare("SELECT fecha,descripcion,tipo_movimiento,importe,tipo,categoria_nombre,referencia,nombre_cliente,lote_importacion
        FROM mp_movimientos $where ORDER BY fecha ASC,id ASC");
    $st->execute($params);
    while ($r=$st->fetch()) {
        fputcsv($out,[
            date('d/m/Y',strtotime($r['fecha'])),
            $r['descripcion'],
            $r['tipo_movimiento']??'',
            number_format($r['importe'],2,',','.'),
            ucfirst($r['tipo']),
            $r['categoria_nombre']??'',
            $r['referencia']??'',
            $r['nombre_cliente']??'',
            $r['lote_importacion']??'',
        ], ';');
    }
}
fclose($out);
?>
