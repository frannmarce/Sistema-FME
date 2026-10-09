<?php

/* =========================
   REGISTRAR MOVIMIENTO MANUAL (POST)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $modActual === 'movimientos') {
    $form = $_POST['form'] ?? '';

    if ($form === 'add_movimiento') {
        $idProducto = (int) ($_POST['id_producto'] ?? 0);
        $idTipo     = (int) ($_POST['id_tipo'] ?? 0);
        $idMotivo   = (int) ($_POST['id_motivo'] ?? 0);
        $cantidadRaw = trim((string) ($_POST['cantidad_movimiento'] ?? ''));

        $errores = [];
        if ($idProducto <= 0) $errores[] = 'Debe seleccionar un producto.';
        if ($idTipo <= 0) $errores[] = 'Debe seleccionar un tipo de movimiento.';
        if ($idMotivo <= 0) $errores[] = 'Debe seleccionar un motivo.';
        if ($cantidadRaw === '' || !ctype_digit($cantidadRaw) || (int) $cantidadRaw <= 0) {
            $errores[] = 'La cantidad debe ser un entero positivo.';
        }

        if ($errores) {
            flash('error', implode(' ', $errores));
            header('Location: index.php?mod=movimientos&action=add');
            exit;
        }

        $cantidad = (int) $cantidadRaw;

        try {
            $pdo->beginTransaction();

            // Se bloquea el producto durante la operación para evitar carreras de stock.
            $stmt = $pdo->prepare(
                'SELECT id_producto, stock_producto, activo_producto
                 FROM Producto
                 WHERE id_producto = ?
                 FOR UPDATE'
            );
            $stmt->execute([$idProducto]);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$producto) {
                throw new RuntimeException('Producto no encontrado.');
            }
            if ((int)$producto['activo_producto'] !== 1) {
                throw new RuntimeException('El producto seleccionado está archivado y no admite nuevos movimientos manuales.');
            }

            $stmt = $pdo->prepare('SELECT nombre_tipo FROM Tipo_movimiento WHERE id_tipo = ? LIMIT 1');
            $stmt->execute([$idTipo]);
            $tipoNombre = mb_strtolower(trim((string) ($stmt->fetchColumn() ?: '')));
            if ($tipoNombre === '') {
                throw new RuntimeException('El tipo de movimiento seleccionado no existe.');
            }

            $stmt = $pdo->prepare('SELECT nombre_motivo FROM Motivo_movimiento WHERE id_motivo = ? LIMIT 1');
            $stmt->execute([$idMotivo]);
            $motivoNombre = mb_strtolower(trim((string) ($stmt->fetchColumn() ?: '')));
            if ($motivoNombre === '') {
                throw new RuntimeException('El motivo seleccionado no existe.');
            }

            // Venta y Cancelación de venta son motivos reservados al sistema.
            if (in_array($motivoNombre, ['venta', 'cancelación de venta', 'cancelacion de venta'], true)) {
                throw new RuntimeException('Los movimientos vinculados a ventas se generan automáticamente. Use Reposición o Ajuste para movimientos manuales.');
            }

            $stockActual = (int) $producto['stock_producto'];
            $delta = 0;

            if ($tipoNombre === 'entrada') {
                $delta = $cantidad;
            } elseif ($tipoNombre === 'salida') {
                if ($stockActual < $cantidad) {
                    throw new RuntimeException('No hay stock suficiente para realizar la salida.');
                }
                $delta = -$cantidad;
            } else {
                throw new RuntimeException('El tipo de movimiento debe ser Entrada o Salida.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO Movimiento
                 (cantidad_movimiento, fecha_movimiento, id_producto, id_usuario, id_tipo, id_motivo,
                  origen_movimiento, id_venta, detalle_movimiento)
                 VALUES (?, NOW(), ?, ?, ?, ?, 'MANUAL', NULL, ?)"
            );
            $stmt->execute([
                $cantidad,
                $idProducto,
                $userId,
                $idTipo,
                $idMotivo,
                'Movimiento manual de inventario',
            ]);
            $idMovimiento = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'UPDATE Producto
                 SET stock_producto = stock_producto + ?
                 WHERE id_producto = ?'
            );
            $stmt->execute([$delta, $idProducto]);

            $pdo->commit();
            registrarAuditoria($pdo, 'movimientos', 'CREAR', 'Movimiento', $idMovimiento, [
                'producto_id' => $idProducto,
                'tipo' => $tipoNombre,
                'motivo' => $motivoNombre,
                'cantidad' => $cantidad,
                'origen' => 'MANUAL',
            ]);
            flash('success', 'El movimiento manual se registró correctamente.');
            header('Location: index.php?mod=movimientos');
            exit;
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $e->getMessage());
            header('Location: index.php?mod=movimientos&action=add');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('FME - Movimiento: ' . $e->getMessage());
            flash('error', 'No se pudo registrar el movimiento manual.');
            header('Location: index.php?mod=movimientos&action=add');
            exit;
        }
    }
}
