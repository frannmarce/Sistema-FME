<?php
// app/core/bootstrap.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../test.php';
require_once __DIR__ . '/../../db.php';

/* =========================
   SEGURIDAD / AUTENTICACIÓN
   ========================= */
if (!isset($_SESSION['auth_id'])) {
  header('Location: login.php');
  exit;
}

$usuario = $_SESSION['auth_user'] ?? 'Usuario';
$rolId   = (int)($_SESSION['auth_rol'] ?? 2); // 1=Admin, 2=Operador
$userId  = (int)$_SESSION['auth_id'];

$modActual = $_GET['mod'] ?? 'panel';
$action    = $_GET['action'] ?? 'list';

/* =========================
   PERFIL COMPLETO (Persona/Dirección)
   ========================= */
if (!isset($_SESSION['needs_profile'])) {
  $stmt = $pdo->prepare('SELECT id_persona FROM Usuario WHERE id_usuario = ?');
  $stmt->execute([$userId]);
  $row = $stmt->fetch();
  $_SESSION['needs_profile'] = $row && is_null($row['id_persona']);
}
$needsProfile = $_SESSION['needs_profile'] ?? false;

/* =========================
   FORM PERFIL USUARIO (POST)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $modActual === 'config_usuario') {

  $nombre     = trim($_POST['nombre_persona'] ?? '');
  $apellido   = trim($_POST['apellido_persona'] ?? '');
  $cuil       = trim($_POST['CUIL_persona'] ?? '');
  $telefono   = trim($_POST['telefono_persona'] ?? '');
  $pais       = trim($_POST['nombre_pais'] ?? '');
  $ciudad     = trim($_POST['nombre_ciudad'] ?? '');
  $direccion  = trim($_POST['num_direccion'] ?? '');

  $errores = [];
  if ($nombre === '')    $errores[] = 'Falta el nombre.';
  if ($apellido === '')  $errores[] = 'Falta el apellido.';
  if ($cuil === '')      $errores[] = 'Falta el CUIL.';
  if ($pais === '')      $errores[] = 'Falta el país.';
  if ($ciudad === '')    $errores[] = 'Falta la ciudad.';
  if ($direccion === '') $errores[] = 'Falta la dirección.';

  if ($errores) {
    flash('error', implode(' ', $errores));
    header('Location: index.php?mod=config_usuario');
    exit;
  }

  try {
    $pdo->beginTransaction();

    // Dirección
    $stmt = $pdo->prepare('INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion) VALUES (?,?,?)');
    $stmt->execute([$pais, $ciudad, $direccion]);
    $id_direccion = (int)$pdo->lastInsertId();

    // Persona
    $stmt = $pdo->prepare('INSERT INTO Persona (nombre_persona, apellido_persona, CUIL_persona, telefono_persona, id_direccion)
                           VALUES (?,?,?,?,?)');
    $stmt->execute([$nombre, $apellido, $cuil, $telefono ?: null, $id_direccion]);
    $id_persona = (int)$pdo->lastInsertId();

    // Usuario
    $stmt = $pdo->prepare('UPDATE Usuario SET id_persona = ? WHERE id_usuario = ?');
    $stmt->execute([$id_persona, $userId]);

    $pdo->commit();
    $_SESSION['needs_profile'] = false;

    flash('success', 'La información se ha guardado con éxito.');
    header('Location: index.php');
    exit;

  } catch (Throwable $e) {
    $pdo->rollBack();
    flash('error', 'No se pudo guardar la información.');
    header('Location: index.php?mod=config_usuario');
    exit;
  }
}

/* =========================
   EDICIÓN DE USUARIO (POST)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $modActual === 'usuarios') {

  $form = $_POST['form'] ?? '';

  if ($form === 'edit_usuario') {

    if ($rolId !== 1) {
      flash('error', 'No tienes permiso para editar usuarios.');
      header('Location: index.php');
      exit;
    }

    $id_usuario     = (int)($_POST['id_usuario'] ?? 0);
    $nombre_usuario = trim($_POST['nombre_usuario'] ?? '');
    $correo_usuario = trim($_POST['correo_usuario'] ?? '');
    $id_rol         = (int)($_POST['id_rol'] ?? 0);

    $errores = [];

    if ($id_usuario <= 0) {
      $errores[] = 'ID de usuario inválido.';
    }

    if ($id_usuario === $userId) {
      $errores[] = 'No puedes editar tu propio usuario desde Gestión de usuarios.';
    }

    if ($nombre_usuario === '') {
      $errores[] = 'Falta el nombre de usuario.';
    }

    if ($correo_usuario === '') {
      $errores[] = 'Falta el correo.';
    }

    if ($correo_usuario !== '' && !filter_var($correo_usuario, FILTER_VALIDATE_EMAIL)) {
      $errores[] = 'El correo no tiene un formato válido.';
    }

    if (!in_array($id_rol, [1, 2], true)) {
      $errores[] = 'Debe seleccionar un rol válido.';
    }

    if (!$errores) {
      $stmt = $pdo->prepare('SELECT id_usuario
                             FROM Usuario
                             WHERE nombre_usuario = ?
                             AND id_usuario <> ?
                             LIMIT 1');
      $stmt->execute([$nombre_usuario, $id_usuario]);

      if ($stmt->fetch()) {
        $errores[] = 'Ese nombre de usuario ya está en uso.';
      }
    }

    if (!$errores) {
      $stmt = $pdo->prepare('SELECT id_usuario
                             FROM Usuario
                             WHERE correo_usuario = ?
                             AND id_usuario <> ?
                             LIMIT 1');
      $stmt->execute([$correo_usuario, $id_usuario]);

      if ($stmt->fetch()) {
        $errores[] = 'Ese correo ya está en uso.';
      }
    }

    if ($errores) {
      flash('error', implode(' ', $errores));
      header('Location: index.php?mod=usuarios&action=edit&id=' . $id_usuario);
      exit;
    }

    try {
      $stmt = $pdo->prepare('UPDATE Usuario
                             SET nombre_usuario = ?,
                                 correo_usuario = ?,
                                 id_rol = ?
                             WHERE id_usuario = ?
                             AND id_usuario <> ?');

      $stmt->execute([
        $nombre_usuario,
        $correo_usuario,
        $id_rol,
        $id_usuario,
        $userId
      ]);

      if ($stmt->rowCount() > 0) {
        flash('success', 'El usuario se actualizó correctamente.');
      } else {
        flash('info', 'No se realizaron cambios o el usuario no se puede editar.');
      }

      header('Location: index.php?mod=usuarios');
      exit;

    } catch (Throwable $e) {
      flash('error', 'No se pudo actualizar el usuario.');
      header('Location: index.php?mod=usuarios&action=edit&id=' . $id_usuario);
      exit;
    }
  }
}

/* =========================
   ALTA / EDICIÓN / ELIMINACIÓN PRODUCTO (POST)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $modActual === 'productos') {

  $form = $_POST['form'] ?? '';

  if ($form === 'add_producto') {
    $nombre_prod  = trim($_POST['nombre_producto'] ?? '');
    $precio_prod  = trim($_POST['precio_producto'] ?? '');
    $stock_prod   = trim($_POST['stock_producto'] ?? '');
    $id_categoria = $_POST['id_categoria'] ?? '';
    $id_proveedor = $_POST['id_proveedor'] ?? '';

    $errores = [];
    if ($nombre_prod === '')                               $errores[] = 'Falta el nombre del producto.';
    if ($precio_prod === '')                               $errores[] = 'Falta el precio del producto.';
    if ($stock_prod === '')                                $errores[] = 'Falta el stock del producto.';
    if (!is_numeric($precio_prod) || $precio_prod < 0)     $errores[] = 'El precio no es válido.';
    if (!ctype_digit($stock_prod) || (int)$stock_prod < 0) $errores[] = 'El stock no es válido.';
    if ($id_categoria === '')                              $errores[] = 'Debe seleccionar una categoría.';
    if ($id_proveedor === '')                              $errores[] = 'Debe seleccionar un proveedor.';

    if ($errores) {
      flash('error', implode(' ', $errores));
      header('Location: index.php?mod=productos&action=add');
      exit;
    }

    try {
      $stmt = $pdo->prepare('INSERT INTO Producto (nombre_producto, precio_producto, stock_producto, id_categoria, id_proveedor)
                             VALUES (?,?,?,?,?)');
      $stmt->execute([
        $nombre_prod,
        (float)$precio_prod,
        (int)$stock_prod,
        (int)$id_categoria,
        (int)$id_proveedor
      ]);

      flash('success', 'El producto se ha agregado correctamente.');
      header('Location: index.php?mod=productos');
      exit;

    } catch (Throwable $e) {
      flash('error', 'No se pudo agregar el producto. Inténtalo nuevamente.');
      header('Location: index.php?mod=productos&action=add');
      exit;
    }

  } elseif ($form === 'edit_producto') {

    $id_producto  = (int)($_POST['id_producto'] ?? 0);
    $nombre_prod  = trim($_POST['nombre_producto'] ?? '');
    $precio_prod  = trim($_POST['precio_producto'] ?? '');
    $stock_prod   = trim($_POST['stock_producto'] ?? '');
    $id_categoria = $_POST['id_categoria'] ?? '';
    $id_proveedor = $_POST['id_proveedor'] ?? '';

    $errores = [];
    if ($id_producto <= 0)                                 $errores[] = 'ID de producto inválido.';
    if ($nombre_prod === '')                               $errores[] = 'Falta el nombre del producto.';
    if ($precio_prod === '')                               $errores[] = 'Falta el precio del producto.';
    if ($stock_prod === '')                                $errores[] = 'Falta el stock del producto.';
    if (!is_numeric($precio_prod) || $precio_prod < 0)     $errores[] = 'El precio no es válido.';
    if (!ctype_digit($stock_prod) || (int)$stock_prod < 0) $errores[] = 'El stock no es válido.';
    if ($id_categoria === '')                              $errores[] = 'Debe seleccionar una categoría.';
    if ($id_proveedor === '')                              $errores[] = 'Debe seleccionar un proveedor.';

    if ($errores) {
      flash('error', implode(' ', $errores));
      header('Location: index.php?mod=productos&action=edit&id=' . $id_producto);
      exit;
    }

    try {
      $stmt = $pdo->prepare('UPDATE Producto
                             SET nombre_producto = ?, precio_producto = ?, stock_producto = ?,
                                 id_categoria = ?, id_proveedor = ?
                             WHERE id_producto = ?');
      $stmt->execute([
        $nombre_prod,
        (float)$precio_prod,
        (int)$stock_prod,
        (int)$id_categoria,
        (int)$id_proveedor,
        $id_producto
      ]);

      flash('success', 'El producto se ha actualizado correctamente.');
      header('Location: index.php?mod=productos');
      exit;

    } catch (Throwable $e) {
      flash('error', 'No se pudo actualizar el producto.');
      header('Location: index.php?mod=productos&action=edit&id=' . $id_producto);
      exit;
    }

  } elseif ($form === 'delete_producto') {

    $id_producto = (int)($_POST['id_producto'] ?? 0);

    if ($id_producto <= 0) {
      flash('error', 'ID de producto inválido.');
      header('Location: index.php?mod=productos');
      exit;
    }

    try {
      $stmt = $pdo->prepare('DELETE FROM Producto WHERE id_producto = ?');
      $stmt->execute([$id_producto]);

      if ($stmt->rowCount() > 0) {
        flash('success', 'El producto se eliminó correctamente.');
      } else {
        flash('error', 'No se encontró el producto a eliminar.');
      }

      header('Location: index.php?mod=productos');
      exit;

    } catch (Throwable $e) {
      flash('error', 'No se puede eliminar el producto porque está relacionado con otros registros.');
      header('Location: index.php?mod=productos');
      exit;
    }
  }
}

/* =========================
   REGISTRAR MOVIMIENTO (POST)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $modActual === 'movimientos') {

  $form = $_POST['form'] ?? '';

  if ($form === 'add_movimiento') {

    $id_producto = (int)($_POST['id_producto'] ?? 0);
    $id_tipo     = (int)($_POST['id_tipo'] ?? 0);
    $id_motivo   = (int)($_POST['id_motivo'] ?? 0);
    $cantidad    = trim($_POST['cantidad_movimiento'] ?? '');

    $errores = [];
    if ($id_producto <= 0)                  $errores[] = 'Debe seleccionar un producto.';
    if ($id_tipo <= 0)                      $errores[] = 'Debe seleccionar un tipo de movimiento.';
    if ($id_motivo <= 0)                    $errores[] = 'Debe seleccionar un motivo.';
    if ($cantidad === '' || !ctype_digit($cantidad) || (int)$cantidad <= 0) {
      $errores[] = 'La cantidad debe ser un entero positivo.';
    }

    if ($errores) {
      flash('error', implode(' ', $errores));
      header('Location: index.php?mod=movimientos&action=add');
      exit;
    }

    $cantidadInt = (int)$cantidad;

    try {
      $pdo->beginTransaction();

      // Traer stock actual del producto
      $stmt = $pdo->prepare("SELECT stock_producto FROM Producto WHERE id_producto = ?");
      $stmt->execute([$id_producto]);
      $prod = $stmt->fetch();

      if (!$prod) {
        $pdo->rollBack();
        flash('error', 'Producto no encontrado.');
        header('Location: index.php?mod=movimientos&action=add');
        exit;
      }

      $stockActual = (int)$prod['stock_producto'];

      // Convención:
      // id_tipo = 1 => Entrada (suma stock)
      // id_tipo = 2 => Salida (resta stock)
      $delta = 0;

      if ($id_tipo === 1) {
        $delta = $cantidadInt; // Entrada
      } elseif ($id_tipo === 2) {
        if ($stockActual < $cantidadInt) {
          $pdo->rollBack();
          flash('error', 'No hay stock suficiente para realizar la salida.');
          header('Location: index.php?mod=movimientos&action=add');
          exit;
        }
        $delta = -$cantidadInt;
      } else {
        $delta = 0; // otros tipos sin impacto en stock
      }

      // Insertar movimiento
      $stmt = $pdo->prepare("INSERT INTO Movimiento
        (cantidad_movimiento, fecha_movimiento, id_producto, id_usuario, id_tipo, id_motivo)
        VALUES (?, NOW(), ?, ?, ?, ?)");
      $stmt->execute([
        $cantidadInt,
        $id_producto,
        $userId,
        $id_tipo,
        $id_motivo
      ]);

      // Actualizar stock si corresponde
      if ($delta !== 0) {
        $stmt = $pdo->prepare("UPDATE Producto SET stock_producto = stock_producto + ? WHERE id_producto = ?");
        $stmt->execute([$delta, $id_producto]);
      }

      $pdo->commit();

      flash('success', 'El movimiento se registró correctamente.');
      header('Location: index.php?mod=movimientos');
      exit;

    } catch (Throwable $e) {
      $pdo->rollBack();
      flash('error', 'No se pudo registrar el movimiento.');
      header('Location: index.php?mod=movimientos&action=add');
      exit;
    }
  }
}

/* =========================
   DEFINICIÓN DE MÓDULOS (para menú y tarjetas)
   ========================= */
