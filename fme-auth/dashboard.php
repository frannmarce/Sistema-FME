<?php
session_start();
if(!isset($_SESSION['auth'])){ header('Location: login.php'); exit; }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>FME — Dashboard</title>
  <link rel="stylesheet" href="styles.css"/>
</head>
<body>
  <main class="container">
    <section class="card">
      <h1>¡Hola, <?= htmlspecialchars($_SESSION['auth']) ?>!</h1>
      <p class="sub">El dashboard estará aquí</p>
      <a class="btn" href="logout.php">Cerrar sesión</a>
    </section>
  </main>
</body>
</html>
