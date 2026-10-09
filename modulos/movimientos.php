<?php
//listas y filtros
$tipos   = $pdo->query("SELECT id_tipo, nombre_tipo FROM Tipo_movimiento ORDER BY nombre_tipo")->fetchAll();
$motivos = $pdo->query("SELECT id_motivo, nombre_motivo FROM Motivo_movimiento ORDER BY nombre_motivo")->fetchAll();
$motivosManuales = array_values(array_filter($motivos, static function ($m) {
  $nombre = mb_strtolower(trim((string)($m['nombre_motivo'] ?? '')));
  return !in_array($nombre, ['venta', 'cancelación de venta', 'cancelacion de venta'], true);
}));
$productosLista = $pdo->query("SELECT id_producto, nombre_producto FROM Producto WHERE activo_producto = 1 ORDER BY nombre_producto")->fetchAll();

$f_producto = $_GET['f_producto'] ?? '';
$f_usuario  = $_GET['f_usuario']  ?? '';
$f_tipo     = $_GET['f_tipo']     ?? '';
$f_motivo   = $_GET['f_motivo']   ?? '';
$f_desde    = $_GET['f_desde']    ?? '';
$f_hasta    = $_GET['f_hasta']    ?? '';
$f_origen   = strtoupper(trim((string)($_GET['f_origen'] ?? '')));
if (!in_array($f_origen, ['', 'MANUAL', 'VENTA', 'CANCELACION_VENTA'], true)) $f_origen = '';
?>

