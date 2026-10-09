<?php
$action = $_GET['action'] ?? 'list';
$idEditar = (int)($_GET['id'] ?? 0);

if (!isset($modulosPermitidosConArchivo['perfiles'])) {
  echo '<div class="alert error">No tienes permiso para administrar perfiles.</div>';
  return;
}
?>

<h1 class="title">Gestión de perfiles</h1>
<p class="subtitle">Alta, baja lógica y modificación de perfiles de acceso del sistema.</p>

<?php if ($action === 'add' || $action === 'edit'): ?>

  <?php
  $perfilEdit = null;

  if ($action === 'edit') {
    $stmt = $pdo->prepare('SELECT id_rol, nombre_rol, descripcion_rol, activo FROM Tipo_Rol WHERE id_rol = ? LIMIT 1');
    $stmt->execute([$idEditar]);
    $perfilEdit = $stmt->fetch();
  }

  $modulosDisponibles = [];
  $modulosAsignados = [];

  if ($action === 'edit' && $perfilEdit) {
    $stmt = $pdo->query("SELECT id_modulo, nombre_modulo, codigo_modulo, icono_modulo, activo
                         FROM Modulo
                         WHERE activo = 1
                         ORDER BY nombre_modulo ASC");
    $modulosDisponibles = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT id_modulo FROM Rol_Modulo WHERE id_rol = ?');
    $stmt->execute([(int)$perfilEdit['id_rol']]);
    $modulosAsignados = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
  }
  ?>

  <div class="profile-card users-card">
    <div class="profile-card-header">
      <div class="profile-icon">🛡️</div>
      <div>
        <h2><?= $action === 'add' ? 'Nuevo perfil' : 'Editar perfil' ?></h2>
        <p>Un perfil define el tipo de acceso que luego se asigna a los usuarios.</p>
      </div>
    </div>

    <?php if ($action === 'edit' && !$perfilEdit): ?>
      <div class="alert error">No se encontró el perfil solicitado.</div>
      <a class="btn btn-secondary" href="index.php?mod=perfiles">Volver</a>
    <?php else: ?>

      <form method="post" action="index.php?mod=perfiles" class="profile-form">
        <input type="hidden" name="form" value="<?= $action === 'add' ? 'add_perfil' : 'edit_perfil' ?>">

        <?php if ($action === 'edit'): ?>
          <input type="hidden" name="id_rol" value="<?= (int)$perfilEdit['id_rol'] ?>">
        <?php endif; ?>

        <div class="profile-section">
          <h3>Datos del perfil</h3>

          <div class="profile-grid">
            <div class="form-group">
              <label class="label" for="nombre_rol">Nombre del perfil</label>
              <input
                class="input"
                id="nombre_rol"
                type="text"
                name="nombre_rol"
                maxlength="50"
                value="<?= htmlspecialchars($perfilEdit['nombre_rol'] ?? '') ?>"
                required
              >
            </div>

            <div class="form-group">
              <label class="label" for="descripcion_rol">Descripción</label>
              <input
                class="input"
                id="descripcion_rol"
                type="text"
                name="descripcion_rol"
                maxlength="100"
                value="<?= htmlspecialchars($perfilEdit['descripcion_rol'] ?? '') ?>"
              >
            </div>
          </div>
        </div>

        <?php if ($action === 'edit'): ?>
          <div class="profile-section">
            <h3>Módulos asignados al perfil</h3>
            <p class="subtitle" style="margin-top:0">
              <?= ((int)($perfilEdit['id_rol'] ?? 0) === 1)
                  ? 'El perfil Administrador conserva acceso total a todos los módulos activos.'
                  : 'Selecciona qué módulos podrá utilizar este perfil.' ?>
            </p>

            <?php if (!$modulosDisponibles): ?>
              <div class="alert error">No hay módulos activos cargados. Primero ejecuta el SQL de módulos.</div>
            <?php else: ?>
              <div class="profile-grid">
                <?php foreach ($modulosDisponibles as $modulo): ?>
                  <label class="check-row" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #eee;border-radius:12px;">
                    <input
                      type="checkbox"
                      name="modulos[]"
                      value="<?= (int)$modulo['id_modulo'] ?>"
                      <?= ((int)($perfilEdit['id_rol'] ?? 0) === 1 || in_array((int)$modulo['id_modulo'], $modulosAsignados, true)) ? 'checked' : '' ?>
                      <?= ((int)($perfilEdit['id_rol'] ?? 0) === 1) ? 'disabled' : '' ?>
                    >
                    <span><?= htmlspecialchars(($modulo['icono_modulo'] ?? '') . ' ' . $modulo['nombre_modulo']) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="profile-actions">
          <a class="btn btn-secondary" href="index.php?mod=perfiles">Cancelar</a>
          <button class="btn" type="submit">Guardar perfil</button>
        </div>
      </form>

    <?php endif; ?>
  </div>

<?php else: ?>

  <?php
  $stmt = $pdo->query("SELECT
                          r.id_rol,
                          r.nombre_rol,
                          r.descripcion_rol,
                          r.activo,
                          COUNT(DISTINCT u.id_usuario) AS usuarios_asignados,
                          COUNT(DISTINCT rm.id_modulo) AS modulos_asignados
                       FROM Tipo_Rol r
                       LEFT JOIN Usuario u ON u.id_rol = r.id_rol
                       LEFT JOIN Rol_Modulo rm ON rm.id_rol = r.id_rol
                       GROUP BY r.id_rol, r.nombre_rol, r.descripcion_rol, r.activo
                       ORDER BY r.id_rol");
  $perfiles = $stmt->fetchAll();
  ?>

  <div class="profile-card users-card">
    <div class="profile-card-header">
      <div class="profile-icon">🛡️</div>
      <div>
        <h2>Perfiles disponibles</h2>
        <p>Administra los perfiles que luego se asignan a cada usuario del sistema.</p>
      </div>
      <div class="profile-actions">
        <a class="btn-add" href="index.php?mod=perfiles&action=add">➕ Nuevo perfil</a>
      </div>
    </div>

    <h3 class="result-count">Resultados: <?= count($perfiles) ?></h3>

    <div class="table-responsive">
      <table class="result-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Perfil</th>
            <th>Descripción</th>
            <th>Usuarios asignados</th>
            <th>Módulos asignados</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>

        <tbody>
          <?php foreach ($perfiles as $p): ?>
            <tr>
              <td><?= (int)$p['id_rol'] ?></td>
              <td><?= htmlspecialchars($p['nombre_rol']) ?></td>
              <td><?= htmlspecialchars($p['descripcion_rol'] ?? '-') ?></td>
              <td><?= (int)$p['usuarios_asignados'] ?></td>
              <td><?= (int)$p['modulos_asignados'] ?></td>
              <td><?= ((int)$p['activo'] === 1) ? 'Activo' : 'Inactivo' ?></td>
              <td>
                <a class="table-action" href="index.php?mod=perfiles&action=edit&id=<?= (int)$p['id_rol'] ?>">Editar</a>

                <?php if ((int)$p['id_rol'] !== 1): ?>
                  <?php if ((int)$p['activo'] === 1 && (int)$p['usuarios_asignados'] === 0): ?>
                    <form method="post" action="index.php?mod=perfiles" style="display:inline" data-confirm="¿Desactivar este perfil?">
                      <input type="hidden" name="form" value="delete_perfil">
                      <input type="hidden" name="id_rol" value="<?= (int)$p['id_rol'] ?>">
                      <button class="btn-delete" type="submit">Desactivar</button>
                    </form>
                  <?php elseif ((int)$p['activo'] === 0): ?>
                    <form method="post" action="index.php?mod=perfiles" style="display:inline" data-confirm="¿Activar este perfil?">
                      <input type="hidden" name="form" value="activate_perfil">
                      <input type="hidden" name="id_rol" value="<?= (int)$p['id_rol'] ?>">
                      <button class="table-action" type="submit">Activar</button>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (!$perfiles): ?>
            <tr>
              <td colspan="7">No se encontraron perfiles.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>
