<?php
require_once dirname(__DIR__) . '/auth/check.php';
$titulo = 'Informes';
require_once __DIR__ . '/includes/header.php';
?>

<div class="empty-state" style="padding:80px 40px">
    <div style="font-size:48px;margin-bottom:16px">📊</div>
    <div style="font-size:20px;font-weight:700;margin-bottom:8px">Informes en desarrollo</div>
    <div style="color:var(--text-secondary);font-size:14px;max-width:400px;text-align:center">
        Esta sección va a mostrar los datos extraídos de las declaraciones juradas (IIBB, F.931 y Portal IVA)
        para armar el cuadro de ventas, compras y gastos.
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
