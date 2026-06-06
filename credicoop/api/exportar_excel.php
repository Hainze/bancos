<?php
require_once dirname(__DIR__, 2) . '/auth/check_api.php';

$tipo=$_GET['tipo']??'movimientos';$desde=$_GET['desde']??'';$hasta=$_GET['hasta']??'';$lote=$_GET['lote']??'';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="coop_'.$tipo.'_'.date('Ymd_His').'.csv"');
header('Cache-Control: no-cache');
echo "\xEF\xBB\xBF";
$pdo=getDB();$out=fopen('php://output','w');
if($tipo==='totales'){
    fputcsv($out,['Categoría','Código','Tipo','Total Ingresos','Total Gastos','Neto','Cantidad'],';');
    $w='WHERE 1=1';$p=[];if($desde){$w.=" AND fecha >= ?";$p[]=$desde;}if($hasta){$w.=" AND fecha <= ?";$p[]=$hasta;}
    $st=$pdo->prepare("SELECT COALESCE(categoria_nombre,'Sin clasificar') as categoria,COALESCE(codigo,'—') as codigo,tipo,SUM(CASE WHEN importe>0 THEN importe ELSE 0 END) as total_ing,SUM(CASE WHEN importe<0 THEN ABS(importe) ELSE 0 END) as total_gasto,SUM(importe) as neto,COUNT(*) as cantidad FROM coop_movimientos $w GROUP BY categoria_nombre,codigo,tipo ORDER BY categoria_nombre,tipo");$st->execute($p);
    while($r=$st->fetch())fputcsv($out,[$r['categoria'],$r['codigo'],ucfirst($r['tipo']),number_format($r['total_ing'],2,',','.'),number_format($r['total_gasto'],2,',','.'),number_format($r['neto'],2,',','.'),$r['cantidad']],';');
}else{
    fputcsv($out,['Fecha','Descripción','Importe','Tipo','Categoría','Código','CUIT','DNI','N° Cuenta','Nombre/Razón Social','Lote'],';');
    $w='WHERE 1=1';$p=[];if($desde){$w.=" AND fecha >= ?";$p[]=$desde;}if($hasta){$w.=" AND fecha <= ?";$p[]=$hasta;}if($lote){$w.=" AND lote_importacion = ?";$p[]=$lote;}
    $st=$pdo->prepare("SELECT fecha,descripcion,importe,tipo,categoria_nombre,codigo,cuit,dni,numero_cuenta,nombre_cliente,lote_importacion FROM coop_movimientos $w ORDER BY fecha ASC,id ASC");$st->execute($p);
    while($r=$st->fetch())fputcsv($out,[date('d/m/Y',strtotime($r['fecha'])),$r['descripcion'],number_format($r['importe'],2,',','.'),ucfirst($r['tipo']),$r['categoria_nombre']??'',$r['codigo']??'',$r['cuit']??'',$r['dni']??'',$r['numero_cuenta']??'',$r['nombre_cliente']??'',$r['lote_importacion']??''],';');
}
fclose($out);
?>
