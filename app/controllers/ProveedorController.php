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

  if ($form === 'delete_proveedor') {
    $idProveedor = (int)($_POST['id_proveedor'] ?? 0);

    if ($idProveedor <= 0) {
      flash('error', 'ID de proveedor inválido.');
      header('Location: index.php?mod=proveedores');
      exit;
    }

    try {
      $pdo->beginTransaction();

      $stmt = $pdo->prepare('SELECT COUNT(*) FROM Producto WHERE id_proveedor = ?');
      $stmt->execute([$idProveedor]);
      if ((int)$stmt->fetchColumn() > 0) {
        $pdo->rollBack();
        flash('error', 'No se puede eliminar el proveedor porque tiene productos asignados.');
        header('Location: index.php?mod=proveedores');
        exit;
      }

      $stmt = $pdo->prepare('SELECT id_direccion FROM Proveedor WHERE id_proveedor = ? LIMIT 1');
      $stmt->execute([$idProveedor]);
      $idDireccion = (int)($stmt->fetchColumn() ?: 0);

      $stmt = $pdo->prepare('SELECT nombre_proveedor FROM Proveedor WHERE id_proveedor = ? LIMIT 1');
      $stmt->execute([$idProveedor]);
      $nombreProveedorEliminado = (string)($stmt->fetchColumn() ?: '');

      $stmt = $pdo->prepare('DELETE FROM Proveedor WHERE id_proveedor = ?');
      $stmt->execute([$idProveedor]);

      if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        flash('error', 'No se encontró el proveedor a eliminar.');
        header('Location: index.php?mod=proveedores');
        exit;
      }

      // Limpiamos la dirección solo si ya no está vinculada a ninguna persona/proveedor.
      if ($idDireccion > 0) {
        $stmt = $pdo->prepare('SELECT
          (SELECT COUNT(*) FROM Persona WHERE id_direccion = ?) +
          (SELECT COUNT(*) FROM Proveedor WHERE id_direccion = ?)');
        $stmt->execute([$idDireccion, $idDireccion]);
        if ((int)$stmt->fetchColumn() === 0) {
          $stmt = $pdo->prepare('DELETE FROM Direccion WHERE id_direccion = ?');
          $stmt->execute([$idDireccion]);
        }
      }

      $pdo->commit();
      registrarAuditoria($pdo, 'proveedores', 'ELIMINAR', 'Proveedor', $idProveedor, [
        'nombre' => $nombreProveedorEliminado,
      ]);
      flash('success', 'Proveedor eliminado correctamente.');
      header('Location: index.php?mod=proveedores');
      exit;

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      error_log('FME - Eliminar proveedor: ' . $e->getMessage());
      flash('error', 'No se pudo eliminar el proveedor.');
      header('Location: index.php?mod=proveedores');
      exit;
    }
  }
}
