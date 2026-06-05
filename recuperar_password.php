<?php
if(session_status()===PHP_SESSION_NONE) session_start();

require_once __DIR__.'/test.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/mail_config.php';
require_once __DIR__.'/mail_helper.php';

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

    enviarCorreo(
      $user['correo_usuario'],
      $user['nombre_usuario'],
      'Recuperar contraseña - Sistema FME',
      $html,
      $textoPlano
    );
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
  <link rel="stylesheet" href="styles.css"/>
</head>
<body>
  <main class="container">
    <section class="card">
      <div class="logo">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M7 11a5 5 0 1 1 10 0v1h1a2 2 0 1 1 0 4H6a2 2 0 1 1 0-4h1v-1Z" stroke="white" stroke-width="1.5"/>
        </svg>
      </div>

      <h1>Recuperar contraseña</h1>
      <p class="sub">Ingresa el correo asociado a tu cuenta</p>

      <?php if($e = flash('error')): ?>
        <div class="alert error"><?= htmlspecialchars($e) ?></div>
      <?php endif; ?>

      <?php if($s = flash('success')): ?>
        <div class="alert success"><?= htmlspecialchars($s) ?></div>
      <?php endif; ?>

      <form method="post" action="recuperar_password.php" id="recoverForm" novalidate>
        <div class="form-group">
          <label class="label" for="correo">Correo electrónico</label>
          <input class="input" id="correo" name="correo" type="email" placeholder="tu@email.com" required/>
        </div>

        <button class="btn" type="submit">Enviar enlace</button>

        <p class="footer mt-8">
          <a class="link" href="login.php">Volver al login</a>
        </p>
      </form>
    </section>
  </main>

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