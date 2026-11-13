<?php

require_once __DIR__ . '/../app/core/helpers.php';
require_once __DIR__ . '/../app/config/db.php';

if (!isset($_SESSION['auth_id'], $_SESSION['auth_rol'])) {
    header('Location: ../login.php');
    exit;
}

$rolId = (int)$_SESSION['auth_rol'];
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
    flash('error', 'No tienes permiso para exportar ventas.');
    header('Location: ../index.php');
    exit;
}

$fDesde = trim((string)($_GET['desde'] ?? ''));
$fHasta = trim((string)($_GET['hasta'] ?? ''));
$fMedio = (int)($_GET['medio'] ?? 0);
$fUsuario = trim((string)($_GET['usuario'] ?? ''));
$fEstado = strtoupper(trim((string)($_GET['estado'] ?? '')));
if (!in_array($fEstado, ['', 'ACTIVA', 'CANCELADA'], true)) {
    $fEstado = '';
}

$where = ' WHERE 1=1';
$params = [];
if ($fDesde !== '') { $where .= ' AND DATE(v.fecha_venta) >= ?'; $params[] = $fDesde; }
if ($fHasta !== '') { $where .= ' AND DATE(v.fecha_venta) <= ?'; $params[] = $fHasta; }
if ($fMedio > 0) { $where .= ' AND EXISTS (SELECT 1 FROM Venta_Pago vpf WHERE vpf.id_venta = v.id_venta AND vpf.id_medio = ?)'; $params[] = $fMedio; }
if ($fUsuario !== '') { $where .= ' AND u.nombre_usuario LIKE ?'; $params[] = '%' . $fUsuario . '%'; }
if ($fEstado !== '') { $where .= ' AND v.estado_venta = ?'; $params[] = $fEstado; }

try {
    $sql = "SELECT v.id_venta, v.fecha_venta, v.total_venta, v.estado_venta,
                   u.nombre_usuario,
                   cv.fecha_cancelacion, cv.monto_reintegrado, cv.motivo_cancelacion,
                   COALESCE((SELECT SUM(dv2.cantidad_detalle) FROM Detalle_venta dv2 WHERE dv2.id_venta = v.id_venta), 0) AS unidades,
                   COALESCE((SELECT GROUP_CONCAT(CONCAT(p2.nombre_producto, ' x', dv2.cantidad_detalle)
                                                 ORDER BY p2.nombre_producto SEPARATOR ', ')
                             FROM Detalle_venta dv2
                             INNER JOIN Producto p2 ON p2.id_producto = dv2.id_producto
                             WHERE dv2.id_venta = v.id_venta), '-') AS detalle,
                   COALESCE((SELECT GROUP_CONCAT(CONCAT(mp2.nombre_medio, ': $', FORMAT(vp2.monto, 2))
                                                 ORDER BY mp2.nombre_medio SEPARATOR ' + ')
                             FROM Venta_Pago vp2
                             INNER JOIN Medio_pago mp2 ON mp2.id_medio = vp2.id_medio
                             WHERE vp2.id_venta = v.id_venta), '-') AS pagos_detalle,
                   COALESCE((SELECT SUM(CASE WHEN LOWER(mp3.nombre_medio) = 'efectivo' THEN vp3.monto ELSE 0 END)
                             FROM Venta_Pago vp3 INNER JOIN Medio_pago mp3 ON mp3.id_medio = vp3.id_medio
                             WHERE vp3.id_venta = v.id_venta), 0) AS pago_efectivo,
                   COALESCE((SELECT SUM(CASE WHEN LOWER(mp4.nombre_medio) = 'transferencia' THEN vp4.monto ELSE 0 END)
                             FROM Venta_Pago vp4 INNER JOIN Medio_pago mp4 ON mp4.id_medio = vp4.id_medio
                             WHERE vp4.id_venta = v.id_venta), 0) AS pago_transferencia,
                   COALESCE((SELECT SUM(CASE WHEN LOWER(mp5.nombre_medio) IN ('débito','debito') THEN vp5.monto ELSE 0 END)
                             FROM Venta_Pago vp5 INNER JOIN Medio_pago mp5 ON mp5.id_medio = vp5.id_medio
                             WHERE vp5.id_venta = v.id_venta), 0) AS pago_debito,
                   COALESCE((SELECT SUM(CASE WHEN LOWER(mp6.nombre_medio) IN ('crédito','credito') THEN vp6.monto ELSE 0 END)
                             FROM Venta_Pago vp6 INNER JOIN Medio_pago mp6 ON mp6.id_medio = vp6.id_medio
                             WHERE vp6.id_venta = v.id_venta), 0) AS pago_credito,
                   COALESCE((SELECT SUM(CASE WHEN LOWER(mp7.nombre_medio) NOT IN ('efectivo','transferencia','débito','debito','crédito','credito','mixto') THEN vp7.monto ELSE 0 END)
                             FROM Venta_Pago vp7 INNER JOIN Medio_pago mp7 ON mp7.id_medio = vp7.id_medio
                             WHERE vp7.id_venta = v.id_venta), 0) AS pago_otros
            FROM Venta v
            LEFT JOIN Cancelacion_Venta cv ON cv.id_venta = v.id_venta
            LEFT JOIN Usuario u ON v.id_usuario = u.id_usuario" . $where .
           " ORDER BY v.fecha_venta DESC, v.id_venta DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ventas_fme.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $output = fopen('php://output', 'w');
    if ($output === false) throw new RuntimeException('No se pudo abrir la salida CSV.');
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        'Nro. venta', 'Fecha', 'Usuario', 'Medios de pago', 'Efectivo', 'Transferencia', 'Debito', 'Credito', 'Otros medios',
        'Productos', 'Unidades', 'Total original', 'Estado', 'Monto reintegrado', 'Fecha reintegro', 'Motivo reintegro'
    ], ';');

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cancelada = strtoupper((string)$row['estado_venta']) === 'CANCELADA';
        fputcsv($output, [
            $row['id_venta'],
            $row['fecha_venta'],
            $row['nombre_usuario'] ?: '-',
            $row['pagos_detalle'] ?: '-',
            $row['pago_efectivo'],
            $row['pago_transferencia'],
            $row['pago_debito'],
            $row['pago_credito'],
            $row['pago_otros'],
            $row['detalle'] ?: '-',
            (int)$row['unidades'],
            $row['total_venta'],
            $cancelada ? 'CANCELADA' : 'ACTIVA',
            $cancelada ? ($row['monto_reintegrado'] ?? 0) : '',
            $cancelada ? ($row['fecha_cancelacion'] ?? '') : '',
            $cancelada ? ($row['motivo_cancelacion'] ?? '') : '',
        ], ';');
    }

    fclose($output);
    exit;
} catch (Throwable $e) {
    error_log('FME - Exportación de ventas: ' . $e->getMessage());
    flash('error', 'No se pudo generar el archivo de ventas.');
    header('Location: ../index.php?mod=ventas');
    exit;
}
