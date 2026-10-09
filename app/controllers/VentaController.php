<?php
/* =========================
   VENTAS (POST)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $modActual === 'ventas') {

  if (!isset($modulosPermitidosConArchivo['ventas'])) {
    flash('error', 'No tienes permiso para operar con ventas.');
    header('Location: index.php');
    exit;
  }

  $form = $_POST['form'] ?? '';

  /* =========================
     REGISTRAR VENTA
     ========================= */
  if ($form === 'add_venta') {
    $idMedio = (int)($_POST['id_medio'] ?? 0);
    $productosPost = $_POST['id_producto'] ?? [];
    $cantidadesPost = $_POST['cantidad'] ?? [];

    $errores = [];
    if ($idMedio <= 0) $errores[] = 'Debe seleccionar un medio de pago.';
    if (!is_array($productosPost) || !is_array($cantidadesPost) || count($productosPost) === 0) {
      $errores[] = 'Debe agregar al menos un producto a la venta.';
    }

    // Agrupamos productos repetidos y validamos cantidades antes de iniciar la transacción.
    $items = [];
    $cantidadFilas = min(count((array)$productosPost), count((array)$cantidadesPost));
    for ($i = 0; $i < $cantidadFilas; $i++) {
      $idProducto = (int)$productosPost[$i];
      $cantidadRaw = trim((string)$cantidadesPost[$i]);

      if ($idProducto <= 0 || $cantidadRaw === '' || !ctype_digit($cantidadRaw) || (int)$cantidadRaw <= 0) {
        $errores[] = 'Todos los productos deben tener una cantidad válida mayor a cero.';
        break;
      }

      $items[$idProducto] = ($items[$idProducto] ?? 0) + (int)$cantidadRaw;
    }

    if ($errores) {
      flash('error', implode(' ', $errores));
      header('Location: index.php?mod=ventas&action=add');
      exit;
    }

    try {
      $pdo->beginTransaction();

      $stmt = $pdo->prepare('SELECT id_medio FROM Medio_pago WHERE id_medio = ? LIMIT 1');
      $stmt->execute([$idMedio]);
      if (!$stmt->fetchColumn()) {
        throw new RuntimeException('El medio de pago seleccionado no existe.');
      }

      // Buscamos los IDs por nombre para no depender de IDs fijos en la base.
      $stmt = $pdo->query("SELECT id_tipo FROM Tipo_movimiento WHERE LOWER(nombre_tipo) = 'salida' LIMIT 1");
      $idTipoSalida = (int)($stmt->fetchColumn() ?: 0);
      $stmt = $pdo->query("SELECT id_motivo FROM Motivo_movimiento WHERE LOWER(nombre_motivo) = 'venta' LIMIT 1");
      $idMotivoVenta = (int)($stmt->fetchColumn() ?: 0);

      if ($idTipoSalida <= 0 || $idMotivoVenta <= 0) {
        throw new RuntimeException('Falta configurar el tipo de movimiento Salida o el motivo Venta.');
      }

      $detalle = [];
      $totalVenta = 0.0;
      $stmtProducto = $pdo->prepare('SELECT id_producto, nombre_producto, precio_producto, stock_producto, activo_producto
                                     FROM Producto WHERE id_producto = ? FOR UPDATE');

      foreach ($items as $idProducto => $cantidad) {
        $stmtProducto->execute([$idProducto]);
        $producto = $stmtProducto->fetch(PDO::FETCH_ASSOC);

        if (!$producto) {
          throw new RuntimeException('Uno de los productos seleccionados ya no existe.');
        }

        if ((int)$producto['activo_producto'] !== 1) {
          throw new RuntimeException('El producto ' . $producto['nombre_producto'] . ' está archivado y no puede incluirse en nuevas ventas.');
        }

        if ((int)$producto['stock_producto'] < $cantidad) {
          throw new RuntimeException('Stock insuficiente para ' . $producto['nombre_producto'] . '. Disponible: ' . (int)$producto['stock_producto'] . '.');
        }

        $precio = (float)$producto['precio_producto'];
        $subtotal = $precio * $cantidad;
        $totalVenta = round($totalVenta + $subtotal, 2);

        $detalle[] = [
          'id_producto' => (int)$idProducto,
          'cantidad' => $cantidad,
          'precio' => $precio,
        ];
      }

      if (!$detalle || $totalVenta <= 0) {
        throw new RuntimeException('La venta no contiene productos válidos.');
      }

      $stmt = $pdo->prepare("INSERT INTO Venta
        (fecha_venta, total_venta, estado_venta, id_usuario, id_medio)
        VALUES (NOW(), ?, 'ACTIVA', ?, ?)");
      $stmt->execute([$totalVenta, $userId, $idMedio]);
      $idVenta = (int)$pdo->lastInsertId();

      $stmtDetalle = $pdo->prepare('INSERT INTO Detalle_venta (id_venta, id_producto, cantidad_detalle, precio_unitario)
                                    VALUES (?, ?, ?, ?)');
      $stmtStock = $pdo->prepare('UPDATE Producto SET stock_producto = stock_producto - ? WHERE id_producto = ?');
      $stmtMovimiento = $pdo->prepare("INSERT INTO Movimiento
        (cantidad_movimiento, fecha_movimiento, id_producto, id_usuario, id_tipo, id_motivo,
         origen_movimiento, id_venta, detalle_movimiento)
        VALUES (?, NOW(), ?, ?, ?, ?, 'VENTA', ?, ?)");

      foreach ($detalle as $item) {
        $stmtDetalle->execute([$idVenta, $item['id_producto'], $item['cantidad'], $item['precio']]);
        $stmtStock->execute([$item['cantidad'], $item['id_producto']]);
        $stmtMovimiento->execute([
          $item['cantidad'],
          $item['id_producto'],
          $userId,
          $idTipoSalida,
          $idMotivoVenta,
          $idVenta,
          'Salida automática generada por la venta #' . $idVenta,
        ]);
      }

      $pdo->commit();
      registrarAuditoria($pdo, 'ventas', 'CREAR', 'Venta', $idVenta, [
        'total' => $totalVenta,
        'medio_pago_id' => $idMedio,
        'productos_distintos' => count($detalle),
        'estado' => 'ACTIVA',
      ]);
      flash('success', 'Venta registrada correctamente. N.º de venta: ' . $idVenta . '.');
      header('Location: index.php?mod=ventas');
      exit;

    } catch (RuntimeException $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      flash('error', $e->getMessage());
      header('Location: index.php?mod=ventas&action=add');
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      error_log('FME - Venta: ' . $e->getMessage());
      flash('error', 'No se pudo registrar la venta. No se aplicó ningún cambio de stock.');
      header('Location: index.php?mod=ventas&action=add');
      exit;
    }
  }

  /* =========================
     CANCELAR / ANULAR VENTA
     ========================= */
  if ($form === 'cancel_venta') {
    $idVenta = (int)($_POST['id_venta'] ?? 0);
    $motivoCancelacion = trim((string)($_POST['motivo_cancelacion'] ?? ''));

    if ($idVenta <= 0) {
      flash('error', 'La venta seleccionada no es válida.');
      header('Location: index.php?mod=ventas');
      exit;
    }

    if ($motivoCancelacion === '') {
      flash('error', 'Debe indicar el motivo de la cancelación.');
      header('Location: index.php?mod=ventas&action=cancel&id=' . $idVenta);
      exit;
    }

    if (mb_strlen($motivoCancelacion) > 255) {
      flash('error', 'El motivo de cancelación no puede superar los 255 caracteres.');
      header('Location: index.php?mod=ventas&action=cancel&id=' . $idVenta);
      exit;
    }

    try {
      $pdo->beginTransaction();

      // Bloqueamos la venta para impedir una doble cancelación concurrente.
      $stmt = $pdo->prepare(
        "SELECT id_venta, total_venta, estado_venta
         FROM Venta
         WHERE id_venta = ?
         FOR UPDATE"
      );
      $stmt->execute([$idVenta]);
      $venta = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$venta) {
        throw new RuntimeException('La venta ya no existe.');
      }

      // La cancelación es un registro independiente. UNIQUE(id_venta) también
      // evita que la misma venta pueda anularse dos veces.
      $stmt = $pdo->prepare(
        "SELECT id_cancelacion
         FROM Cancelacion_Venta
         WHERE id_venta = ?
         LIMIT 1
         FOR UPDATE"
      );
      $stmt->execute([$idVenta]);
      $idCancelacionExistente = (int)($stmt->fetchColumn() ?: 0);

      if (strtoupper((string)$venta['estado_venta']) === 'CANCELADA' || $idCancelacionExistente > 0) {
        $sufijo = $idCancelacionExistente > 0 ? ' (cancelación #' . $idCancelacionExistente . ')' : '';
        throw new RuntimeException('La venta #' . $idVenta . ' ya fue cancelada anteriormente' . $sufijo . '.');
      }

      // El stock que debe reponerse se obtiene del detalle histórico original.
      $stmt = $pdo->prepare(
        'SELECT dv.id_producto, dv.cantidad_detalle, p.nombre_producto
         FROM Detalle_venta dv
         INNER JOIN Producto p ON p.id_producto = dv.id_producto
         WHERE dv.id_venta = ?
         ORDER BY dv.id_producto
         FOR UPDATE'
      );
      $stmt->execute([$idVenta]);
      $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

      if (!$detalle) {
        throw new RuntimeException('La venta no tiene un detalle de productos válido para devolver el stock.');
      }

      $stmt = $pdo->query("SELECT id_tipo FROM Tipo_movimiento WHERE LOWER(nombre_tipo) = 'entrada' LIMIT 1");
      $idTipoEntrada = (int)($stmt->fetchColumn() ?: 0);
      $stmt = $pdo->query(
        "SELECT id_motivo
         FROM Motivo_movimiento
         WHERE LOWER(nombre_motivo) IN ('cancelación de venta', 'cancelacion de venta')
         LIMIT 1"
      );
      $idMotivoCancelacion = (int)($stmt->fetchColumn() ?: 0);

      if ($idTipoEntrada <= 0 || $idMotivoCancelacion <= 0) {
        throw new RuntimeException('Falta configurar el movimiento de Entrada o el motivo Cancelación de venta.');
      }

      $montoReintegrado = round((float)$venta['total_venta'], 2);

      // La Venta conserva sus datos comerciales originales. Solo cambia su
      // estado; la información de la anulación se inserta como un nuevo registro.
      $stmt = $pdo->prepare(
        "INSERT INTO Cancelacion_Venta
         (id_venta, fecha_cancelacion, monto_reintegrado, motivo_cancelacion, id_usuario)
         VALUES (?, NOW(), ?, ?, ?)"
      );
      $stmt->execute([$idVenta, $montoReintegrado, $motivoCancelacion, $userId]);
      $idCancelacion = (int)$pdo->lastInsertId();

      if ($idCancelacion <= 0) {
        throw new RuntimeException('No se pudo generar el registro de cancelación.');
      }

      $stmt = $pdo->prepare(
        "UPDATE Venta
         SET estado_venta = 'CANCELADA'
         WHERE id_venta = ?"
      );
      $stmt->execute([$idVenta]);

      $stmtStock = $pdo->prepare(
        'UPDATE Producto
         SET stock_producto = stock_producto + ?
         WHERE id_producto = ?'
      );
      $stmtMovimiento = $pdo->prepare(
        "INSERT INTO Movimiento
         (cantidad_movimiento, fecha_movimiento, id_producto, id_usuario, id_tipo, id_motivo,
          origen_movimiento, id_venta, detalle_movimiento)
         VALUES (?, NOW(), ?, ?, ?, ?, 'CANCELACION_VENTA', ?, ?)"
      );

      $unidadesDevueltas = 0;
      foreach ($detalle as $item) {
        $cantidad = (int)$item['cantidad_detalle'];
        $idProducto = (int)$item['id_producto'];
        if ($cantidad <= 0 || $idProducto <= 0) {
          throw new RuntimeException('La venta contiene un detalle inválido y no puede cancelarse.');
        }

        $stmtStock->execute([$cantidad, $idProducto]);
        $stmtMovimiento->execute([
          $cantidad,
          $idProducto,
          $userId,
          $idTipoEntrada,
          $idMotivoCancelacion,
          $idVenta,
          'Entrada automática por cancelación #' . $idCancelacion . ' de la venta #' . $idVenta,
        ]);
        $unidadesDevueltas += $cantidad;
      }

      $pdo->commit();

      registrarAuditoria($pdo, 'ventas', 'CANCELAR', 'Cancelacion_Venta', $idCancelacion, [
        'venta_original_id' => $idVenta,
        'total_original' => $montoReintegrado,
        'monto_reintegrado' => $montoReintegrado,
        'motivo' => $motivoCancelacion,
        'unidades_devueltas' => $unidadesDevueltas,
        'estado_venta' => 'CANCELADA',
      ]);

      flash(
        'success',
        'Venta #' . $idVenta . ' cancelada correctamente. Se creó la cancelación #' . $idCancelacion .
        ', se registró un reintegro de $' . number_format($montoReintegrado, 2, ',', '.') .
        ' y se devolvió el stock.'
      );
      header('Location: index.php?mod=ventas');
      exit;

    } catch (RuntimeException $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      flash('error', $e->getMessage());
      header('Location: index.php?mod=ventas');
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      error_log('FME - Cancelación de venta: ' . $e->getMessage());
      flash('error', 'No se pudo cancelar la venta. No se aplicó ningún reintegro ni cambio de stock.');
      header('Location: index.php?mod=ventas');
      exit;
    }
  }
}
