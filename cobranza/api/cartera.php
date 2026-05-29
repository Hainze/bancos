<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/auth/check_api.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$pdo    = getDB();

switch ($action) {

    // ── Guardar un lote completo ───────────────────────────────────────────
    case 'guardar_lote':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'POST requerido']); break;
        }
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body || empty($body['filas'])) {
            echo json_encode(['error' => 'Datos inválidos']); break;
        }

        $filas   = $body['filas'];
        $archivo = substr($body['archivo_nombre'] ?? 'Sin nombre', 0, 255);

        // Calcular totales del lote (solo positivos)
        $totalClientes = 0;
        $totalImporte  = 0.0;
        foreach ($filas as $f) {
            if ((float)($f['total'] ?? 0) > 0) {
                $totalClientes++;
                $totalImporte += (float)$f['total'];
            }
        }

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare("
                INSERT INTO cobranza_lotes (archivo_nombre, total_clientes, total_importe)
                VALUES (?, ?, ?)
            ");
            $ins->execute([$archivo, $totalClientes, $totalImporte]);
            $loteId = $pdo->lastInsertId();

            $insRow = $pdo->prepare("
                INSERT INTO cobranza_cartera (lote_id, sistema, codigo, nombre, d30, d60, d90, d120, d120plus, total)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($filas as $f) {
                $insRow->execute([
                    $loteId,
                    (int)($f['sistema']   ?? 1),
                    substr((string)($f['codigo'] ?? ''), 0, 50),
                    substr((string)($f['nombre'] ?? ''), 0, 255),
                    (float)($f['d30']      ?? 0),
                    (float)($f['d60']      ?? 0),
                    (float)($f['d90']      ?? 0),
                    (float)($f['d120']     ?? 0),
                    (float)($f['d120plus'] ?? 0),
                    (float)($f['total']    ?? 0),
                ]);
            }
            $pdo->commit();
            echo json_encode(['lote_id' => (int)$loteId, 'total' => count($filas)]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    // ── Eliminar lote ─────────────────────────────────────────────────────
    case 'eliminar_lote':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'POST requerido']); break;
        }
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); break; }

        $pdo->prepare("DELETE FROM cobranza_lotes WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}
