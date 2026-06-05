<?php
require_once __DIR__ . '/mail_helper.php';

$para = 'frannmarce420@gmail.com';

$enviado = enviarCorreo(
  $para,
  'Prueba FME',
  'Prueba de correo - Sistema FME',
  '<h2>Correo de prueba</h2><p>Si recibiste este mensaje, PHPMailer funciona correctamente.</p>',
  'Si recibiste este mensaje, PHPMailer funciona correctamente.'
);

if($enviado){
  echo 'Correo enviado correctamente.';
}else{
  echo 'No se pudo enviar el correo.';
}