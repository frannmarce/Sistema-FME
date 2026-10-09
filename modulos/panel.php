<?php
$hora = (int) date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');

$quickActions = [];
$quickCandidates = [
    'ventas' => ['label' => 'Nueva venta', 'icon' => '🧾', 'url' => 'index.php?mod=ventas&action=add'],
    'productos' => ['label' => 'Ver productos', 'icon' => '📦', 'url' => 'index.php?mod=productos'],
    'movimientos' => ['label' => 'Registrar movimiento', 'icon' => '📑', 'url' => 'index.php?mod=movimientos&action=add'],
    'reportes' => ['label' => 'Ver informes', 'icon' => '📊', 'url' => 'index.php?mod=reportes&section=graficos'],
];
foreach ($quickCandidates as $codigo => $item) {
    if (isset($modulosPermitidosPorCodigo[$codigo])) {
        $quickActions[] = $item;
    }
}

$accionTexto = static function (array $fila): string {
    $accion = strtolower(str_replace('_', ' ', (string) ($fila['accion'] ?? 'acción')));
    $entidad = trim((string) ($fila['entidad'] ?? ''));
    $id = (int) ($fila['id_registro'] ?? 0);
    $objeto = $entidad !== '' ? $entidad . ($id > 0 ? ' #' . $id : '') : (string) ($fila['modulo'] ?? 'sistema');
    return ucfirst($accion) . ' · ' . $objeto;
};
?>

<section class="page-heading dashboard-heading">
  <div>
    <p class="eyebrow">Panel de control</p>
    <h1 class="title"><?= htmlspecialchars($saludo) ?>, <?= htmlspecialchars($usuario) ?></h1>
    <p class="subtitle">Resumen operativo del Sistema FME y accesos rápidos a tus módulos habilitados.</p>
  </div>
</section>

<?php if ($dashboardError): ?>
  <div class="alert error"><?= htmlspecialchars($dashboardError) ?></div>
<?php endif; ?>

<div class="dashboard-kpis" aria-label="Resumen de inventario">
  <article class="dashboard-kpi">
    <div class="dashboard-kpi-icon">📦</div>
    <div><span>Productos activos</span><strong><?= (int) $dashboardKpis['productos'] ?></strong><small>disponibles para operar</small></div>
  </article>
  <article class="dashboard-kpi">
    <div class="dashboard-kpi-icon">▦</div>
    <div><span>Stock disponible</span><strong><?= (int) $dashboardKpis['stock_total'] ?></strong><small>unidades en inventario</small></div>
  </article>
  <article class="dashboard-kpi dashboard-kpi-warning">
    <div class="dashboard-kpi-icon">⚠</div>
    <div><span>Stock bajo</span><strong><?= (int) $dashboardKpis['stock_bajo'] ?></strong><small>productos entre 1 y 5</small></div>
  </article>
  <article class="dashboard-kpi dashboard-kpi-danger">
    <div class="dashboard-kpi-icon">!</div>
    <div><span>Agotados</span><strong><?= (int) $dashboardKpis['agotados'] ?></strong><small>productos sin unidades</small></div>
  </article>
</div>

<div class="dashboard-grid">
  <section class="surface-card dashboard-main-card">
    <div class="surface-card-header">
      <div>
        <p class="eyebrow">Accesos rápidos</p>
        <h2>¿Qué querés hacer?</h2>
        <p>Entrá directamente a las tareas que usás con más frecuencia.</p>
      </div>
    </div>

    <div class="quick-actions">
      <?php foreach ($quickActions as $item): ?>
        <a class="quick-action" href="<?= htmlspecialchars($item['url']) ?>">
          <span class="quick-action-icon"><?= htmlspecialchars($item['icon']) ?></span>
          <span><?= htmlspecialchars($item['label']) ?></span>
          <span class="quick-action-arrow">→</span>
        </a>
      <?php endforeach; ?>
      <?php if (!$quickActions): ?>
        <div class="empty-state">No hay accesos operativos asignados a este perfil.</div>
      <?php endif; ?>
    </div>
  </section>

  <section class="surface-card dashboard-alert-card">
    <div class="surface-card-header compact-header">
      <div>
        <p class="eyebrow">Atención requerida</p>
        <h2>Inventario crítico</h2>
      </div>
      <?php if (isset($modulosPermitidosPorCodigo['productos'])): ?>
        <a class="text-link" href="index.php?mod=productos&stock_max=5">Ver productos</a>
      <?php endif; ?>
    </div>

    <div class="stock-alert-list">
      <?php if (!$dashboardStockAlertas): ?>
        <div class="empty-state success-state">✓ No hay productos con stock bajo.</div>
      <?php endif; ?>
      <?php foreach ($dashboardStockAlertas as $producto): ?>
        <?php $stock = (int) $producto['stock_producto']; ?>
        <div class="stock-alert-item">
          <div>
            <strong><?= htmlspecialchars($producto['nombre_producto']) ?></strong>
            <span><?= $stock === 0 ? 'Sin stock disponible' : 'Reabastecimiento recomendado' ?></span>
          </div>
          <span class="stock-pill <?= $stock === 0 ? 'is-out' : 'is-low' ?>"><?= $stock ?> u.</span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="surface-card dashboard-activity-card">
    <div class="surface-card-header compact-header">
      <div>
        <p class="eyebrow">Actividad reciente</p>
        <h2>Últimos movimientos del sistema</h2>
      </div>
      <?php if (isset($modulosPermitidosPorCodigo['auditoria'])): ?>
        <a class="text-link" href="index.php?mod=auditoria">Abrir auditoría</a>
      <?php endif; ?>
    </div>

    <div class="activity-list">
      <?php if (!$dashboardActividad): ?>
        <div class="empty-state">Todavía no hay actividad reciente para mostrar.</div>
      <?php endif; ?>
      <?php foreach ($dashboardActividad as $actividad): ?>
        <div class="activity-item">
          <span class="activity-dot"></span>
          <div class="activity-content">
            <div><strong><?= htmlspecialchars($actividad['usuario_nombre']) ?></strong> · <?= htmlspecialchars($accionTexto($actividad)) ?></div>
            <small><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $actividad['fecha_auditoria']))) ?> · <?= htmlspecialchars((string) $actividad['modulo']) ?></small>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>
