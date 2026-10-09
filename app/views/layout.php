<?php
if (!function_exists('flash')) {
    require __DIR__ . '/../core/helpers.php';
}

$toastSuccess = flash('success');
$toastError   = flash('error');
$toastInfo    = flash('info');
$toastMsg     = $toastError ?: $toastSuccess ?: $toastInfo;
$toastType    = $toastError ? 'error' : ($toastSuccess ? 'success' : ($toastInfo ? 'info' : ''));

$reportSection = $modActual === 'reportes' ? trim((string) ($_GET['section'] ?? 'resumen')) : '';
if (!in_array($reportSection, ['resumen', 'graficos'], true)) {
    $reportSection = 'resumen';
}

$menuByCode = [];
foreach ($modulos as $menuItem) {
    $menuByCode[$menuItem['id']] = $menuItem;
}

$menuGroups = [
    'Operaciones' => ['productos', 'movimientos', 'ventas', 'proveedores'],
    'Análisis' => ['reportes'],
    'Administración' => ['usuarios', 'perfiles', 'auditoria'],
    'Soporte' => ['ayuda'],
];

$knownCodes = ['panel', 'config_usuario', 'ayuda'];
foreach ($menuGroups as $codes) {
    $knownCodes = array_merge($knownCodes, $codes);
}
$extraCodes = [];
foreach ($menuByCode as $code => $item) {
    if (!in_array($code, $knownCodes, true) && ($item['url'] ?? '#') !== '#') {
        $extraCodes[] = $code;
    }
}
if ($extraCodes) {
    $menuGroups['Otros'] = $extraCodes;
}

