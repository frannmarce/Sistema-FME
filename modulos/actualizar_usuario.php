<?php

require_once __DIR__ . '/../app/config/db.php';

if($_SERVER['REQUEST_METHOD'] != 'POST'){
    die("Acceso inválido");
}

$id      = trim($_POST['id_usuario']);
$nombre  = trim($_POST['nombre_usuario']);
$correo  = trim($_POST['correo_usuario']);
$id_rol  = trim($_POST['id_rol']);

if(empty($nombre) || empty($correo)){

    die("Todos los campos son obligatorios");

}

$stmt = $pdo->prepare("
    SELECT *
    FROM Usuario
    WHERE correo_usuario = ?
    AND id_usuario != ?
");

$stmt->execute([$correo, $id]);

$usuarioExistente = $stmt->fetch();

if($usuarioExistente){

    die("El correo ya pertenece a otro usuario");

}

$stmt = $pdo->prepare("
    UPDATE Usuario
    SET nombre_usuario = ?,
        correo_usuario = ?,
        id_rol = ?
    WHERE id_usuario = ?
");

$resultado = $stmt->execute([
    $nombre,
    $correo,
    $id_rol,
    $id
]);

if($resultado){

    header("Location: ../index.php?mod=usuarios");
    exit;

}else{

    echo "Error al actualizar";

}
?>