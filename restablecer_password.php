<?php
if(session_status()===PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/config/db.php';
require_once __DIR__ . '/app/core/auditoria.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';

if($token === ''){
  flash('error', 'Token inválido.');
  header('Location: login.php'); exit;
}

$tokenHash = hash('sha256', $token);

$stmt = $pdo->prepare("
  SELECT id_usuario, nombre_usuario
  FROM Usuario
  WHERE token_password = ?
    AND token_password_expira >= NOW()
  LIMIT 1
");
$stmt->execute([$tokenHash]);
$user = $stmt->fetch();

if(!$user){
  flash('error', 'El enlace para restablecer la contraseña es inválido o expiró.');
  header('Location: login.php'); exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
  $pass  = trim($_POST['password'] ?? '');
  $pass2 = trim($_POST['confirm_password'] ?? '');

  $errores = [];

  if($pass === ''){
    $errores[] = 'Debes ingresar una contraseña.';
  }else{
    if(strlen($pass) < 8) $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    if(!preg_match('/[A-Z]/', $pass)) $errores[] = 'La contraseña debe contener al menos una letra mayúscula.';
    if(!preg_match('/[^a-zA-Z0-9]/', $pass)) $errores[] = 'La contraseña debe contener al menos un carácter especial.';
  }

  if($pass2 === ''){
    $errores[] = 'Debes confirmar la contraseña.';
  }

  if($pass !== $pass2){
    $errores[] = 'Las contraseñas no coinciden.';
  }

  if($errores){
    flash('error', implode(' ', $errores));
    header('Location: restablecer_password.php?token=' . urlencode($token)); exit;
  }

  $passHash = password_hash($pass, PASSWORD_DEFAULT);

  $stmt = $pdo->prepare("
    UPDATE Usuario
    SET contraseña_usuario = ?,
        token_password = NULL,
        token_password_expira = NULL
    WHERE id_usuario = ?
  ");
  $stmt->execute([$passHash, $user['id_usuario']]);

  registrarAuditoria(
    $pdo,
    'auth',
    'RESTABLECER_PASSWORD',
    'Usuario',
    (int)$user['id_usuario'],
    null,
    (int)$user['id_usuario'],
    (string)$user['nombre_usuario']
  );

  flash('success', 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.');
  header('Location: login.php'); exit;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>FME — Restablecer contraseña</title>
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
            <path d="M7 11V8a5 5 0 0 1 10 0v3" stroke-width="1.7" stroke-linecap="round"/>
            <rect x="5" y="11" width="14" height="10" rx="2.5" stroke-width="1.7"/>
            <path d="M9.5 16h5" stroke-width="1.7" stroke-linecap="round"/>
          </svg>
        </div>
        <h1>Nueva contraseña</h1>
        <p class="sub">Define una contraseña nueva para recuperar el acceso a tu cuenta.</p>

        <?php if($e = flash('error')): ?>
          <div class="alert error"><?= htmlspecialchars($e) ?></div>
        <?php endif; ?>
        <?php if($s = flash('success')): ?>
          <div class="alert success"><?= htmlspecialchars($s) ?></div>
        <?php endif; ?>

        <div class="auth-security-note">
          <span class="auth-security-icon">i</span>
          <div><strong>Requisitos de seguridad</strong>Mínimo 8 caracteres, una letra mayúscula y un carácter especial.</div>
        </div>

        <form method="post" action="restablecer_password.php" id="resetForm" novalidate>
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"/>
          <div class="form-group">
            <label class="label" for="password">Nueva contraseña</label>
            <input class="input" id="password" name="password" type="password" placeholder="Ingresa la nueva contraseña" autocomplete="new-password" required/>
          </div>
          <div class="form-group">
            <label class="label" for="confirm_password">Confirmar contraseña</label>
            <input class="input" id="confirm_password" name="confirm_password" type="password" placeholder="Repite la nueva contraseña" autocomplete="new-password" required/>
          </div>
          <button class="btn" type="submit">Actualizar contraseña</button>
          <p class="footer"><a href="login.php">Volver a iniciar sesión</a></p>
        </form>
      </section>
      <div class="auth-page-footer">El token se invalida después de actualizar correctamente la contraseña.</div>
    </div>
  </main>
</div>

<script>
  document.getElementById('resetForm').addEventListener('submit', function(ev){
    const p = document.getElementById('password').value.trim();
    const p2 = document.getElementById('confirm_password').value.trim();
    let mensaje = '';
    if(!p || !p2){
      mensaje = 'Por favor completa todos los campos.';
    }else if(p.length < 8){
      mensaje = 'La contraseña debe tener al menos 8 caracteres.';
    }else if(!/[A-Z]/.test(p)){
      mensaje = 'La contraseña debe contener al menos una letra mayúscula.';
    }else if(!/[^a-zA-Z0-9]/.test(p)){
      mensaje = 'La contraseña debe contener al menos un carácter especial.';
    }else if(p !== p2){
      mensaje = 'Las contraseñas no coinciden.';
    }
    if(mensaje !== ''){
      ev.preventDefault();
      let el = document.querySelector('.alert.error');
      if(!el){
        el = document.createElement('div');
        el.className = 'alert error';
        document.querySelector('.card').insertBefore(el, document.querySelector('.auth-security-note'));
      }
      el.textContent = mensaje;
    }
  });
</script>
</body>
</html>