$pageTitle = (string) ($moduloActualInfo['titulo'] ?? 'Sistema FME');
if ($modActual === 'reportes') {
    $pageTitle = $reportSection === 'graficos' ? 'Informes gráficos' : 'Reportes';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <meta name="color-scheme" content="light"/>
  <title><?= htmlspecialchars($pageTitle) ?> · Sistema FME</title>
  <link rel="stylesheet" href="assets/css/dashboard.css"/>
</head>
<body>

<script id="fmeSessionContext" type="application/json"><?= json_encode([
  'userId' => $userId,
  'username' => $usuario,
  'role' => $rolNombre,
  'module' => $modActual,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<?php if ($toastMsg): ?>
<div class="toast toast-<?= htmlspecialchars($toastType) ?>" id="toast" role="status">
  <?= htmlspecialchars($toastMsg) ?>
</div>
<?php endif; ?>

<?php if ($needsProfile && $modActual !== 'config_usuario'): ?>
<div class="modal-overlay">
  <div class="modal-box">
    <div class="modal-icon">👤</div>
    <h2 class="modal-title">Información adicional requerida</h2>
    <p class="modal-body">
      Completá tus datos personales para habilitar todas las funciones disponibles para tu perfil.
    </p>
    <div class="modal-actions">
      <a href="index.php?mod=config_usuario" class="modal-btn">Completar información</a>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="app-shell">
  <aside class="sidebar" id="sidebar" aria-label="Navegación principal">
    <div class="sidebar-brand">
      <div class="brand-mark">F</div>
      <div class="brand-copy">
        <strong>Sistema FME</strong>
        <span>Gestión comercial</span>
      </div>
      <button class="sidebar-close" id="sidebarClose" type="button" aria-label="Cerrar menú">×</button>
    </div>

    <nav class="menu">
      <a href="index.php" class="menu-item <?= $modActual === 'panel' ? 'active' : '' ?>">
        <span class="mi-icon">⌂</span>
        <span class="menu-label">Dashboard</span>
      </a>

      <?php foreach ($menuGroups as $groupName => $codes): ?>
        <?php
        $visibleItems = [];
        foreach ($codes as $code) {
            if (isset($menuByCode[$code]) && ($menuByCode[$code]['url'] ?? '#') !== '#') {
                $visibleItems[$code] = $menuByCode[$code];
            }
        }
        if (!$visibleItems) continue;
        ?>
        <div class="menu-group">
          <div class="menu-group-title"><?= htmlspecialchars($groupName) ?></div>
          <?php foreach ($visibleItems as $code => $m): ?>
            <?php if ($code === 'reportes'): ?>
              <a href="index.php?mod=reportes&section=resumen"
                 class="menu-item menu-parent <?= $modActual === 'reportes' ? 'active' : '' ?>">
                <span class="mi-icon"><?= htmlspecialchars($m['icon'] ?: '📊') ?></span>
                <span class="menu-label"><?= htmlspecialchars($m['titulo']) ?></span>
                <span class="menu-caret">›</span>
              </a>
              <?php if ($modActual === 'reportes'): ?>
                <div class="submenu" aria-label="Submódulos de Reportes">
                  <a href="index.php?mod=reportes&section=resumen" class="submenu-item <?= $reportSection === 'resumen' ? 'active' : '' ?>">Resumen</a>
                  <a href="index.php?mod=reportes&section=graficos" class="submenu-item <?= $reportSection === 'graficos' ? 'active' : '' ?>">Informes gráficos</a>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <a href="<?= htmlspecialchars($m['url']) ?>"
                 class="menu-item <?= $modActual === $code ? 'active' : '' ?>">
                <span class="mi-icon"><?= htmlspecialchars($m['icon'] ?: '•') ?></span>
                <span class="menu-label"><?= htmlspecialchars($m['titulo']) ?></span>
              </a>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
      <span class="sidebar-status-dot"></span>
      <div><strong>Sistema activo</strong><span>Sesión protegida por PHP</span></div>
    </div>
  </aside>

  <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

  <main class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="Abrir menú" aria-controls="sidebar" aria-expanded="false">☰</button>
        <div class="topbar-context">
          <span class="topbar-kicker">Sistema FME</span>
          <strong><?= htmlspecialchars($pageTitle) ?></strong>
        </div>
      </div>

      <div class="user">
        <button class="user-btn" id="userBtn" type="button" aria-expanded="false" aria-controls="userMenu">
          <span class="avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($usuario, 0, 1))) ?></span>
          <span class="top-user-summary" aria-label="Usuario actual">
            <span class="top-user-name" data-session-username><?= htmlspecialchars($usuario) ?></span>
            <span class="top-user-role" data-session-role><?= htmlspecialchars($rolNombre) ?></span>
          </span>
          <span class="user-chevron">⌄</span>
        </button>

        <div class="user-menu" id="userMenu" hidden>
          <div class="user-info">
            <div class="user-menu-avatar"><?= htmlspecialchars(strtoupper(substr($usuario, 0, 1))) ?></div>
            <div>
              <div class="user-name" data-session-username><?= htmlspecialchars($usuario) ?></div>
              <div class="user-role" data-session-role><?= htmlspecialchars($rolNombre) ?></div>
            </div>
          </div>
          <div class="session-cache-status" id="sessionCacheStatus">Sincronizando caché local…</div>
          <div class="user-menu-divider"></div>
          <a class="user-action" href="index.php?mod=config_usuario"><span>⚙</span> Configuración de usuario</a>
          <a class="user-action user-action-danger" href="logout.php"><span>↪</span> Cerrar sesión</a>
        </div>
      </div>
    </header>

    <section class="content">
      <?php
      $archivoModulo = $moduloActualInfo['archivo'] ?? 'panel.php';
      $rutaModulo = __DIR__ . '/../../modulos/' . basename((string) $archivoModulo);

      if (is_file($rutaModulo)) {
          include $rutaModulo;
      } else {
          echo '<div class="empty-state"><h1 class="title">Módulo no disponible</h1><p class="subtitle">Este módulo está registrado, pero no tiene una pantalla asociada.</p></div>';
      }
      ?>
    </section>
  </main>
</div>

<div class="confirm-overlay" id="confirmOverlay" hidden>
  <div class="confirm-box" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
    <div class="confirm-icon">!</div>
    <h2 id="confirmTitle">Confirmar acción</h2>
    <p id="confirmMessage">¿Deseas continuar?</p>
    <div class="confirm-actions">
      <button class="btn btn-secondary" type="button" id="confirmCancel">Cancelar</button>
      <button class="btn btn-danger-solid" type="button" id="confirmAccept">Confirmar</button>
    </div>
  </div>
</div>

<script src="assets/js/session-cache.js"></script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
