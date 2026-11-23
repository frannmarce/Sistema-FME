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

  // Facturación/Ventas solo puede operar con un turno de caja abierto del día actual.
  $estadoCajaVenta = obtenerEstadoOperativoCaja($pdo);
  if (empty($estadoCajaVenta['habilita_ventas'])) {
    $mensajeCaja = (string)($estadoCajaVenta['mensaje'] ?? 'Debes abrir la caja antes de operar con ventas.');
    flash('error', $mensajeCaja . ' Ingresa al módulo Caja para continuar.');
    header('Location: index.php?mod=caja');
    exit;
  }
  $idCajaVenta = (int)($estadoCajaVenta['caja']['id_caja'] ?? 0);
  if ($idCajaVenta <= 0) {
    flash('error', 'No se pudo identificar el turno de caja abierto.');
    header('Location: index.php?mod=caja');
    exit;
  }

  /* =========================
     REGISTRAR VENTA
     ========================= */
  if ($form === 'add_venta') {
    $productosPost = $_POST['id_producto'] ?? [];
    $cantidadesPost = $_POST['cantidad'] ?? [];
    $mediosPost = $_POST['pago_id_medio'] ?? [];
    $montosPost = $_POST['pago_monto'] ?? [];

    $errores = [];
    if (!is_array($mediosPost) || !is_array($montosPost) || count($mediosPost) === 0) {
      $errores[] = 'Debe indicar al menos un medio de pago.';
    }
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

    // Agrupamos medios repetidos. El importe llega desde inputs number con dos decimales.
    $pagos = [];
    $cantidadPagos = min(count((array)$mediosPost), count((array)$montosPost));
    for ($i = 0; $i < $cantidadPagos; $i++) {
      $idMedioPago = (int)$mediosPost[$i];
      $montoRaw = trim((string)$montosPost[$i]);
      $montoNormalizado = str_replace(',', '.', $montoRaw);

      if ($idMedioPago <= 0 || $montoNormalizado === '' || !is_numeric($montoNormalizado)) {
        $errores[] = 'Todos los pagos deben tener un medio y un monto válido.';
        break;
      }

      $montoPago = round((float)$montoNormalizado, 2);
      if ($montoPago <= 0) {
        $errores[] = 'Los montos de pago deben ser mayores a cero.';
        break;
      }
      $pagos[$idMedioPago] = round(($pagos[$idMedioPago] ?? 0) + $montoPago, 2);
    }

    if (!$pagos && !$errores) {
      $errores[] = 'Debe indicar al menos un medio de pago.';
    }

    if ($errores) {
      flash('error', implode(' ', $errores));
      header('Location: index.php?mod=ventas&action=add');
      exit;
    }

    try {
      $pdo->beginTransaction();

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

      // Validamos todos los medios y evitamos que "Mixto" pueda cargarse como un pago real.
      $idsMedio = array_keys($pagos);
      $placeholders = implode(',', array_fill(0, count($idsMedio), '?'));
      $stmt = $pdo->prepare("SELECT id_medio, nombre_medio FROM Medio_pago WHERE id_medio IN ($placeholders)");
      $stmt->execute($idsMedio);
      $mediosValidos = [];
      $idMedioEfectivo = 0;
      foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $medio) {
        $nombreMedioNormalizado = mb_strtolower(trim((string)$medio['nombre_medio']));
        if (in_array($nombreMedioNormalizado, ['mixto', 'sin especificar'], true)) {
          throw new RuntimeException('El medio seleccionado es de uso interno y no puede utilizarse en ventas nuevas.');
        }
        $idMedioActual = (int)$medio['id_medio'];
        $mediosValidos[$idMedioActual] = (string)$medio['nombre_medio'];
        if ($nombreMedioNormalizado === 'efectivo') {
          $idMedioEfectivo = $idMedioActual;
        }
      }
      if (count($mediosValidos) !== count($idsMedio)) {
        throw new RuntimeException('Uno de los medios de pago seleccionados no existe.');
      }

      // El cliente puede entregar más dinero cuando hay efectivo. El excedente se devuelve como vuelto.
      // Venta_Pago.monto conserva solo el importe aplicado a la venta para no inflar Caja, reportes ni reintegros.
      $totalEntregado = round(array_sum($pagos), 2);
      if ($totalEntregado + 0.009 < $totalVenta) {
        throw new RuntimeException(
          'El importe entregado ($' . number_format($totalEntregado, 2, ',', '.') .
          ') no alcanza para cubrir el total de la venta ($' . number_format($totalVenta, 2, ',', '.') . ').'
        );
      }

      $vuelto = round(max(0, $totalEntregado - $totalVenta), 2);
      $pagosAplicados = $pagos;
      if ($vuelto > 0.009) {
        $efectivoEntregado = $idMedioEfectivo > 0 ? round((float)($pagos[$idMedioEfectivo] ?? 0), 2) : 0.0;
        if ($efectivoEntregado <= 0) {
          throw new RuntimeException('El excedente solo puede devolverse como vuelto cuando la venta incluye un pago en efectivo.');
        }
        if ($vuelto - $efectivoEntregado > 0.009) {
          throw new RuntimeException('El vuelto no puede superar el efectivo entregado por el cliente.');
        }
        $pagosAplicados[$idMedioEfectivo] = round($efectivoEntregado - $vuelto, 2);
      }

      if (count($pagos) > 1) {
        $stmt = $pdo->query("SELECT id_medio FROM Medio_pago WHERE LOWER(nombre_medio) = 'mixto' LIMIT 1");
        $idMedioVenta = (int)($stmt->fetchColumn() ?: 0);
        if ($idMedioVenta <= 0) {
          throw new RuntimeException('Falta ejecutar la migración de Pago mixto para configurar el medio interno Mixto.');
        }
      } else {
        $idMedioVenta = (int)array_key_first($pagos);
      }

      $stmt = $pdo->prepare("INSERT INTO Venta
        (fecha_venta, total_venta, estado_venta, id_usuario, id_medio, id_caja)
        VALUES (NOW(), ?, 'ACTIVA', ?, ?, ?)");
      $stmt->execute([$totalVenta, $userId, $idMedioVenta, $idCajaVenta]);
      $idVenta = (int)$pdo->lastInsertId();

      $stmtPago = $pdo->prepare('INSERT INTO Venta_Pago (id_venta, id_medio, monto, monto_entregado) VALUES (?, ?, ?, ?)');
      foreach ($pagos as $idMedioPago => $montoEntregado) {
        $montoAplicado = round((float)($pagosAplicados[$idMedioPago] ?? $montoEntregado), 2);
        $stmtPago->execute([$idVenta, (int)$idMedioPago, $montoAplicado, $montoEntregado]);
      }

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
        'medios_pago' => array_map(
          static fn($id, $montoEntregado) => [
            'id_medio' => (int)$id,
            'medio' => $mediosValidos[(int)$id] ?? 'Desconocido',
            'monto_aplicado' => (float)($pagosAplicados[(int)$id] ?? $montoEntregado),
            'monto_entregado' => (float)$montoEntregado,
          ],
          array_keys($pagos),
          array_values($pagos)
        ),
        'vuelto' => $vuelto,
        'caja_id' => $idCajaVenta,
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
         (id_venta, id_caja_reintegro, fecha_cancelacion, monto_reintegrado, motivo_cancelacion, id_usuario)
         VALUES (?, ?, NOW(), ?, ?, ?)"
      );
      $stmt->execute([$idVenta, $idCajaVenta, $montoReintegrado, $motivoCancelacion, $userId]);
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
        'id_caja_reintegro' => $idCajaVenta,
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
