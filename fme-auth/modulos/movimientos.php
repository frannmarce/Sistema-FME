<?php
//listas y filtros
$tipos   = $pdo->query("SELECT id_tipo, nombre_tipo FROM Tipo_movimiento ORDER BY nombre_tipo")->fetchAll();
$motivos = $pdo->query("SELECT id_motivo, nombre_motivo FROM Motivo_movimiento ORDER BY nombre_motivo")->fetchAll();
$productosLista = $pdo->query("SELECT id_producto, nombre_producto FROM Producto ORDER BY nombre_producto")->fetchAll();

$f_producto = $_GET['f_producto'] ?? '';
$f_usuario  = $_GET['f_usuario']  ?? '';
$f_tipo     = $_GET['f_tipo']     ?? '';
$f_motivo   = $_GET['f_motivo']   ?? '';
$f_desde    = $_GET['f_desde']    ?? '';
$f_hasta    = $_GET['f_hasta']    ?? '';
?>

<?php if ($action === 'add'): ?>

  <h1 class="title">Registrar movimiento</h1>
  <p class="subtitle">Registra una entrada o salida de stock y actualiza automáticamente el inventario.</p>

  <div class="profile-card">
    <div class="profile-card-header">
      <div class="profile-icon">➕</div>
      <div>
        <h2>Nuevo movimiento</h2>
        <p>Selecciona el producto, tipo de movimiento, motivo y cantidad.</p>
      </div>
    </div>

    <?php if ($e = flash('error')): ?>
      <div class="alert error" style="margin-bottom: 14px;"><?= htmlspecialchars($e) ?></div>
    <?php endif; ?>

    <form method="post" action="index.php?mod=movimientos&action=add" class="profile-form">
      <input type="hidden" name="form" value="add_movimiento">

      <div class="profile-section">
        <h3>Datos del movimiento</h3>

        <div class="profile-grid">
          <div class="form-group">
            <label class="label">Producto</label>
            <select class="input" name="id_producto" required>
              <option value="">Seleccione...</option>
              <?php foreach ($productosLista as $p): ?>
                <option value="<?= $p['id_producto'] ?>"><?= htmlspecialchars($p['nombre_producto']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="label">Cantidad</label>
            <input class="input" type="number" name="cantidad_movimiento" min="1" required>
          </div>
        </div>

        <div class="profile-grid">
          <div class="form-group">
            <label class="label">Tipo de movimiento</label>
            <select class="input" name="id_tipo" required>
              <option value="">Seleccione...</option>
              <?php foreach ($tipos as $t): ?>
                <option value="<?= $t['id_tipo'] ?>"><?= htmlspecialchars($t['nombre_tipo']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="label">Motivo</label>
            <select class="input" name="id_motivo" required>
              <option value="">Seleccione...</option>
              <?php foreach ($motivos as $m): ?>
                <option value="<?= $m['id_motivo'] ?>"><?= htmlspecialchars($m['nombre_motivo']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

      </div>

      <div class="profile-actions">
        <button class="btn" type="submit">Guardar movimiento</button>
        <button class="btn" type="button" style="background:#888; margin-left:8px;"
                onclick="window.location.href='index.php?mod=movimientos'">
          Cancelar
        </button>
      </div>
    </form>
  </div>

<?php else: ?>

  <h1 class="title">Consulta de movimientos</h1>
  <p class="subtitle">Filtra los movimientos de stock por producto, usuario, tipo de movimiento, motivo y fecha.</p>

  <div class="profile-card">
    <div class="profile-card-header">
      <div class="profile-icon">📑</div>
      <div>
        <h2>Movimientos de stock</h2>
        <p>Información consultada desde la tabla Movimiento y sus relaciones.</p>
      </div>
    </div>

    <?php if ($e = flash('error')): ?>
      <div class="alert error" style="margin-bottom: 10px;"><?= htmlspecialchars($e) ?></div>
    <?php endif; ?>

    <div class="profile-actions" style="text-align:right; margin-bottom:15px;">
      <button class="btn-add" onclick="window.location.href='index.php?mod=movimientos&action=add'">
        ➕ Registrar movimiento
      </button>
    </div>

    
    <form method="get" action="index.php" class="profile-form">
      <input type="hidden" name="mod" value="movimientos">

      <div class="profile-section">
        <h3>Filtros de búsqueda</h3>

        <div class="profile-grid">
          <div class="form-group">
            <label class="label">Producto</label>
            <input class="input" type="text" name="f_producto"
                   placeholder="Ej.: Taladro"
                   value="<?= htmlspecialchars($f_producto) ?>">
          </div>

          <div class="form-group">
            <label class="label">Usuario</label>
            <input class="input" type="text" name="f_usuario"
                   placeholder="Ej.: Fgimenez"
                   value="<?= htmlspecialchars($f_usuario) ?>">
          </div>
        </div>

        <div class="profile-grid">
          <div class="form-group">
            <label class="label">Tipo de movimiento</label>
            <select class="input" name="f_tipo">
              <option value="">Todos</option>
              <?php foreach ($tipos as $t): ?>
                <option value="<?= $t['id_tipo'] ?>"
                  <?= ($f_tipo !== '' && $f_tipo == $t['id_tipo']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($t['nombre_tipo']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="label">Motivo</label>
            <select class="input" name="f_motivo">
              <option value="">Todos</option>
              <?php foreach ($motivos as $m): ?>
                <option value="<?= $m['id_motivo'] ?>"
                  <?= ($f_motivo !== '' && $f_motivo == $m['id_motivo']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($m['nombre_motivo']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="profile-grid">
          <div class="form-group">
            <label class="label">Fecha desde</label>
            <input class="input" type="date" name="f_desde"
                   value="<?= htmlspecialchars($f_desde) ?>">
          </div>
          <div class="form-group">
            <label class="label">Fecha hasta</label>
            <input class="input" type="date" name="f_hasta"
                   value="<?= htmlspecialchars($f_hasta) ?>">
          </div>
        </div>
      </div>

      <div class="profile-actions">
        <button class="btn" type="submit">Buscar</button>
        <button class="btn" type="button" style="background:#888; margin-left:8px;"
                onclick="window.location.href='index.php?mod=movimientos'">
          Limpiar filtros
        </button>
      </div>
    </form>

    
    <?php
    $sql = "SELECT 
              m.fecha_movimiento,
              m.cantidad_movimiento,
              p.nombre_producto,
              u.nombre_usuario,
              tm.nombre_tipo,
              mm.nombre_motivo
            FROM Movimiento m
            LEFT JOIN Producto p ON m.id_producto = p.id_producto
            LEFT JOIN Usuario u ON m.id_usuario = u.id_usuario
            LEFT JOIN Tipo_movimiento tm ON m.id_tipo = tm.id_tipo
            LEFT JOIN Motivo_movimiento mm ON m.id_motivo = mm.id_motivo
            WHERE 1=1";

    $params = [];

    if ($f_producto !== '') {
      $sql .= " AND p.nombre_producto LIKE ?";
      $params[] = "%".$f_producto."%";
    }

    if ($f_usuario !== '') {
      $sql .= " AND u.nombre_usuario LIKE ?";
      $params[] = "%".$f_usuario."%";
    }

    if ($f_tipo !== '') {
      $sql .= " AND m.id_tipo = ?";
      $params[] = $f_tipo;
    }

    if ($f_motivo !== '') {
      $sql .= " AND m.id_motivo = ?";
      $params[] = $f_motivo;
    }

    if ($f_desde !== '') {
      $sql .= " AND DATE(m.fecha_movimiento) >= ?";
      $params[] = $f_desde;
    }

    if ($f_hasta !== '') {
      $sql .= " AND DATE(m.fecha_movimiento) <= ?";
      $params[] = $f_hasta;
    }

    $sql .= " ORDER BY m.fecha_movimiento DESC, m.id_movimiento DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movs = $stmt->fetchAll();
    ?>

    <h3 style="margin-top:20px;">Movimientos encontrados: <?= count($movs) ?></h3>

    <table class="result-table">
      <tr>
        <th>Fecha</th>
        <th>Producto</th>
        <th>Usuario</th>
        <th>Tipo</th>
        <th>Motivo</th>
        <th>Cantidad</th>
      </tr>
      <?php foreach ($movs as $mv): ?>
      <tr>
        <td><?= htmlspecialchars($mv['fecha_movimiento']) ?></td>
        <td><?= htmlspecialchars($mv['nombre_producto'] ?? '-') ?></td>
        <td><?= htmlspecialchars($mv['nombre_usuario'] ?? '-') ?></td>
        <td><?= htmlspecialchars($mv['nombre_tipo'] ?? '-') ?></td>
        <td><?= htmlspecialchars($mv['nombre_motivo'] ?? '-') ?></td>
        <td><?= (int)$mv['cantidad_movimiento'] ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

<?php endif; ?>
