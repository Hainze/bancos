<?php
/**
 * hip/api/procesar_excel.php
 * Parsea extractos del Banco Hipotecario Argentina.
 * Detecta automáticamente formato Debe/Haber o Importe único.
 */
require_once dirname(__DIR__, 2) . '/auth/check_api.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']); exit;
}
if (!isset($_FILES['excel']) || $_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No se recibió archivo válido']); exit;
}

$ext = strtolower(pathinfo($_FILES['excel']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx','xls','csv'])) {
    echo json_encode(['error' => 'Solo se permiten .xlsx, .xls o .csv']); exit;
}

$tmpPath = sys_get_temp_dir() . '/' . uniqid('hip_') . '.' . $ext;
move_uploaded_file($_FILES['excel']['tmp_name'], $tmpPath);

try {
    $pdo = getDB();

    $pdo->exec("CREATE TABLE IF NOT EXISTS hip_lotes (
        id                   INT AUTO_INCREMENT PRIMARY KEY,
        codigo               VARCHAR(50) NOT NULL UNIQUE,
        archivo_nombre       VARCHAR(200),
        total_filas          INT DEFAULT 0,
        filas_clasificadas   INT DEFAULT 0,
        filas_sin_clasificar INT DEFAULT 0,
        created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS hip_movimientos (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        fecha            DATE NOT NULL,
        descripcion      TEXT NOT NULL,
        importe          DECIMAL(15,2) NOT NULL,
        tipo             VARCHAR(10) NOT NULL,
        categoria_id     INT DEFAULT NULL,
        categoria_nombre VARCHAR(100) DEFAULT NULL,
        codigo           VARCHAR(20) DEFAULT NULL,
        cuit             VARCHAR(20) DEFAULT NULL,
        dni              VARCHAR(20) DEFAULT NULL,
        numero_cuenta    VARCHAR(50) DEFAULT NULL,
        nombre_cliente   VARCHAR(150) DEFAULT NULL,
        lote_importacion VARCHAR(50) DEFAULT NULL,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_fecha (fecha),
        INDEX idx_tipo (tipo),
        INDEX idx_lote (lote_importacion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $cats = $pdo->query("SELECT c.id, c.nombre, c.codigo, c.tipo, p.palabra
                         FROM palabras_clave p JOIN categorias c ON p.categoria_id = c.id
                         WHERE c.activo=1 AND p.activo=1 ORDER BY c.id")->fetchAll();
    $keywordMap = [];
    foreach ($cats as $cat) {
        $keywordMap[] = ['id'=>$cat['id'],'nombre'=>$cat['nombre'],'codigo'=>$cat['codigo'],'tipo'=>$cat['tipo'],'palabra'=>mb_strtolower(trim($cat['palabra']))];
    }

    $clientes = $pdo->query("SELECT * FROM clientes WHERE activo=1")->fetchAll();
    $cuitMap=[]; $cuentaMap=[]; $dniMap=[];
    foreach ($clientes as $cl) {
        if ($cl['cuit'])          $cuitMap[trim($cl['cuit'])]            = $cl;
        if ($cl['numero_cuenta']) $cuentaMap[trim($cl['numero_cuenta'])] = $cl;
        if ($cl['dni'])           $dniMap[trim($cl['dni'])]              = $cl;
    }

    $rawRows = parseExcelHIP($tmpPath, $ext);
    if (empty($rawRows)) { echo json_encode(['error' => 'El archivo no tiene datos o no pudo leerse']); exit; }

    $header   = array_map('mb_strtolower', array_map('trim', $rawRows[0] ?? []));
    $colFecha=-1; $colDesc=-1; $colDebe=-1; $colHaber=-1; $colImp=-1;

    foreach ($header as $i => $h) {
        $h = normalizeStrHIP($h);
        if (preg_match('/^fecha/', $h))                           $colFecha = $i;
        elseif (preg_match('/^desc|concepto|detalle|movimiento/', $h)) $colDesc = $i;
        elseif (preg_match('/debe|debito|debitos|cargo|extraccion/', $h)) $colDebe = $i;
        elseif (preg_match('/haber|credito|creditos|abono|deposito/', $h)) $colHaber = $i;
        elseif (preg_match('/importe|monto/', $h))                $colImp  = $i;
    }

    $formatoDH = ($colDebe >= 0 && $colHaber >= 0);
    if ($colFecha < 0) $colFecha = 0;
    if ($colDesc  < 0) $colDesc  = 1;
    if (!$formatoDH && $colImp < 0) $colImp = 2;
    if ($formatoDH) {
        if ($colDebe  < 0) $colDebe  = 2;
        if ($colHaber < 0) $colHaber = 3;
    }

    $loteCode  = 'HIP-' . date('Ymd-His');
    $processed = [];
    $clasificadas = 0;

    foreach (array_slice($rawRows, 1) as $row) {
        $fechaRaw    = $row[$colFecha] ?? '';
        $descripcion = trim($row[$colDesc] ?? '');

        if ($formatoDH) {
            $debe  = parseNumHIP($row[$colDebe]  ?? '');
            $haber = parseNumHIP($row[$colHaber] ?? '');
            if ($debe == 0 && $haber == 0 && !$descripcion) continue;
            $importe = $haber > 0 ? $haber : -$debe;
        } else {
            $importe = parseNumHIP($row[$colImp] ?? 0);
        }

        if (empty($descripcion) && $importe == 0) continue;

        $fecha = parseDateHIP($fechaRaw);
        $tipo  = $importe >= 0 ? 'ingreso' : 'gasto';

        $cat_id=null; $cat_nom=null; $cat_cod=null;
        $descLower = mb_strtolower($descripcion);
        foreach ($keywordMap as $kw) {
            if ($kw['tipo'] !== 'ambos' && $kw['tipo'] !== $tipo) continue;
            if (strpos($descLower, $kw['palabra']) !== false) {
                $cat_id=$kw['id']; $cat_nom=$kw['nombre']; $cat_cod=$kw['codigo']; break;
            }
        }
        if ($cat_id) $clasificadas++;

        $cuit=null; $dni=null; $cuenta=null; $nombre=null;
        if ($tipo === 'ingreso') {
            if (preg_match('/(?:cuit[:\s]+)?[(\s]?(\d{11})[)\s]?/i', $descripcion, $m)) $cuit=$m[1];
            if (!$cuit && preg_match('/dni[:\s]+(\d{7,8})/i', $descripcion, $m)) $dni=$m[1];
            if (preg_match('/(?:C[:\.]|ORI:[^-]+-[^-]+-[^-]+-[^-]+-?)(\d{8,})/i', $descripcion, $m)) $cuenta=$m[1];
            if (preg_match('/(?:TRANSF(?:ERENCIA)?\s+DE\s+)([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s,\.]+?)(?:\s*\(|\s+FAC|\s+\d)/i', $descripcion, $m)) $nombre=trim($m[1],' ,');
            elseif (preg_match('/(?:CUIT\s+\d{11}\s+)([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s]+)$/i', $descripcion, $m)) $nombre=trim($m[1]);
            elseif (preg_match('/DNI\s+\d{7,8}\s+([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s]+)$/i', $descripcion, $m)) $nombre=trim($m[1]);
            $cm=null;
            if ($cuit && isset($cuitMap[$cuit])) $cm=$cuitMap[$cuit];
            if (!$cm && $dni && isset($dniMap[$dni])) $cm=$dniMap[$dni];
            if (!$cm && $cuenta && isset($cuentaMap[$cuenta])) $cm=$cuentaMap[$cuenta];
            if ($cm) { if (!$nombre)$nombre=$cm['nombre']; if (!$cuit)$cuit=$cm['cuit']; if (!$dni)$dni=$cm['dni']; if (!$cuenta)$cuenta=$cm['numero_cuenta']; }
        }

        $processed[] = ['fecha'=>$fecha,'descripcion'=>$descripcion,'importe'=>$importe,'tipo'=>$tipo,
            'categoria_id'=>$cat_id,'categoria'=>$cat_nom,'codigo'=>$cat_cod,
            'cuit'=>$cuit,'dni'=>$dni,'numero_cuenta'=>$cuenta,'nombre'=>$nombre,'lote'=>$loteCode];
    }

    if (empty($processed)) { echo json_encode(['error' => 'No se encontraron filas válidas. Verificá el formato.']); exit; }

    $stmt = $pdo->prepare("INSERT INTO hip_movimientos (fecha,descripcion,importe,tipo,categoria_id,categoria_nombre,codigo,cuit,dni,numero_cuenta,nombre_cliente,lote_importacion) VALUES (:fecha,:desc,:imp,:tipo,:cat_id,:cat_nom,:cod,:cuit,:dni,:cuenta,:nombre,:lote)");
    foreach ($processed as $p) {
        $stmt->execute([':fecha'=>$p['fecha']?:date('Y-m-d'),':desc'=>$p['descripcion'],':imp'=>$p['importe'],':tipo'=>$p['tipo'],
            ':cat_id'=>$p['categoria_id'],':cat_nom'=>$p['categoria'],':cod'=>$p['codigo'],
            ':cuit'=>$p['cuit'],':dni'=>$p['dni'],':cuenta'=>$p['numero_cuenta'],':nombre'=>$p['nombre'],':lote'=>$p['lote']]);
    }

    $sinClas = count($processed) - $clasificadas;
    $pdo->prepare("INSERT INTO hip_lotes (codigo,archivo_nombre,total_filas,filas_clasificadas,filas_sin_clasificar) VALUES (?,?,?,?,?)")
        ->execute([$loteCode,$_FILES['excel']['name'],count($processed),$clasificadas,$sinClas]);

    @unlink($tmpPath);
    echo json_encode(['success'=>true,'lote'=>$loteCode,'total'=>count($processed),'clasificadas'=>$clasificadas,'sin_clasificar'=>$sinClas,
        'formato'=>$formatoDH?'Debe/Haber':'Importe firmado','rows'=>$processed]);

} catch (Exception $e) {
    @unlink($tmpPath);
    echo json_encode(['error' => $e->getMessage()]);
}

function parseExcelHIP($path,$ext){$rows=[];if($ext==='csv'){if(($h=fopen($path,'r'))!==false){$first=fgets($h);rewind($h);$d=(substr_count($first,';')>=substr_count($first,','))?';':',';while(($row=fgetcsv($h,0,$d))!==false)$rows[]=$row;fclose($h);}}else $rows=readXlsxHIP($path);return $rows;}
function readXlsxHIP($path){$rows=[];$zip=new ZipArchive();if($zip->open($path)!==true)return $rows;$ss=[];$ssX=$zip->getFromName('xl/sharedStrings.xml');if($ssX){$xml=simplexml_load_string($ssX);if($xml)foreach($xml->si as $si){if(isset($si->t))$ss[]=(string)$si->t;else{$s='';foreach($si->r as $r)$s.=(string)$r->t;$ss[]=$s;}}}$shX=$zip->getFromName('xl/worksheets/sheet1.xml');$zip->close();if(!$shX)return $rows;$xml=simplexml_load_string($shX);if(!$xml)return $rows;foreach($xml->sheetData->row as $row){$rd=[];foreach($row->c as $cell){$ref=(string)$cell['r'];preg_match('/^([A-Z]+)(\d+)$/',$ref,$m);$ci=colHIP($m[1]);while(count($rd)<$ci)$rd[]='';$t=(string)$cell['t'];$v=(string)$cell->v;if($t==='s')$v=$ss[(int)$v]??'';elseif(isset($cell->v)&&$ci===0&&is_numeric($v)&&(float)$v>40000)$v=dateHIP((float)$v);$rd[]=$v;}$f=array_filter($rd,fn($v)=>$v!==''&&$v!==null);if(!empty($f))$rows[]=$rd;}return $rows;}
function colHIP($l){$i=0;foreach(str_split(strtoupper($l))as $c)$i=$i*26+(ord($c)-64);return $i-1;}
function dateHIP($s){return date('Y-m-d',(int)(($s-25569)*86400));}
function parseDateHIP($v){if(empty($v))return null;$v=trim($v);if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$v))return $v;if(preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/',$v,$m))return sprintf('%04d-%02d-%02d',$m[3],$m[2],$m[1]);if(preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/',$v,$m))return sprintf('%04d-%02d-%02d',$m[3],$m[2],$m[1]);$t=strtotime($v);return $t?date('Y-m-d',$t):date('Y-m-d');}
function parseNumHIP($v){if(is_numeric($v))return(float)$v;$v=str_replace(['$','  ',"\xc2\xa0"],['','',''],trim($v));if(preg_match('/^-?[\d\.]+,\d{1,2}$/',$v)){$v=str_replace('.','',$v);$v=str_replace(',','.',$v);}else $v=str_replace([',',' '],['',''],$v);return(float)$v;}
function normalizeStrHIP($s){return iconv('UTF-8','ASCII//TRANSLIT',mb_strtolower($s))?:mb_strtolower($s);}
?>
