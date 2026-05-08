<?php
if(session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/test.php';
require_once __DIR__.'/db.php';


if($_SERVER['REQUEST_METHOD']==='POST'){
  $username = trim($_POST['user_id'] ?? '');
  $pass     = trim($_POST['password'] ?? '');

  if($username==='' || $pass===''){
    flash('error','Por favor completa tu usuario y contraseña.');
    header('Location: login.php'); exit;
  }

  $stmt = $pdo->prepare('SELECT id_usuario, nombre_usuario, contraseña_usuario, id_rol
                         FROM Usuario
                         WHERE nombre_usuario = ? LIMIT 1');
  $stmt->execute([$username]);
  $row = $stmt->fetch();

  if(!$row || !password_verify($pass, $row['contraseña_usuario'])){
    flash('error','El usuario no existe o la contraseña es incorrecta.');
    header('Location: login.php'); exit;
  }

  $_SESSION['auth_id']   = (int)$row['id_usuario'];
  $_SESSION['auth_user'] = $row['nombre_usuario'];
  $_SESSION['auth_rol']  = (int)$row['id_rol'];

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
  <link rel="stylesheet" href="styles.css"/>
</head>
<body>
  <main class="container">
    <section class="card">
      <div class="logo">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="8" r="3" stroke="white" stroke-width="1.5"/>
          <path d="M4 20c1.5-3.5 4.5-5.5 8-5.5s6.5 2 8 5.5" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
      </div>
      <h1>Bienvenido</h1>
      <p class="sub">Inicia sesión en tu cuenta</p>

      <?php if($e = flash('error')): ?>
        <div class="alert error"><?= htmlspecialchars($e) ?></div>
      <?php endif; ?>
      <?php if($s = flash('success')): ?>
        <div class="alert success"><?= htmlspecialchars($s) ?></div>
      <?php endif; ?>

      <form method="post" action="login.php" id="loginForm" novalidate>
        <div class="form-group">
          <label class="label" for="user_id">Nombre de usuario</label>
          <input class="input" id="user_id" name="user_id" type="text" placeholder="Ej.: JRodriguez1" required/>
        </div>
        <div class="form-group">
          <label class="label" for="password">Contraseña</label>
          <input class="input" id="password" name="password" type="password" placeholder="••••••••" required minlength="4"/>
        </div>
        <div class="actions">
          <label class="checkbox"><input type="checkbox"/> Recordarme</label>
          <a class="link small" href="#" onclick="alert('Función en construcción'); return false;">¿Olvidaste tu contraseña?</a>
        </div>
        <button class="btn" type="submit">Iniciar Sesión</button>
      </form>
      <p class="footer mt-8">¿No tienes cuenta? <a class="link" href="register.php">Regístrate aquí</a></p>
    </section>
  </main>

  <script>
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
