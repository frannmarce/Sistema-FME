<?php
require __DIR__ . '/db.php';

try {
    $sql = "SELECT 
                p.id_producto,
                p.nombre_producto,
                p.precio_producto,
                p.stock_producto,
                c.nombre_categoria,
                pr.nombre_proveedor
            FROM Producto p
            LEFT JOIN Categoria c  ON p.id_categoria = c.id_categoria
            LEFT JOIN Proveedor pr ON p.id_proveedor = pr.id_proveedor
            ORDER BY p.id_producto ASC";

    $stmt = $pdo->query($sql);

    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="productos_fme.csv"');

    
    $output = fopen('php://output', 'w');

    
    fputcsv($output, [
        'ID Producto',
        'Nombre producto',
        'Precio',
        'Stock',
        'Categoría',
        'Proveedor'
    ], ';'); 

    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id_producto'],
            $row['nombre_producto'],
            $row['precio_producto'],
            $row['stock_producto'],
            $row['nombre_categoria'],
            $row['nombre_proveedor']
        ], ';');
    }

    fclose($output);
    exit;

} catch (Throwable $e) {
    
    echo "No se pudo generar el archivo de productos.";
    
}
