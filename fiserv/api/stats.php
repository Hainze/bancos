<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/auth/check_api.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$pdo    = getDB();

switch ($action) {

    // ── Dashboard: totales + gráfico mensual + últimos lotes ───────────────
    case 'dashboard':
        $desde = $_GET['desde'] ?? null;
        $hasta = $_GET['hasta'] ?? null;

        $where  = '';
        $params = [];
        if ($desde && $hasta) {
            $where  = 'WHERE l.fecha_pago BETWEEN ? AND ?';
            $params = [$desde, $hasta];
        }

        // Totales globales del período
        $totales = $pdo->prepare("
            SELECT
                COALESCE(SUM(l.ventas_contado), 0)   AS total_ventas,
                COALESCE(SUM(l.total_descuentos), 0) AS total_descuentos,
                COALESCE(SUM(l.acreditado), 0)       AS total_acreditado,
                COUNT(*)                              AS cant_liquidaciones
            FROM fiserv_liquidaciones l
            $where
        ");
        $totales->execute($params);
        $totals = $totales->fetch();

        // Totales de descuentos desglosados
        $desc = $pdo->prepare("
            SELECT
                COALESCE(SUM(arancel), 0)            AS arancel,
                COALESCE(SUM(iva_arancel), 0)        AS iva_arancel,
                COALESCE(SUM(ret_iibb_sirtac), 0)    AS ret_iibb_sirtac,
                COALESCE(SUM(per_bai_brdn), 0)       AS per_bai_brdn,
                COALESCE(SUM(arancel_cuotas), 0)     AS arancel_cuotas,
                COALESCE(SUM(promo_cuota_ahora), 0)  AS promo_cuota_ahora,
                COALESCE(SUM(dto_financ_cuotas), 0)  AS dto_financ_cuotas,
                COALESCE(SUM(perc_iva_1_5), 0)       AS perc_iva_1_5,
                COALESCE(SUM(perc_iva_3), 0)         AS perc_iva_3,
                COALESCE(SUM(cargo_terminal), 0)     AS cargo_terminal,
                COALESCE(SUM(qr_perc_iva), 0)        AS qr_perc_iva,
                COALESCE(SUM(qr_ret_iibb), 0)        AS qr_ret_iibb
            FROM fiserv_liquidaciones l
            $where
        ");
        $desc->execute($params);
        $descuentos = $desc->fetch();

        // Evolución mensual (ventas vs acreditado)
        $mensual = $pdo->prepare("
            SELECT
                DATE_FORMAT(l.fecha_pago, '%Y-%m') AS mes,
                SUM(l.ventas_contado)               AS ventas,
                SUM(l.acreditado)                   AS acreditado
            FROM fiserv_liquidaciones l
            $where
            GROUP BY mes
            ORDER BY mes
        ");
        $mensual->execute($params);
        $evolucion = $mensual->fetchAll();

        // Últimos lotes
        $lotes = $pdo->query("
            SELECT id, codigo, archivo_nombre, tarjeta, periodo, total_filas, neto_pagos, created_at
            FROM fiserv_lotes
            ORDER BY created_at DESC
            LIMIT 10
        ")->fetchAll();

        echo json_encode([
            'totales'    => $totals,
            'descuentos' => $descuentos,
            'evolucion'  => $evolucion,
            'lotes'      => $lotes,
        ]);
        break;

    // ── Listado de lotes ───────────────────────────────────────────────────
    case 'lotes':
        $lotes = $pdo->query("
            SELECT id, codigo, archivo_nombre, tarjeta, periodo, nro_comercio,
                   total_filas, total_presentado, neto_pagos, created_at
            FROM fiserv_lotes
            ORDER BY created_at DESC
        ")->fetchAll();
        echo json_encode(['lotes' => $lotes]);
        break;

    // ── Detalle de un lote (filas para Excel) ─────────────────────────────
    case 'detalle_lote':
        $lote_id = (int)($_GET['lote_id'] ?? 0);
        if (!$lote_id) { echo json_encode(['error' => 'lote_id requerido']); break; }

        $lote = $pdo->prepare("SELECT * FROM fiserv_lotes WHERE id = ?");
        $lote->execute([$lote_id]);
        $loteData = $lote->fetch();
        if (!$loteData) { echo json_encode(['error' => 'Lote no encontrado']); break; }

        $rows = $pdo->prepare("
            SELECT nro_liq, fecha_pago, fecha_pres,
                   ventas_contado, arancel, iva_arancel, arancel_cuotas, iva_arancel_cuotas,
                   promo_cuota_ahora, dto_financ_cuotas, iva_ri_dto_financ, dto_ventas_fin_adq,
                   per_bai_brdn, ret_iibb_sirtac, iva_promo_cuota, iva_dto_fin_adq,
                   perc_iva_1_5, perc_iva_3, cargo_terminal, cargo_sist_cuotas, iva_ri_sist_cuotas,
                   qr_perc_iva, qr_ret_iibb, total_descuentos, acreditado
            FROM fiserv_liquidaciones
            WHERE lote_id = ?
            ORDER BY fecha_pago, id
        ");
        $rows->execute([$lote_id]);

        echo json_encode([
            'lote' => $loteData,
            'rows' => $rows->fetchAll(),
        ]);
        break;

    // ── Eliminar lote ──────────────────────────────────────────────────────
    case 'eliminar_lote':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST requerido']); break; }
        $lote_id = (int)($_POST['lote_id'] ?? 0);
        if (!$lote_id) { echo json_encode(['error' => 'lote_id requerido']); break; }

        $pdo->prepare("DELETE FROM fiserv_lotes WHERE id = ?")->execute([$lote_id]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}
