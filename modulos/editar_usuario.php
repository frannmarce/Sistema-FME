<?php

require_once __DIR__ . '/../app/config/db.php';

if(!isset($_GET['id'])){
    die("ID no recibido");
}

$id = $_GET['id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM Usuario
    WHERE id_usuario = ?
");

$stmt->execute([$id]);

$usuario = $stmt->fetch();

if(!$usuario){
    die("Usuario no encontrado");
}

$roles = $pdo->query("
    SELECT *
    FROM Tipo_Rol
")->fetchAll();

?>

<h1 class="title">Editar usuario</h1>

<div class="profile-card">

  <form action="modulos/actualizar_usuario.php" method="POST" class="profile-form">

    <input type="hidden"
           name="id_usuario"
           value="<?= $usuario['id_usuario'] ?>">

    <div class="profile-section">

      <div class="form-group">
        <label class="label">Usuario</label>

        <input class="input"
               type="text"
               name="nombre_usuario"
               value="<?= htmlspecialchars($usuario['nombre_usuario']) ?>"
               required>
      </div>

      <div class="form-group">
        <label class="label">Correo</label>

        <input class="input"
               type="email"
               name="correo_usuario"
               value="<?= htmlspecialchars($usuario['correo_usuario']) ?>"
               required>
      </div>

      <div class="form-group">
        <label class="label">Rol</label>

        <select class="input" name="id_rol">

          <?php foreach($roles as $rol): ?>

            <option value="<?= $rol['id_rol'] ?>"
              <?= ($rol['id_rol'] == $usuario['id_rol']) ? 'selected' : '' ?>>

              <?= htmlspecialchars($rol['nombre_rol']) ?>

            </option>

          <?php endforeach; ?>

        </select>

      </div>

    </div>

    <div class="profile-actions">

      <button class="btn" type="submit">
        Actualizar usuario
      </button>

    </div>

  </form>

</div>