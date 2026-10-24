<?php
$fUsuario = trim((string) ($_GET['f_usuario'] ?? ''));
$fModulo  = trim((string) ($_GET['f_modulo'] ?? ''));
$fAccion  = trim((string) ($_GET['f_accion'] ?? ''));
$fDesde   = trim((string) ($_GET['f_desde'] ?? ''));
$fHasta   = trim((string) ($_GET['f_hasta'] ?? ''));
?>

<h1 class="title">Auditoría</h1>
<p class="subtitle">Consulta quién realizó acciones relevantes, sobre qué registro y cuándo ocurrieron.</p>

<div class="profile-card wide-card audit-card">
  <div class="profile-card-header">
    <div class="profile-icon">📜</div>
    <div>
      <h2>Historial de actividad</h2>
      <p>Los registros son de solo lectura y se muestran del más reciente al más antiguo.</p>
    </div>
  </div>

  <div class="profile-section">
    <form method="get" action="index.php" class="filter-form">
      <input type="hidden" name="mod" value="auditoria">

      <div class="filter-grid audit-filter-grid">
        <div class="form-group">
          <label class="label" for="f_usuario">Usuario</label>
          <input class="input" id="f_usuario" type="text" name="f_usuario"
                 placeholder="Ej.: Fgimenez" value="<?= htmlspecialchars($fUsuario) ?>">
        </div>

        <div class="form-group">
          <label class="label" for="f_modulo">Módulo</label>
          <select class="input" id="f_modulo" name="f_modulo">
            <option value="">Todos</option>
            <?php foreach ($auditoriaModulos as $modulo): ?>
              <option value="<?= htmlspecialchars((string) $modulo) ?>"
                <?= $fModulo === (string) $modulo ? 'selected' : '' ?>>
                <?= htmlspecialchars((string) $modulo) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="label" for="f_accion">Acción</label>
          <select class="input" id="f_accion" name="f_accion">
            <option value="">Todas</option>
            <?php foreach ($auditoriaAcciones as $accionItem): ?>
              <option value="<?= htmlspecialchars((string) $accionItem) ?>"
                <?= $fAccion === (string) $accionItem ? 'selected' : '' ?>>
                <?= htmlspecialchars((string) $accionItem) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="label" for="f_desde">Desde</label>
          <input class="input" id="f_desde" type="date" name="f_desde" value="<?= htmlspecialchars($fDesde) ?>">
        </div>

        <div class="form-group">
          <label class="label" for="f_hasta">Hasta</label>
          <input class="input" id="f_hasta" type="date" name="f_hasta" value="<?= htmlspecialchars($fHasta) ?>">
        </div>
      </div>

      <div class="module-actions left-actions">
        <button class="btn" type="submit">Buscar</button>
        <a class="btn-secondary-link" href="index.php?mod=auditoria">Limpiar filtros</a>
      </div>
    </form>
  </div>

  <?php if ($auditoriaError): ?>
    <div class="module-warning"><?= htmlspecialchars($auditoriaError) ?></div>
  <?php else: ?>
    <h3 class="result-count">Registros encontrados: <?= $auditoriaTotalRegistros ?></h3>

    <div class="table-responsive">
      <table class="result-table audit-table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Usuario</th>
            <th>Módulo</th>
            <th>Acción</th>
            <th>Registro</th>
            <th>Detalle</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$auditorias): ?>
          <tr><td colspan="7" class="empty-cell">No hay registros de auditoría para los filtros seleccionados.</td></tr>
        <?php else: ?>
          <?php foreach ($auditorias as $registro): ?>
            <?php
              $detalle = trim((string) ($registro['detalle'] ?? ''));
              $detalleDecodificado = $detalle !== '' ? json_decode($detalle, true) : null;
              if (is_array($detalleDecodificado)) {
                  $detalleMostrado = json_encode(
                      $detalleDecodificado,
                      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
                  );
              } else {
                  $detalleMostrado = $detalle;
              }

              $registroTexto = trim((string) ($registro['entidad'] ?? ''));
              if (!empty($registro['id_registro'])) {
                  $registroTexto .= ($registroTexto !== '' ? ' ' : '') . '#' . (int) $registro['id_registro'];
              }
              if ($registroTexto === '') {
                  $registroTexto = '-';
              }
            ?>
            <tr>
              <td class="audit-date"><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime((string) $registro['fecha_auditoria']))) ?></td>
              <td>
                <strong><?= htmlspecialchars((string) $registro['usuario_nombre']) ?></strong>
                <?php if (!empty($registro['id_usuario'])): ?>
                  <small class="audit-user-id">ID <?= (int) $registro['id_usuario'] ?></small>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars((string) $registro['modulo']) ?></td>
              <td><span class="audit-action-badge"><?= htmlspecialchars((string) $registro['accion']) ?></span></td>
              <td><?= htmlspecialchars($registroTexto) ?></td>
              <td class="audit-detail-cell">
                <?php if ($detalleMostrado !== ''): ?>
                  <details>
                    <summary>Ver detalle</summary>
                    <pre><?= htmlspecialchars((string) $detalleMostrado) ?></pre>
                  </details>
                <?php else: ?>
                  <span class="audit-muted">-</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars((string) ($registro['ip_origen'] ?: '-')) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="pagination-bar">
      <div class="pagination-summary">
        <?= $auditoriaTotalRegistros ?> registro<?= $auditoriaTotalRegistros === 1 ? '' : 's' ?> encontrado<?= $auditoriaTotalRegistros === 1 ? '' : 's' ?> · Página <?= $auditoriaPaginaActual ?> de <?= $auditoriaTotalPaginas ?>
      </div>
      <?php if ($auditoriaTotalPaginas > 1): ?>
        <nav class="pagination" aria-label="Paginación de auditoría">
          <a class="page-link <?= $auditoriaPaginaActual <= 1 ? 'disabled' : '' ?>" href="<?= $auditoriaPaginaActual > 1 ? htmlspecialchars($auditoriaPaginaUrl($auditoriaPaginaActual - 1)) : '#' ?>">‹ Anterior</a>
          <?php for ($p = max(1, $auditoriaPaginaActual - 2); $p <= min($auditoriaTotalPaginas, $auditoriaPaginaActual + 2); $p++): ?>
            <a class="page-link <?= $p === $auditoriaPaginaActual ? 'active' : '' ?>" href="<?= htmlspecialchars($auditoriaPaginaUrl($p)) ?>"><?= $p ?></a>
          <?php endfor; ?>
          <a class="page-link <?= $auditoriaPaginaActual >= $auditoriaTotalPaginas ? 'disabled' : '' ?>" href="<?= $auditoriaPaginaActual < $auditoriaTotalPaginas ? htmlspecialchars($auditoriaPaginaUrl($auditoriaPaginaActual + 1)) : '#' ?>">Siguiente ›</a>
        </nav>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
