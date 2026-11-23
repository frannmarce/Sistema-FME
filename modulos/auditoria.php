<?php
$fUsuario = trim((string) ($_GET['f_usuario'] ?? ''));
$fModulo  = trim((string) ($_GET['f_modulo'] ?? ''));
$fAccion  = trim((string) ($_GET['f_accion'] ?? ''));
$fDesde   = trim((string) ($_GET['f_desde'] ?? ''));
$fHasta   = trim((string) ($_GET['f_hasta'] ?? ''));

// Mapa de perfiles para mostrar nombres legibles en los detalles de auditoría.
$auditoriaPerfiles = [];
try {
    $stmtPerfilesAuditoria = $pdo->query('SELECT id_rol, nombre_rol FROM Tipo_Rol ORDER BY id_rol ASC');
    foreach ($stmtPerfilesAuditoria->fetchAll(PDO::FETCH_ASSOC) as $perfilAuditoria) {
        $auditoriaPerfiles[(int) $perfilAuditoria['id_rol']] = (string) $perfilAuditoria['nombre_rol'];
    }
} catch (Throwable $e) {
    // La auditoría sigue funcionando aunque no se puedan resolver nombres de perfiles.
}

$auditoriaEtiquetaDetalle = static function (string $clave): string {
    $etiquetas = [
        'usuario' => 'Usuario',
        'perfil' => 'Perfil',
        'nombre' => 'Nombre',
        'correo' => 'Correo',
        'cuil' => 'CUIL / DNI',
        'estado' => 'Estado',
        'stock' => 'Stock',
        'precio' => 'Precio',
        'cantidad' => 'Cantidad',
        'monto' => 'Monto',
        'motivo' => 'Motivo',
        'medio_pago' => 'Medio de pago',
        'producto' => 'Producto',
        'proveedor' => 'Proveedor',
        'categoria' => 'Categoría',
        'medios_pago' => 'Medios de pago',
        'caja_id' => 'Caja N.º',
        'productos_distintos' => 'Productos diferentes',
        'total' => 'Total',
    ];
    if (isset($etiquetas[$clave])) {
        return $etiquetas[$clave];
    }
    return ucwords(str_replace('_', ' ', $clave));
};


$auditoriaFormatoMoneda = static function (mixed $valor): string {
    if (!is_numeric($valor)) {
        return (string) $valor;
    }
    return '$' . number_format((float) $valor, 2, ',', '.');
};

$auditoriaMediosPago = static function (mixed $valor) use ($auditoriaFormatoMoneda): array {
    $items = is_array($valor) ? $valor : [];
    $salida = [];
    $totalAplicado = 0.0;

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $medio = trim((string) ($item['medio'] ?? $item['nombre_medio'] ?? 'Medio de pago'));

        // Compatibilidad con auditorías anteriores (monto) y con las nuevas
        // ventas que separan monto aplicado de monto entregado por el cliente.
        $montoAplicadoRaw = $item['monto_aplicado'] ?? $item['monto'] ?? $item['monto_entregado'] ?? null;
        $montoEntregadoRaw = $item['monto_entregado'] ?? $item['monto'] ?? $montoAplicadoRaw;

        $montoAplicado = is_numeric($montoAplicadoRaw) ? (float) $montoAplicadoRaw : null;
        $montoEntregado = is_numeric($montoEntregadoRaw) ? (float) $montoEntregadoRaw : null;

        if ($montoAplicado !== null) {
            $totalAplicado += $montoAplicado;
        }

        $salida[] = [
            'medio' => $medio !== '' ? $medio : 'Medio de pago',
            'monto' => $montoAplicado !== null ? $auditoriaFormatoMoneda($montoAplicado) : '—',
            'entregado' => $montoEntregado !== null ? $auditoriaFormatoMoneda($montoEntregado) : null,
            'mostrar_entregado' => $montoAplicado !== null
                && $montoEntregado !== null
                && abs($montoEntregado - $montoAplicado) >= 0.01,
        ];
    }

    return [
        'items' => $salida,
        'total' => $auditoriaFormatoMoneda($totalAplicado),
    ];
};

