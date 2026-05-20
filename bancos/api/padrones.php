<?php
/**
 * api/padrones.php
 * CRUD for categorias, palabras_clave, clientes
 */
require_once dirname(__DIR__, 2) . '/auth/check_api.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = getDB();

try {
    switch ($action) {

        // ── CATEGORÍAS ──────────────────────────────
        case 'listar_categorias':
            $rows = $pdo->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll();
            echo json_encode(['data' => $rows]);
            break;

        case 'guardar_categoria':
            $d = json_decode(file_get_contents('php://input'), true);
            $id     = (int)($d['id'] ?? 0);
            $nombre = trim($d['nombre'] ?? '');
            $codigo = trim($d['codigo'] ?? '');
            $tipo   = $d['tipo'] ?? 'ambos';
            if (!$nombre || !$codigo) { echo json_encode(['error' => 'Nombre y código son requeridos']); break; }
            if ($id) {
                $pdo->prepare("UPDATE categorias SET nombre=?,codigo=?,tipo=? WHERE id=?")->execute([$nombre,$codigo,$tipo,$id]);
                echo json_encode(['success' => true, 'msg' => 'Categoría actualizada']);
            } else {
                $pdo->prepare("INSERT INTO categorias (nombre,codigo,tipo) VALUES (?,?,?)")->execute([$nombre,$codigo,$tipo]);
                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'msg' => 'Categoría creada']);
            }
            break;

        case 'eliminar_categoria':
            $d = json_decode(file_get_contents('php://input'), true);
            $id = (int)($d['id'] ?? 0);
            $pdo->prepare("DELETE FROM categorias WHERE id=?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        // ── PALABRAS CLAVE ──────────────────────────
        case 'listar_palabras':
            $catId = (int)($_GET['categoria_id'] ?? 0);
            if ($catId) {
                $stmt = $pdo->prepare("SELECT p.*, c.nombre as cat_nombre, c.codigo as cat_codigo 
                                       FROM palabras_clave p JOIN categorias c ON p.categoria_id=c.id 
                                       WHERE p.categoria_id=? ORDER BY p.palabra");
                $stmt->execute([$catId]);
            } else {
                $stmt = $pdo->query("SELECT p.*, c.nombre as cat_nombre, c.codigo as cat_codigo 
                                     FROM palabras_clave p JOIN categorias c ON p.categoria_id=c.id 
                                     ORDER BY c.nombre, p.palabra");
            }
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        case 'guardar_palabra':
            $d = json_decode(file_get_contents('php://input'), true);
            $id      = (int)($d['id'] ?? 0);
            $cat_id  = (int)($d['categoria_id'] ?? 0);
            $palabra = mb_strtolower(trim($d['palabra'] ?? ''));
            if (!$cat_id || !$palabra) { echo json_encode(['error' => 'Categoría y palabra son requeridas']); break; }
            if ($id) {
                $pdo->prepare("UPDATE palabras_clave SET categoria_id=?,palabra=? WHERE id=?")->execute([$cat_id,$palabra,$id]);
                echo json_encode(['success' => true, 'msg' => 'Palabra actualizada']);
            } else {
                $pdo->prepare("INSERT INTO palabras_clave (categoria_id,palabra) VALUES (?,?)")->execute([$cat_id,$palabra]);
                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            }
            break;

        case 'eliminar_palabra':
            $d = json_decode(file_get_contents('php://input'), true);
            $pdo->prepare("DELETE FROM palabras_clave WHERE id=?")->execute([(int)($d['id'] ?? 0)]);
            echo json_encode(['success' => true]);
            break;

        // ── CLIENTES ────────────────────────────────
        case 'listar_clientes':
            $search = trim($_GET['q'] ?? '');
            $page   = max(1, (int)($_GET['page'] ?? 1));
            $limit  = 50;
            $offset = ($page - 1) * $limit;
            if ($search) {
                $like = "%$search%";
                $stmt = $pdo->prepare("SELECT * FROM clientes WHERE activo=1 AND (nombre LIKE ? OR cuit LIKE ? OR dni LIKE ? OR numero_cuenta LIKE ?) ORDER BY nombre LIMIT ? OFFSET ?");
                $stmt->execute([$like,$like,$like,$like,$limit,$offset]);
                $total = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE activo=1 AND (nombre LIKE ? OR cuit LIKE ? OR dni LIKE ? OR numero_cuenta LIKE ?)");
                $total->execute([$like,$like,$like,$like]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM clientes WHERE activo=1 ORDER BY nombre LIMIT ? OFFSET ?");
                $stmt->execute([$limit, $offset]);
                $total = $pdo->query("SELECT COUNT(*) FROM clientes WHERE activo=1");
            }
            echo json_encode(['data' => $stmt->fetchAll(), 'total' => (int)$total->fetchColumn(), 'page' => $page, 'limit' => $limit]);
            break;

        case 'guardar_cliente':
            $d      = json_decode(file_get_contents('php://input'), true);
            $id     = (int)($d['id'] ?? 0);
            $nombre = trim($d['nombre'] ?? '');
            $cuit   = trim($d['cuit'] ?? '') ?: null;
            $dni    = trim($d['dni'] ?? '') ?: null;
            $cuenta = trim($d['numero_cuenta'] ?? '') ?: null;
            if (!$nombre) { echo json_encode(['error' => 'Nombre es requerido']); break; }
            if ($id) {
                $pdo->prepare("UPDATE clientes SET nombre=?,cuit=?,dni=?,numero_cuenta=? WHERE id=?")->execute([$nombre,$cuit,$dni,$cuenta,$id]);
                echo json_encode(['success' => true, 'msg' => 'Cliente actualizado']);
            } else {
                $pdo->prepare("INSERT INTO clientes (nombre,cuit,dni,numero_cuenta) VALUES (?,?,?,?)")->execute([$nombre,$cuit,$dni,$cuenta]);
                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            }
            break;

        case 'eliminar_cliente':
            $d = json_decode(file_get_contents('php://input'), true);
            $pdo->prepare("UPDATE clientes SET activo=0 WHERE id=?")->execute([(int)($d['id'] ?? 0)]);
            echo json_encode(['success' => true]);
            break;

        case 'importar_clientes':
            // Bulk import via JSON array
            $data = json_decode(file_get_contents('php://input'), true);
            $rows = $data['clientes'] ?? [];
            $inserted = 0; $updated = 0;
            $stmt = $pdo->prepare("INSERT INTO clientes (nombre,cuit,dni,numero_cuenta) VALUES (?,?,?,?) 
                                   ON DUPLICATE KEY UPDATE nombre=VALUES(nombre)");
            foreach ($rows as $r) {
                $nombre = trim($r['nombre'] ?? '');
                if (!$nombre) continue;
                $stmt->execute([
                    $nombre,
                    trim($r['cuit'] ?? '') ?: null,
                    trim($r['dni'] ?? '') ?: null,
                    trim($r['numero_cuenta'] ?? '') ?: null,
                ]);
                $inserted++;
            }
            echo json_encode(['success' => true, 'insertados' => $inserted]);
            break;

        // ── MOVIMIENTOS (for listing) ────────────────
        case 'listar_movimientos':
            $desde = $_GET['desde'] ?? '';
            $hasta = $_GET['hasta'] ?? '';
            $tipo  = $_GET['tipo']  ?? '';
            $cat   = $_GET['cat']   ?? '';
            $page  = max(1, (int)($_GET['page'] ?? 1));
            $limit = 100;
            $offset= ($page-1)*$limit;

            $where = "WHERE 1=1";
            $params= [];
            if ($desde) { $where .= " AND fecha >= ?"; $params[] = $desde; }
            if ($hasta) { $where .= " AND fecha <= ?"; $params[] = $hasta; }
            if ($tipo)  { $where .= " AND tipo = ?";   $params[] = $tipo; }
            if ($cat)   { $where .= " AND categoria_nombre = ?"; $params[] = $cat; }

            $stmtTotal = $pdo->prepare("SELECT COUNT(*), SUM(CASE WHEN tipo='ingreso' THEN importe ELSE 0 END), SUM(CASE WHEN tipo='gasto' THEN ABS(importe) ELSE 0 END) FROM movimientos $where");
            $stmtTotal->execute($params);
            [$total, $totalIng, $totalGasto] = $stmtTotal->fetch(PDO::FETCH_NUM);

            $stmt = $pdo->prepare("SELECT * FROM movimientos $where ORDER BY fecha DESC, id DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);

            echo json_encode([
                'data'  => $stmt->fetchAll(),
                'total' => (int)$total,
                'total_ingresos' => (float)$totalIng,
                'total_gastos'   => (float)$totalGasto,
                'page'  => $page,
                'limit' => $limit,
            ]);
            break;

        case 'stats_dashboard':
            $desde = $_GET['desde'] ?? date('Y-m-01');
            $hasta = $_GET['hasta'] ?? date('Y-m-d');
            $params = [$desde, $hasta];

            $totales = $pdo->prepare("SELECT
                COUNT(*) as total_mov,
                SUM(CASE WHEN tipo='ingreso' THEN importe ELSE 0 END) as total_ing,
                SUM(CASE WHEN tipo='gasto' THEN ABS(importe) ELSE 0 END) as total_gasto,
                SUM(CASE WHEN tipo='ingreso' THEN 1 ELSE 0 END) as cant_ing,
                SUM(CASE WHEN tipo='gasto' THEN 1 ELSE 0 END) as cant_gasto,
                SUM(CASE WHEN categoria_id IS NULL THEN 1 ELSE 0 END) as sin_clasificar
                FROM movimientos WHERE fecha BETWEEN ? AND ?");
            $totales->execute($params);
            $t = $totales->fetch();

            $porCat = $pdo->prepare("SELECT 
                COALESCE(categoria_nombre,'Sin clasificar') as cat,
                tipo,
                SUM(ABS(importe)) as total,
                COUNT(*) as cant
                FROM movimientos WHERE fecha BETWEEN ? AND ?
                GROUP BY categoria_nombre, tipo ORDER BY total DESC");
            $porCat->execute($params);

            $lotes = $pdo->query("SELECT * FROM lotes ORDER BY created_at DESC LIMIT 10")->fetchAll();

            echo json_encode([
                'totales' => $t,
                'por_categoria' => $porCat->fetchAll(),
                'lotes_recientes' => $lotes,
            ]);
            break;

        default:
            echo json_encode(['error' => 'Acción no reconocida: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>

