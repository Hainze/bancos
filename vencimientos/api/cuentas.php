<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/auth/check_api.php';

header('Content-Type: application/json; charset=utf-8');

$action    = $_GET['action'] ?? '';
$clienteId = (int)($_SESSION['fact_cliente_id'] ?? 0);

try { $pdo = getDB(); } catch (Exception $e) {
    echo json_encode(['error' => 'DB: ' . $e->getMessage()]); exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS venc_cuentas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id  INT NOT NULL,
    tipo        VARCHAR(10) NOT NULL DEFAULT 'servicio',
    categoria   VARCHAR(50) NOT NULL,
    nombre      VARCHAR(200) NOT NULL,
    descripcion VARCHAR(500) DEFAULT '',
    url_web     VARCHAR(500) DEFAULT '',
    usuario     VARCHAR(200) DEFAULT '',
    clave       VARCHAR(800) DEFAULT '',
    notas       TEXT,
    activo      TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cliente (cliente_id),
    INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Cifrado AES-128-CBC ───────────────────────────────────
define('VENC_CIPHER', 'AES-128-CBC');
define('VENC_KEY',    'SmartAdm1n_V3nc!');

function encryptClave(string $text): string {
    if (!$text) return '';
    $iv        = substr(md5(uniqid('', true)), 0, 16);
    $encrypted = openssl_encrypt($text, VENC_CIPHER, VENC_KEY, 0, $iv);
    return $iv . ':' . $encrypted;
}

function decryptClave(string $data): string {
    if (!$data) return '';
    if (!str_contains($data, ':')) return $data;
    [$iv, $enc] = explode(':', $data, 2);
    $result = openssl_decrypt($enc, VENC_CIPHER, VENC_KEY, 0, $iv);
    return $result ?: '';
}

switch ($action) {

    case 'listar':
        if (!$clienteId) { echo json_encode(['error' => 'Sin cliente']); break; }
        $tipo = $_GET['tipo'] ?? '';
        $w = $tipo ? 'AND tipo = ?' : '';
        $p = $tipo ? [$clienteId, $tipo] : [$clienteId];
        $st = $pdo->prepare("SELECT id, cliente_id, tipo, categoria, nombre, descripcion, url_web, usuario, notas, activo, created_at
            FROM venc_cuentas WHERE cliente_id = ? $w ORDER BY tipo, categoria, nombre");
        $st->execute($p);
        echo json_encode(['data' => $st->fetchAll()]);
        break;

    case 'get_clave':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['id'] ?? 0);
        if (!$id || !$clienteId) { echo json_encode(['error' => 'Inválido']); break; }
        $row = $pdo->prepare("SELECT clave FROM venc_cuentas WHERE id = ? AND cliente_id = ?");
        $row->execute([$id, $clienteId]);
        $r = $row->fetch();
        if (!$r) { echo json_encode(['error' => 'No encontrado']); break; }
        echo json_encode(['clave' => decryptClave($r['clave'])]);
        break;

    case 'guardar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        if (!$clienteId) { echo json_encode(['error' => 'Sin cliente']); break; }
        $body = json_decode(file_get_contents('php://input'), true);

        $id          = (int)($body['id'] ?? 0);
        $tipo        = in_array($body['tipo'] ?? '', ['fiscal','servicio']) ? $body['tipo'] : 'servicio';
        $categoria   = substr($body['categoria'] ?? '', 0, 50);
        $nombre      = substr($body['nombre']    ?? '', 0, 200);
        $descripcion = substr($body['descripcion'] ?? '', 0, 500);
        $url_web     = substr($body['url_web']   ?? '', 0, 500);
        $usuario     = substr($body['usuario']   ?? '', 0, 200);
        $claveRaw    = $body['clave'] ?? '';
        $notas       = $body['notas'] ?? '';
        $activo      = (int)($body['activo'] ?? 1);

        if (!$nombre || !$categoria) { echo json_encode(['error' => 'Nombre y categoría requeridos']); break; }

        if ($id) {
            // Si la clave llega vacía, no la sobreescribimos
            if ($claveRaw !== '') {
                $claveEnc = encryptClave($claveRaw);
                $pdo->prepare("UPDATE venc_cuentas SET tipo=?,categoria=?,nombre=?,descripcion=?,url_web=?,usuario=?,clave=?,notas=?,activo=? WHERE id=? AND cliente_id=?")
                    ->execute([$tipo,$categoria,$nombre,$descripcion,$url_web,$usuario,$claveEnc,$notas,$activo,$id,$clienteId]);
            } else {
                $pdo->prepare("UPDATE venc_cuentas SET tipo=?,categoria=?,nombre=?,descripcion=?,url_web=?,usuario=?,notas=?,activo=? WHERE id=? AND cliente_id=?")
                    ->execute([$tipo,$categoria,$nombre,$descripcion,$url_web,$usuario,$notas,$activo,$id,$clienteId]);
            }
            echo json_encode(['success' => true, 'id' => $id]);
        } else {
            $claveEnc = $claveRaw ? encryptClave($claveRaw) : '';
            $pdo->prepare("INSERT INTO venc_cuentas (cliente_id,tipo,categoria,nombre,descripcion,url_web,usuario,clave,notas,activo) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$clienteId,$tipo,$categoria,$nombre,$descripcion,$url_web,$usuario,$claveEnc,$notas,$activo]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        }
        break;

    case 'eliminar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); break; }
        $pdo->prepare("DELETE FROM venc_cuentas WHERE id=? AND cliente_id=?")->execute([$id, $clienteId]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}
