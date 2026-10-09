<?php
// ===========================
// Módulo: Productos
// ===========================

$stmt = $pdo->query("SELECT id_categoria, nombre_categoria FROM Categoria ORDER BY nombre_categoria ASC");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT id_proveedor, nombre_proveedor FROM Proveedor ORDER BY nombre_proveedor ASC");
$proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$busqueda      = trim($_GET['busqueda'] ?? '');
$filtro_cat    = $_GET['categoria']  ?? '';
$filtro_prov   = $_GET['proveedor']  ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$precio_min    = $_GET['precio_min'] ?? '';
$precio_max    = $_GET['precio_max'] ?? '';
$stock_min     = $_GET['stock_min']  ?? '';
$stock_max     = $_GET['stock_max']  ?? '';

if (!in_array($filtro_estado, ['', 'activo', 'archivado'], true)) {
    $filtro_estado = '';
}

$porPagina = 15;
$paginaActual = max(1, (int)($_GET['pagina'] ?? 1));
$where = ' WHERE 1=1';
$params = [];

if ($busqueda !== '') {
    $where .= ' AND (p.nombre_producto LIKE ? OR p.codigo_barras LIKE ?)';
    $params[] = '%' . $busqueda . '%';
    $params[] = '%' . $busqueda . '%';
}
if ($filtro_cat !== '') {
    $where .= ' AND p.id_categoria = ?';
    $params[] = $filtro_cat;
}
if ($filtro_prov !== '') {
    $where .= ' AND p.id_proveedor = ?';
    $params[] = $filtro_prov;
}
if ($filtro_estado === 'activo') {
    $where .= ' AND p.activo_producto = 1';
} elseif ($filtro_estado === 'archivado') {
    $where .= ' AND p.activo_producto = 0';
}
if ($precio_min !== '') {
    $where .= ' AND p.precio_producto >= ?';
    $params[] = $precio_min;
}
if ($precio_max !== '') {
    $where .= ' AND p.precio_producto <= ?';
    $params[] = $precio_max;
}
if ($stock_min !== '') {
    $where .= ' AND p.stock_producto >= ?';
    $params[] = $stock_min;
}
if ($stock_max !== '') {
    $where .= ' AND p.stock_producto <= ?';
    $params[] = $stock_max;
}

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM Producto p' . $where);
$countStmt->execute($params);
$totalProductos = (int)$countStmt->fetchColumn();
$totalPaginas = max(1, (int)ceil($totalProductos / $porPagina));
if ($paginaActual > $totalPaginas) $paginaActual = $totalPaginas;
$offset = ($paginaActual - 1) * $porPagina;

$query = "SELECT p.id_producto, p.nombre_producto, p.codigo_barras,
                 p.precio_producto, p.stock_producto, p.activo_producto,
                 c.nombre_categoria, pr.nombre_proveedor
          FROM Producto p
          LEFT JOIN Categoria c ON p.id_categoria = c.id_categoria
          LEFT JOIN Proveedor pr ON p.id_proveedor = pr.id_proveedor" . $where .
         ' ORDER BY p.activo_producto DESC, p.id_producto ASC LIMIT ' . $porPagina . ' OFFSET ' . $offset;
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$paginaUrl = static function (int $pagina): string {
    $query = $_GET;
    $query['mod'] = 'productos';
    $query['pagina'] = $pagina;
    unset($query['action'], $query['id']);
    return 'index.php?' . http_build_query($query);
};

// ===========================
// ALTA MÚLTIPLE
// ===========================
if ($action === 'add'):
?>
<section class="page-heading">
  <div>
    <p class="eyebrow">Inventario</p>
    <h1 class="title">Agregar productos</h1>
    <p class="subtitle">Carga uno o varios productos nuevos y guárdalos en una sola operación.</p>
  </div>
</section>