$baseModulos = [
  ['id'=>'productos','icon'=>'📦','titulo'=>'Productos','url'=>'index.php?mod=productos'],
  ['id'=>'movimientos','icon'=>'📑','titulo'=>'Movimientos','url'=>'index.php?mod=movimientos'],
  ['id'=>'proveedores','icon'=>'🤝','titulo'=>'Proveedores','url'=>'#'],
];

$modConfigUsuario = [
  'id'=>'config-usuario','icon'=>'👤','titulo'=>'Configuración de usuario','url'=>'index.php?mod=config_usuario'
];

$modUsuarios = [
  'id'=>'usuarios','icon'=>'👥','titulo'=>'Gestión de usuarios','url'=>'index.php?mod=usuarios'
];

$modVentas = [
  'id'=>'ventas','icon'=>'🧾','titulo'=>'Facturación / Ventas','url'=>'index.php?mod=ventas'
];

$modReportes = [
  'id'=>'reportes','icon'=>'📄','titulo'=>'Reportes','url'=>'#'
];

if ($rolId === 1) {
  // Admin
  $modulos = array_merge($baseModulos, [$modConfigUsuario, $modUsuarios, $modVentas, $modReportes]);
} else {
  // Operador
  $modulos = array_merge($baseModulos, [$modConfigUsuario]);
}


