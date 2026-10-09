<?php
$productosVenta = $pdo->query("SELECT id_producto, nombre_producto, codigo_barras, precio_producto, stock_producto
                                FROM Producto
                                WHERE activo_producto = 1
                                  AND stock_producto > 0
                                ORDER BY nombre_producto ASC")->fetchAll(PDO::FETCH_ASSOC);
$mediosPago = $pdo->query("SELECT id_medio, nombre_medio FROM Medio_pago ORDER BY nombre_medio ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($action === 'add'):
?>
<h1 class="title">Registrar venta</h1>
<p class="subtitle">Registra una venta, descuenta stock y genera automáticamente sus movimientos.</p>

<div class="profile-card wide-card">
  <div class="profile-card-header">
    <div class="profile-icon">🧾</div>
    <div>
      <h2>Nueva venta</h2>
      <p>El total mostrado es informativo; el servidor recalcula precios y stock antes de guardar.</p>
    </div>
  </div>

  <?php if (!$mediosPago): ?>
    <div class="module-warning">No hay medios de pago cargados. Revisa la migración de módulos pendientes antes de registrar ventas.</div>
  <?php endif; ?>
  <?php if (!$productosVenta): ?>
    <div class="module-warning">No hay productos con stock disponible para vender.</div>
  <?php endif; ?>

  <form method="post" action="index.php?mod=ventas&action=add" class="profile-form" id="ventaForm">
    <input type="hidden" name="form" value="add_venta">

    <div class="profile-section">
      <div class="section-heading-row">
        <div>
          <h3>Productos</h3>
          <p class="section-help">Puedes incluir varios productos en una misma venta.</p>
        </div>
        <button class="btn-add compact-btn" type="button" id="agregarItem">+ Agregar producto</button>
      </div>

      <div class="barcode-scanner-box">
        <div class="form-group">
          <label class="label" for="codigoBarrasVenta">Escanear código de barras</label>
          <input class="input barcode-scan-input" id="codigoBarrasVenta" type="text" inputmode="numeric" maxlength="13" autocomplete="off" placeholder="Escanea el código y presiona Enter">
          <small class="field-help">El lector puede escribir el código aquí. El selector manual sigue disponible.</small>
        </div>
        <div id="barcodeScanStatus" class="barcode-scan-status" aria-live="polite"></div>
      </div>

      <div id="ventaItems" class="sale-items">
        <div class="sale-item-row">
          <div class="form-group sale-product">
            <label class="label">Producto</label>
            <select class="input producto-select" name="id_producto[]" required>
              <option value="">Seleccione...</option>
              <?php foreach ($productosVenta as $p): ?>
                <option value="<?= (int)$p['id_producto'] ?>"
                        data-price="<?= htmlspecialchars((string)$p['precio_producto']) ?>"
                        data-stock="<?= (int)$p['stock_producto'] ?>"
                        data-barcode="<?= htmlspecialchars((string)($p['codigo_barras'] ?? '')) ?>">
                  <?= htmlspecialchars($p['nombre_producto']) ?> — Stock: <?= (int)$p['stock_producto'] ?> — $<?= number_format((float)$p['precio_producto'], 2, ',', '.') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group sale-qty">
            <label class="label">Cantidad</label>
            <input class="input cantidad-input" type="number" name="cantidad[]" min="1" value="1" required>
          </div>
          <div class="form-group sale-subtotal">
            <label class="label">Subtotal</label>
            <div class="subtotal-display">$0,00</div>
          </div>
          <button class="remove-item-btn" type="button" title="Quitar producto" aria-label="Quitar producto">×</button>
        </div>
      </div>
    </div>

    <div class="profile-section">
      <h3>Pago y total</h3>
      <div class="profile-grid">
        <div class="form-group">
          <label class="label">Medio de pago</label>
          <select class="input" name="id_medio" required <?= !$mediosPago ? 'disabled' : '' ?>>
            <option value="">Seleccione...</option>
            <?php foreach ($mediosPago as $m): ?>
              <option value="<?= (int)$m['id_medio'] ?>"><?= htmlspecialchars($m['nombre_medio']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="sale-total-box">
          <span>Total estimado</span>
          <strong id="ventaTotal">$0,00</strong>
        </div>
      </div>
    </div>

    <div class="module-actions">
      <a class="btn-secondary-link" href="index.php?mod=ventas">Cancelar</a>
      <button class="btn" type="submit" <?= (!$mediosPago || !$productosVenta) ? 'disabled' : '' ?>>Registrar venta</button>
    </div>
  </form>
</div>

<script>
(() => {
  const container = document.getElementById('ventaItems');
  const addBtn = document.getElementById('agregarItem');
  const totalEl = document.getElementById('ventaTotal');
  const barcodeInput = document.getElementById('codigoBarrasVenta');
  const barcodeStatus = document.getElementById('barcodeScanStatus');
  if (!container || !addBtn || !totalEl) return;

  const money = value => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value || 0);

  function updateTotals() {
    let total = 0;
    container.querySelectorAll('.sale-item-row').forEach(row => {
      const select = row.querySelector('.producto-select');
      const qtyInput = row.querySelector('.cantidad-input');
      const option = select.options[select.selectedIndex];
      const price = parseFloat(option?.dataset.price || '0');
      const stock = parseInt(option?.dataset.stock || '0', 10);
      const qty = Math.max(0, parseInt(qtyInput.value || '0', 10));
      qtyInput.max = stock > 0 ? stock : '';
      const subtotal = price * qty;
      row.querySelector('.subtotal-display').textContent = money(subtotal);
      total += subtotal;
    });
    totalEl.textContent = money(total);
  }

  function bindRow(row) {
    row.querySelector('.producto-select').addEventListener('change', updateTotals);
    row.querySelector('.cantidad-input').addEventListener('input', updateTotals);
    row.querySelector('.remove-item-btn').addEventListener('click', () => {
      if (container.querySelectorAll('.sale-item-row').length > 1) {
        row.remove();
        updateTotals();
      }
    });
  }

  function addEmptyRow() {
    const first = container.querySelector('.sale-item-row');
    if (!first) return null;
    const clone = first.cloneNode(true);
    clone.querySelector('.producto-select').selectedIndex = 0;
    clone.querySelector('.cantidad-input').value = 1;
    clone.querySelector('.subtotal-display').textContent = '$0,00';
    bindRow(clone);
    container.appendChild(clone);
    return clone;
  }

  function setBarcodeStatus(message, type = '') {
    if (!barcodeStatus) return;
    barcodeStatus.textContent = message;
    barcodeStatus.className = 'barcode-scan-status' + (type ? ' ' + type : '');
  }

  function scanBarcode(code) {
    if (!/^\d{13}$/.test(code)) {
      setBarcodeStatus('El código de barras debe contener exactamente 13 dígitos.', 'error');
      return;
    }

    let matchedOption = null;
    for (const option of container.querySelectorAll('.producto-select option[data-barcode]')) {
      if (option.dataset.barcode === code) {
        matchedOption = option;
        break;
      }
    }

    if (!matchedOption) {
      setBarcodeStatus('No se encontró un producto activo con stock para ese código de barras.', 'error');
      return;
    }

    const productId = matchedOption.value;
    const stock = parseInt(matchedOption.dataset.stock || '0', 10);
    let targetRow = null;

    for (const row of container.querySelectorAll('.sale-item-row')) {
      if (row.querySelector('.producto-select').value === productId) {
        targetRow = row;
        break;
      }
    }

    if (targetRow) {
      const qtyInput = targetRow.querySelector('.cantidad-input');
      const current = Math.max(0, parseInt(qtyInput.value || '0', 10));
      if (current >= stock) {
        setBarcodeStatus('No se puede agregar otra unidad: alcanzaste el stock disponible.', 'error');
        return;
      }
      qtyInput.value = current + 1;
    } else {
      targetRow = Array.from(container.querySelectorAll('.sale-item-row')).find(row => !row.querySelector('.producto-select').value) || addEmptyRow();
      if (!targetRow) return;
      const select = targetRow.querySelector('.producto-select');
      select.value = productId;
      targetRow.querySelector('.cantidad-input').value = 1;
    }

    updateTotals();
    setBarcodeStatus('Producto agregado: ' + matchedOption.textContent.trim(), 'success');
  }

  container.querySelectorAll('.sale-item-row').forEach(bindRow);
  addBtn.addEventListener('click', () => {
    addEmptyRow();
    updateTotals();
  });

  barcodeInput?.addEventListener('keydown', event => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    const code = barcodeInput.value.trim();
    scanBarcode(code);
    barcodeInput.value = '';
    barcodeInput.focus();
  });

  updateTotals();
})();
</script>
<?php
return;
endif;

if ($action === 'cancel'):
  $idCancelacion = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
  $idCancelacion = $idCancelacion !== false && $idCancelacion !== null ? (int)$idCancelacion : 0;

  $cancelPageError = null;
  $ventaCancelar = null;
  $detalleCancelar = [];

  if ($idCancelacion <= 0) {
    $cancelPageError = 'La venta seleccionada no es válida.';
  } else {
    $stmt = $pdo->prepare("SELECT v.id_venta, v.fecha_venta, v.total_venta, v.estado_venta,
                                  cv.id_cancelacion,
                                  u.nombre_usuario, mp.nombre_medio
                           FROM Venta v
                           LEFT JOIN Cancelacion_Venta cv ON cv.id_venta = v.id_venta
                           LEFT JOIN Usuario u ON u.id_usuario = v.id_usuario
                           LEFT JOIN Medio_pago mp ON mp.id_medio = v.id_medio
                           WHERE v.id_venta = ?
                           LIMIT 1");
    $stmt->execute([$idCancelacion]);
    $ventaCancelar = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ventaCancelar) {
      $cancelPageError = 'La venta seleccionada no existe.';
    } elseif (strtoupper((string)$ventaCancelar['estado_venta']) === 'CANCELADA' || !empty($ventaCancelar['id_cancelacion'])) {
      $refCancelacion = !empty($ventaCancelar['id_cancelacion']) ? ' Cancelación #' . (int)$ventaCancelar['id_cancelacion'] . '.' : '';
      $cancelPageError = 'La venta #' . $idCancelacion . ' ya está cancelada.' . $refCancelacion;
    } else {
      $stmt = $pdo->prepare("SELECT p.nombre_producto, dv.cantidad_detalle, dv.precio_unitario
                             FROM Detalle_venta dv
                             INNER JOIN Producto p ON p.id_producto = dv.id_producto
                             WHERE dv.id_venta = ?
                             ORDER BY p.nombre_producto");
      $stmt->execute([$idCancelacion]);
      $detalleCancelar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
  }

  if ($cancelPageError !== null):
?>
<section class="page-heading"><div><p class="eyebrow">Facturación / Ventas</p><h1 class="title">Cancelar venta</h1></div></section>
<div class="profile-card"><div class="alert error"><?= htmlspecialchars($cancelPageError) ?></div><a class="btn-secondary-link" href="index.php?mod=ventas">Volver al historial</a></div>
<?php
    return;
  endif;
?>
<section class="page-heading">
  <div>
    <p class="eyebrow">Facturación / Ventas</p>
    <h1 class="title">Cancelar venta #<?= $idCancelacion ?></h1>
    <p class="subtitle">La factura no se eliminará. Se conservará como cancelada, se registrará el reintegro y se devolverá el stock.</p>
  </div>
</section>

<div class="profile-card wide-card sale-cancel-card">
  <div class="profile-card-header">
    <div class="profile-icon sale-cancel-icon">↩</div>
    <div>
      <h2>Confirmar cancelación</h2>
      <p>Esta reversión quedará trazada en Ventas, Movimientos y Auditoría.</p>
    </div>
  </div>

  <?php if ($e = flash('error')): ?>
    <div class="alert error"><?= htmlspecialchars($e) ?></div>
  <?php endif; ?>

  <div class="cancel-sale-summary">
    <div><span>Venta</span><strong>#<?= $idCancelacion ?></strong></div>
    <div><span>Fecha</span><strong><?= htmlspecialchars($ventaCancelar['fecha_venta']) ?></strong></div>
    <div><span>Usuario</span><strong><?= htmlspecialchars($ventaCancelar['nombre_usuario'] ?: '-') ?></strong></div>
    <div><span>Medio de pago</span><strong><?= htmlspecialchars($ventaCancelar['nombre_medio'] ?: '-') ?></strong></div>
    <div class="cancel-sale-total"><span>Monto a reintegrar</span><strong>$<?= number_format((float)$ventaCancelar['total_venta'], 2, ',', '.') ?></strong></div>
  </div>

  <div class="profile-section">
    <h3>Productos que volverán al stock</h3>
    <div class="table-responsive">
      <table class="result-table">
        <thead><tr><th>Producto</th><th>Cantidad</th><th>Precio facturado</th><th>Subtotal</th></tr></thead>
        <tbody>
          <?php foreach ($detalleCancelar as $item): ?>
            <tr>
              <td><?= htmlspecialchars($item['nombre_producto']) ?></td>
              <td><?= (int)$item['cantidad_detalle'] ?></td>
              <td>$<?= number_format((float)$item['precio_unitario'], 2, ',', '.') ?></td>
              <td>$<?= number_format((float)$item['precio_unitario'] * (int)$item['cantidad_detalle'], 2, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="cancel-warning-box">
    <strong>Qué hará el sistema</strong>
    <span>La venta original seguirá existiendo y quedará con estado CANCELADA.</span>
    <span>Se creará un nuevo registro independiente en Cancelacion_Venta.</span>
    <span>Ese registro conservará el reintegro, el motivo, la fecha y el usuario responsable.</span>
    <span>Las unidades vendidas volverán automáticamente al stock.</span>
    <span>Se crearán movimientos automáticos de entrada vinculados a esta venta.</span>
  </div>

  <form method="post" action="index.php?mod=ventas&action=cancel&id=<?= $idCancelacion ?>" class="profile-form" data-confirm="¿Confirmar la cancelación? La factura se conservará, se registrará el reintegro y las unidades volverán al stock.">
    <input type="hidden" name="form" value="cancel_venta">
    <input type="hidden" name="id_venta" value="<?= $idCancelacion ?>">
    <div class="form-group">
      <label class="label" for="motivo_cancelacion">Motivo de cancelación</label>
      <textarea class="input" id="motivo_cancelacion" name="motivo_cancelacion" maxlength="255" required placeholder="Ej.: devolución completa solicitada por el cliente"></textarea>
      <div class="form-help">Este motivo quedará guardado en el nuevo registro de cancelación y en Auditoría.</div>
    </div>
    <div class="module-actions">
      <a class="btn-secondary-link" href="index.php?mod=ventas">Volver</a>
      <button class="btn-danger-solid" type="submit">Cancelar venta y registrar reintegro</button>
    </div>
  </form>
</div>
<?php
return;
endif;

$fDesde = trim($_GET['desde'] ?? '');
$fHasta = trim($_GET['hasta'] ?? '');
$fMedio = (int)($_GET['medio'] ?? 0);
$fUsuario = trim($_GET['usuario'] ?? '');
$fEstado = strtoupper(trim((string)($_GET['estado'] ?? '')));
if (!in_array($fEstado, ['', 'ACTIVA', 'CANCELADA'], true)) $fEstado = '';

$porPagina = 15;
$paginaActual = max(1, (int)($_GET['pagina'] ?? 1));
$where = ' WHERE 1=1';
$params = [];
if ($fDesde !== '') { $where .= ' AND DATE(v.fecha_venta) >= ?'; $params[] = $fDesde; }
if ($fHasta !== '') { $where .= ' AND DATE(v.fecha_venta) <= ?'; $params[] = $fHasta; }
if ($fMedio > 0) { $where .= ' AND v.id_medio = ?'; $params[] = $fMedio; }
if ($fUsuario !== '') { $where .= ' AND u.nombre_usuario LIKE ?'; $params[] = '%' . $fUsuario . '%'; }
if ($fEstado !== '') { $where .= ' AND v.estado_venta = ?'; $params[] = $fEstado; }

$summarySql = "SELECT COUNT(*) AS cantidad,
                      COALESCE(SUM(CASE WHEN v.estado_venta = 'CANCELADA' THEN 0 ELSE v.total_venta END), 0) AS total_activo,
                      COALESCE(SUM(CASE WHEN v.estado_venta = 'CANCELADA' THEN 1 ELSE 0 END), 0) AS canceladas,
                      COALESCE(SUM(CASE WHEN v.estado_venta = 'CANCELADA' THEN cv.monto_reintegrado ELSE 0 END), 0) AS reintegrado
               FROM Venta v
               LEFT JOIN Cancelacion_Venta cv ON cv.id_venta = v.id_venta
               LEFT JOIN Usuario u ON v.id_usuario = u.id_usuario" . $where;
$stmt = $pdo->prepare($summarySql);
$stmt->execute($params);
$resumenVentas = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$totalVentas = (int)($resumenVentas['cantidad'] ?? 0);
$totalActivo = (float)($resumenVentas['total_activo'] ?? 0);
$canceladas = (int)($resumenVentas['canceladas'] ?? 0);
$totalReintegrado = (float)($resumenVentas['reintegrado'] ?? 0);

$totalPaginas = max(1, (int)ceil($totalVentas / $porPagina));
if ($paginaActual > $totalPaginas) $paginaActual = $totalPaginas;
$offset = ($paginaActual - 1) * $porPagina;

$sql = "SELECT v.id_venta, v.fecha_venta, v.total_venta, v.estado_venta,
               cv.id_cancelacion, cv.fecha_cancelacion, cv.motivo_cancelacion, cv.monto_reintegrado,
               u.nombre_usuario, uc.nombre_usuario AS usuario_cancelacion, mp.nombre_medio,
               COALESCE(SUM(dv.cantidad_detalle), 0) AS unidades,
               GROUP_CONCAT(CONCAT(p.nombre_producto, ' x', dv.cantidad_detalle)
                            ORDER BY p.nombre_producto SEPARATOR ', ') AS detalle
        FROM Venta v
        LEFT JOIN Cancelacion_Venta cv ON cv.id_venta = v.id_venta
        LEFT JOIN Usuario u ON v.id_usuario = u.id_usuario
        LEFT JOIN Usuario uc ON cv.id_usuario = uc.id_usuario
        LEFT JOIN Medio_pago mp ON v.id_medio = mp.id_medio
        LEFT JOIN Detalle_venta dv ON v.id_venta = dv.id_venta
        LEFT JOIN Producto p ON dv.id_producto = p.id_producto" . $where .
       " GROUP BY v.id_venta, v.fecha_venta, v.total_venta, v.estado_venta,
                  cv.id_cancelacion, cv.fecha_cancelacion, cv.motivo_cancelacion, cv.monto_reintegrado,
                  u.nombre_usuario, uc.nombre_usuario, mp.nombre_medio
         ORDER BY v.fecha_venta DESC, v.id_venta DESC
         LIMIT " . $porPagina . " OFFSET " . $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$paginaUrl = static function (int $pagina): string {
  $query = $_GET;
  $query['mod'] = 'ventas';
  $query['pagina'] = $pagina;
  unset($query['action'], $query['id']);
  return 'index.php?' . http_build_query($query);
};
?>

<h1 class="title">Facturación / Ventas</h1>
<p class="subtitle">Las ventas se conservan históricamente. Una venta confirmada puede cancelarse sin borrar su factura, registrando reintegro y devolución automática de stock.</p>

<div class="report-kpis sales-kpis">
  <div class="report-kpi"><span>Ventas encontradas</span><strong><?= $totalVentas ?></strong></div>
  <div class="report-kpi"><span>Facturación activa</span><strong>$<?= number_format($totalActivo, 2, ',', '.') ?></strong></div>
  <div class="report-kpi <?= $canceladas > 0 ? 'kpi-warning' : '' ?>"><span>Ventas canceladas</span><strong><?= $canceladas ?></strong></div>
  <div class="report-kpi <?= $totalReintegrado > 0 ? 'kpi-danger' : '' ?>"><span>Reintegros registrados</span><strong class="negative-amount">- $<?= number_format($totalReintegrado, 2, ',', '.') ?></strong></div>
</div>

<div class="profile-card wide-card">
  <div class="profile-card-header">
    <div class="profile-icon">🧾</div>
    <div>
      <h2>Historial de ventas</h2>
      <p>Consulta ventas activas y canceladas. Las cancelaciones conservan el importe y el detalle original.</p>
    </div>
    <div class="profile-actions">
      <a class="btn-add" href="index.php?mod=ventas&action=add">+ Registrar venta</a>
    </div>
  </div>

  <div class="profile-section">
    <form method="get" action="index.php" class="filter-form">
      <input type="hidden" name="mod" value="ventas">
      <div class="filter-grid">
        <div class="form-group"><label class="label">Desde</label><input class="input" type="date" name="desde" value="<?= htmlspecialchars($fDesde) ?>"></div>
        <div class="form-group"><label class="label">Hasta</label><input class="input" type="date" name="hasta" value="<?= htmlspecialchars($fHasta) ?>"></div>
        <div class="form-group">
          <label class="label">Medio de pago</label>
          <select class="input" name="medio">
            <option value="0">Todos</option>
            <?php foreach ($mediosPago as $m): ?>
              <option value="<?= (int)$m['id_medio'] ?>" <?= $fMedio === (int)$m['id_medio'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nombre_medio']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="label">Usuario</label><input class="input" type="text" name="usuario" value="<?= htmlspecialchars($fUsuario) ?>"></div>
        <div class="form-group">
          <label class="label">Estado</label>
          <select class="input" name="estado">
            <option value="" <?= $fEstado === '' ? 'selected' : '' ?>>Todas</option>
            <option value="ACTIVA" <?= $fEstado === 'ACTIVA' ? 'selected' : '' ?>>Activas</option>
            <option value="CANCELADA" <?= $fEstado === 'CANCELADA' ? 'selected' : '' ?>>Canceladas</option>
          </select>
        </div>
      </div>
      <div class="module-actions left-actions">
        <button class="btn" type="submit">Aplicar filtros</button>
        <a class="btn-secondary-link" href="index.php?mod=ventas">Limpiar</a>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="result-table sales-history-table">
      <thead><tr><th>N.º</th><th>Fecha</th><th>Usuario</th><th>Productos</th><th>Medio</th><th>Total original</th><th>Estado / reintegro</th><th>Acción</th></tr></thead>
      <tbody>
      <?php if (!$ventas): ?>
        <tr><td colspan="8" class="empty-cell">No se encontraron ventas.</td></tr>
      <?php else: ?>
        <?php foreach ($ventas as $v): ?>
          <?php $esCancelada = strtoupper((string)$v['estado_venta']) === 'CANCELADA' || !empty($v['id_cancelacion']); ?>
          <tr class="<?= $esCancelada ? 'sale-row-cancelled' : '' ?>">
            <td><strong>#<?= (int)$v['id_venta'] ?></strong></td>
            <td><?= htmlspecialchars($v['fecha_venta']) ?></td>
            <td><?= htmlspecialchars($v['nombre_usuario'] ?: '-') ?></td>
            <td>
              <?= htmlspecialchars($v['detalle'] ?: 'Sin detalle histórico') ?>
              <div class="audit-muted"><?= (int)$v['unidades'] ?> unidad<?= (int)$v['unidades'] === 1 ? '' : 'es' ?></div>
            </td>
            <td><?= htmlspecialchars($v['nombre_medio'] ?: '-') ?></td>
            <td><strong>$<?= number_format((float)$v['total_venta'], 2, ',', '.') ?></strong></td>
            <td>
              <?php if ($esCancelada): ?>
                <span class="status-badge status-cancelled">CANCELADA</span>
                <?php if (!empty($v['id_cancelacion'])): ?><div class="audit-muted"><strong>Cancelación #<?= (int)$v['id_cancelacion'] ?></strong></div><?php endif; ?>
                <div class="sale-refund">Reintegro: <strong class="negative-amount">- $<?= number_format((float)$v['monto_reintegrado'], 2, ',', '.') ?></strong></div>
                <div class="audit-muted"><?= htmlspecialchars($v['fecha_cancelacion'] ?: '') ?> · <?= htmlspecialchars($v['usuario_cancelacion'] ?: 'Usuario no disponible') ?></div>
                <?php if (!empty($v['motivo_cancelacion'])): ?><div class="sale-cancel-reason" title="<?= htmlspecialchars($v['motivo_cancelacion']) ?>"><?= htmlspecialchars($v['motivo_cancelacion']) ?></div><?php endif; ?>
              <?php else: ?>
                <span class="status-badge status-active">ACTIVA</span>
              <?php endif; ?>
            </td>
            <td class="actions-cell">
              <?php if (!$esCancelada): ?>
                <a class="btn-danger compact-btn" href="index.php?mod=ventas&action=cancel&id=<?= (int)$v['id_venta'] ?>">Cancelar venta</a>
              <?php else: ?>
                <div class="sale-cancel-action-note" aria-label="Venta cancelada">
                  <strong>Venta cancelada N.º <?= (int)$v['id_venta'] ?></strong>
                  <?php if (!empty($v['id_cancelacion'])): ?>
                    <span>Cancelación N.º <?= (int)$v['id_cancelacion'] ?></span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="pagination-bar">
    <div class="pagination-summary">
      <?= $totalVentas ?> venta<?= $totalVentas === 1 ? '' : 's' ?> encontrada<?= $totalVentas === 1 ? '' : 's' ?> · Página <?= $paginaActual ?> de <?= $totalPaginas ?>
    </div>
    <?php if ($totalPaginas > 1): ?>
      <nav class="pagination" aria-label="Paginación de ventas">
        <a class="page-link <?= $paginaActual <= 1 ? 'disabled' : '' ?>" href="<?= $paginaActual > 1 ? htmlspecialchars($paginaUrl($paginaActual - 1)) : '#' ?>">‹ Anterior</a>
        <?php for ($p = max(1, $paginaActual - 2); $p <= min($totalPaginas, $paginaActual + 2); $p++): ?>
          <a class="page-link <?= $p === $paginaActual ? 'active' : '' ?>" href="<?= htmlspecialchars($paginaUrl($p)) ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a class="page-link <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>" href="<?= $paginaActual < $totalPaginas ? htmlspecialchars($paginaUrl($paginaActual + 1)) : '#' ?>">Siguiente ›</a>
      </nav>
    <?php endif; ?>
  </div>
</div>
