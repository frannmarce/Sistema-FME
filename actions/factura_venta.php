<?php

require_once __DIR__ . '/../app/core/helpers.php';
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/core/pdf_factura.php';

if (!isset($_SESSION['auth_id'], $_SESSION['auth_rol'])) {
    header('Location: ../login.php');
    exit;
}

$rolId = (int)$_SESSION['auth_rol'];
$idVenta = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$idVenta = $idVenta !== false && $idVenta !== null ? (int)$idVenta : 0;

if ($idVenta <= 0) {
    flash('error', 'La venta seleccionada no es válida.');
    header('Location: ../index.php?mod=ventas');
    exit;
}

// El PDF respeta el mismo permiso del módulo Ventas.
$stmt = $pdo->prepare(
    "SELECT 1
     FROM Rol_Modulo rm
     INNER JOIN Modulo m ON rm.id_modulo = m.id_modulo
     WHERE rm.id_rol = ?
       AND m.codigo_modulo = 'ventas'
       AND m.activo = 1
     LIMIT 1"
);
$stmt->execute([$rolId]);
if (!$stmt->fetchColumn()) {
    flash('error', 'No tienes permiso para consultar facturas de ventas.');
    header('Location: ../index.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT v.id_venta, v.fecha_venta, v.total_venta, v.estado_venta,
                u.nombre_usuario, u.correo_usuario,
                mp.nombre_medio,
                cv.id_cancelacion, cv.fecha_cancelacion, cv.monto_reintegrado, cv.motivo_cancelacion,
                uc.nombre_usuario AS usuario_cancelacion
         FROM Venta v
         INNER JOIN Usuario u ON u.id_usuario = v.id_usuario
         INNER JOIN Medio_pago mp ON mp.id_medio = v.id_medio
         LEFT JOIN Cancelacion_Venta cv ON cv.id_venta = v.id_venta
         LEFT JOIN Usuario uc ON uc.id_usuario = cv.id_usuario
         WHERE v.id_venta = ?
         LIMIT 1"
    );
    $stmt->execute([$idVenta]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        flash('error', 'La venta solicitada no existe.');
        header('Location: ../index.php?mod=ventas');
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT p.nombre_producto, p.codigo_barras,
                dv.cantidad_detalle, dv.precio_unitario
         FROM Detalle_venta dv
         INNER JOIN Producto p ON p.id_producto = dv.id_producto
         WHERE dv.id_venta = ?
         ORDER BY p.nombre_producto ASC"
    );
    $stmt->execute([$idVenta]);
    $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$detalle) {
        throw new RuntimeException('La venta no tiene un detalle de productos disponible.');
    }

    $pdf = generarFacturaVentaPdf($venta, $detalle);
    $filename = 'factura_FME_' . str_pad((string)$idVenta, 6, '0', STR_PAD_LEFT) . '.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    echo $pdf;
    exit;
} catch (Throwable $e) {
    error_log('FME - Factura PDF venta #' . $idVenta . ': ' . $e->getMessage());
    flash('error', 'No se pudo generar la factura PDF de la venta seleccionada.');
    header('Location: ../index.php?mod=ventas');
    exit;
}
