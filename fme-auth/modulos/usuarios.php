<h1 class="title">Gestión de usuarios</h1>
<p class="subtitle">Consulta usuarios con filtros.</p>

<div class="profile-card">
  <div class="profile-card-header">
    <div class="profile-icon">👥</div>
    <div>
      <h2>Búsqueda de usuarios</h2>
      <p>Consulta desde Usuario, Persona, Dirección y Tipo_Rol.</p>
    </div>
  </div>

  <form method="get" action="index.php" class="profile-form">
    <input type="hidden" name="mod" value="usuarios">

    <div class="profile-section">
      <h3>Filtros</h3>

      <div class="profile-grid">
        <div class="form-group">
          <label class="label">Nombre</label>
          <input class="input" type="text" name="f_nombre"
                 value="<?= htmlspecialchars($_GET['f_nombre'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="label">Apellido</label>
          <input class="input" type="text" name="f_apellido"
                 value="<?= htmlspecialchars($_GET['f_apellido'] ?? '') ?>">
        </div>
      </div>

      <div class="profile-grid">
        <div class="form-group">
          <label class="label">Usuario</label>
          <input class="input" type="text" name="f_usuario"
                 value="<?= htmlspecialchars($_GET['f_usuario'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="label">Correo</label>
          <input class="input" type="text" name="f_correo"
                 value="<?= htmlspecialchars($_GET['f_correo'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="label">Rol</label>
        <select class="input" name="f_rol">
          <option value="">Todos</option>
          <option value="1" <?= (($_GET['f_rol'] ?? '') === '1' ? 'selected' : '') ?>>Administrador</option>
          <option value="2" <?= (($_GET['f_rol'] ?? '') === '2' ? 'selected' : '') ?>>Operador</option>
        </select>
      </div>
    </div>

    <div class="profile-actions">
      <button class="btn" type="submit">Buscar</button>
      <button class="btn" type="button" style="background:#888; margin-left:8px;"
              onclick="window.location.href='index.php?mod=usuarios'">
        Limpiar filtros
      </button>
    </div>
  </form>

  <?php
  $sql = "SELECT u.id_usuario, u.nombre_usuario, u.correo_usuario,
                 t.nombre_rol,
                 p.nombre_persona, p.apellido_persona,
                 d.nombre_ciudad, d.nombre_pais
          FROM Usuario u
          LEFT JOIN Tipo_Rol t ON u.id_rol = t.id_rol
          LEFT JOIN Persona p ON u.id_persona = p.id_persona
          LEFT JOIN Direccion d ON p.id_direccion = d.id_direccion
          WHERE 1=1";

  $params = [];

  if (!empty($_GET['f_nombre'])) {
    $sql .= " AND p.nombre_persona LIKE ?";
    $params[] = "%".$_GET['f_nombre']."%";
  }
  if (!empty($_GET['f_apellido'])) {
    $sql .= " AND p.apellido_persona LIKE ?";
    $params[] = "%".$_GET['f_apellido']."%";
  }
  if (!empty($_GET['f_usuario'])) {
    $sql .= " AND u.nombre_usuario LIKE ?";
    $params[] = "%".$_GET['f_usuario']."%";
  }
  if (!empty($_GET['f_correo'])) {
    $sql .= " AND u.correo_usuario LIKE ?";
    $params[] = "%".$_GET['f_correo']."%";
  }
  if (!empty($_GET['f_rol'])) {
    $sql .= " AND u.id_rol = ?";
    $params[] = $_GET['f_rol'];
  }

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $usuarios = $stmt->fetchAll();
  ?>

  <h3 style="margin-top:20px;">Resultados: <?= count($usuarios) ?></h3>

  <table class="result-table">
    <tr>
      <th>Nombre completo</th>
      <th>Usuario</th>
      <th>Correo</th>
      <th>Rol</th>
      <th>Ciudad</th>
      <th>País</th>
    </tr>
    <?php foreach ($usuarios as $u): ?>
    <tr>
      <td><?= htmlspecialchars(trim(($u['nombre_persona'] ?? '').' '.($u['apellido_persona'] ?? ''))) ?></td>
      <td><?= htmlspecialchars($u['nombre_usuario']) ?></td>
      <td><?= htmlspecialchars($u['correo_usuario']) ?></td>
      <td><?= htmlspecialchars($u['nombre_rol']) ?></td>
      <td><?= htmlspecialchars($u['nombre_ciudad'] ?? '-') ?></td>
      <td><?= htmlspecialchars($u['nombre_pais'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
