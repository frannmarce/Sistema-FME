<?php
$stmt = $pdo->prepare(
    'SELECT p.nombre_persona, p.apellido_persona, p.CUIL_persona, p.telefono_persona,
            d.nombre_pais, d.nombre_ciudad, d.num_direccion
     FROM Usuario u
     LEFT JOIN Persona p ON u.id_persona = p.id_persona
     LEFT JOIN Direccion d ON p.id_direccion = d.id_direccion
     WHERE u.id_usuario = ?
     LIMIT 1'
);
$stmt->execute([$userId]);
$configUsuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
?>

<h1 class="title">Configuración de usuario</h1>
<p class="subtitle">Completa o actualiza tus datos personales y de dirección.</p>

<div class="profile-card">
  <div class="profile-card-header">
    <div class="profile-icon">👤</div>
    <div>
      <h2>Datos del usuario</h2>
    </div>
  </div>

  <?php if ($e = flash('error')): ?>
    <div class="alert error" style="margin-bottom: 14px;"><?= htmlspecialchars($e) ?></div>
  <?php endif; ?>

  <form method="post" action="index.php?mod=config_usuario" class="profile-form">
    <div class="profile-section">
      <h3>Datos personales</h3>
      <div class="profile-grid">
        <div class="form-group">
          <label class="label" for="nombre_persona">Nombre</label>
          <input class="input" id="nombre_persona" name="nombre_persona" maxlength="50"
                 value="<?= htmlspecialchars($configUsuario['nombre_persona'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="label" for="apellido_persona">Apellido</label>
          <input class="input" id="apellido_persona" name="apellido_persona" maxlength="50"
                 value="<?= htmlspecialchars($configUsuario['apellido_persona'] ?? '') ?>" required>
        </div>
      </div>

      <div class="profile-grid">
        <div class="form-group">
          <label class="label" for="CUIL_persona">CUIL / DNI</label>
          <input class="input" id="CUIL_persona" name="CUIL_persona" maxlength="20"
                 value="<?= htmlspecialchars($configUsuario['CUIL_persona'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="label" for="telefono_persona">Teléfono</label>
          <input class="input" id="telefono_persona" name="telefono_persona" maxlength="20"
                 value="<?= htmlspecialchars($configUsuario['telefono_persona'] ?? '') ?>">
        </div>
      </div>
    </div>

    <div class="profile-section">
      <h3>Dirección</h3>
      <div class="profile-grid">
        <div class="form-group">
          <label class="label" for="nombre_pais">País</label>
          <input class="input" id="nombre_pais" name="nombre_pais" maxlength="50"
                 value="<?= htmlspecialchars($configUsuario['nombre_pais'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="label" for="nombre_ciudad">Ciudad</label>
          <input class="input" id="nombre_ciudad" name="nombre_ciudad" maxlength="50"
                 value="<?= htmlspecialchars($configUsuario['nombre_ciudad'] ?? '') ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label class="label" for="num_direccion">Dirección</label>
        <input class="input" id="num_direccion" name="num_direccion" maxlength="100"
               value="<?= htmlspecialchars($configUsuario['num_direccion'] ?? '') ?>" required>
      </div>
    </div>

    <div class="profile-actions">
      <button class="btn" type="submit">Guardar datos</button>
    </div>
  </form>
</div>
