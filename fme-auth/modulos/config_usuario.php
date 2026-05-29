<h1 class="title">Configuración de usuario</h1>
<p class="subtitle">Completa tus datos personales y de dirección.</p>

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
          <label class="label">Nombre</label>
          <input class="input" name="nombre_persona" required>
        </div>
        <div class="form-group">
          <label class="label">Apellido</label>
          <input class="input" name="apellido_persona" required>
        </div>
      </div>

      <div class="profile-grid">
        <div class="form-group">
          <label class="label">CUIL</label>
          <input class="input" name="CUIL_persona" required>
        </div>
        <div class="form-group">
          <label class="label">Teléfono</label>
          <input class="input" name="telefono_persona">
        </div>
      </div>
    </div>

    <div class="profile-section">
      <h3>Dirección</h3>
      <div class="profile-grid">
        <div class="form-group">
          <label class="label">País</label>
          <input class="input" name="nombre_pais" required>
        </div>
        <div class="form-group">
          <label class="label">Ciudad</label>
          <input class="input" name="nombre_ciudad" required>
        </div>
      </div>

      <div class="form-group">
        <label class="label">Dirección</label>
        <input class="input" name="num_direccion" required>
      </div>
    </div>

    <div class="profile-actions">
      <button class="btn" type="submit">Guardar datos</button>
    </div>
  </form>
</div>
