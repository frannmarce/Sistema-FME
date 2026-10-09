<?php
$busqueda = trim($_GET['busqueda'] ?? '');
$ciudad = trim($_GET['ciudad'] ?? '');

if ($action === 'add'):
?>
<h1 class="title">Agregar proveedor</h1>
<p class="subtitle">Registra los datos comerciales y la dirección del proveedor.</p>

<form method="post" action="index.php?mod=proveedores" class="profile-form">
  <input type="hidden" name="form" value="add_proveedor">

  <div class="profile-card">
    <div class="profile-card-header">
      <div class="profile-icon">🤝</div>
      <div>
        <h2>Nuevo proveedor</h2>
        <p>Completa los datos necesarios para incorporarlo al sistema.</p>
      </div>
    </div>

    <div class="profile-section">
      <h3>Datos del proveedor</h3>
      <div class="profile-grid">
        <div class="form-group">
          <label class="label">Nombre</label>
          <input class="input" type="text" name="nombre_proveedor" maxlength="80" required>
        </div>
        <div class="form-group">
          <label class="label">Teléfono</label>
          <input class="input" type="text" name="telefono_proveedor" maxlength="30">
        </div>
        <div class="form-group">
          <label class="label">Correo</label>
          <input class="input" type="email" name="correo_proveedor" maxlength="80">
        </div>
      </div>
    </div>

    <div class="profile-section">
      <h3>Dirección</h3>
      <div class="profile-grid">
        <div class="form-group">
          <label class="label">País</label>
          <input class="input" type="text" name="nombre_pais" maxlength="50" required>
        </div>
        <div class="form-group">
          <label class="label">Ciudad</label>
          <input class="input" type="text" name="nombre_ciudad" maxlength="50" required>
        </div>
        <div class="form-group full-row">
          <label class="label">Dirección</label>
          <input class="input" type="text" name="num_direccion" maxlength="100" required>
        </div>
      </div>
    </div>

    <div class="module-actions">
      <a class="btn-secondary-link" href="index.php?mod=proveedores">Cancelar</a>
      <button class="btn" type="submit">Guardar proveedor</button>
    </div>
  </div>
</form>

<?php
return;
endif;

if ($action === 'edit' && isset($_GET['id'])):
  $idProveedor = (int)$_GET['id'];
  $stmt = $pdo->prepare("SELECT pr.id_proveedor, pr.nombre_proveedor, pr.telefono_proveedor, pr.correo_proveedor,
                                d.nombre_pais, d.nombre_ciudad, d.num_direccion
                         FROM Proveedor pr
                         LEFT JOIN Direccion d ON pr.id_direccion = d.id_direccion
                         WHERE pr.id_proveedor = ? LIMIT 1");
  $stmt->execute([$idProveedor]);
  $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$proveedor):
?>
  <h1 class="title">Proveedor no encontrado</h1>
  <p class="subtitle">El proveedor solicitado no existe.</p>
<?php
    return;
  endif;
?>
<h1 class="title">Editar proveedor</h1>
<p class="subtitle">Actualiza los datos comerciales y la dirección del proveedor.</p>

<form method="post" action="index.php?mod=proveedores&action=edit&id=<?= $idProveedor ?>" class="profile-form">
  <input type="hidden" name="form" value="edit_proveedor">
  <input type="hidden" name="id_proveedor" value="<?= $idProveedor ?>">

  <div class="profile-card">
    <div class="profile-card-header">
      <div class="profile-icon">✏️</div>
      <div>
        <h2>Editar proveedor</h2>
        <p>Modifica únicamente la información que sea necesaria.</p>
      </div>
    </div>

    <div class="profile-section">
      <h3>Datos del proveedor</h3>
      <div class="profile-grid">
        <div class="form-group">
          <label class="label">Nombre</label>
          <input class="input" type="text" name="nombre_proveedor" maxlength="80" value="<?= htmlspecialchars($proveedor['nombre_proveedor']) ?>" required>
        </div>
        <div class="form-group">
          <label class="label">Teléfono</label>
          <input class="input" type="text" name="telefono_proveedor" maxlength="30" value="<?= htmlspecialchars($proveedor['telefono_proveedor'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="label">Correo</label>
          <input class="input" type="email" name="correo_proveedor" maxlength="80" value="<?= htmlspecialchars($proveedor['correo_proveedor'] ?? '') ?>">
        </div>
      </div>
    </div>

    <div class="profile-section">
      <h3>Dirección</h3>
      <div class="profile-grid">
        <div class="form-group">
          <label class="label">País</label>
          <input class="input" type="text" name="nombre_pais" maxlength="50" value="<?= htmlspecialchars($proveedor['nombre_pais'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="label">Ciudad</label>
          <input class="input" type="text" name="nombre_ciudad" maxlength="50" value="<?= htmlspecialchars($proveedor['nombre_ciudad'] ?? '') ?>" required>
        </div>
        <div class="form-group full-row">
          <label class="label">Dirección</label>
          <input class="input" type="text" name="num_direccion" maxlength="100" value="<?= htmlspecialchars($proveedor['num_direccion'] ?? '') ?>" required>
        </div>
      </div>
    </div>

    <div class="module-actions">
      <a class="btn-secondary-link" href="index.php?mod=proveedores">Cancelar</a>
      <button class="btn" type="submit">Guardar cambios</button>
    </div>
  </div>
