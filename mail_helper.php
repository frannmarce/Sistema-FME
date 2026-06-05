<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviarCorreo(string $para, string $nombre, string $asunto, string $html, string $textoPlano = ''): bool
{
  $mail = new PHPMailer(true);

  try {
    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USER;
    $mail->Password   = MAIL_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = MAIL_PORT;

    $mail->CharSet = 'UTF-8';

    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress($para, $nombre);

    $mail->isHTML(true);
    $mail->Subject = $asunto;
    $mail->Body    = $html;
    $mail->AltBody = $textoPlano !== '' ? $textoPlano : strip_tags($html);

    return $mail->send();

  } catch (Exception $e) {
    return false;
  }
}