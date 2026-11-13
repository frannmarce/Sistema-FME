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
       AND m.codigo_modulo = 'movimientos'
       AND m.activo = 1
     LIMIT 1"
);
$stmt->execute([$rolId]);
if (!$stmt->fetchColumn()) {
    flash('error', 'No tienes permiso para exportar movimientos.');
    header('Location: ../index.php');
    exit;
}

$fProducto = trim((string)($_GET['f_producto'] ?? ''));
$fUsuario = trim((string)($_GET['f_usuario'] ?? ''));
$fTipo = trim((string)($_GET['f_tipo'] ?? ''));
$fMotivo = trim((string)($_GET['f_motivo'] ?? ''));
$fDesde = trim((string)($_GET['f_desde'] ?? ''));
$fHasta = trim((string)($_GET['f_hasta'] ?? ''));
$fOrigen = strtoupper(trim((string)($_GET['f_origen'] ?? '')));
if (!in_array($fOrigen, ['', 'MANUAL', 'VENTA', 'CANCELACION_VENTA'], true)) {
    $fOrigen = '';
}

$where = ' WHERE 1=1';
$params = [];
if ($fProducto !== '') { $where .= ' AND p.nombre_producto LIKE ?'; $params[] = '%' . $fProducto . '%'; }
if ($fUsuario !== '') { $where .= ' AND u.nombre_usuario LIKE ?'; $params[] = '%' . $fUsuario . '%'; }
if ($fTipo !== '') { $where .= ' AND m.id_tipo = ?'; $params[] = $fTipo; }
if ($fMotivo !== '') { $where .= ' AND m.id_motivo = ?'; $params[] = $fMotivo; }
if ($fOrigen !== '') { $where .= ' AND m.origen_movimiento = ?'; $params[] = $fOrigen; }
if ($fDesde !== '') { $where .= ' AND DATE(m.fecha_movimiento) >= ?'; $params[] = $fDesde; }
if ($fHasta !== '') { $where .= ' AND DATE(m.fecha_movimiento) <= ?'; $params[] = $fHasta; }

try {
    $sql = "SELECT m.id_movimiento, m.fecha_movimiento, m.cantidad_movimiento,
                   p.nombre_producto, u.nombre_usuario, tm.nombre_tipo, mm.nombre_motivo,
                   m.origen_movimiento, m.id_venta, m.detalle_movimiento
            FROM Movimiento m
            LEFT JOIN Producto p ON m.id_producto = p.id_producto
            LEFT JOIN Usuario u ON m.id_usuario = u.id_usuario
            LEFT JOIN Tipo_movimiento tm ON m.id_tipo = tm.id_tipo
            LEFT JOIN Motivo_movimiento mm ON m.id_motivo = mm.id_motivo" . $where .
           " ORDER BY m.fecha_movimiento DESC, m.id_movimiento DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="movimientos_fme.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $output = fopen('php://output', 'w');
    if ($output === false) throw new RuntimeException('No se pudo abrir la salida CSV.');
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        'ID Movimiento', 'Fecha', 'Producto', 'Usuario', 'Tipo', 'Motivo',
        'Origen', 'Nro. venta relacionada', 'Cantidad', 'Detalle'
    ], ';');

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $origen = strtoupper((string)($row['origen_movimiento'] ?? 'MANUAL'));
        $origenTexto = match ($origen) {
            'VENTA' => 'Automatico - Venta',
            'CANCELACION_VENTA' => 'Automatico - Cancelacion de venta',
            default => 'Manual',
        };
        fputcsv($output, [
            $row['id_movimiento'],
            $row['fecha_movimiento'],
            $row['nombre_producto'] ?: '-',
            $row['nombre_usuario'] ?: '-',
            $row['nombre_tipo'] ?: '-',
            $row['nombre_motivo'] ?: '-',
            $origenTexto,
            $row['id_venta'] ?: '',
            (int)$row['cantidad_movimiento'],
            $row['detalle_movimiento'] ?: '',
        ], ';');
    }

    fclose($output);
    exit;
} catch (Throwable $e) {
    error_log('FME - Exportación de movimientos: ' . $e->getMessage());
    flash('error', 'No se pudo generar el archivo de movimientos.');
    header('Location: ../index.php?mod=movimientos');
    exit;
}
