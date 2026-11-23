<?php
$desde = trim($_GET['desde'] ?? '');
$hasta = trim($_GET['hasta'] ?? '');
$reportSection = trim((string) ($_GET['section'] ?? 'resumen'));
if (!in_array($reportSection, ['resumen', 'graficos'], true)) {
    $reportSection = 'resumen';
}

$whereVenta = " WHERE v.estado_venta = 'ACTIVA'";
$paramsVenta = [];
if ($desde !== '') { $whereVenta .= ' AND DATE(v.fecha_venta) >= ?'; $paramsVenta[] = $desde; }
if ($hasta !== '') { $whereVenta .= ' AND DATE(v.fecha_venta) <= ?'; $paramsVenta[] = $hasta; }

$stmt = $pdo->prepare("SELECT COUNT(*) AS cantidad, COALESCE(SUM(v.total_venta), 0) AS total FROM Venta v" . $whereVenta);
$stmt->execute($paramsVenta);
$resumenVenta = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT COALESCE(SUM(dv.cantidad_detalle), 0)
                       FROM Venta v
                       INNER JOIN Detalle_venta dv ON v.id_venta = dv.id_venta" . $whereVenta);
$stmt->execute($paramsVenta);
$unidadesVendidas = (int)$stmt->fetchColumn();

