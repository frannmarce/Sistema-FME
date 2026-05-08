<?php
if(session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/test.php';
require_once __DIR__.'/db.php';


if($_SERVER['REQUEST_METHOD']==='POST'){
  $userName = trim($_POST['user_id'] ?? '');
  $correo   = trim($_POST['correo'] ?? '');
  $pass     = trim($_POST['password'] ?? '');

  $errores = [];
  if($userName==='') $errores[]='Falta el nombre de usuario.';
  if($correo==='' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[]='Correo inválido.';
  if($pass==='' || strlen($pass)<4) $errores[]='La contraseña debe tener al menos 4 caracteres.';

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

  $passHash = password_hash($pass, PASSWORD_BCRYPT);
  $stmt = $pdo->prepare('INSERT INTO Usuario (nombre_usuario, correo_usuario, contraseña_usuario, id_persona, id_rol)
                         VALUES (?, ?, ?, NULL, 2)');
  $stmt->execute([$userName, $correo, $passHash]);

  flash('success','Cuenta creada correctamente. Ahora puedes iniciar sesión.');
  header('Location: login.php'); exit;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>FME — Registro</title>
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
      <h1>Crear cuenta</h1>
      <p class="sub">Completa los datos para registrarte</p>

      <?php if($e = flash('error')): ?>
        <div class="alert error"><?= htmlspecialchars($e) ?></div>
      <?php endif; ?>
      <?php if($s = flash('success')): ?>
        <div class="alert success"><?= htmlspecialchars($s) ?></div>
      <?php endif; ?>

      <form method="post" action="register.php" id="regForm" novalidate>
        <div class="form-group">
          <label class="label" for="user_id">Nombre de usuario</label>
          <input class="input" id="user_id" name="user_id" type="text" placeholder="Ej.: JRodriguez1" required/>
        </div>
        <div class="form-group">
          <label class="label" for="correo">Correo electrónico</label>
          <input class="input" id="correo" name="correo" type="email" placeholder="tu@email.com" required/>
        </div>
        <div class="form-group">
          <label class="label" for="password">Contraseña</label>
          <input class="input" id="password" name="password" type="password" placeholder="Mínimo 4 caracteres" required/>
        </div>
        <button class="btn" type="submit">Registrarme</button>
        <p class="footer mt-8">¿Ya tienes cuenta? <a class="link" href="login.php">Inicia sesión</a></p>
      </form>
    </section>
  </main>

  <script>
    document.getElementById('regForm').addEventListener('submit', function(ev){
      const u=document.getElementById('user_id').value.trim();
      const c=document.getElementById('correo').value.trim();
      const p=document.getElementById('password').value.trim();
      if(!u||!c||!p){
        ev.preventDefault();
        let el=document.querySelector('.alert.error');
        if(!el){el=document.createElement('div');el.className='alert error';
        document.querySelector('.card').insertBefore(el,document.querySelector('form'));}
        el.textContent='Por favor completa todos los campos.';
      }
    });
  </script>
</body>
</html>

