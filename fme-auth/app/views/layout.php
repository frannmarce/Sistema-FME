<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Sistema FME — Panel</title>
  <link rel="stylesheet" href="dashboard.css"/>
</head>
<body>

<?php
// Aseguramos que flash() exista
if (!function_exists('flash')) {
    require __DIR__ . '/../../test.php';
}

// Tomamos mensajes de sesión
$toastSuccess = flash('success');
$toastError   = flash('error');
$toastInfo    = flash('info');

// Elegimos cuál mostrar (error > success > info)
$toastMsg  = $toastError ?: $toastSuccess ?: $toastInfo;
$toastType = $toastError ? 'error' : ($toastSuccess ? 'success' : ($toastInfo ? 'info' : ''));
?>

<?php if ($toastMsg): ?>
<div class="toast toast-<?= htmlspecialchars($toastType) ?>" id="toast">
  <?= htmlspecialchars($toastMsg) ?>
</div>
<?php endif; ?>


<?php if ($needsProfile && $modActual !== 'config_usuario'): ?>
<div class="modal-overlay">
  <div class="modal-box">
    <div class="modal-icon">💼</div>
    <h2 class="modal-title">Información adicional requerida</h2>
    <p class="modal-body">
      Necesitamos que completes tus datos personales para habilitar 
      todas las funciones del Sistema FME.<br><br>
      Presiona <b>Continuar</b> para completar la información.
    </p>
    <div class="modal-actions">
      <a href="index.php?mod=config_usuario" class="modal-btn">Continuar</a>
    </div>
  </div>
</div>
<?php endif; ?>


<div class="layout">
  <aside class="sidebar">
    <div class="brand">Sistema FME</div>
    <nav class="menu">
      <?php foreach($modulos as $m): ?>
        <a href="<?= htmlspecialchars($m['url']) ?>" class="menu-item">
          <span class="mi-icon"><?= $m['icon'] ?></span>
          <span><?= htmlspecialchars($m['titulo']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  </aside>

  <main class="main">
    <header class="topbar">
      <div></div>
      <div class="user">
        <button class="user-btn" id="userBtn">
          <span class="avatar">👤</span>
        </button>
        <div class="user-menu" id="userMenu" hidden>
          <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($usuario) ?></div>
            <div class="user-role"><?= $rolId === 1 ? 'Administrador' : 'Operador' ?></div>
          </div>
          <a class="user-action" href="logout.php">Cerrar sesión</a>
        </div>
      </div>
    </header>

    <section class="content">
      <div class="tabs">
        <button class="tab-btn <?= $modActual === 'panel' ? 'active' : '' ?>" data-href="index.php">Panel de control</button>
        <button class="tab-btn <?= $modActual === 'config_usuario' ? 'active' : '' ?>" data-href="index.php?mod=config_usuario">Configuración de usuario</button>

        <?php if ($rolId === 1): ?>
        <button class="tab-btn <?= $modActual === 'usuarios' ? 'active' : '' ?>" data-href="index.php?mod=usuarios">Gestión de usuarios</button>
        <button class="tab-btn <?= $modActual === 'ventas' ? 'active' : '' ?>" data-href="index.php?mod=ventas">Ventas</button>
        <?php endif; ?>

        <button class="tab-btn <?= $modActual === 'movimientos' ? 'active' : '' ?>" data-href="index.php?mod=movimientos">Movimientos</button>
        <button class="tab-btn <?= $modActual === 'productos' ? 'active' : '' ?>" data-href="index.php?mod=productos">Productos</button>
      </div>

      <?php
      // Carga de módulos
      if ($modActual === 'config_usuario') {
        include __DIR__ . '/../../modulos/config_usuario.php';
      } elseif ($modActual === 'usuarios' && $rolId === 1) {
        include __DIR__ . '/../../modulos/usuarios.php';
      } elseif ($modActual === 'ventas' && $rolId === 1) {
        include __DIR__ . '/../../modulos/ventas.php';
      } elseif ($modActual === 'movimientos') {
        include __DIR__ . '/../../modulos/movimientos.php';
      } elseif ($modActual === 'productos') {
        include __DIR__ . '/../../modulos/productos.php';
      } else {
        include __DIR__ . '/../../modulos/panel.php';
      }
      ?>

    </section>
  </main>
</div>

<script src="dashboard.js"></script>

<script>
window.addEventListener('load', () => {
  const t = document.getElementById('toast');
  if (!t) return;

  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2600);
  setTimeout(() => t.remove(), 3200);
});

// Tabs
document.querySelectorAll('.tab-btn[data-href]').forEach(btn => {
  btn.addEventListener('click', () => window.location.href = btn.dataset.href);
});

// Menú usuario
const userBtn = document.getElementById('userBtn');
const userMenu = document.getElementById('userMenu');

if (userBtn && userMenu) {
  userBtn.addEventListener('click', () => {
    const hidden = userMenu.hasAttribute('hidden');
    if (hidden) userMenu.removeAttribute('hidden');
    else userMenu.setAttribute('hidden', 'hidden');
  });

  document.addEventListener('click', (e) => {
    if (!userMenu.contains(e.target) && !userBtn.contains(e.target)) {
      userMenu.setAttribute('hidden', 'hidden');
    }
  });
}

// Botón Abrir módulos
document.querySelectorAll('.card-btn[data-href]').forEach(btn => {
  btn.addEventListener('click', () => window.location.href = btn.dataset.href);
});
</script>

</body>
</html>
