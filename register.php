<?php
if(session_status()===PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/config/db.php';
require_once __DIR__ . '/app/core/auditoria.php';
require_once __DIR__ . '/app/config/mail_config.php';
require_once __DIR__ . '/app/services/mail_helper.php';

if($_SERVER['REQUEST_METHOD']==='POST'){
  $userName = trim($_POST['user_id'] ?? '');
  $correo   = trim($_POST['correo'] ?? '');
  $pass     = trim($_POST['password'] ?? '');
  $pass2    = trim($_POST['confirm_password'] ?? '');

  $errores = [];

  if($userName==='') $errores[]='Falta el nombre de usuario.';
  if(mb_strlen($userName) > 50) $errores[]='El nombre de usuario no puede superar los 50 caracteres.';
  if($correo==='' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[]='Correo inválido.';
  if(mb_strlen($correo) > 80) $errores[]='El correo no puede superar los 80 caracteres.';
  if($pass === ''){
    $errores[] = 'Debes ingresar una contraseña.';
  }else{
    $passwordValida = strlen($pass) >= 8
      && preg_match('/[A-Z]/', $pass)
      && preg_match('/[^a-zA-Z0-9]/', $pass);

    if(!$passwordValida){
      $errores[] = 'La contraseña no cumple los requisitos. Debe tener al menos 8 caracteres, una mayúscula y un carácter especial.';
    }
  }
  if($pass2==='') {
    $errores[]='Debes confirmar la contraseña.';
  } elseif($pass !== $pass2) {
    $errores[]='Las contraseñas no coinciden.';
  }

  if($errores){
    flash('error', implode(' ', $errores));
    header('Location: register.php'); exit;
  }

  $stmt = $pdo->prepare('SELECT 1 FROM Usuario WHERE nombre_usuario = ? OR correo_usuario = ? LIMIT 1');
  $stmt->execute([$userName, $correo]);

  if($stmt->fetch()){
    flash('error','El usuario o correo ya existen.');
    header('Location: register.php'); exit;
  }

  $passHash = password_hash($pass, PASSWORD_DEFAULT);

  $tokenEmail = bin2hex(random_bytes(32));
  $tokenEmailHash = hash('sha256', $tokenEmail);
  $tokenEmailExpira = date('Y-m-d H:i:s', time() + 86400); // 24 horas

  $stmt = $pdo->prepare('SELECT id_rol FROM Tipo_Rol WHERE id_rol = 2 AND activo = 1 LIMIT 1');
  $stmt->execute();
  $idRolRegistro = (int)($stmt->fetchColumn() ?: 0);

  if($idRolRegistro <= 0){
    flash('error', 'No existe un perfil de usuario activo para completar el registro.');
    header('Location: register.php'); exit;
  }

  try {
    $stmt = $pdo->prepare('INSERT INTO Usuario 
      (nombre_usuario, correo_usuario, contraseña_usuario, id_persona, id_rol, email_verificado, token_email, token_email_expira)
      VALUES (?, ?, ?, NULL, ?, 0, ?, ?)');

    $stmt->execute([
      $userName,
      $correo,
      $passHash,
      $idRolRegistro,
      $tokenEmailHash,
      $tokenEmailExpira
    ]);
    $idNuevoUsuario = (int)$pdo->lastInsertId();
  } catch (Throwable $e) {
    error_log('FME - Registro: ' . $e->getMessage());
    flash('error', 'No se pudo crear la cuenta. Verifica los datos e inténtalo nuevamente.');
    header('Location: register.php'); exit;
  }

  $linkVerificacion = APP_URL . '/verificar_email.php?token=' . urlencode($tokenEmail);

  $nombreSeguro = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
  $linkSeguro = htmlspecialchars($linkVerificacion, ENT_QUOTES, 'UTF-8');

  $html = "
    <h2>Verificación de correo - Sistema FME</h2>
    <p>Hola <strong>{$nombreSeguro}</strong>, gracias por registrarte.</p>
    <p>Para activar tu cuenta, haz clic en el siguiente enlace:</p>
    <p>
      <a href='{$linkSeguro}'>Verificar mi correo</a>
    </p>
    <p>Este enlace expira en 24 horas.</p>
    <p>Si no solicitaste esta cuenta, puedes ignorar este mensaje.</p>
  ";

  $textoPlano = "Hola {$userName}. Para activar tu cuenta ingresa a: {$linkVerificacion}";

  $enviado = enviarCorreo(
    $correo,
    $userName,
    'Verifica tu correo - Sistema FME',
    $html,
    $textoPlano
  );

  if(!$enviado){
    // Si no fue posible enviar el enlace, se revierte el alta para que el usuario pueda intentar registrarse nuevamente.
    if (!empty($idNuevoUsuario)) {
      $stmt = $pdo->prepare('DELETE FROM Usuario WHERE id_usuario = ? AND email_verificado = 0 AND id_persona IS NULL');
      $stmt->execute([$idNuevoUsuario]);
    }
    flash('error', 'No se pudo enviar el correo de verificación. La cuenta no fue creada; inténtalo nuevamente.');
    header('Location: register.php'); exit;
  }

  registrarAuditoria(
    $pdo,
    'auth',
    'CREAR_CUENTA',
    'Usuario',
    $idNuevoUsuario,
    ['estado' => 'Pendiente de verificación de correo'],
    $idNuevoUsuario,
    $userName
  );

  flash('success','Cuenta creada correctamente. Revisa tu correo para verificar tu cuenta.');
  header('Location: login.php'); exit;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>FME — Registro</title>
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
    <div class="container register-container">
      <section class="card">
        <div class="logo" aria-hidden="true">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="10" cy="8" r="3" stroke-width="1.7"/>
            <path d="M3.5 20c1.2-3.2 3.7-5 6.5-5 1.7 0 3.3.6 4.5 1.7" stroke-width="1.7" stroke-linecap="round"/>
            <path d="M18 12v6M15 15h6" stroke-width="1.7" stroke-linecap="round"/>
          </svg>
        </div>
        <h1>Crear cuenta</h1>
        <p class="sub">Registra tus datos de acceso. Luego recibirás un correo para verificar la cuenta.</p>

        <?php if($e = flash('error')): ?>
          <div class="alert error"><?= htmlspecialchars($e) ?></div>
        <?php endif; ?>
        <?php if($s = flash('success')): ?>
          <div class="alert success"><?= htmlspecialchars($s) ?></div>
        <?php endif; ?>

        <div class="auth-security-note">
          <span class="auth-security-icon">i</span>
          <div><strong>Registro seguro</strong>La contraseña debe tener al menos 8 caracteres, una mayúscula y un carácter especial.</div>
        </div>

        <form method="post" action="register.php" id="regForm" novalidate>
          <div class="form-grid two">
            <div class="form-group">
              <label class="label" for="user_id">Nombre de usuario</label>
              <input class="input" id="user_id" name="user_id" type="text" placeholder="Ej.: JRodriguez1" autocomplete="username" required/>
            </div>
            <div class="form-group">
              <label class="label" for="correo">Correo electrónico</label>
              <input class="input" id="correo" name="correo" type="email" placeholder="tu@email.com" autocomplete="email" required/>
            </div>
            <div class="form-group">
              <label class="label" for="password">Contraseña</label>
              <input class="input" id="password" name="password" type="password" placeholder="Mínimo 8 caracteres" autocomplete="new-password" required/>
            </div>
            <div class="form-group">
              <label class="label" for="confirm_password">Confirmar contraseña</label>
              <input class="input" id="confirm_password" name="confirm_password" type="password" placeholder="Repite tu contraseña" autocomplete="new-password" required/>
            </div>
          </div>

          <button class="btn" type="submit">Crear cuenta</button>
          <p class="footer">¿Ya tienes cuenta? <a href="login.php">Iniciar sesión</a></p>
        </form>
      </section>
      <div class="auth-page-footer">Tu cuenta debe verificarse por correo antes del primer ingreso.</div>
    </div>
  </main>
</div>

<script>
  document.getElementById('regForm').addEventListener('submit', function(ev){
    const u = document.getElementById('user_id').value.trim();
    const c = document.getElementById('correo').value.trim();
    const p = document.getElementById('password').value.trim();
    const p2 = document.getElementById('confirm_password').value.trim();

    let mensaje = '';
    if(!u || !c || !p || !p2){
      mensaje = 'Por favor completa todos los campos.';
    }else{
      const passwordValida = p.length >= 8
        && /[A-Z]/.test(p)
        && /[^a-zA-Z0-9]/.test(p);

      if(!passwordValida){
        mensaje = 'La contraseña no cumple los requisitos. Debe tener al menos 8 caracteres, una mayúscula y un carácter especial.';
      }else if(p !== p2){
        mensaje = 'Las contraseñas no coinciden.';
      }
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
