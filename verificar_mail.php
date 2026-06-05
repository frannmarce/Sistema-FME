<?php
if(session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/test.php';
require_once __DIR__ . '/db.php';

$token = $_GET['token'] ?? '';

if($token === ''){
  flash('error', 'Token de verificación inválido.');
  header('Location: login.php');
  exit;
}

$tokenHash = hash('sha256', $token);

$stmt = $pdo->prepare("
  SELECT id_usuario
  FROM Usuario
  WHERE token_email = ?
    AND token_email_expira >= NOW()
  LIMIT 1
");

$stmt->execute([$tokenHash]);
$user = $stmt->fetch();

if(!$user){
  flash('error', 'El enlace de verificación es inválido o expiró.');
  header('Location: login.php');
  exit;
}

$stmt = $pdo->prepare("
  UPDATE Usuario
  SET email_verificado = 1,
      token_email = NULL,
      token_email_expira = NULL
  WHERE id_usuario = ?
");

$stmt->execute([$user['id_usuario']]);

flash('success', 'Correo verificado correctamente. Ahora puedes iniciar sesión.');
header('Location: login.php');
exit;