<div class="profile-card wide-card">
  <div class="profile-card-header">
    <div class="profile-icon">➕</div>
    <div>
      <h2>Alta múltiple</h2>
      <p>Agrega tantas filas como necesites. Si una fila falla, no se guarda ningún producto del lote.</p>
    </div>
  </div>

  <?php if ($e = flash('error')): ?>
    <div class="alert error"><?= htmlspecialchars($e) ?></div>
  <?php endif; ?>

  <?php if (!$categorias || !$proveedores): ?>
    <div class="module-warning">Para cargar productos debe existir al menos una categoría y un proveedor.</div>
  <?php endif; ?>

  <form method="post" action="index.php?mod=productos&action=add" class="profile-form" id="productosBatchForm">
    <input type="hidden" name="form" value="add_productos"/>

    <div class="profile-section">
      <div class="section-heading-row">
        <div>
          <h3>Productos a registrar</h3>
          <p class="section-help">Cada fila representa un producto nuevo. Si no cargas un código de barras, FME genera uno interno automáticamente.</p>
        </div>
        <button class="btn-add compact-btn" type="button" id="agregarProductoFila">+ Agregar otro producto</button>
      </div>

      <div id="productosBatch" class="product-batch-list">
        <div class="product-entry-row">
          <div class="form-group product-entry-name">
            <label class="label">Nombre</label>
            <input class="input" type="text" name="nombre_producto[]" maxlength="80" required>
          </div>
          <div class="form-group product-entry-barcode">
            <label class="label">Código de barras (opcional)</label>
            <input class="input" type="text" name="codigo_barras[]" inputmode="numeric" maxlength="13" pattern="[0-9]{13}" placeholder="Se genera si queda vacío">
          </div>
          <div class="form-group">
            <label class="label">Precio</label>
            <input class="input" type="number" step="0.01" min="0" name="precio_producto[]" required>
          </div>
          <div class="form-group">
            <label class="label">Stock inicial</label>
            <input class="input" type="number" min="0" step="1" name="stock_producto[]" required>
          </div>
          <div class="form-group">
            <label class="label">Categoría</label>
            <select class="input" name="id_categoria[]" required>
              <option value="">Seleccione...</option>
              <?php foreach($categorias as $c): ?>
                <option value="<?= (int)$c['id_categoria'] ?>"><?= htmlspecialchars($c['nombre_categoria']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="label">Proveedor</label>
            <select class="input" name="id_proveedor[]" required>
              <option value="">Seleccione...</option>
              <?php foreach($proveedores as $p): ?>
                <option value="<?= (int)$p['id_proveedor'] ?>"><?= htmlspecialchars($p['nombre_proveedor']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="remove-item-btn remove-product-entry" type="button" title="Quitar fila" aria-label="Quitar fila">×</button>
        </div>
      </div>
    </div>

    <div class="module-actions">
      <a class="btn-secondary-link" href="index.php?mod=productos">Cancelar</a>
      <button class="btn" type="submit" <?= (!$categorias || !$proveedores) ? 'disabled' : '' ?>>Guardar productos</button>
    </div>
  </form>
</div>

<script>
(() => {
  const container = document.getElementById('productosBatch');
  const addBtn = document.getElementById('agregarProductoFila');
  if (!container || !addBtn) return;

  function bindRow(row) {
    const removeBtn = row.querySelector('.remove-product-entry');
    removeBtn?.addEventListener('click', () => {
      const rows = container.querySelectorAll('.product-entry-row');
      if (rows.length > 1) row.remove();
    });
  }

  container.querySelectorAll('.product-entry-row').forEach(bindRow);
  addBtn.addEventListener('click', () => {
    const base = container.querySelector('.product-entry-row');
    if (!base) return;
    const clone = base.cloneNode(true);
    clone.querySelectorAll('input').forEach(input => input.value = '');
    clone.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
    bindRow(clone);
    container.appendChild(clone);
    clone.querySelector('input')?.focus();
  });
})();
</script>
<?php
  return;
endif;

// ===========================
// EDICIÓN DE PRODUCTO
// ===========================
if ($action === 'edit' && isset($_GET['id'])):
  $idp = (int)$_GET['id'];
  $stmt = $pdo->prepare("
      SELECT id_producto, nombre_producto, codigo_barras, precio_producto, stock_producto,
             id_categoria, id_proveedor, activo_producto
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

<section class="page-heading">
  <div>
    <p class="eyebrow">Inventario</p>
    <h1 class="title">Editar producto</h1>
    <p class="subtitle">Modifica la información necesaria y guarda los cambios.</p>
  </div>
</section>

<div class="profile-card">
  <?php if ((int)$prod['activo_producto'] !== 1): ?>
    <div class="module-warning">Este producto está archivado. Puedes editar sus datos históricos, pero no estará disponible para nuevas ventas o movimientos hasta reactivarlo.</div>
  <?php endif; ?>

  <?php if ($e = flash('error')): ?>
    <div class="alert error"><?= htmlspecialchars($e) ?></div>
  <?php endif; ?>

  <form method="post" action="index.php?mod=productos&action=edit&id=<?= $idp ?>" class="profile-form">
    <input type="hidden" name="form" value="edit_producto"/>
    <input type="hidden" name="id_producto" value="<?= $idp ?>"/>

    <div class="profile-section grid two">
      <div class="form-group">
        <label class="label">Nombre</label>
        <input class="input" type="text" name="nombre_producto" maxlength="80" value="<?= htmlspecialchars($prod['nombre_producto']) ?>" required>
      </div>
      <div class="form-group">
        <label class="label">Código de barras</label>
        <input class="input" type="text" name="codigo_barras" inputmode="numeric" maxlength="13" pattern="[0-9]{13}" value="<?= htmlspecialchars((string)($prod['codigo_barras'] ?? '')) ?>" placeholder="Vacío = generar automáticamente">
        <small class="field-help">Puedes conservar o escanear el código de barras del fabricante. Si lo dejas vacío, FME genera uno interno automáticamente.</small>
      </div>
      <div class="form-group">
        <label class="label">Precio</label>
        <input class="input" type="number" step="0.01" min="0" name="precio_producto" value="<?= htmlspecialchars((string)$prod['precio_producto']) ?>" required>
      </div>
      <div class="form-group">
        <label class="label">Stock</label>
        <input class="input" type="number" min="0" step="1" name="stock_producto" value="<?= (int)$prod['stock_producto'] ?>" required>
      </div>
      <div class="form-group">
        <label class="label">Categoría</label>
        <select class="input" name="id_categoria" required>
          <?php foreach($categorias as $c): ?>
            <option value="<?= (int)$c['id_categoria'] ?>" <?= ((int)$c['id_categoria'] === (int)$prod['id_categoria']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['nombre_categoria']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="label">Proveedor</label>
        <select class="input" name="id_proveedor" required>
          <?php foreach($proveedores as $p): ?>
            <option value="<?= (int)$p['id_proveedor'] ?>" <?= ((int)$p['id_proveedor'] === (int)$prod['id_proveedor']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($p['nombre_proveedor']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="module-actions">
      <a class="btn-secondary-link" href="index.php?mod=productos">Cancelar</a>
      <button class="btn" type="submit">Actualizar producto</button>
    </div>
  </form>
</div>
<?php
  return;
endif;
?>

<section class="page-heading">
  <div>
    <p class="eyebrow">Inventario</p>
    <h1 class="title">Productos</h1>
    <p class="subtitle">Consulta el catálogo, administra su disponibilidad y conserva el historial de productos archivados.</p>
  </div>
</section>

<div class="profile-card">
  <div class="profile-card-header">
    <div class="profile-icon">📦</div>
    <div>
      <h2>Listado de productos</h2>
      <p>Los productos archivados siguen en el historial, pero no pueden utilizarse en nuevas operaciones.</p>
    </div>
    <div class="profile-actions">
      <a class="btn-add" href="index.php?mod=productos&action=add">+ Agregar productos</a>
      <a class="btn-icon" href="actions/export_productos.php" title="Exportar listado a CSV" aria-label="Exportar listado a CSV">📊</a>
    </div>
  </div>

  <?php if ($e = flash('success')): ?><div class="alert success"><?= htmlspecialchars($e) ?></div><?php endif; ?>
  <?php if ($e = flash('error')): ?><div class="alert error"><?= htmlspecialchars($e) ?></div><?php endif; ?>

  <div class="profile-section">
    <form method="get" action="index.php" class="filter-form">
      <input type="hidden" name="mod" value="productos"/>
      <div class="filter-grid">
        <div class="form-group">
          <label class="label">Buscar</label>
          <input class="input" type="text" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Nombre o código de barras">
        </div>
        <div class="form-group">
          <label class="label">Estado</label>
          <select class="input" name="estado">
            <option value="" <?= $filtro_estado === '' ? 'selected' : '' ?>>Todos</option>
            <option value="activo" <?= $filtro_estado === 'activo' ? 'selected' : '' ?>>Activos</option>
            <option value="archivado" <?= $filtro_estado === 'archivado' ? 'selected' : '' ?>>Archivados</option>
          </select>
        </div>
        <div class="form-group">
          <label class="label">Categoría</label>
          <select class="input" name="categoria">
            <option value="">Todas</option>
            <?php foreach($categorias as $c): ?>
            <option value="<?= (int)$c['id_categoria'] ?>" <?= $filtro_cat == $c['id_categoria'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre_categoria']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="label">Proveedor</label>
          <select class="input" name="proveedor">
            <option value="">Todos</option>
            <?php foreach($proveedores as $p): ?>
            <option value="<?= (int)$p['id_proveedor'] ?>" <?= $filtro_prov == $p['id_proveedor'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre_proveedor']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="label">Precio mínimo</label><input class="input" type="number" step="0.01" name="precio_min" value="<?= htmlspecialchars($precio_min) ?>"></div>
        <div class="form-group"><label class="label">Precio máximo</label><input class="input" type="number" step="0.01" name="precio_max" value="<?= htmlspecialchars($precio_max) ?>"></div>
        <div class="form-group"><label class="label">Stock mínimo</label><input class="input" type="number" name="stock_min" value="<?= htmlspecialchars($stock_min) ?>"></div>
        <div class="form-group"><label class="label">Stock máximo</label><input class="input" type="number" name="stock_max" value="<?= htmlspecialchars($stock_max) ?>"></div>
      </div>
      <div class="profile-actions left-actions">
        <button class="btn" type="submit">Aplicar filtros</button>
        <a class="btn-secondary-link" href="index.php?mod=productos">Limpiar</a>
      </div>
    </form>
  </div>
</div>

<div class="table-responsive">
<table class="result-table products-table">
  <thead>
    <tr>
      <th>ID</th><th>Nombre</th><th>Código de barras</th><th>Precio</th><th>Stock</th><th>Categoría</th><th>Proveedor</th><th>Estado</th><th>Acciones</th>
    </tr>
  </thead>
  <tbody>
  <?php if (!$productos): ?>
    <tr><td colspan="9" class="empty-cell">No se encontraron productos con los filtros seleccionados.</td></tr>
  <?php endif; ?>
  <?php foreach($productos as $p): ?>
  <tr class="<?= (int)$p['activo_producto'] === 1 ? '' : 'archived-row' ?>">
    <td><?= (int)$p['id_producto'] ?></td>
    <td><?= htmlspecialchars($p['nombre_producto']) ?></td>
    <td><span class="barcode-value"><?= htmlspecialchars((string)($p['codigo_barras'] ?? '-')) ?></span></td>
    <td>$<?= number_format((float)$p['precio_producto'], 2, ',', '.') ?></td>
    <td><?= (int)$p['stock_producto'] ?></td>
    <td><?= htmlspecialchars($p['nombre_categoria'] ?? '-') ?></td>
    <td><?= htmlspecialchars($p['nombre_proveedor'] ?? '-') ?></td>
    <td>
      <?php if ((int)$p['activo_producto'] === 1): ?>
        <span class="status-badge status-active">Activo</span>
      <?php else: ?>
        <span class="status-badge status-archived">Archivado</span>
      <?php endif; ?>
    </td>
    <td class="actions-cell">
      <a class="btn-small" href="index.php?mod=productos&action=edit&id=<?= (int)$p['id_producto'] ?>">Editar</a>

      <?php if ((int)$p['activo_producto'] === 1): ?>
        <form method="post" action="index.php?mod=productos" class="inline-form" data-confirm="¿Archivar este producto? Se conservará su historial, pero dejará de estar disponible para nuevas ventas y movimientos manuales.">
          <input type="hidden" name="form" value="archive_producto"/>
          <input type="hidden" name="id_producto" value="<?= (int)$p['id_producto'] ?>"/>
          <button class="btn-small btn-archive" type="submit">Archivar</button>
        </form>
      <?php else: ?>
        <form method="post" action="index.php?mod=productos" class="inline-form" data-confirm="¿Reactivar este producto? Volverá a estar disponible para nuevas operaciones.">
          <input type="hidden" name="form" value="reactivate_producto"/>
          <input type="hidden" name="id_producto" value="<?= (int)$p['id_producto'] ?>"/>
          <button class="btn-small btn-reactivate" type="submit">Reactivar</button>
        </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<div class="pagination-bar">
  <div class="pagination-summary">
    <?= $totalProductos ?> producto<?= $totalProductos === 1 ? '' : 's' ?> encontrado<?= $totalProductos === 1 ? '' : 's' ?> · Página <?= $paginaActual ?> de <?= $totalPaginas ?>
  </div>
  <?php if ($totalPaginas > 1): ?>
    <nav class="pagination" aria-label="Paginación de productos">
      <a class="page-link <?= $paginaActual <= 1 ? 'disabled' : '' ?>" href="<?= $paginaActual > 1 ? htmlspecialchars($paginaUrl($paginaActual - 1)) : '#' ?>">‹ Anterior</a>
      <?php for ($p = max(1, $paginaActual - 2); $p <= min($totalPaginas, $paginaActual + 2); $p++): ?>
        <a class="page-link <?= $p === $paginaActual ? 'active' : '' ?>" href="<?= htmlspecialchars($paginaUrl($p)) ?>"><?= $p ?></a>
      <?php endfor; ?>
      <a class="page-link <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>" href="<?= $paginaActual < $totalPaginas ? htmlspecialchars($paginaUrl($paginaActual + 1)) : '#' ?>">Siguiente ›</a>
    </nav>
  <?php endif; ?>
</div>
