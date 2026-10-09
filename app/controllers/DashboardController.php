<?php

$dashboardKpis = [
    'productos' => 0,
    'stock_total' => 0,
    'stock_bajo' => 0,
    'agotados' => 0,
];
$dashboardStockAlertas = [];
$dashboardActividad = [];
$dashboardError = null;

if ($modActual === 'panel') {
    try {
        $stmt = $pdo->query(
            'SELECT COUNT(*) AS productos,
                    COALESCE(SUM(stock_producto), 0) AS stock_total,
                    COALESCE(SUM(CASE WHEN stock_producto BETWEEN 1 AND 5 THEN 1 ELSE 0 END), 0) AS stock_bajo,
                    COALESCE(SUM(CASE WHEN stock_producto = 0 THEN 1 ELSE 0 END), 0) AS agotados
             FROM Producto
             WHERE activo_producto = 1'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $dashboardKpis = [
            'productos' => (int) ($row['productos'] ?? 0),
            'stock_total' => (int) ($row['stock_total'] ?? 0),
            'stock_bajo' => (int) ($row['stock_bajo'] ?? 0),
            'agotados' => (int) ($row['agotados'] ?? 0),
        ];

        $stmt = $pdo->query(
            'SELECT id_producto, nombre_producto, stock_producto
             FROM Producto
             WHERE activo_producto = 1
               AND stock_producto <= 5
             ORDER BY stock_producto ASC, nombre_producto ASC
             LIMIT 6'
        );
        $dashboardStockAlertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('FME - Dashboard inventario: ' . $e->getMessage());
        $dashboardError = 'No se pudo cargar el resumen de inventario.';
    }

    try {
        $stmt = $pdo->query(
            'SELECT usuario_nombre, modulo, accion, entidad, id_registro, fecha_auditoria
             FROM Auditoria
             ORDER BY fecha_auditoria DESC, id_auditoria DESC
             LIMIT 7'
        );
        $dashboardActividad = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // La ausencia temporal de auditoría no debe impedir usar el panel.
        error_log('FME - Dashboard actividad: ' . $e->getMessage());
        $dashboardActividad = [];
    }
}
