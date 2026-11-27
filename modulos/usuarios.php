<?php
$action = $_GET['action'] ?? 'list';
$idEditar = (int)($_GET['id'] ?? 0);
?>

<h1 class="title">Gestión de usuarios</h1>
<p class="subtitle">Consulta usuarios con filtros.</p>

<?php if ($action === 'edit'): ?>

  <?php
  $stmt = $pdo->prepare("SELECT 
                            u.id_usuario,
                            u.nombre_usuario,
                            u.correo_usuario,
                            u.id_rol,
                            u.activo_usuario,
                            t.nombre_rol,
                            p.CUIL_persona
                         FROM Usuario u
                         LEFT JOIN Tipo_Rol t ON u.id_rol = t.id_rol
                         LEFT JOIN Persona p ON u.id_persona = p.id_persona
                         WHERE u.id_usuario = ?
                         LIMIT 1");
  $stmt->execute([$idEditar]);
  $usuarioEdit = $stmt->fetch();

  $stmtRoles = $pdo->query("
    SELECT id_rol, nombre_rol
    FROM Tipo_Rol
    WHERE activo = 1
    ORDER BY nombre_rol ASC
  ");
  $rolesDisponibles = $stmtRoles->fetchAll();

  $esUnicoAdministrador = false;
  if ($usuarioEdit && (int)$usuarioEdit['activo_usuario'] === 1 && (int)$usuarioEdit['id_rol'] === 1) {
    $stmtAdminCount = $pdo->query('SELECT COUNT(*) FROM Usuario WHERE id_rol = 1 AND activo_usuario = 1');
    $esUnicoAdministrador = ((int)$stmtAdminCount->fetchColumn() <= 1);
  }
  ?>

  <div class="profile-card users-card">
    <div class="profile-card-header">
      <div class="profile-icon">✏️</div>
      <div>
        <h2>Editar usuario</h2>
        <p>Modifica usuario, correo, CUIL y perfil sin salir del panel principal.</p>
      </div>
    </div>

    <?php if (!$usuarioEdit): ?>

      <div class="alert error">No se encontró el usuario solicitado.</div>
      <a class="btn" href="index.php?mod=usuarios">Volver</a>

    <?php else: ?>

      <form method="post" action="index.php?mod=usuarios" class="profile-form">
        <input type="hidden" name="form" value="edit_usuario">
        <input type="hidden" name="id_usuario" value="<?= (int)$usuarioEdit['id_usuario'] ?>">

        <div class="profile-section">
          <h3>Datos de acceso</h3>

          <div class="profile-grid">
            <div class="form-group">
              <label class="label" for="nombre_usuario">Usuario</label>
              <input
                class="input"
                id="nombre_usuario"
                type="text"
                name="nombre_usuario"
                value="<?= htmlspecialchars($usuarioEdit['nombre_usuario']) ?>"
                required
              >
            </div>

            <div class="form-group">
              <label class="label" for="correo_usuario">Correo</label>
              <input
                class="input"
                id="correo_usuario"
                type="email"
                name="correo_usuario"
                value="<?= htmlspecialchars($usuarioEdit['correo_usuario']) ?>"
                required
              >
            </div>
          </div>

          <div class="profile-grid">
            <div class="form-group">
              <label class="label" for="CUIL_persona">CUIL / DNI</label>
              <input
                class="input"
                id="CUIL_persona"
                type="text"
                name="CUIL_persona"
                value="<?= htmlspecialchars($usuarioEdit['CUIL_persona'] ?? '') ?>"
              >
            </div>

            <div class="form-group form-rol">
              <label class="label" for="id_rol">Perfil</label>

              <select class="input" id="id_rol" name="id_rol" required>
                <option value="">Seleccione un perfil</option>

                <?php foreach ($rolesDisponibles as $rol): ?>
                  <option
                    value="<?= (int)$rol['id_rol'] ?>"
                    <?= ((int)$usuarioEdit['id_rol'] === (int)$rol['id_rol'] ? 'selected' : '') ?>
                    <?= ($esUnicoAdministrador && (int)$rol['id_rol'] !== 1 ? 'disabled' : '') ?>
                  >
                    <?= htmlspecialchars($rol['nombre_rol']) ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <?php if ($esUnicoAdministrador): ?>
                <small class="form-help">
                  Este usuario es el único administrador activo. No se puede cambiar a otro perfil.
                </small>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="profile-section">
          <h3>Estado de la cuenta</h3>
          <p class="form-help">
            Estado actual:
            <strong><?= (int)$usuarioEdit['activo_usuario'] === 1 ? 'Activo' : 'Inactivo' ?></strong>.
            La activación o baja lógica se administra desde el listado de usuarios.
          </p>
        </div>

        <div class="profile-actions">
          <a class="btn btn-secondary" href="index.php?mod=usuarios">Cancelar</a>
          <button class="btn" type="submit">Guardar cambios</button>
        </div>
      </form>

    <?php endif; ?>
  </div>

<?php else: ?>

  <?php
  $stmtRolesFiltro = $pdo->query("
    SELECT id_rol, nombre_rol
    FROM Tipo_Rol
    WHERE activo = 1
    ORDER BY nombre_rol ASC
  ");
  $rolesFiltro = $stmtRolesFiltro->fetchAll();
  ?>

  <div class="profile-card users-card wide-card">
    <div class="profile-card-header">
      <div class="profile-icon">👥</div>
      <div>
        <h2>Búsqueda de usuarios</h2>
        <p>Consulta desde Usuario, Persona, Dirección y Perfil.</p>
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

        <div class="profile-grid">
          <div class="form-group">
            <label class="label">CUIL</label>
            <input class="input" type="text" name="f_cuil"
                   value="<?= htmlspecialchars($_GET['f_cuil'] ?? '') ?>">
          </div>

          <div class="form-group form-rol">
            <label class="label">Perfil</label>

            <select class="input" name="f_rol">
              <option value="">Todos</option>

              <?php foreach ($rolesFiltro as $rol): ?>
                <option
                  value="<?= (int)$rol['id_rol'] ?>"
                  <?= (($_GET['f_rol'] ?? '') === (string)$rol['id_rol'] ? 'selected' : '') ?>
                >
                  <?= htmlspecialchars($rol['nombre_rol']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="profile-grid">
          <div class="form-group">
            <label class="label">Estado</label>
            <select class="input" name="f_estado">
              <option value="">Todos</option>
              <option value="1" <?= (($_GET['f_estado'] ?? '') === '1' ? 'selected' : '') ?>>Activos</option>
              <option value="0" <?= (($_GET['f_estado'] ?? '') === '0' ? 'selected' : '') ?>>Inactivos</option>
            </select>
          </div>
        </div>
      </div>

      <div class="profile-actions">
        <button class="btn" type="submit">Buscar</button>
        <a class="btn btn-secondary" href="index.php?mod=usuarios">Limpiar filtros</a>
      </div>
    </form>

            <?php
    $porPagina = 15;
    $paginaActual = max(1, (int)($_GET['pagina'] ?? 1));

    $where = " WHERE 1 = 1";
    $params = [];

    if (!empty($_GET['f_nombre'])) {
      $where .= " AND p.nombre_persona LIKE ?";
      $params[] = "%" . $_GET['f_nombre'] . "%";
    }

    if (!empty($_GET['f_apellido'])) {
      $where .= " AND p.apellido_persona LIKE ?";
      $params[] = "%" . $_GET['f_apellido'] . "%";
    }

    if (!empty($_GET['f_usuario'])) {
      $where .= " AND u.nombre_usuario LIKE ?";
      $params[] = "%" . $_GET['f_usuario'] . "%";
    }

    if (!empty($_GET['f_correo'])) {
      $where .= " AND u.correo_usuario LIKE ?";
      $params[] = "%" . $_GET['f_correo'] . "%";
    }

    if (!empty($_GET['f_cuil'])) {
      $where .= " AND p.CUIL_persona LIKE ?";
      $params[] = "%" . $_GET['f_cuil'] . "%";
    }

    if (!empty($_GET['f_rol'])) {
      $where .= " AND u.id_rol = ?";
      $params[] = $_GET['f_rol'];
    }

    if (isset($_GET['f_estado']) && in_array((string)$_GET['f_estado'], ['0', '1'], true)) {
      $where .= " AND u.activo_usuario = ?";
      $params[] = (int)$_GET['f_estado'];
    }

    $countSql = "SELECT COUNT(*)
                 FROM Usuario u
                 LEFT JOIN Persona p ON u.id_persona = p.id_persona
                 LEFT JOIN Direccion d ON p.id_direccion = d.id_direccion" . $where;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalUsuarios = (int)$countStmt->fetchColumn();

    $totalPaginas = max(1, (int)ceil($totalUsuarios / $porPagina));
    if ($paginaActual > $totalPaginas) $paginaActual = $totalPaginas;
    $offset = ($paginaActual - 1) * $porPagina;

    $sql = "SELECT
                u.id_usuario,
                u.nombre_usuario,
                u.correo_usuario,
                u.id_rol,
                u.activo_usuario,
                t.nombre_rol,
                p.nombre_persona,
                p.apellido_persona,
                p.CUIL_persona,
                p.telefono_persona,
                d.nombre_ciudad,
                d.nombre_pais
            FROM Usuario u
            LEFT JOIN Tipo_Rol t ON u.id_rol = t.id_rol
            LEFT JOIN Persona p ON u.id_persona = p.id_persona
            LEFT JOIN Direccion d ON p.id_direccion = d.id_direccion" . $where .
           " ORDER BY p.apellido_persona, p.nombre_persona, u.nombre_usuario
             LIMIT " . $porPagina . " OFFSET " . $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll();

    $administradoresActivos = (int)$pdo->query(
      'SELECT COUNT(*) FROM Usuario WHERE id_rol = 1 AND activo_usuario = 1'
    )->fetchColumn();

    $paginaUrl = static function (int $pagina): string {
      $query = $_GET;
      $query['mod'] = 'usuarios';
      $query['pagina'] = $pagina;
      unset($query['action'], $query['id']);
      return 'index.php?' . http_build_query($query);
    };
    ?>

    <h3 class="result-count">Resultados: <?= $totalUsuarios ?></h3>

    <style>
      .users-table {
        min-width: 1250px;
      }

      .users-table th.actions-col,
      .users-table td.actions-col {
        position: sticky;
        right: 0;
        z-index: 3;
        background: #fff;
        min-width: 190px;
        white-space: nowrap;
        box-shadow: -8px 0 12px rgba(15, 23, 42, 0.04);
      }

      .users-table thead th.actions-col {
        z-index: 4;
        background: #f8fafc;
      }

      .user-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: nowrap;
      }

      .user-actions form {
        margin: 0;
      }

      .user-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 7px 11px;
        border-radius: 8px;
        border: 1px solid #d6dee8;
        background: #fff;
        color: #172033;
        font: inherit;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
      }

      .user-action-btn:hover {
        background: #f4f7fb;
      }

      .user-action-btn.danger {
        border-color: #f2b8b5;
        color: #b42318;
      }

      .user-action-btn.success {
        border-color: #a7dfc1;
        color: #067647;
      }

      .user-action-btn:disabled {
        cursor: not-allowed;
        opacity: .5;
        background: #f5f6f8;
      }
    </style>

    <div class="table-responsive">
      <table class="result-table users-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre completo</th>
            <th>Usuario</th>
            <th>Correo</th>
            <th>CUIL</th>
            <th>Teléfono</th>
            <th>Perfil</th>
            <th>Ciudad</th>
            <th>País</th>
            <th>Estado</th>
            <th class="actions-col">Acciones</th>
          </tr>
        </thead>

        <tbody>
          <?php foreach ($usuarios as $u): ?>
            <tr>
              <td><?= (int)$u['id_usuario'] ?></td>

              <td>
                <?= htmlspecialchars(trim(($u['nombre_persona'] ?? '') . ' ' . ($u['apellido_persona'] ?? '')) ?: '-') ?>
              </td>

              <td><?= htmlspecialchars($u['nombre_usuario'] ?? '-') ?></td>
              <td><?= htmlspecialchars($u['correo_usuario'] ?? '-') ?></td>
              <td><?= htmlspecialchars($u['CUIL_persona'] ?? '-') ?></td>
              <td><?= htmlspecialchars($u['telefono_persona'] ?? '-') ?></td>
              <td><?= htmlspecialchars($u['nombre_rol'] ?? '-') ?></td>
              <td><?= htmlspecialchars($u['nombre_ciudad'] ?? '-') ?></td>
              <td><?= htmlspecialchars($u['nombre_pais'] ?? '-') ?></td>
              <td>
                <?php if ((int)$u['activo_usuario'] === 1): ?>
                  <span title="Usuario habilitado para iniciar sesión">🟢 Activo</span>
                <?php else: ?>
                  <span title="Usuario con acceso bloqueado">🔴 Inactivo</span>
                <?php endif; ?>
              </td>

              <td class="actions-col">
                <?php
                  $esPropioUsuario = (int)$u['id_usuario'] === $userId;
                  $esUltimoAdminActivo = (int)$u['activo_usuario'] === 1
                    && (int)$u['id_rol'] === 1
                    && $administradoresActivos <= 1;
                ?>

                <div class="user-actions">
                  <a
                    class="user-action-btn"
                    href="index.php?mod=usuarios&action=edit&id=<?= (int)$u['id_usuario'] ?>"
                  >
                    Editar
                  </a>

                  <?php if ((int)$u['activo_usuario'] === 1): ?>
                    <?php if (!$esPropioUsuario && !$esUltimoAdminActivo): ?>
                      <form method="post" action="index.php?mod=usuarios" onsubmit="return confirm('¿Desactivar este usuario? Ya no podrá iniciar sesión.');">
                        <input type="hidden" name="form" value="toggle_usuario_estado">
                        <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                        <input type="hidden" name="nuevo_estado" value="0">
                        <button class="user-action-btn danger" type="submit">Desactivar</button>
                      </form>
                    <?php else: ?>
                      <button
                        class="user-action-btn danger"
                        type="button"
                        disabled
                        title="<?= $esPropioUsuario
                          ? 'No puedes desactivar tu propia cuenta.'
                          : 'Debe existir al menos un administrador activo.' ?>"
                      >
                        Desactivar
                      </button>
                    <?php endif; ?>
                  <?php else: ?>
                    <form method="post" action="index.php?mod=usuarios" onsubmit="return confirm('¿Reactivar este usuario? Volverá a poder iniciar sesión si su perfil también está activo.');">
                      <input type="hidden" name="form" value="toggle_usuario_estado">
                      <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                      <input type="hidden" name="nuevo_estado" value="1">
                      <button class="user-action-btn success" type="submit">Reactivar</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (!$usuarios): ?>
            <tr>
              <td colspan="11">No se encontraron usuarios.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="pagination-bar">
      <div class="pagination-summary">
        <?= $totalUsuarios ?> usuario<?= $totalUsuarios === 1 ? '' : 's' ?> encontrado<?= $totalUsuarios === 1 ? '' : 's' ?> · Página <?= $paginaActual ?> de <?= $totalPaginas ?>
      </div>
      <?php if ($totalPaginas > 1): ?>
        <nav class="pagination" aria-label="Paginación de usuarios">
          <a class="page-link <?= $paginaActual <= 1 ? 'disabled' : '' ?>" href="<?= $paginaActual > 1 ? htmlspecialchars($paginaUrl($paginaActual - 1)) : '#' ?>">‹ Anterior</a>
          <?php for ($paginaNumero = max(1, $paginaActual - 2); $paginaNumero <= min($totalPaginas, $paginaActual + 2); $paginaNumero++): ?>
            <a class="page-link <?= $paginaNumero === $paginaActual ? 'active' : '' ?>" href="<?= htmlspecialchars($paginaUrl($paginaNumero)) ?>"><?= $paginaNumero ?></a>
          <?php endfor; ?>
          <a class="page-link <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>" href="<?= $paginaActual < $totalPaginas ? htmlspecialchars($paginaUrl($paginaActual + 1)) : '#' ?>">Siguiente ›</a>
        </nav>
      <?php endif; ?>
    </div>
  </div>

<?php endif; ?>