$stockResumen = $pdo->query("SELECT COUNT(*) AS productos,
                                    COALESCE(SUM(stock_producto),0) AS unidades_stock,
                                    SUM(CASE WHEN stock_producto <= 5 THEN 1 ELSE 0 END) AS stock_bajo
                             FROM Producto
                             WHERE activo_producto = 1")->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT p.nombre_producto,
                              COALESCE(SUM(dv.cantidad_detalle),0) AS unidades,
                              COALESCE(SUM(dv.cantidad_detalle * dv.precio_unitario),0) AS importe
                       FROM Venta v
                       INNER JOIN Detalle_venta dv ON v.id_venta = dv.id_venta
                       INNER JOIN Producto p ON dv.id_producto = p.id_producto" . $whereVenta . "
                       GROUP BY p.id_producto, p.nombre_producto
                       ORDER BY unidades DESC, importe DESC
                       LIMIT 10");
$stmt->execute($paramsVenta);
$topProductos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$whereMov = ' WHERE 1=1';
$paramsMov = [];
if ($desde !== '') { $whereMov .= ' AND DATE(m.fecha_movimiento) >= ?'; $paramsMov[] = $desde; }
if ($hasta !== '') { $whereMov .= ' AND DATE(m.fecha_movimiento) <= ?'; $paramsMov[] = $hasta; }
$stmt = $pdo->prepare("SELECT tm.nombre_tipo, COUNT(*) AS operaciones, COALESCE(SUM(m.cantidad_movimiento),0) AS unidades
                       FROM Movimiento m
                       LEFT JOIN Tipo_movimiento tm ON m.id_tipo = tm.id_tipo" . $whereMov . "
                       GROUP BY tm.id_tipo, tm.nombre_tipo
                       ORDER BY tm.nombre_tipo");
$stmt->execute($paramsMov);
$movimientosResumen = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stockBajo = $pdo->query("SELECT nombre_producto, stock_producto
                          FROM Producto
                          WHERE activo_producto = 1
                            AND stock_producto <= 5
                          ORDER BY stock_producto ASC, nombre_producto ASC
                          LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
$donutPalette = ['#2563eb', '#0ea5e9', '#14b8a6', '#22c55e', '#84cc16', '#eab308', '#f97316', '#ef4444', '#a855f7', '#64748b'];
$buildDonutGradient = static function (array $filas, string $valorKey, array $colores): string {
    $total = 0.0;
    foreach ($filas as $fila) {
        $total += max(0.0, (float) ($fila[$valorKey] ?? 0));
    }
    if ($total <= 0) {
        return 'conic-gradient(#e5e7eb 0% 100%)';
    }
    $segmentos = [];
    $inicio = 0.0;
    foreach ($filas as $indice => $fila) {
        $valor = max(0.0, (float) ($fila[$valorKey] ?? 0));
        if ($valor <= 0) {
            continue;
        }
        $fin = $inicio + (($valor / $total) * 100);
        $color = $colores[$indice % count($colores)];
        $segmentos[] = $color . ' ' . number_format($inicio, 3, '.', '') . '% ' . number_format($fin, 3, '.', '') . '%';
        $inicio = $fin;
    }
    return 'conic-gradient(' . implode(', ', $segmentos) . ')';
};
?>

<style>
  .donut-reports-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px;margin-top:18px}
  .donut-report-card{min-width:0}
  .donut-report-body{display:grid;grid-template-columns:210px minmax(0,1fr);gap:28px;align-items:center;padding-top:10px}
  .donut-ring{width:184px;height:184px;border-radius:50%;position:relative;display:grid;place-items:center;margin:auto;box-shadow:inset 0 0 0 1px rgba(15,23,42,.06)}
  .donut-ring::after{content:'';position:absolute;width:108px;height:108px;border-radius:50%;background:#fff;box-shadow:0 0 0 1px rgba(15,23,42,.06)}
  .donut-center{position:relative;z-index:2;text-align:center;display:flex;flex-direction:column;line-height:1.15}
  .donut-center strong{font-size:1.6rem;color:#172033}
  .donut-center span{margin-top:5px;font-size:.76rem;color:#6b7280}
  .donut-legend{display:grid;gap:10px;align-content:center}
  .donut-legend-row{display:grid;grid-template-columns:12px minmax(0,1fr) auto;gap:8px;align-items:center;font-size:.84rem;color:#374151}
  .donut-legend-dot{width:10px;height:10px;border-radius:50%;background:var(--donut-color)}
  .donut-legend-label{min-width:0;white-space:normal;line-height:1.3}
  .donut-legend-value{text-align:right;font-weight:700;color:#172033;white-space:nowrap}
  .donut-legend-meta{grid-column:2 / 4;margin-top:-5px;font-size:.76rem;color:#8a94a6;line-height:1.3}
  .donut-empty{min-height:184px;display:grid;place-items:center;text-align:center;color:#7b8494;padding:20px}
  .donut-report-card:last-child{grid-column:1 / -1}.donut-report-card:last-child .donut-report-body{grid-template-columns:220px minmax(0,440px);max-width:760px;margin:0 auto}
  @media (max-width:1180px){.donut-reports-grid{grid-template-columns:1fr}.donut-report-card:last-child{grid-column:auto}.donut-report-body,.donut-report-card:last-child .donut-report-body{grid-template-columns:210px minmax(0,1fr);max-width:none;margin:0}}
  @media (max-width:620px){.donut-report-body{grid-template-columns:1fr}.donut-legend{width:100%}}
</style>

<section class="page-heading report-page-heading">
  <div>
    <p class="eyebrow">Análisis</p>
    <h1 class="title">Reportes</h1>
    <p class="subtitle">Consultá indicadores operativos o explorá los siete informes gráficos del sistema.</p>
  </div>
</section>

<nav class="submodule-nav" aria-label="Secciones de Reportes">
  <a href="index.php?mod=reportes&section=resumen" class="submodule-link <?= $reportSection === 'resumen' ? 'active' : '' ?>">
    <span>▤</span><div><strong>Resumen</strong><small>Indicadores y tablas</small></div>
  </a>
  <a href="index.php?mod=reportes&section=graficos" class="submodule-link <?= $reportSection === 'graficos' ? 'active' : '' ?>">
    <span>▥</span><div><strong>Informes gráficos</strong><small>Visualizaciones y tendencias</small></div>
  </a>
</nav>

<div class="surface-card report-filter-card">
  <div class="surface-card-header compact-header">
    <div>
      <p class="eyebrow">Filtro común</p>
      <h2>Período de análisis</h2>
      <p>Las fechas afectan los informes históricos. Las ventas usan su fecha de venta y las cancelaciones usan su fecha de cancelación, para reflejar cuándo ocurrió realmente el reintegro.</p>
    </div>
  </div>
  <form method="get" action="index.php" class="filter-form">
    <input type="hidden" name="mod" value="reportes">
    <input type="hidden" name="section" value="<?= htmlspecialchars($reportSection) ?>">
    <?php if ($reportSection === 'graficos'): ?><input type="hidden" name="anio" value="<?= (int)$reporteAnio ?>"><?php endif; ?>
    <div class="filter-grid report-date-grid">
      <div class="form-group"><label class="label">Desde</label><input class="input" type="date" name="desde" value="<?= htmlspecialchars($desde) ?>"></div>
      <div class="form-group"><label class="label">Hasta</label><input class="input" type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>"></div>
    </div>
    <div class="module-actions left-actions">
      <button class="btn" type="submit">Aplicar período</button>
      <a class="btn-secondary-link" href="index.php?mod=reportes&section=<?= htmlspecialchars($reportSection) ?>">Limpiar filtros</a>
    </div>
  </form>
</div>

<?php if ($reportSection === 'resumen'): ?>
  <div class="report-kpis report-summary-kpis">
    <div class="report-kpi"><span>Ventas activas</span><strong><?= (int)$resumenVenta['cantidad'] ?></strong><small>operaciones efectivas</small></div>
    <div class="report-kpi"><span>Facturación efectiva</span><strong>$<?= number_format((float)$resumenVenta['total'], 2, ',', '.') ?></strong><small>excluye ventas canceladas</small></div>
    <div class="report-kpi <?= $reporteCancelacionesCantidad > 0 ? 'kpi-danger' : '' ?>"><span>Ventas canceladas</span><strong><?= $reporteCancelacionesCantidad ?></strong><small>según fecha de cancelación</small></div>
    <div class="report-kpi <?= $reporteReintegrosTotal > 0 ? 'kpi-danger' : '' ?>"><span>Total reintegrado</span><strong class="negative-amount">- $<?= number_format($reporteReintegrosTotal, 2, ',', '.') ?></strong><small>impacto negativo del período</small></div>
    <div class="report-kpi"><span>Unidades vendidas</span><strong><?= $unidadesVendidas ?></strong><small>productos despachados</small></div>
    <div class="report-kpi"><span>Productos activos</span><strong><?= (int)$stockResumen['productos'] ?></strong><small>disponibles para operar</small></div>
    <div class="report-kpi"><span>Unidades en stock</span><strong><?= (int)$stockResumen['unidades_stock'] ?></strong><small>inventario actual</small></div>
    <div class="report-kpi <?= (int)$stockResumen['stock_bajo'] > 0 ? 'kpi-warning' : '' ?>"><span>Stock bajo (≤ 5)</span><strong><?= (int)$stockResumen['stock_bajo'] ?></strong><small>requieren atención</small></div>
  </div>

  <div class="profile-card wide-card">
    <div class="profile-card-header">
      <div class="profile-icon">💳</div>
      <div>
        <h2>Ventas de hoy</h2>
        <p>Facturación activa del día desglosada por medio de pago. No representa ganancia.</p>
      </div>
    </div>
    <?php if ($ventasHoyError): ?>
      <div class="module-warning"><?= htmlspecialchars($ventasHoyError) ?></div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="result-table">
          <thead><tr><th>Medio de pago</th><th>Ventas que lo usaron</th><th>Importe</th></tr></thead>
          <tbody>
          <?php if (!$ventasHoyPorMedio): ?>
            <tr><td colspan="3" class="empty-cell">Todavía no hay ventas activas registradas hoy.</td></tr>
          <?php else: ?>
            <?php foreach ($ventasHoyPorMedio as $medioHoy): ?>
              <tr>
                <td><?= htmlspecialchars($medioHoy['medio']) ?></td>
                <td><?= (int)$medioHoy['cantidad_ventas'] ?></td>
                <td><strong>$<?= number_format((float)$medioHoy['total'], 2, ',', '.') ?></strong></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
          <tfoot>
            <tr><th colspan="2">Total vendido hoy (<?= $ventasHoyCantidad ?> venta<?= $ventasHoyCantidad === 1 ? '' : 's' ?>)</th><th>$<?= number_format($ventasHoyTotal, 2, ',', '.') ?></th></tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="report-grid">
    <div class="profile-card wide-card">
      <div class="profile-card-header"><div class="profile-icon">🏆</div><div><h2>Productos más vendidos</h2><p>Top 10 por unidades registradas en el período.</p></div></div>
      <div class="table-responsive">
        <table class="result-table">
          <thead><tr><th>Producto</th><th>Unidades</th><th>Importe</th></tr></thead>
          <tbody>
          <?php if (!$topProductos): ?><tr><td colspan="3" class="empty-cell">Todavía no hay detalles de ventas para el período.</td></tr><?php endif; ?>
          <?php foreach ($topProductos as $p): ?>
            <tr><td><?= htmlspecialchars($p['nombre_producto']) ?></td><td><?= (int)$p['unidades'] ?></td><td>$<?= number_format((float)$p['importe'], 2, ',', '.') ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="profile-card wide-card">
      <div class="profile-card-header"><div class="profile-icon">⚠</div><div><h2>Productos con stock bajo</h2><p>Artículos con 5 unidades o menos disponibles.</p></div></div>
      <div class="table-responsive">
        <table class="result-table">
          <thead><tr><th>Producto</th><th>Stock</th></tr></thead>
          <tbody>
          <?php if (!$stockBajo): ?><tr><td colspan="2" class="empty-cell">No hay productos con stock bajo.</td></tr><?php endif; ?>
          <?php foreach ($stockBajo as $p): ?>
            <tr><td><?= htmlspecialchars($p['nombre_producto']) ?></td><td><span class="status-badge status-low"><?= (int)$p['stock_producto'] ?></span></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="profile-card wide-card report-movement-card">
    <div class="profile-card-header"><div class="profile-icon">📑</div><div><h2>Resumen de movimientos</h2><p>Operaciones y unidades movilizadas por tipo de movimiento.</p></div></div>
    <div class="table-responsive">
      <table class="result-table">
        <thead><tr><th>Tipo</th><th>Operaciones</th><th>Unidades</th></tr></thead>
        <tbody>
        <?php if (!$movimientosResumen): ?><tr><td colspan="3" class="empty-cell">No hay movimientos para el período.</td></tr><?php endif; ?>
        <?php foreach ($movimientosResumen as $m): ?>
          <tr><td><?= htmlspecialchars($m['nombre_tipo'] ?: 'Sin tipo') ?></td><td><?= (int)$m['operaciones'] ?></td><td><?= (int)$m['unidades'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="profile-card wide-card cancellation-report-card">
    <div class="profile-card-header">
      <div class="profile-icon cancellation-icon">↩</div>
      <div>
        <h2>Cancelaciones y reintegros</h2>
        <p>Últimas cancelaciones del período seleccionado. El reintegro se muestra como importe negativo porque representa una salida de dinero.</p>
      </div>
    </div>
    <?php if ($reporteCancelacionesError): ?>
      <div class="module-warning"><?= htmlspecialchars($reporteCancelacionesError) ?></div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="result-table">
          <thead><tr><th>Cancelación</th><th>Venta</th><th>Fecha</th><th>Usuario</th><th>Motivo</th><th>Reintegro</th></tr></thead>
          <tbody>
          <?php if (!$reporteCancelaciones): ?><tr><td colspan="6" class="empty-cell">No hay ventas canceladas para el período.</td></tr><?php endif; ?>
          <?php foreach ($reporteCancelaciones as $cancelacion): ?>
            <tr>
              <td>N.º <?= (int)$cancelacion['id_cancelacion'] ?></td>
              <td>Venta N.º <?= (int)$cancelacion['id_venta'] ?></td>
              <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($cancelacion['fecha_cancelacion']))) ?></td>
              <td><?= htmlspecialchars($cancelacion['usuario_cancelacion']) ?></td>
              <td><?= htmlspecialchars($cancelacion['motivo_cancelacion']) ?></td>
              <td><strong class="negative-amount">- $<?= number_format((float)$cancelacion['monto_reintegrado'], 2, ',', '.') ?></strong></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

<?php else: ?>
  <div class="report-kpis graphics-kpis">
    <div class="report-kpi"><span>Facturación efectiva</span><strong>$<?= number_format((float)$resumenVenta['total'], 2, ',', '.') ?></strong><small>excluye ventas canceladas</small></div>
    <div class="report-kpi"><span>Ventas activas</span><strong><?= (int)$resumenVenta['cantidad'] ?></strong><small>operaciones efectivas</small></div>
    <div class="report-kpi <?= $reporteCancelacionesCantidad > 0 ? 'kpi-danger' : '' ?>"><span>Ventas canceladas</span><strong><?= $reporteCancelacionesCantidad ?></strong><small>según fecha de cancelación</small></div>
    <div class="report-kpi <?= $reporteReintegrosTotal > 0 ? 'kpi-danger' : '' ?>"><span>Total reintegrado</span><strong class="negative-amount">- $<?= number_format($reporteReintegrosTotal, 2, ',', '.') ?></strong><small>salida de dinero</small></div>
    <div class="report-kpi"><span>Stock total</span><strong><?= (int)$stockResumen['unidades_stock'] ?></strong><small>unidades actuales</small></div>
    <div class="report-kpi <?= (int)$stockResumen['stock_bajo'] > 0 ? 'kpi-warning' : '' ?>"><span>Stock bajo</span><strong><?= (int)$stockResumen['stock_bajo'] ?></strong><small>productos</small></div>
  </div>

  <div class="profile-card wide-card monthly-sales-card">
    <div class="profile-card-header report-card-heading">
      <div class="profile-icon">📊</div>
      <div>
        <div class="report-title-line"><h2>Ventas por mes</h2><span class="report-number-badge">Informe 1</span></div>
        <p>Facturación efectiva mensual del año seleccionado; las ventas canceladas quedan fuera del cálculo.</p>
      </div>
      <form method="get" action="index.php" class="monthly-year-form report-heading-form">
        <input type="hidden" name="mod" value="reportes">
        <input type="hidden" name="section" value="graficos">
        <?php if ($desde !== ''): ?><input type="hidden" name="desde" value="<?= htmlspecialchars($desde) ?>"><?php endif; ?>
        <?php if ($hasta !== ''): ?><input type="hidden" name="hasta" value="<?= htmlspecialchars($hasta) ?>"><?php endif; ?>
        <div class="form-group monthly-year-group"><label class="label" for="reporteAnio">Año</label><select class="input" id="reporteAnio" name="anio"><?php foreach ($reporteAnios as $anio): ?><option value="<?= (int)$anio ?>" <?= (int)$anio === (int)$reporteAnio ? 'selected' : '' ?>><?= (int)$anio ?></option><?php endforeach; ?></select></div>
        <button class="btn compact-btn" type="submit">Ver año</button>
      </form>
    </div>

    <?php if ($reporteVentasError): ?>
      <div class="module-warning"><?= htmlspecialchars($reporteVentasError) ?></div>
    <?php else: ?>
      <div class="report-kpis monthly-sales-kpis">
        <div class="report-kpi"><span>Total vendido en <?= (int)$reporteAnio ?></span><strong>$<?= number_format($reporteVentasTotal, 2, ',', '.') ?></strong></div>
        <div class="report-kpi"><span>Cantidad de ventas</span><strong><?= $reporteVentasCantidad ?></strong></div>
        <div class="report-kpi"><span>Ticket promedio</span><strong>$<?= number_format($reporteTicketPromedio, 2, ',', '.') ?></strong></div>
      </div>
      <?php if ($reporteVentasCantidad === 0): ?>
        <div class="monthly-chart-empty">No hay ventas registradas para <?= (int)$reporteAnio ?>.</div>
      <?php else: ?>
        <div class="monthly-chart-scroll" role="region" aria-label="Gráfico de ventas mensuales" tabindex="0">
          <div class="monthly-chart">
            <?php foreach ($ventasMensuales as $mes): ?>
              <?php $altura = $reporteMaxMensual > 0 ? ($mes['total'] / $reporteMaxMensual) * 100 : 0; if ($mes['total'] > 0 && $altura < 3) $altura = 3; ?>
              <div class="monthly-bar-item">
                <div class="monthly-bar-value" title="$<?= number_format((float)$mes['total'], 2, ',', '.') ?>"><?php if ((float)$mes['total'] > 0): ?>$<?= number_format((float)$mes['total'], 0, ',', '.') ?><?php else: ?><span class="monthly-zero">$0</span><?php endif; ?></div>
                <div class="monthly-bar-track"><div class="monthly-bar-fill" style="height: <?= number_format($altura, 2, '.', '') ?>%;" aria-label="<?= htmlspecialchars($mes['nombre']) ?>: $<?= number_format((float)$mes['total'], 2, ',', '.') ?>"></div></div>
                <strong class="monthly-bar-label"><?= htmlspecialchars($mes['nombre']) ?></strong>
                <span class="monthly-bar-count"><?= (int)$mes['cantidad'] ?> venta<?= (int)$mes['cantidad'] === 1 ? '' : 's' ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="report-graphics-grid">
    <div class="profile-card wide-card report-graphic-card">
      <div class="profile-card-header report-card-heading"><div class="profile-icon">🏆</div><div><div class="report-title-line"><h2>Productos más vendidos</h2><span class="report-number-badge">Informe 2</span></div><p>Top 5 por unidades de ventas activas<?= ($desde !== '' || $hasta !== '') ? ' dentro del período seleccionado' : ' en todo el historial' ?>.</p></div></div>
      <?php if ($reporteProductosError): ?><div class="module-warning"><?= htmlspecialchars($reporteProductosError) ?></div>
      <?php elseif (!$productosMasVendidosGrafico): ?><div class="horizontal-chart-empty">Todavía no hay detalles de ventas para generar este gráfico.</div>
      <?php else: ?><div class="horizontal-report-chart" role="img" aria-label="Gráfico de productos más vendidos">
        <?php foreach ($productosMasVendidosGrafico as $producto): ?><?php $ancho = $reporteMaxProductoUnidades > 0 ? ((int)$producto['unidades'] / $reporteMaxProductoUnidades) * 100 : 0; if ((int)$producto['unidades'] > 0 && $ancho < 4) $ancho = 4; ?>
          <div class="horizontal-report-row"><div class="horizontal-report-row-head"><strong><?= htmlspecialchars($producto['nombre_producto']) ?></strong><span><?= (int)$producto['unidades'] ?> unidad<?= (int)$producto['unidades'] === 1 ? '' : 'es' ?></span></div><div class="horizontal-report-track"><div class="horizontal-report-fill" style="width: <?= number_format($ancho, 2, '.', '') ?>%;"></div></div><div class="horizontal-report-meta">Importe generado: $<?= number_format((float)$producto['importe'], 2, ',', '.') ?></div></div>
        <?php endforeach; ?></div><?php endif; ?>
    </div>

    <div class="profile-card wide-card report-graphic-card">
      <div class="profile-card-header report-card-heading"><div class="profile-icon">📦</div><div><div class="report-title-line"><h2>Stock por categoría</h2><span class="report-number-badge">Informe 3</span></div><p>Unidades disponibles actualmente agrupadas por categoría.</p></div></div>
      <?php if ($reporteStockCategoriaError): ?><div class="module-warning"><?= htmlspecialchars($reporteStockCategoriaError) ?></div>
      <?php elseif (!$stockPorCategoria): ?><div class="horizontal-chart-empty">No hay productos cargados para generar este gráfico.</div>
      <?php else: ?><div class="horizontal-report-chart" role="img" aria-label="Gráfico de stock por categoría">
        <?php foreach ($stockPorCategoria as $categoria): ?><?php $ancho = $reporteMaxStockCategoria > 0 ? ((int)$categoria['stock_total'] / $reporteMaxStockCategoria) * 100 : 0; if ((int)$categoria['stock_total'] > 0 && $ancho < 4) $ancho = 4; ?>
          <div class="horizontal-report-row"><div class="horizontal-report-row-head"><strong><?= htmlspecialchars($categoria['categoria']) ?></strong><span><?= (int)$categoria['stock_total'] ?> unidad<?= (int)$categoria['stock_total'] === 1 ? '' : 'es' ?></span></div><div class="horizontal-report-track"><div class="horizontal-report-fill stock-category-fill" style="width: <?= number_format($ancho, 2, '.', '') ?>%;"></div></div><div class="horizontal-report-meta"><?= (int)$categoria['cantidad_productos'] ?> producto<?= (int)$categoria['cantidad_productos'] === 1 ? '' : 's' ?> en la categoría</div></div>
        <?php endforeach; ?></div><?php endif; ?>
    </div>
  </div>

  <div class="profile-card wide-card report-graphic-card payment-report-card">
    <div class="profile-card-header report-card-heading"><div class="profile-icon">💳</div><div><div class="report-title-line"><h2>Ventas por medio de pago</h2><span class="report-number-badge">Informe 4</span></div><p>Facturación efectiva y cantidad de ventas activas por forma de pago<?= ($desde !== '' || $hasta !== '') ? ' dentro del período seleccionado' : ' en todo el historial' ?>.</p></div></div>
    <?php if ($reporteMediosPagoError): ?><div class="module-warning"><?= htmlspecialchars($reporteMediosPagoError) ?></div>
    <?php elseif (!$ventasPorMedioPago): ?><div class="horizontal-chart-empty">Todavía no hay ventas para generar este gráfico.</div>
    <?php else: ?><div class="horizontal-report-chart" role="img" aria-label="Gráfico de ventas por medio de pago">
      <?php foreach ($ventasPorMedioPago as $medioPago): ?><?php $ancho = $reporteMaxMedioPago > 0 ? ((float)$medioPago['total_facturado'] / $reporteMaxMedioPago) * 100 : 0; if ((float)$medioPago['total_facturado'] > 0 && $ancho < 4) $ancho = 4; $porcentaje = $reporteTotalMediosPago > 0 ? ((float)$medioPago['total_facturado'] / $reporteTotalMediosPago) * 100 : 0; ?>
        <div class="horizontal-report-row"><div class="horizontal-report-row-head"><strong><?= htmlspecialchars($medioPago['medio']) ?></strong><span>$<?= number_format((float)$medioPago['total_facturado'], 2, ',', '.') ?></span></div><div class="horizontal-report-track"><div class="horizontal-report-fill payment-method-fill" style="width: <?= number_format($ancho, 2, '.', '') ?>%;"></div></div><div class="horizontal-report-meta"><?= (int)$medioPago['cantidad_ventas'] ?> venta<?= (int)$medioPago['cantidad_ventas'] === 1 ? '' : 's' ?> · <?= number_format($porcentaje, 1, ',', '.') ?>% de la facturación del período</div></div>
      <?php endforeach; ?></div><?php endif; ?>
  </div>

  <div class="donut-reports-grid">
    <div class="profile-card wide-card report-graphic-card donut-report-card">
      <div class="profile-card-header report-card-heading"><div class="profile-icon">◔</div><div><div class="report-title-line"><h2>Ventas por categoría</h2><span class="report-number-badge">Informe 5</span></div><p>Distribución de unidades vendidas por categoría<?= ($desde !== '' || $hasta !== '') ? ' dentro del período seleccionado' : ' en todo el historial' ?>.</p></div></div>
      <?php if ($reporteVentasCategoriaError): ?>
        <div class="module-warning"><?= htmlspecialchars($reporteVentasCategoriaError) ?></div>
      <?php elseif ($reporteVentasCategoriaTotal <= 0): ?>
        <div class="donut-empty">Todavía no hay unidades vendidas para generar este gráfico.</div>
      <?php else: ?>
        <div class="donut-report-body" role="img" aria-label="Gráfico circular de ventas por categoría">
          <div class="donut-ring" style="background: <?= htmlspecialchars($buildDonutGradient($ventasPorCategoria, 'unidades', $donutPalette), ENT_QUOTES) ?>;"><div class="donut-center"><strong><?= (int)$reporteVentasCategoriaTotal ?></strong><span>unidades vendidas</span></div></div>
          <div class="donut-legend">
            <?php foreach ($ventasPorCategoria as $i => $categoriaVenta): ?><?php $pct = $reporteVentasCategoriaTotal > 0 ? ((int)$categoriaVenta['unidades'] / $reporteVentasCategoriaTotal) * 100 : 0; $color = $donutPalette[$i % count($donutPalette)]; ?>
              <div class="donut-legend-row"><span class="donut-legend-dot" style="--donut-color: <?= htmlspecialchars($color) ?>;"></span><span class="donut-legend-label"><?= htmlspecialchars($categoriaVenta['categoria']) ?></span><span class="donut-legend-value"><?= (int)$categoriaVenta['unidades'] ?> · <?= number_format($pct, 1, ',', '.') ?>%</span><span class="donut-legend-meta">$<?= number_format((float)$categoriaVenta['importe'], 2, ',', '.') ?> facturados</span></div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="profile-card wide-card report-graphic-card donut-report-card">
      <div class="profile-card-header report-card-heading"><div class="profile-icon">◕</div><div><div class="report-title-line"><h2>Estado del inventario</h2><span class="report-number-badge">Informe 6</span></div><p>Situación actual de los productos activos según su nivel de stock.</p></div></div>
      <?php if ($reporteInventarioError): ?>
        <div class="module-warning"><?= htmlspecialchars($reporteInventarioError) ?></div>
      <?php elseif ($reporteInventarioTotal <= 0): ?>
        <div class="donut-empty">No hay productos activos para analizar.</div>
      <?php else: ?>
        <?php $inventarioColores = ['#22c55e', '#eab308', '#ef4444']; ?>
        <div class="donut-report-body" role="img" aria-label="Gráfico circular del estado del inventario">
          <div class="donut-ring" style="background: <?= htmlspecialchars($buildDonutGradient($estadoInventario, 'cantidad', $inventarioColores), ENT_QUOTES) ?>;"><div class="donut-center"><strong><?= (int)$reporteInventarioTotal ?></strong><span>productos activos</span></div></div>
          <div class="donut-legend">
            <?php foreach ($estadoInventario as $i => $estadoInv): ?><?php $pct = $reporteInventarioTotal > 0 ? ((int)$estadoInv['cantidad'] / $reporteInventarioTotal) * 100 : 0; ?>
              <div class="donut-legend-row"><span class="donut-legend-dot" style="--donut-color: <?= htmlspecialchars($inventarioColores[$i]) ?>;"></span><span class="donut-legend-label"><?= htmlspecialchars($estadoInv['estado']) ?></span><span class="donut-legend-value"><?= (int)$estadoInv['cantidad'] ?> · <?= number_format($pct, 1, ',', '.') ?>%</span></div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="profile-card wide-card report-graphic-card donut-report-card">
      <div class="profile-card-header report-card-heading"><div class="profile-icon">◑</div><div><div class="report-title-line"><h2>Estado de las ventas</h2><span class="report-number-badge">Informe 7</span></div><p>Proporción de ventas activas y canceladas según la fecha de venta<?= ($desde !== '' || $hasta !== '') ? ' del período seleccionado' : ' de todo el historial' ?>.</p></div></div>
      <?php if ($reporteEstadoVentasError): ?>
        <div class="module-warning"><?= htmlspecialchars($reporteEstadoVentasError) ?></div>
      <?php elseif ($reporteEstadoVentasTotal <= 0): ?>
        <div class="donut-empty">Todavía no hay ventas para analizar.</div>
      <?php else: ?>
        <?php $estadoVentasColores = ['#22c55e', '#ef4444']; ?>
        <div class="donut-report-body" role="img" aria-label="Gráfico circular del estado de las ventas">
          <div class="donut-ring" style="background: <?= htmlspecialchars($buildDonutGradient($estadoVentas, 'cantidad', $estadoVentasColores), ENT_QUOTES) ?>;"><div class="donut-center"><strong><?= (int)$reporteEstadoVentasTotal ?></strong><span>ventas registradas</span></div></div>
          <div class="donut-legend">
            <?php foreach ($estadoVentas as $i => $estadoVenta): ?><?php $pct = $reporteEstadoVentasTotal > 0 ? ((int)$estadoVenta['cantidad'] / $reporteEstadoVentasTotal) * 100 : 0; ?>
              <div class="donut-legend-row"><span class="donut-legend-dot" style="--donut-color: <?= htmlspecialchars($estadoVentasColores[$i]) ?>;"></span><span class="donut-legend-label"><?= htmlspecialchars($estadoVenta['estado']) ?></span><span class="donut-legend-value"><?= (int)$estadoVenta['cantidad'] ?> · <?= number_format($pct, 1, ',', '.') ?>%</span></div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
