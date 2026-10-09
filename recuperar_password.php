<?php
if(session_status()===PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/config/db.php';
require_once __DIR__ . '/app/config/mail_config.php';
require_once __DIR__ . '/app/services/mail_helper.php';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
  $correo = trim($_POST['correo'] ?? '');

  if($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)){
    flash('error', 'Ingresa un correo válido.');
    header('Location: recuperar_password.php'); exit;
  }

  $stmt = $pdo->prepare("
    SELECT id_usuario, nombre_usuario, correo_usuario, email_verificado
    FROM Usuario
    WHERE correo_usuario = ?
    LIMIT 1
  ");
  $stmt->execute([$correo]);
  $user = $stmt->fetch();


  if($user && (int)$user['email_verificado'] === 1){
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expira = date('Y-m-d H:i:s', time() + 3600); // 1 hora

    $stmt = $pdo->prepare("
      UPDATE Usuario
      SET token_password = ?,
          token_password_expira = ?
      WHERE id_usuario = ?
    ");
    $stmt->execute([$tokenHash, $expira, $user['id_usuario']]);

    $link = APP_URL . '/restablecer_password.php?token=' . urlencode($token);

    $nombreSeguro = htmlspecialchars($user['nombre_usuario'], ENT_QUOTES, 'UTF-8');
    $linkSeguro = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

    $html = "
      <h2>Recuperación de contraseña - Sistema FME</h2>
      <p>Hola <strong>{$nombreSeguro}</strong>.</p>
      <p>Recibimos una solicitud para restablecer tu contraseña.</p>
      <p>Para crear una nueva contraseña, haz clic en el siguiente enlace:</p>
      <p>
        <a href='{$linkSeguro}'>Restablecer mi contraseña</a>
      </p>
      <p>Este enlace expira en 1 hora.</p>
      <p>Si no solicitaste este cambio, puedes ignorar este correo.</p>
    ";

    $textoPlano = "Para restablecer tu contraseña ingresa a: {$link}";

    $enviado = enviarCorreo(
      $user['correo_usuario'],
      $user['nombre_usuario'],
      'Recuperar contraseña - Sistema FME',
      $html,
      $textoPlano
    );

    if (!$enviado) {
      // Se elimina un token que el usuario nunca pudo recibir.
      $stmt = $pdo->prepare('UPDATE Usuario SET token_password = NULL, token_password_expira = NULL WHERE id_usuario = ?');
      $stmt->execute([$user['id_usuario']]);
    }
  }

  flash('success', 'Si el correo existe y está verificado, recibirás un enlace para restablecer tu contraseña.');
  header('Location: login.php'); exit;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>FME — Recuperar contraseña</title>
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
            <path d="M12 15v2" stroke-width="1.7" stroke-linecap="round"/>
          </svg>
        </div>
        <h1>Recuperar contraseña</h1>
        <p class="sub">Ingresa el correo asociado a tu cuenta y te enviaremos un enlace de recuperación.</p>

        <?php if($e = flash('error')): ?>
          <div class="alert error"><?= htmlspecialchars($e) ?></div>
        <?php endif; ?>
        <?php if($s = flash('success')): ?>
          <div class="alert success"><?= htmlspecialchars($s) ?></div>
        <?php endif; ?>

        <form method="post" action="recuperar_password.php" id="recoverForm" novalidate>
          <div class="form-group">
            <label class="label" for="correo">Correo electrónico</label>
            <input class="input" id="correo" name="correo" type="email" placeholder="tu@email.com" autocomplete="email" required/>
            <small class="field-help">Por seguridad, la respuesta será la misma exista o no una cuenta asociada.</small>
          </div>
          <button class="btn" type="submit">Enviar enlace de recuperación</button>
          <p class="footer"><a href="login.php">Volver a iniciar sesión</a></p>
        </form>
      </section>
      <div class="auth-page-footer">El enlace de recuperación tiene una vigencia limitada.</div>
    </div>
  </main>
</div>

<script>
  document.getElementById('recoverForm').addEventListener('submit', function(ev){
    const c = document.getElementById('correo').value.trim();
    if(!c){
      ev.preventDefault();
      let el = document.querySelector('.alert.error');
      if(!el){
        el = document.createElement('div');
        el.className = 'alert error';
        document.querySelector('.card').insertBefore(el, document.querySelector('form'));
      }
      el.textContent = 'Ingresa tu correo electrónico.';
    }
  });
</script>
</body>
</html>
