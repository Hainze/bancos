<?php
/**
 * api/exportar_excel.php
 * Exports movements or report as CSV (compatible with Excel)
 */
require_once '../config.php';

$tipo    = $_GET['tipo']    ?? 'movimientos'; // movimientos | totales
$desde   = $_GET['desde']   ?? '';
$hasta   = $_GET['hasta']   ?? '';
$lote    = $_GET['lote']    ?? '';

header('Content-Type: text/csv; charset=UTF-8');
$filename = $tipo . '_' . date('Ymd_His') . '.csv';
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache');

// BOM for Excel UTF-8
echo "\xEF\xBB\xBF";

$pdo = getDB();
$out = fopen('php://output', 'w');

if ($tipo === 'totales') {
    // ── Totales por categoría ──
    fputcsv($out, ['Categoría', 'Código', 'Tipo', 'Total Ingresos', 'Total Gastos', 'Neto', 'Cantidad de movimientos'], ';');

    $where = "WHERE 1=1";
    $params = [];
    if ($desde) { $where .= " AND fecha >= ?"; $params[] = $desde; }
    if ($hasta) { $where .= " AND fecha <= ?"; $params[] = $hasta; }

    $sql = "SELECT 
                COALESCE(categoria_nombre, 'Sin clasificar') as categoria,
                COALESCE(codigo, '—') as codigo,
                tipo,
                SUM(CASE WHEN importe > 0 THEN importe ELSE 0 END) as total_ing,
                SUM(CASE WHEN importe < 0 THEN ABS(importe) ELSE 0 END) as total_gasto,
                SUM(importe) as neto,
                COUNT(*) as cantidad
            FROM movimientos $where
            GROUP BY categoria_nombre, codigo, tipo
            ORDER BY categoria_nombre, tipo";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($r = $stmt->fetch()) {
        fputcsv($out, [
            $r['categoria'],
            $r['codigo'],
            ucfirst($r['tipo']),
            number_format($r['total_ing'], 2, ',', '.'),
            number_format($r['total_gasto'], 2, ',', '.'),
            number_format($r['neto'], 2, ',', '.'),
            $r['cantidad'],
        ], ';');
    }

} else {
    // ── Movimientos detallados ──
    fputcsv($out, ['Fecha', 'Descripción', 'Importe', 'Tipo', 'Categoría', 'Código', 'CUIT', 'DNI', 'N° Cuenta', 'Nombre/Razón Social', 'Lote'], ';');

    $where  = "WHERE 1=1";
    $params = [];
    if ($desde) { $where .= " AND fecha >= ?"; $params[] = $desde; }
    if ($hasta) { $where .= " AND fecha <= ?"; $params[] = $hasta; }
    if ($lote)  { $where .= " AND lote_importacion = ?"; $params[] = $lote; }

    $sql = "SELECT fecha, descripcion, importe, tipo, categoria_nombre, codigo, cuit, dni, numero_cuenta, nombre_cliente, lote_importacion
            FROM movimientos $where ORDER BY fecha ASC, id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($r = $stmt->fetch()) {
        fputcsv($out, [
            date('d/m/Y', strtotime($r['fecha'])),
            $r['descripcion'],
            number_format($r['importe'], 2, ',', '.'),
            ucfirst($r['tipo']),
            $r['categoria_nombre'] ?? '',
            $r['codigo'] ?? '',
            $r['cuit'] ?? '',
            $r['dni'] ?? '',
            $r['numero_cuenta'] ?? '',
            $r['nombre_cliente'] ?? '',
            $r['lote_importacion'] ?? '',
        ], ';');
    }
}

fclose($out);
?>
