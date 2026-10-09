<?php

/**
 * Calcula el dígito verificador de una base EAN-13 de 12 dígitos.
 */
function fmeEan13CheckDigit(string $base12): int
{
    if (!preg_match('/^\d{12}$/', $base12)) {
        throw new InvalidArgumentException('La base EAN-13 debe contener exactamente 12 dígitos.');
    }

    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $digit = (int) $base12[$i];
        $sum += ($i % 2 === 0) ? $digit : ($digit * 3);
    }

    return (10 - ($sum % 10)) % 10;
}

/**
 * Valida longitud, formato y dígito verificador de un EAN-13.
 */
function fmeIsValidEan13(string $code): bool
{
    if (!preg_match('/^\d{13}$/', $code)) {
        return false;
    }

    $base12 = substr($code, 0, 12);
    return fmeEan13CheckDigit($base12) === (int) $code[12];
}

/**
 * Genera un EAN-13 interno para FME a partir del id del producto.
 * Se prueban prefijos 20..29 (rango de circulación restringida) para evitar
 * una eventual colisión con un EAN cargado manualmente.
 */
function fmeGenerateInternalEan13(PDO $pdo, int $idProducto): string
{
    if ($idProducto <= 0) {
        throw new InvalidArgumentException('ID de producto inválido para generar EAN-13.');
    }

    $idPart = str_pad((string) $idProducto, 10, '0', STR_PAD_LEFT);
    if (strlen($idPart) !== 10) {
        throw new RuntimeException('El ID del producto excede la capacidad del código EAN-13 interno.');
    }

    $check = $pdo->prepare('SELECT 1 FROM Producto WHERE codigo_barras = ? AND id_producto <> ? LIMIT 1');
    for ($prefix = 20; $prefix <= 29; $prefix++) {
        $base12 = (string) $prefix . $idPart;
        $ean13 = $base12 . fmeEan13CheckDigit($base12);
        $check->execute([$ean13, $idProducto]);
        if (!$check->fetchColumn()) {
            return $ean13;
        }
    }

    throw new RuntimeException('No se pudo generar un EAN-13 interno único para el producto.');
}

