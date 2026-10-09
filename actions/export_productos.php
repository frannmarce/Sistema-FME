<?php

require_once __DIR__ . '/../app/core/helpers.php';
require_once __DIR__ . '/../app/config/db.php';

if (!isset($_SESSION['auth_id'], $_SESSION['auth_rol'])) {
    header('Location: ../login.php');
    exit;
}

$rolId = (int) $_SESSION['auth_rol'];
$stmt = $pdo->prepare(
    "SELECT 1
     FROM Rol_Modulo rm
     INNER JOIN Modulo m ON rm.id_modulo = m.id_modulo
     WHERE rm.id_rol = ?
       AND m.codigo_modulo = 'productos'
       AND m.activo = 1
     LIMIT 1"
);
$stmt->execute([$rolId]);

if (!$stmt->fetchColumn()) {
    flash('error', 'No tienes permiso para exportar productos.');
    header('Location: ../index.php');
    exit;
}

try {
    $stmt = $pdo->query(
        'SELECT p.id_producto,
                p.nombre_producto,
                p.codigo_barras,
                p.precio_producto,
                p.stock_producto,
                p.activo_producto,
                c.nombre_categoria,
                pr.nombre_proveedor
         FROM Producto p
         LEFT JOIN Categoria c ON p.id_categoria = c.id_categoria
         LEFT JOIN Proveedor pr ON p.id_proveedor = pr.id_proveedor
         ORDER BY p.id_producto ASC'
    );

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="productos_fme.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        throw new RuntimeException('No se pudo abrir la salida CSV.');
    }

    // BOM UTF-8 para que Excel interprete correctamente acentos y ñ.
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        'ID Producto',
        'Nombre producto',
        'Código de barras',
        'Precio',
        'Stock',
        'Estado',
        'Categoría',
        'Proveedor',
    ], ';');

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id_producto'],
            $row['nombre_producto'],
            $row['codigo_barras'],
            $row['precio_producto'],
            $row['stock_producto'],
            ((int)$row['activo_producto'] === 1 ? 'Activo' : 'Archivado'),
            $row['nombre_categoria'],
            $row['nombre_proveedor'],
        ], ';');
    }

    fclose($output);
    exit;
} catch (Throwable $e) {
    error_log('FME - Exportación de productos: ' . $e->getMessage());
    flash('error', 'No se pudo generar el archivo de productos.');
    header('Location: ../index.php?mod=productos');
    exit;
}
