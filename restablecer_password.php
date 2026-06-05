<?php
if(session_status()===PHP_SESSION_NONE) session_start();

require_once __DIR__.'/test.php';
require_once __DIR__.'/db.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';

if($token === ''){
  flash('error', 'Token inválido.');
  header('Location: login.php'); exit;
}

$tokenHash = hash('sha256', $token);

$stmt = $pdo->prepare("
  SELECT id_usuario
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

  if($pass === '' || strlen($pass) < 4){
    $errores[] = 'La contraseña debe tener al menos 4 caracteres.';
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

  $passHash = password_hash($pass, PASSWORD_BCRYPT);

  $stmt = $pdo->prepare("
    UPDATE Usuario
    SET contraseña_usuario = ?,
        token_password = NULL,
        token_password_expira = NULL
    WHERE id_usuario = ?
  ");
  $stmt->execute([$passHash, $user['id_usuario']]);

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

      <h1>Nueva contraseña</h1>
      <p class="sub">Crea una nueva contraseña para tu cuenta</p>

      <?php if($e = flash('error')): ?>
        <div class="alert error"><?= htmlspecialchars($e) ?></div>
      <?php endif; ?>

      <?php if($s = flash('success')): ?>
        <div class="alert success"><?= htmlspecialchars($s) ?></div>
      <?php endif; ?>

      <form method="post" action="restablecer_password.php" id="resetForm" novalidate>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"/>

        <div class="form-group">
          <label class="label" for="password">Nueva contraseña</label>
          <input class="input" id="password" name="password" type="password" placeholder="Mínimo 4 caracteres" required/>
        </div>

        <div class="form-group">
          <label class="label" for="confirm_password">Confirmar contraseña</label>
          <input class="input" id="confirm_password" name="confirm_password" type="password" placeholder="Repite tu contraseña" required/>
        </div>

        <button class="btn" type="submit">Actualizar contraseña</button>

        <p class="footer mt-8">
          <a class="link" href="login.php">Volver al login</a>
        </p>
      </form>
    </section>
  </main>

  <script>
    document.getElementById('resetForm').addEventListener('submit', function(ev){
      const p = document.getElementById('password').value.trim();
      const p2 = document.getElementById('confirm_password').value.trim();

      let mensaje = '';

      if(!p || !p2){
        mensaje = 'Por favor completa todos los campos.';
      }else if(p.length < 4){
        mensaje = 'La contraseña debe tener al menos 4 caracteres.';
      }else if(p !== p2){
        mensaje = 'Las contraseñas no coinciden.';
      }

      if(mensaje !== ''){
        ev.preventDefault();

        let el = document.querySelector('.alert.error');

        if(!el){
          el = document.createElement('div');
          el.className = 'alert error';
          document.querySelector('.card').insertBefore(el, document.querySelector('form'));
        }

        el.textContent = mensaje;
      }
    });
  </script>
</body>
</html>