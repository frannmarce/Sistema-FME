<?php
if(session_status()===PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/config/db.php';
require_once __DIR__ . '/app/core/auditoria.php';

if($_SERVER['REQUEST_METHOD']==='POST'){
  $username = trim($_POST['user_id'] ?? '');
  $pass     = trim($_POST['password'] ?? '');

  if($username==='' || $pass===''){
    flash('error','Por favor completa tu usuario y contraseña.');
    header('Location: login.php'); exit;
  }

  $stmt = $pdo->prepare('SELECT u.id_usuario, u.nombre_usuario, u.contraseña_usuario, u.id_rol, u.email_verificado,
                                r.activo AS rol_activo
                         FROM Usuario u
                         INNER JOIN Tipo_Rol r ON u.id_rol = r.id_rol
                         WHERE u.nombre_usuario = ?
                         LIMIT 1');
  $stmt->execute([$username]);
  $row = $stmt->fetch();

  if(!$row || !password_verify($pass, $row['contraseña_usuario'])){
    flash('error','El usuario no existe o la contraseña es incorrecta.');
    header('Location: login.php'); exit;
  }

  if ((int)$row['rol_activo'] !== 1) {
    flash('error', 'Tu perfil de acceso está inactivo. Contacta a un administrador.');
    header('Location: login.php'); exit;
  }

  if((int)$row['email_verificado'] !== 1){
    flash('error','Debes verificar tu correo electrónico antes de iniciar sesión.');
    header('Location: login.php'); exit;
  }

  session_regenerate_id(true);

  $_SESSION['auth_id']   = (int)$row['id_usuario'];
  $_SESSION['auth_user'] = $row['nombre_usuario'];
  $_SESSION['auth_rol']  = (int)$row['id_rol'];

  unset($_SESSION['needs_profile']);

  registrarAuditoria($pdo, 'auth', 'INICIAR_SESION', 'Usuario', (int)$row['id_usuario']);

  flash('success','¡Inicio de sesión correcto!');
  header('Location: index.php'); exit;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>FME — Iniciar sesión</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
</head>
<body>
<div class="auth-shell">

  <aside class="auth-brand-panel">
    <div class="auth-brand">
      <div class="auth-brand-mark">F</div>
      <div class="auth-brand-copy">
        <strong>Sistema FME</strong>
        <span>Gestión, control y ventas</span>
      </div>
    </div>
    <div class="auth-brand-content">
      <span class="auth-kicker">Acceso al sistema</span>
      <h2>Todo tu negocio, organizado en un solo lugar.</h2>
      <p>Gestiona inventario, ventas, movimientos, reportes y permisos desde una interfaz clara y segura.</p>
      <ul class="auth-features">
        <li class="auth-feature"><span class="auth-feature-icon">✓</span><span>Inventario y stock centralizados</span></li>
        <li class="auth-feature"><span class="auth-feature-icon">✓</span><span>Ventas e informes actualizados</span></li>
        <li class="auth-feature"><span class="auth-feature-icon">✓</span><span>Perfiles, permisos y auditoría</span></li>
      </ul>
    </div>
    <div class="auth-brand-footer">FME · Entorno seguro de gestión</div>
  </aside>
  <main class="auth-main">
    <div class="container">
      <section class="card">
        <div class="logo" aria-hidden="true">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="8" r="3" stroke-width="1.7"/>
            <path d="M4 20c1.5-3.5 4.5-5.5 8-5.5s6.5 2 8 5.5" stroke-width="1.7" stroke-linecap="round"/>
          </svg>
        </div>
        <h1>Bienvenido</h1>
        <p class="sub">Ingresa tus credenciales para acceder al Sistema FME.</p>

        <?php if($e = flash('error')): ?>
          <div class="alert error"><?= htmlspecialchars($e) ?></div>
        <?php endif; ?>
        <?php if($s = flash('success')): ?>
          <div class="alert success"><?= htmlspecialchars($s) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" id="loginForm" novalidate>
          <div class="form-group">
            <label class="label" for="user_id">Nombre de usuario</label>
            <input class="input" id="user_id" name="user_id" type="text" placeholder="Ej.: JRodriguez1" autocomplete="username" required/>
          </div>
          <div class="form-group">
            <label class="label" for="password">Contraseña</label>
            <input class="input" id="password" name="password" type="password" placeholder="Ingresa tu contraseña" autocomplete="current-password" required/>
          </div>
          <div class="actions">
            <a class="link" href="recuperar_password.php">¿Olvidaste tu contraseña?</a>
          </div>
          <button class="btn" type="submit">Iniciar sesión</button>
        </form>
        <p class="footer">¿No tienes cuenta? <a href="register.php">Crear una cuenta</a></p>
      </section>
      <div class="auth-page-footer">Acceso protegido mediante sesión PHP y perfiles de usuario.</div>
    </div>
  </main>
</div>

<script>
  try { localStorage.removeItem('fme_session_cache_v1'); } catch (e) {}

  document.getElementById('loginForm').addEventListener('submit', function(ev){
    const u=document.getElementById('user_id').value.trim();
    const p=document.getElementById('password').value.trim();
    if(!u||!p){
      ev.preventDefault();
      let el=document.querySelector('.alert.error');
      if(!el){el=document.createElement('div');el.className='alert error';
      document.querySelector('.card').insertBefore(el,document.querySelector('form'));}
      el.textContent='Por favor completa tu usuario y contraseña.';
    }
  });
</script>
</body>
</html>