<?php if ($action === 'add'): ?>

  <h1 class="title">Registrar movimiento manual</h1>
  <p class="subtitle">Usa esta opción para reposiciones o ajustes que no provienen de una venta. Las ventas y sus cancelaciones generan movimientos automáticamente.</p>

  <div class="profile-card">
    <div class="profile-card-header">
      <div class="profile-icon">➕</div>
      <div>
        <h2>Ajuste manual de inventario</h2>
        <p>Selecciona el producto, tipo de movimiento, motivo y cantidad. Los motivos de venta están reservados al sistema.</p>
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
              <?php foreach ($motivosManuales as $m): ?>
                <option value="<?= $m['id_motivo'] ?>"><?= htmlspecialchars($m['nombre_motivo']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

      </div>

      <div class="profile-actions">
        <button class="btn" type="submit">Guardar movimiento manual</button>
        <a class="btn btn-secondary" href="index.php?mod=movimientos">Cancelar</a>
      </div>
    </form>
  </div>

<?php else: ?>

  <h1 class="title">Consulta de movimientos</h1>
  <p class="subtitle">Consulta movimientos manuales y movimientos automáticos generados por ventas o cancelaciones.</p>

  <div class="profile-card">
    <div class="profile-card-header">
      <div class="profile-icon">📑</div>
      <div>
        <h2>Movimientos de stock</h2>
        <p>Cada registro indica si fue cargado manualmente o generado automáticamente por una venta.</p>
      </div>
    </div>

    <?php if ($e = flash('error')): ?>
      <div class="alert error" style="margin-bottom: 10px;"><?= htmlspecialchars($e) ?></div>
    <?php endif; ?>

    <div class="profile-actions" style="text-align:right; margin-bottom:15px;">
      <a class="btn-add" href="index.php?mod=movimientos&action=add">➕ Registrar ajuste manual</a>
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
            <label class="label">Origen</label>
            <select class="input" name="f_origen">
              <option value="" <?= $f_origen === '' ? 'selected' : '' ?>>Todos</option>
              <option value="MANUAL" <?= $f_origen === 'MANUAL' ? 'selected' : '' ?>>Manual</option>
              <option value="VENTA" <?= $f_origen === 'VENTA' ? 'selected' : '' ?>>Automático - Venta</option>
              <option value="CANCELACION_VENTA" <?= $f_origen === 'CANCELACION_VENTA' ? 'selected' : '' ?>>Automático - Cancelación</option>
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
        <a class="btn btn-secondary" href="index.php?mod=movimientos">Limpiar filtros</a>
      </div>
    </form>

    
    <?php
    $porPagina = 15;
    $paginaActual = max(1, (int)($_GET['pagina'] ?? 1));
    $where = ' WHERE 1=1';
    $params = [];

    if ($f_producto !== '') {
      $where .= ' AND p.nombre_producto LIKE ?';
      $params[] = '%' . $f_producto . '%';
    }
    if ($f_usuario !== '') {
      $where .= ' AND u.nombre_usuario LIKE ?';
      $params[] = '%' . $f_usuario . '%';
    }
    if ($f_tipo !== '') {
      $where .= ' AND m.id_tipo = ?';
      $params[] = $f_tipo;
    }
    if ($f_motivo !== '') {
      $where .= ' AND m.id_motivo = ?';
      $params[] = $f_motivo;
    }
    if ($f_origen !== '') {
      $where .= ' AND m.origen_movimiento = ?';
      $params[] = $f_origen;
    }
    if ($f_desde !== '') {
      $where .= ' AND DATE(m.fecha_movimiento) >= ?';
      $params[] = $f_desde;
    }
    if ($f_hasta !== '') {
      $where .= ' AND DATE(m.fecha_movimiento) <= ?';
      $params[] = $f_hasta;
    }

    $countSql = 'SELECT COUNT(*) FROM Movimiento m
                 LEFT JOIN Producto p ON m.id_producto = p.id_producto
                 LEFT JOIN Usuario u ON m.id_usuario = u.id_usuario' . $where;
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalMovimientos = (int)$stmt->fetchColumn();
    $totalPaginas = max(1, (int)ceil($totalMovimientos / $porPagina));
    if ($paginaActual > $totalPaginas) $paginaActual = $totalPaginas;
    $offset = ($paginaActual - 1) * $porPagina;

    $sql = "SELECT
              m.fecha_movimiento,
              m.cantidad_movimiento,
              p.nombre_producto,
              u.nombre_usuario,
              tm.nombre_tipo,
              mm.nombre_motivo,
              m.origen_movimiento,
              m.id_venta,
              m.detalle_movimiento
            FROM Movimiento m
            LEFT JOIN Producto p ON m.id_producto = p.id_producto
            LEFT JOIN Usuario u ON m.id_usuario = u.id_usuario
            LEFT JOIN Tipo_movimiento tm ON m.id_tipo = tm.id_tipo
            LEFT JOIN Motivo_movimiento mm ON m.id_motivo = mm.id_motivo" . $where .
           ' ORDER BY m.fecha_movimiento DESC, m.id_movimiento DESC LIMIT ' . $porPagina . ' OFFSET ' . $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $paginaUrl = static function (int $pagina): string {
      $query = $_GET;
      $query['mod'] = 'movimientos';
      $query['pagina'] = $pagina;
      unset($query['action'], $query['id']);
      return 'index.php?' . http_build_query($query);
    };
    ?>

    <h3 style="margin-top:20px;">Movimientos encontrados: <?= $totalMovimientos ?></h3>

    <table class="result-table">
      <tr>
        <th>Fecha</th>
        <th>Producto</th>
        <th>Usuario</th>
        <th>Tipo</th>
        <th>Motivo</th>
        <th>Origen</th>
        <th>Cantidad</th>
      </tr>
      <?php foreach ($movs as $mv): ?>
      <tr>
        <td><?= htmlspecialchars($mv['fecha_movimiento']) ?></td>
        <td><?= htmlspecialchars($mv['nombre_producto'] ?? '-') ?></td>
        <td><?= htmlspecialchars($mv['nombre_usuario'] ?? '-') ?></td>
        <td><?= htmlspecialchars($mv['nombre_tipo'] ?? '-') ?></td>
        <td><?= htmlspecialchars($mv['nombre_motivo'] ?? '-') ?></td>
        <td>
          <?php $origen = strtoupper((string)($mv['origen_movimiento'] ?? 'MANUAL')); ?>
          <?php if ($origen === 'VENTA'): ?>
            <span class="status-badge status-auto">Automático · Venta<?= !empty($mv['id_venta']) ? ' #' . (int)$mv['id_venta'] : '' ?></span>
          <?php elseif ($origen === 'CANCELACION_VENTA'): ?>
            <span class="status-badge status-reversal">Automático · Cancelación<?= !empty($mv['id_venta']) ? ' #' . (int)$mv['id_venta'] : '' ?></span>
          <?php else: ?>
            <span class="status-badge status-manual">Manual</span>
          <?php endif; ?>
          <?php if (!empty($mv['detalle_movimiento'])): ?><div class="audit-muted"><?= htmlspecialchars($mv['detalle_movimiento']) ?></div><?php endif; ?>
        </td>
        <td><?= (int)$mv['cantidad_movimiento'] ?></td>
      </tr>
      <?php endforeach; ?>
    </table>

    <div class="pagination-bar">
      <div class="pagination-summary">
        <?= $totalMovimientos ?> movimiento<?= $totalMovimientos === 1 ? '' : 's' ?> encontrado<?= $totalMovimientos === 1 ? '' : 's' ?> · Página <?= $paginaActual ?> de <?= $totalPaginas ?>
      </div>
      <?php if ($totalPaginas > 1): ?>
        <nav class="pagination" aria-label="Paginación de movimientos">
          <a class="page-link <?= $paginaActual <= 1 ? 'disabled' : '' ?>" href="<?= $paginaActual > 1 ? htmlspecialchars($paginaUrl($paginaActual - 1)) : '#' ?>">‹ Anterior</a>
          <?php for ($p = max(1, $paginaActual - 2); $p <= min($totalPaginas, $paginaActual + 2); $p++): ?>
            <a class="page-link <?= $p === $paginaActual ? 'active' : '' ?>" href="<?= htmlspecialchars($paginaUrl($p)) ?>"><?= $p ?></a>
          <?php endfor; ?>
          <a class="page-link <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>" href="<?= $paginaActual < $totalPaginas ? htmlspecialchars($paginaUrl($paginaActual + 1)) : '#' ?>">Siguiente ›</a>
        </nav>
      <?php endif; ?>
    </div>
  </div>

<?php endif; ?>
