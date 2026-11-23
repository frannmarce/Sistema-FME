<?php
/* =========================
   ABM DE PROVEEDORES (POST)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $modActual === 'proveedores') {

  if (!isset($modulosPermitidosConArchivo['proveedores'])) {
    flash('error', 'No tienes permiso para administrar proveedores.');
    header('Location: index.php');
    exit;
  }

  $form = $_POST['form'] ?? '';

  if ($form === 'add_proveedor' || $form === 'edit_proveedor') {
    $idProveedor = (int)($_POST['id_proveedor'] ?? 0);
    $nombre      = trim($_POST['nombre_proveedor'] ?? '');
    $telefono    = trim($_POST['telefono_proveedor'] ?? '');
    $correo      = trim($_POST['correo_proveedor'] ?? '');
    $pais        = trim($_POST['nombre_pais'] ?? '');
    $ciudad      = trim($_POST['nombre_ciudad'] ?? '');
    $direccion   = trim($_POST['num_direccion'] ?? '');

    $errores = [];
    if ($form === 'edit_proveedor' && $idProveedor <= 0) $errores[] = 'ID de proveedor inválido.';
    if ($nombre === '')    $errores[] = 'Debe ingresar el nombre del proveedor.';
    if ($pais === '')      $errores[] = 'Debe ingresar el país.';
    if ($ciudad === '')    $errores[] = 'Debe ingresar la ciudad.';
    if ($direccion === '') $errores[] = 'Debe ingresar la dirección.';
    if (mb_strlen($nombre) > 80) $errores[] = 'El nombre del proveedor no puede superar los 80 caracteres.';
    if (mb_strlen($telefono) > 30) $errores[] = 'El teléfono no puede superar los 30 caracteres.';
    if (mb_strlen($correo) > 80) $errores[] = 'El correo no puede superar los 80 caracteres.';
    if (mb_strlen($pais) > 50) $errores[] = 'El país no puede superar los 50 caracteres.';
    if (mb_strlen($ciudad) > 50) $errores[] = 'La ciudad no puede superar los 50 caracteres.';
    if (mb_strlen($direccion) > 100) $errores[] = 'La dirección no puede superar los 100 caracteres.';
    if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
      $errores[] = 'El correo del proveedor no tiene un formato válido.';
    }

    if ($errores) {
      flash('error', implode(' ', $errores));
      $destino = $form === 'edit_proveedor'
        ? 'index.php?mod=proveedores&action=edit&id=' . $idProveedor
        : 'index.php?mod=proveedores&action=add';
      header('Location: ' . $destino);
      exit;
    }

    try {
      $pdo->beginTransaction();

      if ($form === 'add_proveedor') {
        $stmt = $pdo->prepare('INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion) VALUES (?, ?, ?)');
        $stmt->execute([$pais, $ciudad, $direccion]);
        $idDireccion = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT INTO Proveedor (nombre_proveedor, telefono_proveedor, correo_proveedor, id_direccion)
                               VALUES (?, ?, ?, ?)');
        $stmt->execute([$nombre, $telefono ?: null, $correo ?: null, $idDireccion]);
        $idProveedor = (int)$pdo->lastInsertId();

        flash('success', 'Proveedor creado correctamente.');
      } else {
        $stmt = $pdo->prepare('SELECT id_direccion FROM Proveedor WHERE id_proveedor = ? LIMIT 1');
        $stmt->execute([$idProveedor]);
        $idDireccion = (int)($stmt->fetchColumn() ?: 0);

        if ($idDireccion <= 0) {
          $stmt = $pdo->prepare('INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion) VALUES (?, ?, ?)');
          $stmt->execute([$pais, $ciudad, $direccion]);
          $idDireccion = (int)$pdo->lastInsertId();
        } else {
          $stmt = $pdo->prepare('UPDATE Direccion SET nombre_pais = ?, nombre_ciudad = ?, num_direccion = ? WHERE id_direccion = ?');
          $stmt->execute([$pais, $ciudad, $direccion, $idDireccion]);
        }

        $stmt = $pdo->prepare('UPDATE Proveedor
                               SET nombre_proveedor = ?, telefono_proveedor = ?, correo_proveedor = ?, id_direccion = ?
                               WHERE id_proveedor = ?');
        $stmt->execute([$nombre, $telefono ?: null, $correo ?: null, $idDireccion, $idProveedor]);

        flash('success', 'Proveedor actualizado correctamente.');
      }

      $pdo->commit();
      registrarAuditoria(
        $pdo,
        'proveedores',
        $form === 'add_proveedor' ? 'CREAR' : 'MODIFICAR',
        'Proveedor',
        $idProveedor,
        ['nombre' => $nombre]
      );
      header('Location: index.php?mod=proveedores');
      exit;

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      error_log('FME - Proveedor: ' . $e->getMessage());
      flash('error', 'No se pudo guardar el proveedor.');
      header('Location: index.php?mod=proveedores');
      exit;
    }
  }

  if ($form === 'archive_proveedor' || $form === 'reactivate_proveedor') {
    $idProveedor = (int)($_POST['id_proveedor'] ?? 0);
    $activar = $form === 'reactivate_proveedor';

    if ($idProveedor <= 0) {
      flash('error', 'ID de proveedor inválido.');
      header('Location: index.php?mod=proveedores');
      exit;
    }

    try {
      $stmt = $pdo->prepare('SELECT nombre_proveedor, activo_proveedor FROM Proveedor WHERE id_proveedor = ? LIMIT 1');
      $stmt->execute([$idProveedor]);
      $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$proveedor) {
        flash('error', 'No se encontró el proveedor.');
        header('Location: index.php?mod=proveedores');
        exit;
      }

      $nuevoEstado = $activar ? 1 : 0;
      if ((int)$proveedor['activo_proveedor'] === $nuevoEstado) {
        flash('success', $activar ? 'El proveedor ya estaba activo.' : 'El proveedor ya estaba inactivo.');
        header('Location: index.php?mod=proveedores');
        exit;
      }

      $stmt = $pdo->prepare('SELECT COUNT(*) FROM Producto WHERE id_proveedor = ?');
      $stmt->execute([$idProveedor]);
      $productosAsignados = (int)$stmt->fetchColumn();

      $stmt = $pdo->prepare('UPDATE Proveedor SET activo_proveedor = ? WHERE id_proveedor = ?');
      $stmt->execute([$nuevoEstado, $idProveedor]);

      registrarAuditoria(
        $pdo,
        'proveedores',
        $activar ? 'REACTIVAR' : 'DESACTIVAR',
        'Proveedor',
        $idProveedor,
        [
          'nombre' => (string)$proveedor['nombre_proveedor'],
          'activo' => $nuevoEstado,
          'productos_asignados' => $productosAsignados,
        ]
      );

      flash('success', $activar
        ? 'Proveedor reactivado correctamente. Vuelve a estar disponible para nuevas asignaciones.'
        : ($productosAsignados > 0
            ? 'Proveedor desactivado. Los productos existentes conservan la relación, pero ya no podrá asignarse a productos nuevos.'
            : 'Proveedor desactivado correctamente.'));
    } catch (Throwable $e) {
      error_log('FME - Estado de proveedor: ' . $e->getMessage());
      flash('error', 'No se pudo cambiar el estado del proveedor.');
    }

    header('Location: index.php?mod=proveedores');
    exit;
  }
}