$auditoriaValorDetalle = static function (string $clave, mixed $valor) use ($auditoriaPerfiles): string {
    if ($valor === null || $valor === '') {
        return '—';
    }
    if (is_bool($valor)) {
        return $valor ? 'Sí' : 'No';
    }
    if (($clave === 'perfil' || str_contains($clave, 'perfil')) && is_numeric($valor)) {
        $idPerfil = (int) $valor;
        if (isset($auditoriaPerfiles[$idPerfil])) {
            return $auditoriaPerfiles[$idPerfil];
        }
    }
    if (is_array($valor)) {
        return json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
    }
    return (string) $valor;
};
?>

<style>
  .audit-detail-toggle {
    border: 0;
    background: transparent;
    padding: 0;
    color: #334155;
    font: inherit;
    cursor: pointer;
  }
  .audit-detail-toggle:hover { color: #0f6fb8; }
  .audit-detail-row[hidden] { display: none; }
  .audit-detail-row > td { padding: 0 16px 18px; background: #fbfdff; }
  .audit-detail-panel {
    margin-top: 2px;
    padding: 16px 18px;
    border: 1px solid #dfe7f1;
    border-radius: 12px;
    background: #f7faff;
  }
  .audit-detail-title {
    margin: 0 0 12px;
    font-size: 13px;
    font-weight: 800;
    color: #1e293b;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  .audit-change-list { display: grid; gap: 9px; }
  .audit-change-item {
    display: grid;
    grid-template-columns: minmax(140px, .8fr) minmax(160px, 1fr) 26px minmax(160px, 1fr);
    gap: 10px;
    align-items: center;
    padding: 10px 12px;
    border-radius: 10px;
    background: #fff;
    border: 1px solid #e8eef5;
  }
  .audit-change-label { font-weight: 700; color: #334155; }
  .audit-value-old, .audit-value-new { word-break: break-word; }
  .audit-value-old { color: #64748b; }
  .audit-value-new { color: #0f172a; font-weight: 700; }
  .audit-change-arrow { text-align: center; color: #3b82f6; font-weight: 800; }
  .audit-detail-simple {
    display: grid;
    grid-template-columns: minmax(150px, 220px) 1fr;
    gap: 10px 16px;
    padding: 8px 0;
    border-bottom: 1px solid #e8eef5;
  }
  .audit-detail-simple:last-child { border-bottom: 0; }
  .audit-detail-raw {
    white-space: pre-wrap;
    word-break: break-word;
    margin: 0;
    color: #334155;
    font-family: inherit;
  }

  .audit-payment-list {
    display: grid;
    gap: 8px;
  }
  .audit-payment-item {
    display: flex;
    justify-content: space-between;
    gap: 18px;
    align-items: center;
    padding: 8px 10px;
    border: 1px solid #e8eef5;
    border-radius: 9px;
    background: #fff;
  }
  .audit-payment-name { color: #334155; font-weight: 700; }
  .audit-payment-amount {
    color: #0f172a;
    font-weight: 800;
    white-space: nowrap;
    text-align: right;
  }
  .audit-payment-delivered {
    display: block;
    margin-top: 3px;
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
  }
  .audit-payment-total {
    display: flex;
    justify-content: space-between;
    gap: 18px;
    padding: 9px 10px 2px;
    border-top: 1px solid #dbe4ef;
    color: #334155;
  }
  .audit-payment-total strong { color: #0f172a; }

  @media (max-width: 900px) {
    .audit-change-item { grid-template-columns: 1fr; }
    .audit-change-arrow { text-align: left; transform: rotate(90deg); width: max-content; }
    .audit-detail-simple { grid-template-columns: 1fr; gap: 3px; }
  }
</style>

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
              $detalleEsJson = is_array($detalleDecodificado);
              $detalleId = 'audit-detail-' . (int) $registro['id_auditoria'];

              $cambiosDetalle = [];
              $otrosDetalle = [];
              if ($detalleEsJson) {
                  foreach ($detalleDecodificado as $clave => $valor) {
                      $claveTexto = (string) $clave;
                      if (preg_match('/^(.*)_(anterior|actual)(?:_id)?$/', $claveTexto, $match)) {
                          $base = (string) $match[1];
                          $lado = (string) $match[2];
                          $cambiosDetalle[$base][$lado] = $valor;
                      } else {
                          $otrosDetalle[$claveTexto] = $valor;
                      }
                  }
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
                <?php if ($detalle !== ''): ?>
                  <button type="button" class="audit-detail-toggle"
                          data-audit-detail="<?= htmlspecialchars($detalleId) ?>"
                          aria-expanded="false">
                    ▼ Ver detalle
                  </button>
                <?php else: ?>
                  <span class="audit-muted">-</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars((string) ($registro['ip_origen'] ?: '-')) ?></td>
            </tr>
            <?php if ($detalle !== ''): ?>
              <tr id="<?= htmlspecialchars($detalleId) ?>" class="audit-detail-row" hidden>
                <td colspan="7">
                  <div class="audit-detail-panel">
                    <div class="audit-detail-title">Cambios realizados</div>

                    <?php if ($detalleEsJson): ?>
                      <div class="audit-change-list">
                        <?php foreach ($cambiosDetalle as $claveBase => $cambio): ?>
                          <?php
                            $valorAnterior = $auditoriaValorDetalle((string) $claveBase, $cambio['anterior'] ?? null);
                            $valorActual = $auditoriaValorDetalle((string) $claveBase, $cambio['actual'] ?? null);
                          ?>
                          <div class="audit-change-item">
                            <div class="audit-change-label"><?= htmlspecialchars($auditoriaEtiquetaDetalle((string) $claveBase)) ?></div>
                            <div class="audit-value-old"><small>Anterior</small><br><?= htmlspecialchars($valorAnterior) ?></div>
                            <div class="audit-change-arrow">→</div>
                            <div class="audit-value-new"><small>Actual</small><br><?= htmlspecialchars($valorActual) ?></div>
                          </div>
                        <?php endforeach; ?>

                        <?php foreach ($otrosDetalle as $claveDetalle => $valorDetalle): ?>
                          <?php if ((string) $claveDetalle === 'medios_pago' && is_array($valorDetalle)): ?>
                            <?php $mediosPagoDetalle = $auditoriaMediosPago($valorDetalle); ?>
                            <div class="audit-detail-simple">
                              <div class="audit-change-label"><?= htmlspecialchars($auditoriaEtiquetaDetalle((string) $claveDetalle)) ?></div>
                              <div class="audit-payment-list">
                                <?php if (!empty($mediosPagoDetalle['items'])): ?>
                                  <?php foreach ($mediosPagoDetalle['items'] as $pagoDetalle): ?>
                                    <div class="audit-payment-item">
                                      <span class="audit-payment-name"><?= htmlspecialchars((string) $pagoDetalle['medio']) ?></span>
                                      <span class="audit-payment-amount">
                                        <?= htmlspecialchars((string) $pagoDetalle['monto']) ?>
                                        <?php if (!empty($pagoDetalle['mostrar_entregado'])): ?>
                                          <small class="audit-payment-delivered">Entregado: <?= htmlspecialchars((string) $pagoDetalle['entregado']) ?></small>
                                        <?php endif; ?>
                                      </span>
                                    </div>
                                  <?php endforeach; ?>
                                  <div class="audit-payment-total">
                                    <span>Total aplicado</span>
                                    <strong><?= htmlspecialchars((string) $mediosPagoDetalle['total']) ?></strong>
                                  </div>
                                <?php else: ?>
                                  <span>—</span>
                                <?php endif; ?>
                              </div>
                            </div>
                          <?php else: ?>
                            <div class="audit-detail-simple">
                              <div class="audit-change-label"><?= htmlspecialchars($auditoriaEtiquetaDetalle((string) $claveDetalle)) ?></div>
                              <div><?= htmlspecialchars($auditoriaValorDetalle((string) $claveDetalle, $valorDetalle)) ?></div>
                            </div>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <pre class="audit-detail-raw"><?= htmlspecialchars($detalle) ?></pre>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
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

<script>
  document.querySelectorAll('.audit-detail-toggle').forEach((button) => {
    button.addEventListener('click', () => {
      const targetId = button.dataset.auditDetail;
      const row = targetId ? document.getElementById(targetId) : null;
      if (!row) return;

      const abrir = row.hasAttribute('hidden');
      if (abrir) {
        row.removeAttribute('hidden');
      } else {
        row.setAttribute('hidden', '');
      }
      button.setAttribute('aria-expanded', abrir ? 'true' : 'false');
      button.textContent = abrir ? '▲ Ocultar detalle' : '▼ Ver detalle';
    });
  });
</script>