</form>
<?php
return;
endif;

$sql = "SELECT pr.id_proveedor, pr.nombre_proveedor, pr.telefono_proveedor, pr.correo_proveedor,
               d.nombre_pais, d.nombre_ciudad, d.num_direccion,
               COUNT(p.id_producto) AS productos_asignados
        FROM Proveedor pr
        LEFT JOIN Direccion d ON pr.id_direccion = d.id_direccion
        LEFT JOIN Producto p ON pr.id_proveedor = p.id_proveedor
        WHERE 1=1";
$params = [];

if ($busqueda !== '') {
  $sql .= " AND (pr.nombre_proveedor LIKE ? OR pr.correo_proveedor LIKE ? OR pr.telefono_proveedor LIKE ?)";
  $like = '%' . $busqueda . '%';
  array_push($params, $like, $like, $like);
}
if ($ciudad !== '') {
  $sql .= " AND d.nombre_ciudad LIKE ?";
  $params[] = '%' . $ciudad . '%';
}
$sql .= " GROUP BY pr.id_proveedor, pr.nombre_proveedor, pr.telefono_proveedor, pr.correo_proveedor,
                   d.nombre_pais, d.nombre_ciudad, d.num_direccion
          ORDER BY pr.nombre_proveedor ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1 class="title">Proveedores</h1>
<p class="subtitle">Alta, edición, consulta y control de proveedores vinculados a los productos.</p>

<div class="profile-card wide-card">
  <div class="profile-card-header">
    <div class="profile-icon">🤝</div>
    <div>
      <h2>Gestión de proveedores</h2>
      <p>Consulta proveedores, sus direcciones y la cantidad de productos asociados.</p>
    </div>
    <div class="profile-actions">
      <a class="btn-add" href="index.php?mod=proveedores&action=add">+ Agregar proveedor</a>
    </div>
  </div>

  <div class="profile-section">
    <form method="get" action="index.php" class="filter-form">
      <input type="hidden" name="mod" value="proveedores">
      <div class="filter-grid">
        <div class="form-group">
          <label class="label">Buscar</label>
          <input class="input" type="text" name="busqueda" placeholder="Nombre, correo o teléfono" value="<?= htmlspecialchars($busqueda) ?>">
        </div>
        <div class="form-group">
          <label class="label">Ciudad</label>
          <input class="input" type="text" name="ciudad" placeholder="Ej.: Formosa" value="<?= htmlspecialchars($ciudad) ?>">
        </div>
      </div>
      <div class="module-actions left-actions">
        <button class="btn" type="submit">Buscar</button>
        <a class="btn-secondary-link" href="index.php?mod=proveedores">Limpiar</a>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="result-table">
      <thead>
        <tr>
          <th>ID</th><th>Proveedor</th><th>Teléfono</th><th>Correo</th><th>Ciudad</th><th>Dirección</th><th>Productos</th><th>Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$proveedores): ?>
        <tr><td colspan="8" class="empty-cell">No se encontraron proveedores.</td></tr>
      <?php else: ?>
        <?php foreach ($proveedores as $p): ?>
        <tr>
          <td><?= (int)$p['id_proveedor'] ?></td>
          <td><?= htmlspecialchars($p['nombre_proveedor']) ?></td>
          <td><?= htmlspecialchars($p['telefono_proveedor'] ?: '-') ?></td>
          <td><?= htmlspecialchars($p['correo_proveedor'] ?: '-') ?></td>
          <td><?= htmlspecialchars($p['nombre_ciudad'] ?: '-') ?></td>
          <td><?= htmlspecialchars($p['num_direccion'] ?: '-') ?></td>
          <td><?= (int)$p['productos_asignados'] ?></td>
          <td class="actions-cell">
            <a class="table-action" href="index.php?mod=proveedores&action=edit&id=<?= (int)$p['id_proveedor'] ?>">Editar</a>
            <?php if ((int)$p['productos_asignados'] === 0): ?>
              <form method="post" action="index.php?mod=proveedores" class="inline-form" data-confirm="¿Eliminar este proveedor?">
                <input type="hidden" name="form" value="delete_proveedor">
                <input type="hidden" name="id_proveedor" value="<?= (int)$p['id_proveedor'] ?>">
                <button class="btn-delete" type="submit">Eliminar</button>
              </form>
            <?php else: ?>
              <span class="status-badge status-used" title="Tiene productos asignados">En uso</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
