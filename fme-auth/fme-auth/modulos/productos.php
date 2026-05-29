<?php
// ===========================
// Módulo: Productos
// ===========================

// TRAER CATEGORÍAS
$stmt = $pdo->query("SELECT id_categoria, nombre_categoria FROM Categoria ORDER BY nombre_categoria ASC");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// TRAER PROVEEDORES
$stmt = $pdo->query("SELECT id_proveedor, nombre_proveedor FROM Proveedor ORDER BY nombre_proveedor ASC");
$proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// FILTROS (GET)
$busqueda      = trim($_GET['busqueda'] ?? '');
$filtro_cat    = $_GET['categoria']  ?? '';
$filtro_prov   = $_GET['proveedor']  ?? '';
$precio_min    = $_GET['precio_min'] ?? '';
$precio_max    = $_GET['precio_max'] ?? '';
$stock_min     = $_GET['stock_min']  ?? '';
$stock_max     = $_GET['stock_max']  ?? '';

$query = "
    SELECT 
        p.id_producto,
        p.nombre_producto,
        p.precio_producto,
        p.stock_producto,
        c.nombre_categoria,
        pr.nombre_proveedor
    FROM Producto p
    LEFT JOIN Categoria c  ON p.id_categoria = c.id_categoria
    LEFT JOIN Proveedor pr ON p.id_proveedor = pr.id_proveedor
    WHERE 1=1
";

$params = [];

// APLICAR FILTROS
if ($busqueda !== '') {
    $query .= " AND p.nombre_producto LIKE ?";
    $params[] = "%$busqueda%";
}

if ($filtro_cat !== '') {
    $query .= " AND p.id_categoria = ?";
    $params[] = $filtro_cat;
}

if ($filtro_prov !== '') {
    $query .= " AND p.id_proveedor = ?";
    $params[] = $filtro_prov;
}

if ($precio_min !== '') {
    $query .= " AND p.precio_producto >= ?";
    $params[] = $precio_min;
}

if ($precio_max !== '') {
    $query .= " AND p.precio_producto <= ?";
    $params[] = $precio_max;
}

if ($stock_min !== '') {
    $query .= " AND p.stock_producto >= ?";
    $params[] = $stock_min;
}

if ($stock_max !== '') {
    $query .= " AND p.stock_producto <= ?";
    $params[] = $stock_max;
}

$query .= " ORDER BY p.id_producto ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ===========================
// ALTA DE PRODUCTO
// ===========================
if ($action === 'add'):
?>
<h1 class="title">Agregar producto</h1>
<p class="subtitle">Completa los datos para registrar un nuevo producto.</p>

<?php if ($e = flash('error')): ?>
  <div class="alert error"><?= htmlspecialchars($e) ?></div>
<?php endif; ?>

