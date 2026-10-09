<?php

require_once __DIR__ . '/env.php';

try {
    // Las credenciales y parámetros SMTP provienen exclusivamente del entorno.
    $mailHost = envRequired('MAIL_HOST');
    $mailPort = envRequired('MAIL_PORT');
    $mailUser = envRequired('MAIL_USER');
    $mailPass = envRequired('MAIL_PASS');
    $mailFrom = envRequired('MAIL_FROM');
    $mailFromName = envRequired('MAIL_FROM_NAME');

    // APP_URL puede quedar vacío en .env para detección automática dentro del
    // servidor web. La variable debe existir, pero no se hardcodea una URL.
    $appUrl = envRequired('APP_URL', true);

    if ($appUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? null;
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? null;

        if ($host === null || $host === '' || $scriptName === null || $scriptName === '') {
            throw new RuntimeException('No fue posible detectar APP_URL automáticamente. Defínala en .env.');
        }

        $scriptName = str_replace('\\', '/', $scriptName);
        $basePath = rtrim(dirname($scriptName), '/.');
        $appUrl = $scheme . '://' . $host . ($basePath !== '' ? $basePath : '');
    }

    define('APP_URL', rtrim($appUrl, '/'));
    define('MAIL_HOST', $mailHost);
    define('MAIL_PORT', (int) $mailPort);
    define('MAIL_USER', $mailUser);
    define('MAIL_PASS', $mailPass);
    define('MAIL_FROM', $mailFrom);
    define('MAIL_FROM_NAME', $mailFromName);
} catch (Throwable $e) {
    error_log('FME - Configuración de correo: ' . $e->getMessage());
    http_response_code(500);
    exit('La configuración de correo del sistema está incompleta. Revise las variables de entorno.');
}