/* =========================
   PRODUCTOS (POST)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $modActual === 'productos') {
    $form = $_POST['form'] ?? '';

    /* =========================
       ALTA MÚLTIPLE DE PRODUCTOS
       ========================= */
    if ($form === 'add_productos') {
        $nombres      = $_POST['nombre_producto'] ?? [];
        $codigos      = $_POST['codigo_barras'] ?? [];
        $precios      = $_POST['precio_producto'] ?? [];
        $stocks       = $_POST['stock_producto'] ?? [];
        $categorias   = $_POST['id_categoria'] ?? [];
        $proveedores  = $_POST['id_proveedor'] ?? [];

        $errores = [];
        if (!is_array($nombres) || !is_array($codigos) || !is_array($precios) || !is_array($stocks) || !is_array($categorias) || !is_array($proveedores)) {
            $errores[] = 'Los datos enviados no tienen un formato válido.';
        }

        $cantidadFilas = is_array($nombres) ? count($nombres) : 0;
        if ($cantidadFilas <= 0) {
            $errores[] = 'Debe cargar al menos un producto.';
        }

        if (!$errores && (
            count($codigos) !== $cantidadFilas ||
            count($precios) !== $cantidadFilas ||
            count($stocks) !== $cantidadFilas ||
            count($categorias) !== $cantidadFilas ||
            count($proveedores) !== $cantidadFilas
        )) {
            $errores[] = 'Los datos de los productos están incompletos.';
        }

        $items = [];
        if (!$errores) {
            $idsCategoriasValidas = array_map('intval', $pdo->query('SELECT id_categoria FROM Categoria')->fetchAll(PDO::FETCH_COLUMN));
            $idsProveedoresValidos = array_map('intval', $pdo->query('SELECT id_proveedor FROM Proveedor')->fetchAll(PDO::FETCH_COLUMN));

            $codigosLote = [];
            for ($i = 0; $i < $cantidadFilas; $i++) {
                $fila = $i + 1;
                $nombre = trim((string)$nombres[$i]);
                $codigo = trim((string)$codigos[$i]);
                $precioRaw = trim((string)$precios[$i]);
                $stockRaw = trim((string)$stocks[$i]);
                $idCategoria = (int)$categorias[$i];
                $idProveedor = (int)$proveedores[$i];

                if ($nombre === '') $errores[] = "Fila {$fila}: falta el nombre del producto.";
                if (mb_strlen($nombre) > 80) $errores[] = "Fila {$fila}: el nombre no puede superar los 80 caracteres.";
                if ($codigo !== '') {
                    if (!fmeIsValidEan13($codigo)) {
                        $errores[] = "Fila {$fila}: el código de barras debe contener 13 dígitos válidos.";
                    } elseif (isset($codigosLote[$codigo])) {
                        $errores[] = "Fila {$fila}: el código de barras está repetido dentro del lote.";
                    } else {
                        $codigosLote[$codigo] = true;
                        $stmtCodigo = $pdo->prepare('SELECT 1 FROM Producto WHERE codigo_barras = ? LIMIT 1');
                        $stmtCodigo->execute([$codigo]);
                        if ($stmtCodigo->fetchColumn()) {
                            $errores[] = "Fila {$fila}: el código de barras ya está asignado a otro producto.";
                        }
                    }
                }
                if ($precioRaw === '' || !is_numeric($precioRaw) || (float)$precioRaw < 0) $errores[] = "Fila {$fila}: el precio no es válido.";
                if ($stockRaw === '' || !ctype_digit($stockRaw) || (int)$stockRaw < 0) $errores[] = "Fila {$fila}: el stock no es válido.";
                if ($idCategoria <= 0 || !in_array($idCategoria, $idsCategoriasValidas, true)) $errores[] = "Fila {$fila}: la categoría seleccionada no existe.";
                if ($idProveedor <= 0 || !in_array($idProveedor, $idsProveedoresValidos, true)) $errores[] = "Fila {$fila}: el proveedor seleccionado no existe.";

                $items[] = [
                    'nombre' => $nombre,
                    'codigo_barras' => $codigo !== '' ? $codigo : null,
                    'precio' => round((float)$precioRaw, 2),
                    'stock' => (int)$stockRaw,
                    'id_categoria' => $idCategoria,
                    'id_proveedor' => $idProveedor,
                ];
            }
        }

        if ($errores) {
            flash('error', implode(' ', $errores));
            header('Location: index.php?mod=productos&action=add');
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO Producto
                 (nombre_producto, codigo_barras, precio_producto, stock_producto, id_categoria, id_proveedor, activo_producto)
                 VALUES (?, ?, ?, ?, ?, ?, 1)'
            );
            $stmtCodigoAuto = $pdo->prepare('UPDATE Producto SET codigo_barras = ? WHERE id_producto = ?');

            $idsCreados = [];
            foreach ($items as $item) {
                $stmt->execute([
                    $item['nombre'],
                    $item['codigo_barras'],
                    $item['precio'],
                    $item['stock'],
                    $item['id_categoria'],
                    $item['id_proveedor'],
                ]);

                $idProducto = (int)$pdo->lastInsertId();
                $idsCreados[] = $idProducto;
                $codigoFinal = $item['codigo_barras'];

                if ($codigoFinal === null) {
                    $codigoFinal = fmeGenerateInternalEan13($pdo, $idProducto);
                    $stmtCodigoAuto->execute([$codigoFinal, $idProducto]);
                }

                registrarAuditoria($pdo, 'productos', 'CREAR', 'Producto', $idProducto, [
                    'nombre' => $item['nombre'],
                    'codigo_barras' => $codigoFinal,
                    'precio' => $item['precio'],
                    'stock' => $item['stock'],
                    'alta_multiple' => count($items) > 1,
                ]);
            }

            $pdo->commit();
            $cantidad = count($idsCreados);
            flash('success', $cantidad === 1
                ? 'El producto se agregó correctamente.'
                : "Se agregaron {$cantidad} productos correctamente en una sola operación.");
            header('Location: index.php?mod=productos');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('FME - Alta múltiple de productos: ' . $e->getMessage());
            flash('error', 'No se pudieron guardar los productos. No se registró ningún producto del lote.');
            header('Location: index.php?mod=productos&action=add');
            exit;
        }
    }

    /* =========================
       EDICIÓN DE PRODUCTO
       ========================= */
    if ($form === 'edit_producto') {
        $idProducto  = (int) ($_POST['id_producto'] ?? 0);
        $nombre      = trim((string) ($_POST['nombre_producto'] ?? ''));
        $codigo      = trim((string) ($_POST['codigo_barras'] ?? ''));
        $precioRaw   = trim((string) ($_POST['precio_producto'] ?? ''));
        $stockRaw    = trim((string) ($_POST['stock_producto'] ?? ''));
        $idCategoria = (int) ($_POST['id_categoria'] ?? 0);
        $idProveedor = (int) ($_POST['id_proveedor'] ?? 0);

        $errores = [];
        if ($idProducto <= 0) $errores[] = 'ID de producto inválido.';
        if ($nombre === '') $errores[] = 'Falta el nombre del producto.';
        if (mb_strlen($nombre) > 80) $errores[] = 'El nombre no puede superar los 80 caracteres.';
        if ($codigo !== '' && !fmeIsValidEan13($codigo)) $errores[] = 'El código de barras debe contener 13 dígitos válidos.';
        if ($precioRaw === '' || !is_numeric($precioRaw) || (float) $precioRaw < 0) $errores[] = 'El precio no es válido.';
        if ($stockRaw === '' || !ctype_digit($stockRaw) || (int) $stockRaw < 0) $errores[] = 'El stock no es válido.';
        if ($idCategoria <= 0) $errores[] = 'Debe seleccionar una categoría.';
        if ($idProveedor <= 0) $errores[] = 'Debe seleccionar un proveedor.';

        if (!$errores) {
            $stmt = $pdo->prepare('SELECT 1 FROM Categoria WHERE id_categoria = ? LIMIT 1');
            $stmt->execute([$idCategoria]);
            if (!$stmt->fetchColumn()) $errores[] = 'La categoría seleccionada no existe.';

            $stmt = $pdo->prepare('SELECT 1 FROM Proveedor WHERE id_proveedor = ? LIMIT 1');
            $stmt->execute([$idProveedor]);
            if (!$stmt->fetchColumn()) $errores[] = 'El proveedor seleccionado no existe.';

            $stmt = $pdo->prepare('SELECT 1 FROM Producto WHERE id_producto = ? LIMIT 1');
            $stmt->execute([$idProducto]);
            if (!$stmt->fetchColumn()) {
                $errores[] = 'El producto a editar no existe.';
            } elseif ($codigo !== '') {
                $stmt = $pdo->prepare('SELECT 1 FROM Producto WHERE codigo_barras = ? AND id_producto <> ? LIMIT 1');
                $stmt->execute([$codigo, $idProducto]);
                if ($stmt->fetchColumn()) $errores[] = 'El código de barras ya está asignado a otro producto.';
            }
        }

        if ($errores) {
            flash('error', implode(' ', $errores));
            header('Location: index.php?mod=productos&action=edit&id=' . $idProducto);
            exit;
        }

        $precio = round((float) $precioRaw, 2);
        $stock = (int) $stockRaw;

        try {
            if ($codigo === '') {
                $codigo = fmeGenerateInternalEan13($pdo, $idProducto);
            }

            $stmt = $pdo->prepare(
                'UPDATE Producto
                 SET nombre_producto = ?, codigo_barras = ?, precio_producto = ?, stock_producto = ?,
                     id_categoria = ?, id_proveedor = ?
                 WHERE id_producto = ?'
            );
            $stmt->execute([$nombre, $codigo, $precio, $stock, $idCategoria, $idProveedor, $idProducto]);
            registrarAuditoria($pdo, 'productos', 'MODIFICAR', 'Producto', $idProducto, [
                'nombre' => $nombre,
                'codigo_barras' => $codigo,
                'precio' => $precio,
                'stock' => $stock,
            ]);
            flash('success', 'El producto se actualizó correctamente.');
            header('Location: index.php?mod=productos');
            exit;
        } catch (Throwable $e) {
            error_log('FME - Producto: ' . $e->getMessage());
            flash('error', 'No se pudo actualizar el producto.');
            header('Location: index.php?mod=productos&action=edit&id=' . $idProducto);
            exit;
        }
    }

    /* =========================
       ARCHIVAR / REACTIVAR
       ========================= */
    if ($form === 'archive_producto' || $form === 'reactivate_producto') {
        $idProducto = (int) ($_POST['id_producto'] ?? 0);
        $activar = $form === 'reactivate_producto';

        if ($idProducto <= 0) {
            flash('error', 'ID de producto inválido.');
            header('Location: index.php?mod=productos');
            exit;
        }

        try {
            $stmt = $pdo->prepare('SELECT nombre_producto, activo_producto FROM Producto WHERE id_producto = ? LIMIT 1');
            $stmt->execute([$idProducto]);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$producto) {
                flash('error', 'No se encontró el producto.');
                header('Location: index.php?mod=productos');
                exit;
            }

            $nuevoEstado = $activar ? 1 : 0;
            if ((int)$producto['activo_producto'] === $nuevoEstado) {
                flash('success', $activar ? 'El producto ya estaba activo.' : 'El producto ya estaba archivado.');
                header('Location: index.php?mod=productos');
                exit;
            }

            $stmt = $pdo->prepare('UPDATE Producto SET activo_producto = ? WHERE id_producto = ?');
            $stmt->execute([$nuevoEstado, $idProducto]);

            registrarAuditoria($pdo, 'productos', $activar ? 'REACTIVAR' : 'ARCHIVAR', 'Producto', $idProducto, [
                'nombre' => (string)$producto['nombre_producto'],
                'activo' => $nuevoEstado,
            ]);

            flash('success', $activar
                ? 'El producto fue reactivado y vuelve a estar disponible para nuevas operaciones.'
                : 'El producto fue archivado. Su historial se conserva, pero ya no estará disponible para nuevas ventas o movimientos manuales.');
        } catch (Throwable $e) {
            error_log('FME - Estado de producto: ' . $e->getMessage());
            flash('error', 'No se pudo cambiar el estado del producto.');
        }

        header('Location: index.php?mod=productos');
        exit;
    }
}