<form method="post" action="index.php?mod=productos" class="profile-form">
  <input type="hidden" name="form" value="add_producto"/>

  <div class="profile-card">
    <div class="profile-card-header">
      <div class="profile-icon">➕</div>
      <div>
        <h2>Nuevo producto</h2>
        <p>Ingresa la información básica del producto.</p>
      </div>
    </div>

    <div class="profile-section grid two">
      <div class="form-group">
        <label class="label">Nombre del producto</label>
        <input class="input" type="text" name="nombre_producto" required>
      </div>

      <div class="form-group">
        <label class="label">Precio</label>
        <input class="input" type="number" step="0.01" name="precio_producto" required>
      </div>

      <div class="form-group">
        <label class="label">Stock</label>
        <input class="input" type="number" name="stock_producto" required>
      </div>

      <div class="form-group">
        <label class="label">Categoría</label>
        <select class="input" name="id_categoria" required>
          <option value="">Seleccione</option>
          <?php foreach($categorias as $c): ?>
            <option value="<?= $c['id_categoria'] ?>"><?= htmlspecialchars($c['nombre_categoria']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="label">Proveedor</label>
        <select class="input" name="id_proveedor" required>
          <option value="">Seleccione</option>
          <?php foreach($proveedores as $p): ?>
            <option value="<?= $p['id_proveedor'] ?>"><?= htmlspecialchars($p['nombre_proveedor']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <button class="btn" type="submit">Guardar producto</button>
</form>

<?php
  return;
endif;


// ===========================
// EDICIÓN DE PRODUCTO
// ===========================
if ($action === 'edit' && isset($_GET['id'])):
  $idp = (int)$_GET['id'];
  $stmt = $pdo->prepare("
      SELECT id_producto, nombre_producto, precio_producto, stock_producto, id_categoria, id_proveedor
      FROM Producto
      WHERE id_producto = ?
  ");
  $stmt->execute([$idp]);
  $prod = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$prod):
?>
  <h2>Producto no encontrado.</h2>
<?php
    return;
  endif;
?>

<h1 class="title">Editar producto</h1>
<p class="subtitle">Modifica la información necesaria y guarda los cambios.</p>

<?php if ($e = flash('error')): ?>
  <div class="alert error"><?= htmlspecialchars($e) ?></div>
<?php endif; ?>

<form method="post" action="index.php?mod=productos&action=edit&id=<?= $idp ?>" class="profile-form">
  <input type="hidden" name="form" value="edit_producto"/>
  <input type="hidden" name="id_producto" value="<?= $idp ?>"/>

  <div class="profile-card">
    <div class="profile-card-header">
      <div class="profile-icon">✏️</div>
      <div>
        <h2>Editar producto</h2>
        <p>Actualiza la información del producto seleccionado.</p>
      </div>
    </div>

    <div class="profile-section grid two">
      <div class="form-group">
        <label class="label">Nombre</label>
        <input class="input" type="text" name="nombre_producto" value="<?= htmlspecialchars($prod['nombre_producto']) ?>" required>
      </div>

      <div class="form-group">
        <label class="label">Precio</label>
        <input class="input" type="number" step="0.01" name="precio_producto" value="<?= $prod['precio_producto'] ?>" required>
      </div>

      <div class="form-group">
        <label class="label">Stock</label>
        <input class="input" type="number" name="stock_producto" value="<?= $prod['stock_producto'] ?>" required>
      </div>

      <div class="form-group">
        <label class="label">Categoría</label>
        <select class="input" name="id_categoria" required>
          <?php foreach($categorias as $c): ?>
            <option value="<?= $c['id_categoria'] ?>" <?= ($c['id_categoria'] == $prod['id_categoria']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['nombre_categoria']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="label">Proveedor</label>
        <select class="input" name="id_proveedor" required>
          <?php foreach($proveedores as $p): ?>
            <option value="<?= $p['id_proveedor'] ?>" <?= ($p['id_proveedor'] == $prod['id_proveedor']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($p['nombre_proveedor']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <button class="btn" type="submit">Actualizar producto</button>
</form>

<?php
  return;
endif;
?>


<!-- ==============================
     LISTADO + FILTROS + EXPORTAR
     ============================== -->

<h1 class="title">Productos</h1>
<p class="subtitle">Consulta, filtrado, exportación y gestión de productos.</p>

<div class="profile-card">
  <div class="profile-card-header">
    <div class="profile-icon">📦</div>
    <div>
      <h2>Listado de productos</h2>
      <p>Aplica filtros, exporta el listado y gestiona los productos.</p>
    </div>

    <div class="profile-actions">
      <!-- Botón agregar producto -->
      <button class="btn-add" type="button"
              onclick="window.location.href='index.php?mod=productos&action=add'">
        + Agregar producto
      </button>

      <!-- Botón icono exportar a Excel -->
      <button class="btn-icon" type="button"
              onclick="window.location.href='export_productos.php'"
              title="Exportar listado a Excel">
        📊
      </button>
    </div>
  </div>

  <div class="profile-section">
    <form method="get" action="index.php" class="filter-form">
      <input type="hidden" name="mod" value="productos"/>

      <div class="filter-grid">
        <div class="form-group">
          <label class="label">Buscar</label>
          <input class="input" type="text" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>">
        </div>

        <div class="form-group">
          <label class="label">Categoría</label>
          <select class="input" name="categoria">
            <option value="">Todas</option>
            <?php foreach($categorias as $c): ?>
            <option value="<?= $c['id_categoria'] ?>" <?= $filtro_cat == $c['id_categoria'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['nombre_categoria']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="label">Proveedor</label>
          <select class="input" name="proveedor">
            <option value="">Todos</option>
            <?php foreach($proveedores as $p): ?>
            <option value="<?= $p['id_proveedor'] ?>" <?= $filtro_prov == $p['id_proveedor'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($p['nombre_proveedor']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="label">Precio mínimo</label>
          <input class="input" type="number" step="0.01" name="precio_min" value="<?= htmlspecialchars($precio_min) ?>">
        </div>

        <div class="form-group">
          <label class="label">Precio máximo</label>
          <input class="input" type="number" step="0.01" name="precio_max" value="<?= htmlspecialchars($precio_max) ?>">
        </div>

        <div class="form-group">
          <label class="label">Stock mínimo</label>
          <input class="input" type="number" name="stock_min" value="<?= htmlspecialchars($stock_min) ?>">
        </div>

        <div class="form-group">
          <label class="label">Stock máximo</label>
          <input class="input" type="number" name="stock_max" value="<?= htmlspecialchars($stock_max) ?>">
        </div>
      </div>

      <button class="btn" type="submit" style="margin-top: 10px;">Aplicar filtros</button>
    </form>
  </div>
</div>

<!-- TABLA DE RESULTADOS -->
<table class="result-table">
  <tr>
    <th>ID</th>
    <th>Nombre</th>
    <th>Precio</th>
    <th>Stock</th>
    <th>Categoría</th>
    <th>Proveedor</th>
    <th>Acciones</th>
  </tr>

  <?php foreach($productos as $p): ?>
  <tr>
    <td><?= $p['id_producto'] ?></td>
    <td><?= htmlspecialchars($p['nombre_producto']) ?></td>
    <td>$<?= $p['precio_producto'] ?></td>
    <td><?= $p['stock_producto'] ?></td>
    <td><?= htmlspecialchars($p['nombre_categoria']) ?></td>
    <td><?= htmlspecialchars($p['nombre_proveedor']) ?></td>
    <td>
      <a class="btn-small" href="index.php?mod=productos&action=edit&id=<?= $p['id_producto'] ?>">Editar</a>

      <form method="post" action="index.php?mod=productos" style="display:inline;">
        <input type="hidden" name="form" value="delete_producto"/>
        <input type="hidden" name="id_producto" value="<?= $p['id_producto'] ?>"/>
        <button class="btn-small btn-danger" onclick="return confirm('¿Eliminar este producto?');">
          Eliminar
        </button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
