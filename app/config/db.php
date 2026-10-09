<?php

require_once __DIR__ . '/env.php';

try {
    // Toda la configuración de conexión proviene exclusivamente del entorno.
    // No hay valores fallback dentro del código PHP.
    $DB_HOST = envRequired('DB_HOST');
    $DB_PORT = envRequired('DB_PORT');
    $DB_NAME = envRequired('DB_NAME');
    $DB_USER = envRequired('DB_USER');
    $DB_PASS = envRequired('DB_PASS', true);
    $DB_CHARSET = envRequired('DB_CHARSET');
    $DB_TIMEZONE = envRequired('DB_TIMEZONE');

    $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset={$DB_CHARSET}";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ];

    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
    $pdo->exec('SET time_zone = ' . $pdo->quote($DB_TIMEZONE));
} catch (Throwable $e) {
    error_log('FME - Configuración/Conexión BD: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo iniciar la conexión con la base de datos. Revise la configuración del entorno.');
}
