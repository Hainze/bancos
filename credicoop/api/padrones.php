<?php
require_once dirname(__DIR__, 2) . '/auth/check_api.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo    = getDB();

$pdo->exec("CREATE TABLE IF NOT EXISTS coop_lotes (
    id INT AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(50) NOT NULL UNIQUE,
    archivo_nombre VARCHAR(200), total_filas INT DEFAULT 0,
    filas_clasificadas INT DEFAULT 0, filas_sin_clasificar INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS coop_movimientos (
    id INT AUTO_INCREMENT PRIMARY KEY, fecha DATE NOT NULL,
    descripcion TEXT NOT NULL, importe DECIMAL(15,2) NOT NULL,
    tipo VARCHAR(10) NOT NULL, categoria_id INT DEFAULT NULL,
    categoria_nombre VARCHAR(100) DEFAULT NULL, codigo VARCHAR(20) DEFAULT NULL,
    cuit VARCHAR(20) DEFAULT NULL, dni VARCHAR(20) DEFAULT NULL,
    numero_cuenta VARCHAR(50) DEFAULT NULL, nombre_cliente VARCHAR(150) DEFAULT NULL,
    lote_importacion VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fecha (fecha), INDEX idx_tipo (tipo), INDEX idx_lote (lote_importacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

try {
    switch ($action) {

        case 'listar_categorias':
            echo json_encode(['data' => $pdo->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll()]); break;

        case 'guardar_categoria':
            $d=json_decode(file_get_contents('php://input'),true);$id=(int)($d['id']??0);$n=trim($d['nombre']??'');$c=trim($d['codigo']??'');$t=$d['tipo']??'ambos';
            if(!$n||!$c){echo json_encode(['error'=>'Nombre y código requeridos']);break;}
            if($id)$pdo->prepare("UPDATE categorias SET nombre=?,codigo=?,tipo=? WHERE id=?")->execute([$n,$c,$t,$id]);
            else   $pdo->prepare("INSERT INTO categorias (nombre,codigo,tipo) VALUES (?,?,?)")->execute([$n,$c,$t]);
            echo json_encode(['success'=>true]); break;

        case 'eliminar_categoria':
            $d=json_decode(file_get_contents('php://input'),true);
            $pdo->prepare("DELETE FROM categorias WHERE id=?")->execute([(int)($d['id']??0)]);
            echo json_encode(['success'=>true]); break;

        case 'listar_palabras':
            $ci=(int)($_GET['categoria_id']??0);
            if($ci){$st=$pdo->prepare("SELECT p.*,c.nombre as cat_nombre,c.codigo as cat_codigo FROM palabras_clave p JOIN categorias c ON p.categoria_id=c.id WHERE p.categoria_id=? ORDER BY p.palabra");$st->execute([$ci]);}
            else    $st=$pdo->query("SELECT p.*,c.nombre as cat_nombre,c.codigo as cat_codigo FROM palabras_clave p JOIN categorias c ON p.categoria_id=c.id ORDER BY c.nombre,p.palabra");
            echo json_encode(['data'=>$st->fetchAll()]); break;

        case 'guardar_palabra':
            $d=json_decode(file_get_contents('php://input'),true);$id=(int)($d['id']??0);$ci=(int)($d['categoria_id']??0);$p=mb_strtolower(trim($d['palabra']??''));
            if(!$ci||!$p){echo json_encode(['error'=>'Categoría y palabra requeridas']);break;}
            if($id)$pdo->prepare("UPDATE palabras_clave SET categoria_id=?,palabra=? WHERE id=?")->execute([$ci,$p,$id]);
            else   $pdo->prepare("INSERT INTO palabras_clave (categoria_id,palabra) VALUES (?,?)")->execute([$ci,$p]);
            echo json_encode(['success'=>true]); break;

        case 'eliminar_palabra':
            $d=json_decode(file_get_contents('php://input'),true);
            $pdo->prepare("DELETE FROM palabras_clave WHERE id=?")->execute([(int)($d['id']??0)]);
            echo json_encode(['success'=>true]); break;

        case 'listar_clientes':
            $q=trim($_GET['q']??'');$pg=max(1,(int)($_GET['page']??1));$lim=50;$off=($pg-1)*$lim;
            if($q){$lk="%$q%";$st=$pdo->prepare("SELECT * FROM clientes WHERE activo=1 AND (nombre LIKE ? OR cuit LIKE ? OR dni LIKE ? OR numero_cuenta LIKE ?) ORDER BY nombre LIMIT ? OFFSET ?");$st->execute([$lk,$lk,$lk,$lk,$lim,$off]);$tot=$pdo->prepare("SELECT COUNT(*) FROM clientes WHERE activo=1 AND (nombre LIKE ? OR cuit LIKE ? OR dni LIKE ? OR numero_cuenta LIKE ?)");$tot->execute([$lk,$lk,$lk,$lk]);}
            else{$st=$pdo->prepare("SELECT * FROM clientes WHERE activo=1 ORDER BY nombre LIMIT ? OFFSET ?");$st->execute([$lim,$off]);$tot=$pdo->query("SELECT COUNT(*) FROM clientes WHERE activo=1");}
            echo json_encode(['data'=>$st->fetchAll(),'total'=>(int)$tot->fetchColumn(),'page'=>$pg,'limit'=>$lim]); break;

        case 'guardar_cliente':
            $d=json_decode(file_get_contents('php://input'),true);$id=(int)($d['id']??0);$n=trim($d['nombre']??'');
            if(!$n){echo json_encode(['error'=>'Nombre requerido']);break;}
            $cu=trim($d['cuit']??'')?:null;$dn=trim($d['dni']??'')?:null;$cc=trim($d['numero_cuenta']??'')?:null;
            if($id)$pdo->prepare("UPDATE clientes SET nombre=?,cuit=?,dni=?,numero_cuenta=? WHERE id=?")->execute([$n,$cu,$dn,$cc,$id]);
            else   $pdo->prepare("INSERT INTO clientes (nombre,cuit,dni,numero_cuenta) VALUES (?,?,?,?)")->execute([$n,$cu,$dn,$cc]);
            echo json_encode(['success'=>true]); break;

        case 'eliminar_cliente':
            $d=json_decode(file_get_contents('php://input'),true);
            $pdo->prepare("UPDATE clientes SET activo=0 WHERE id=?")->execute([(int)($d['id']??0)]);
            echo json_encode(['success'=>true]); break;

        case 'importar_clientes':
            $data=json_decode(file_get_contents('php://input'),true);$rows=$data['clientes']??[];$n=0;
            $st=$pdo->prepare("INSERT INTO clientes (nombre,cuit,dni,numero_cuenta) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE nombre=VALUES(nombre)");
            foreach($rows as $r){$nom=trim($r['nombre']??'');if(!$nom)continue;$st->execute([$nom,trim($r['cuit']??'')?:null,trim($r['dni']??'')?:null,trim($r['numero_cuenta']??'')?:null]);$n++;}
            echo json_encode(['success'=>true,'insertados'=>$n]); break;

        case 'listar_movimientos':
            $de=$_GET['desde']??'';$ha=$_GET['hasta']??'';$ti=$_GET['tipo']??'';$ca=$_GET['cat']??'';
            $pg=max(1,(int)($_GET['page']??1));$lim=100;$off=($pg-1)*$lim;
            $w="WHERE 1=1";$p=[];
            if($de){$w.=" AND fecha >= ?";$p[]=$de;}if($ha){$w.=" AND fecha <= ?";$p[]=$ha;}
            if($ti){$w.=" AND tipo = ?";$p[]=$ti;}if($ca){$w.=" AND categoria_nombre = ?";$p[]=$ca;}
            $st=$pdo->prepare("SELECT COUNT(*),SUM(CASE WHEN tipo='ingreso' THEN importe ELSE 0 END),SUM(CASE WHEN tipo='gasto' THEN ABS(importe) ELSE 0 END) FROM coop_movimientos $w");$st->execute($p);
            [$tot,$ing,$gas]=$st->fetch(PDO::FETCH_NUM);
            $st=$pdo->prepare("SELECT * FROM coop_movimientos $w ORDER BY fecha DESC,id DESC LIMIT $lim OFFSET $off");$st->execute($p);
            echo json_encode(['data'=>$st->fetchAll(),'total'=>(int)$tot,'total_ingresos'=>(float)$ing,'total_gastos'=>(float)$gas,'page'=>$pg,'limit'=>$lim]); break;

        case 'stats_dashboard':
            $de=$_GET['desde']??date('Y-m-01');$ha=$_GET['hasta']??date('Y-m-d');$p=[$de,$ha];
            $t=$pdo->prepare("SELECT COUNT(*) as total_mov,SUM(CASE WHEN tipo='ingreso' THEN importe ELSE 0 END) as total_ing,SUM(CASE WHEN tipo='gasto' THEN ABS(importe) ELSE 0 END) as total_gasto,SUM(CASE WHEN tipo='ingreso' THEN 1 ELSE 0 END) as cant_ing,SUM(CASE WHEN tipo='gasto' THEN 1 ELSE 0 END) as cant_gasto,SUM(CASE WHEN categoria_id IS NULL THEN 1 ELSE 0 END) as sin_clasificar FROM coop_movimientos WHERE fecha BETWEEN ? AND ?");$t->execute($p);
            $c=$pdo->prepare("SELECT COALESCE(categoria_nombre,'Sin clasificar') as cat,tipo,SUM(ABS(importe)) as total,COUNT(*) as cant FROM coop_movimientos WHERE fecha BETWEEN ? AND ? GROUP BY categoria_nombre,tipo ORDER BY total DESC");$c->execute($p);
            $l=$pdo->query("SELECT * FROM coop_lotes ORDER BY created_at DESC LIMIT 10")->fetchAll();
            echo json_encode(['totales'=>$t->fetch(),'por_categoria'=>$c->fetchAll(),'lotes_recientes'=>$l]); break;

        case 'resetear_movimientos':
            $pdo->exec("DELETE FROM coop_movimientos");$pdo->exec("DELETE FROM coop_lotes");
            echo json_encode(['success'=>true]); break;

        default:
            echo json_encode(['error' => 'Acción no reconocida: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
