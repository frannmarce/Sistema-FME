<?php
$DB_HOST = 'localhost';
$DB_NAME = 'fme1';       
$DB_USER = 'root';      
$DB_PASS = 'admin';          
$DB_CHARSET = 'utf8mb4';

$dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

try { $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options); }
catch (Throwable $e) { die('Error de conexión a la base de datos'); }
