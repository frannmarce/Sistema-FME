<?php
$estadoCaja = obtenerEstadoOperativoCaja($pdo);
$denominacionesCaja = fmeCajaDenominaciones();

$moneyCaja = static fn(float $valor): string => '$' . number_format($valor, 2, ',', '.');

if ($action === 'detail'):
    $idDetalleCaja = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $idDetalleCaja = $idDetalleCaja !== false && $idDetalleCaja !== null ? (int)$idDetalleCaja : 0;
    $cajaDetalle = obtenerCajaPorId($pdo, $idDetalleCaja);

    if (!$cajaDetalle):
?>
<section class="page-heading"><div><p class="eyebrow">Caja</p><h1 class="title">Detalle de cierre</h1></div></section>
<div class="profile-card"><div class="alert error">La caja seleccionada no existe.</div><a class="btn-secondary-link" href="index.php?mod=caja">Volver a Caja</a></div>
<?php
        return;
    endif;

    $stmt = $pdo->prepare('SELECT denominacion, cantidad, subtotal FROM Caja_Denominacion WHERE id_caja = ? ORDER BY denominacion ASC');
    $stmt->execute([$idDetalleCaja]);
    $detalleBilletes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $movimientosDetalle = obtenerMovimientosCaja($pdo, $idDetalleCaja);
    $totalesMovimientosDetalle = obtenerTotalesMovimientosCaja($pdo, $idDetalleCaja);
    $efectivoDisponiblePostCierre = max(
        0,
        round((float)$cajaDetalle['efectivo_contado'] - (float)$totalesMovimientosDetalle['retiros_post_cierre'], 2)
    );
?>
<section class="page-heading">
  <div>
    <p class="eyebrow">Caja</p>
    <h1 class="title">Detalle de caja #<?= (int)$cajaDetalle['id_caja'] ?> · Turno <?= (int)($cajaDetalle['numero_turno'] ?? 1) ?></h1>
    <p class="subtitle">Actividad correspondiente al <?= htmlspecialchars(date('d/m/Y', strtotime((string)$cajaDetalle['fecha_caja']))) ?>.</p>
  </div>
</section>

<div class="report-kpis report-summary-kpis">
  <article class="report-kpi"><span>Fondo inicial</span><strong><?= $moneyCaja((float)$cajaDetalle['monto_apertura']) ?></strong><small>efectivo declarado en la apertura</small></article>
  <article class="report-kpi"><span>Saldo teórico</span><strong><?= $moneyCaja((float)$cajaDetalle['efectivo_esperado']) ?></strong><small>importe que debía existir al cerrar</small></article>
  <article class="report-kpi"><span>Efectivo contado</span><strong><?= $moneyCaja((float)$cajaDetalle['efectivo_contado']) ?></strong><small>arqueo por denominaciones</small></article>
  <article class="report-kpi <?= (float)$cajaDetalle['diferencia'] < 0 ? 'kpi-danger' : '' ?>"><span>Diferencia</span><strong><?= $moneyCaja((float)$cajaDetalle['diferencia']) ?></strong><small>contado menos saldo teórico</small></article>
</div>

<div class="profile-card wide-card">
  <div class="profile-card-header">
    <div class="profile-icon">🧮</div>
    <div><h2>Conteo de billetes</h2><p>Detalle exacto registrado en el arqueo de cierre.</p></div>
  </div>
  <div class="table-responsive">
    <table class="result-table">
      <thead><tr><th>Denominación</th><th>Cantidad</th><th>Subtotal</th></tr></thead>
      <tbody>
        <?php foreach ($detalleBilletes as $item): ?>
          <tr>
            <td><?= $moneyCaja((float)$item['denominacion']) ?></td>
            <td><?= (int)$item['cantidad'] ?></td>
            <td><strong><?= $moneyCaja((float)$item['subtotal']) ?></strong></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$detalleBilletes): ?><tr><td colspan="3" class="empty-cell">No hay detalle de denominaciones registrado.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="profile-section" style="margin-top:14px">
    <h3>Responsables y horarios</h3>
    <div class="profile-grid">
      <div class="form-group"><label class="label">Turno</label><div>Turno <?= (int)($cajaDetalle['numero_turno'] ?? 1) ?></div></div>
      <div class="form-group"><label class="label">Apertura</label><div><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$cajaDetalle['fecha_apertura']))) ?> · <?= htmlspecialchars((string)$cajaDetalle['usuario_apertura']) ?></div></div>
      <div class="form-group"><label class="label">Fondo inicial</label><div><?= $moneyCaja((float)$cajaDetalle['monto_apertura']) ?></div></div>
      <div class="form-group"><label class="label">Cierre</label><div><?= $cajaDetalle['fecha_cierre'] ? htmlspecialchars(date('d/m/Y H:i', strtotime((string)$cajaDetalle['fecha_cierre']))) : '-' ?> · <?= htmlspecialchars((string)($cajaDetalle['usuario_cierre'] ?: '-')) ?></div></div>
      <div class="form-group"><label class="label">Estado</label><div><?= htmlspecialchars((string)$cajaDetalle['estado_caja']) ?></div></div>
      <div class="form-group full-row"><label class="label">Observación de apertura</label><div><?= htmlspecialchars((string)($cajaDetalle['observacion_apertura'] ?: 'Sin observaciones.')) ?></div></div>
      <div class="form-group full-row"><label class="label">Observación del cierre</label><div><?= htmlspecialchars((string)($cajaDetalle['observacion_cierre'] ?: 'Sin observaciones.')) ?></div></div>
    </div>
  </div>
</div>

<div class="profile-card wide-card">
  <div class="profile-card-header">
    <div class="profile-icon">↔️</div>
    <div><h2>Movimientos manuales de efectivo</h2><p>Salidas registradas durante el turno y retiros efectuados después del cierre.</p></div>
  </div>
  <div class="table-responsive">
    <table class="result-table">
      <thead><tr><th>Fecha y hora</th><th>Tipo</th><th>Motivo</th><th>Usuario</th><th>Monto</th><th>Descripción</th></tr></thead>
      <tbody>
        <?php foreach ($movimientosDetalle as $mov): ?>
          <tr>
            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$mov['fecha_movimiento']))) ?></td>
            <td><?= $mov['tipo'] === 'RETIRO_CIERRE' ? 'Retiro posterior al cierre' : 'Salida de efectivo' ?></td>
            <td><?= htmlspecialchars((string)$mov['motivo']) ?></td>
            <td><?= htmlspecialchars((string)$mov['nombre_usuario']) ?></td>
            <td class="negative-amount"><strong>-<?= $moneyCaja((float)$mov['monto']) ?></strong></td>
            <td><?= htmlspecialchars((string)($mov['descripcion'] ?: '-')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$movimientosDetalle): ?><tr><td colspan="6" class="empty-cell">No hay movimientos manuales registrados en esta caja.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (strtoupper((string)$cajaDetalle['estado_caja']) === 'CERRADA'): ?>
<div class="profile-card wide-card">
  <div class="profile-card-header">
    <div class="profile-icon">🏦</div>
    <div>
      <h2>Retirar efectivo después del cierre</h2>
      <p>Este retiro no modifica el saldo teórico, el efectivo contado ni la diferencia guardados en el cierre.</p>
    </div>
  </div>
  <div class="sale-total-box" style="margin-bottom:14px">
    <span>Efectivo disponible para retirar</span>
    <strong><?= $moneyCaja($efectivoDisponiblePostCierre) ?></strong>
  </div>
  <?php if ($efectivoDisponiblePostCierre > 0): ?>
    <form method="post" action="index.php?mod=caja" class="profile-form" data-confirm="¿Registrar este retiro posterior al cierre?">
      <input type="hidden" name="form" value="retiro_cierre">
      <input type="hidden" name="id_caja" value="<?= (int)$cajaDetalle['id_caja'] ?>">
      <input type="hidden" name="volver_detalle" value="1">
      <div class="profile-grid">
        <div class="form-group">
          <label class="label" for="monto_retiro_detalle">Monto a retirar</label>
          <input class="input" id="monto_retiro_detalle" name="monto" type="number" min="0.01" max="<?= htmlspecialchars(number_format($efectivoDisponiblePostCierre, 2, '.', '')) ?>" step="0.01" required>
        </div>
        <div class="form-group">
          <label class="label" for="motivo_retiro_detalle">Motivo</label>
          <select class="input" id="motivo_retiro_detalle" name="motivo" required>
            <option value="">Seleccionar...</option>
            <option value="Resguardo de efectivo">Resguardo de efectivo</option>
            <option value="Deposito bancario">Depósito bancario</option>
            <option value="Pago posterior">Pago posterior</option>
            <option value="Otro">Otro</option>
          </select>
        </div>
        <div class="form-group full-row">
          <label class="label" for="descripcion_retiro_detalle">Descripción (opcional)</label>
          <textarea class="input" id="descripcion_retiro_detalle" name="descripcion" maxlength="255" placeholder="Ej.: efectivo retirado para depósito"></textarea>
        </div>
      </div>
      <div class="module-actions"><button class="btn" type="submit">Registrar retiro</button></div>
    </form>
  <?php else: ?>
    <div class="alert success">No queda efectivo contado disponible para nuevos retiros posteriores al cierre.</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="module-actions"><a class="btn-secondary-link" href="index.php?mod=caja">Volver a Caja</a></div>
<?php
    return;
endif;

$cajaGestion = $estadoCaja['caja'] ?? null;
$ultimaCajaHoy = $estadoCaja['ultima_caja'] ?? null;
$turnoSiguiente = (int)($estadoCaja['turno_siguiente'] ?? 1);
$puedeAbrirTurno = in_array($estadoCaja['codigo'], ['PENDIENTE_APERTURA', 'PENDIENTE_NUEVO_TURNO'], true);
$resumenActual = null;
$movimientosActuales = [];
$totalesMovimientosActuales = ['salidas_jornada' => 0.0, 'retiros_post_cierre' => 0.0];
if ($cajaGestion) {
    $movimientosActuales = obtenerMovimientosCaja($pdo, (int)$cajaGestion['id_caja']);
    $totalesMovimientosActuales = obtenerTotalesMovimientosCaja($pdo, (int)$cajaGestion['id_caja']);
}
if ($cajaGestion && strtoupper((string)$cajaGestion['estado_caja']) === 'ABIERTA') {
    $resumenActual = calcularResumenCaja($pdo, (int)$cajaGestion['id_caja'], (string)$cajaGestion['fecha_caja']);
}

$porPagina = 15;
$paginaActual = max(1, (int)($_GET['pagina'] ?? 1));
$totalCajas = (int)$pdo->query('SELECT COUNT(*) FROM Caja')->fetchColumn();
$totalPaginas = max(1, (int)ceil($totalCajas / $porPagina));
if ($paginaActual > $totalPaginas) $paginaActual = $totalPaginas;
$offset = ($paginaActual - 1) * $porPagina;

$stmt = $pdo->query(
    "SELECT c.id_caja, c.fecha_caja, c.numero_turno, c.estado_caja, c.fecha_apertura, c.fecha_cierre,
            c.monto_apertura, c.efectivo_esperado, c.efectivo_contado, c.diferencia,
            ua.nombre_usuario AS usuario_apertura,
            uc.nombre_usuario AS usuario_cierre
     FROM Caja c
     INNER JOIN Usuario ua ON ua.id_usuario = c.id_usuario_apertura
     LEFT JOIN Usuario uc ON uc.id_usuario = c.id_usuario_cierre
     ORDER BY c.fecha_caja DESC, c.numero_turno DESC, c.id_caja DESC
     LIMIT {$porPagina} OFFSET {$offset}"
);
$historialCajas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$paginaCajaUrl = static function (int $pagina): string {
    return 'index.php?' . http_build_query(['mod' => 'caja', 'pagina' => $pagina]);
};
?>
<section class="page-heading">
  <div>
    <p class="eyebrow">Operaciones</p>
    <h1 class="title">Caja</h1>
    <p class="subtitle">Apertura por turnos, movimientos de efectivo, arqueo y cierre de actividad.</p>
  </div>
</section>

<?php if ($estadoCaja['codigo'] === 'PENDIENTE_CIERRE_ANTERIOR' && $cajaGestion): ?>
  <div class="module-warning">
    <strong>Cierre pendiente:</strong> el turno <?= (int)($cajaGestion['numero_turno'] ?? 1) ?> del <?= htmlspecialchars(date('d/m/Y', strtotime((string)$cajaGestion['fecha_caja']))) ?> continúa abierto. Debes cerrarlo antes de iniciar otro turno o utilizar Facturación / Ventas.
  </div>
<?php elseif ($estadoCaja['codigo'] === 'PENDIENTE_APERTURA'): ?>
  <div class="alert error">Todavía no se abrió el primer turno de caja de hoy. Facturación / Ventas permanecerá bloqueado hasta realizar la apertura.</div>
<?php elseif ($estadoCaja['codigo'] === 'PENDIENTE_NUEVO_TURNO'): ?>
  <div class="alert success">El turno <?= (int)($ultimaCajaHoy['numero_turno'] ?? 1) ?> ya fue cerrado. Puedes abrir el turno <?= $turnoSiguiente ?> para continuar operando hoy.</div>
<?php elseif ($estadoCaja['codigo'] === 'NO_CONFIGURADA'): ?>
  <div class="module-warning">El módulo Caja todavía no está configurado. Ejecuta las migraciones SQL incluidas para Caja.</div>
<?php endif; ?>

<?php if ($puedeAbrirTurno): ?>
<div class="profile-card wide-card">
  <div class="profile-card-header">
    <div class="profile-icon">🔓</div>
    <div>
      <h2><?= $turnoSiguiente === 1 ? 'Abrir primer turno' : 'Abrir nuevo turno' ?> · Turno <?= $turnoSiguiente ?> · <?= htmlspecialchars(date('d/m/Y')) ?></h2>
      <p>Cuenta el efectivo físico disponible al comenzar este turno e indica el fondo inicial.</p>
    </div>
  </div>
  <form method="post" action="index.php?mod=caja" class="profile-form" data-confirm="¿Abrir el turno <?= $turnoSiguiente ?> y habilitar Facturación / Ventas?">
    <input type="hidden" name="form" value="abrir_caja">
    <div class="profile-grid">
      <div class="form-group">
        <label class="label" for="monto_apertura">Fondo inicial</label>
        <input class="input" id="monto_apertura" name="monto_apertura" type="number" min="0" step="0.01" placeholder="Ej.: 100000" required>
      </div>
      <div class="form-group">
        <label class="label" for="observacion_apertura">Observación (opcional)</label>
        <input class="input" id="observacion_apertura" name="observacion_apertura" maxlength="255" placeholder="Ej.: fondo para cambio">
      </div>
    </div>
    <div class="module-actions left-actions"><button class="btn" type="submit"><?= $turnoSiguiente === 1 ? 'Abrir caja' : 'Abrir nuevo turno' ?></button></div>
  </form>
</div>
<?php endif; ?>

<?php if ($cajaGestion && strtoupper((string)$cajaGestion['estado_caja']) === 'ABIERTA' && $resumenActual): ?>
<div class="report-kpis report-summary-kpis">
  <article class="report-kpi"><span>Fondo inicial</span><strong><?= $moneyCaja((float)$resumenActual['monto_apertura']) ?></strong><small>declarado en la apertura</small></article>
  <article class="report-kpi"><span>Ventas en efectivo</span><strong><?= $moneyCaja((float)$resumenActual['ventas_efectivo']) ?></strong><small>ingresos del turno</small></article>
  <article class="report-kpi kpi-danger"><span>Reintegros + salidas</span><strong><?= $moneyCaja((float)$resumenActual['reintegros_efectivo'] + (float)$resumenActual['salidas_jornada']) ?></strong><small>efectivo que salió de la caja</small></article>
  <article class="report-kpi"><span>Saldo teórico</span><strong><?= $moneyCaja((float)$resumenActual['saldo_teorico']) ?></strong><small>efectivo que debería existir ahora</small></article>
</div>

<?php if ($estadoCaja['codigo'] === 'ABIERTA_HOY'): ?>
<div class="profile-card wide-card">
  <div class="profile-card-header">
    <div class="profile-icon">↘️</div>
    <div>
      <h2>Salida de efectivo</h2>
      <p>Registra pagos o extracciones realizados durante el turno. La salida reduce el saldo teórico de la caja.</p>
    </div>
  </div>
  <form method="post" action="index.php?mod=caja" class="profile-form" data-confirm="¿Registrar esta salida de efectivo?">
    <input type="hidden" name="form" value="salida_efectivo">
    <input type="hidden" name="id_caja" value="<?= (int)$cajaGestion['id_caja'] ?>">
    <div class="profile-grid">
      <div class="form-group">
        <label class="label" for="monto_salida">Monto</label>
        <input class="input" id="monto_salida" name="monto" type="number" min="0.01" max="<?= htmlspecialchars(number_format(max(0, (float)$resumenActual['saldo_teorico']), 2, '.', '')) ?>" step="0.01" required>
      </div>
      <div class="form-group">
        <label class="label" for="motivo_salida">Motivo</label>
        <select class="input" id="motivo_salida" name="motivo" required>
          <option value="">Seleccionar...</option>
          <option value="Pago a empleado">Pago a empleado</option>
          <option value="Pago a proveedor">Pago a proveedor</option>
          <option value="Gasto operativo">Gasto operativo</option>
          <option value="Retiro parcial">Retiro parcial</option>
          <option value="Otro">Otro</option>
        </select>
      </div>
      <div class="form-group full-row">
        <label class="label" for="descripcion_salida">Descripción (opcional)</label>
        <textarea class="input" id="descripcion_salida" name="descripcion" maxlength="255" placeholder="Ej.: pago correspondiente al turno"></textarea>
      </div>
    </div>
    <div class="module-actions"><button class="btn" type="submit" <?= (float)$resumenActual['saldo_teorico'] <= 0 ? 'disabled' : '' ?>>Registrar salida</button></div>
  </form>
</div>
<?php endif; ?>

<div class="profile-card wide-card">
  <div class="profile-card-header">
    <div class="profile-icon">📋</div>
    <div><h2>Movimientos de efectivo del turno</h2><p>Salidas registradas durante este turno antes del cierre.</p></div>
  </div>
  <div class="table-responsive">
    <table class="result-table">
      <thead><tr><th>Hora</th><th>Motivo</th><th>Usuario</th><th>Monto</th><th>Descripción</th></tr></thead>
      <tbody>
        <?php $haySalidas = false; ?>
        <?php foreach ($movimientosActuales as $mov): ?>
          <?php if ($mov['tipo'] !== 'SALIDA') continue; $haySalidas = true; ?>
          <tr>
            <td><?= htmlspecialchars(date('H:i', strtotime((string)$mov['fecha_movimiento']))) ?></td>
            <td><?= htmlspecialchars((string)$mov['motivo']) ?></td>
            <td><?= htmlspecialchars((string)$mov['nombre_usuario']) ?></td>
            <td class="negative-amount"><strong>-<?= $moneyCaja((float)$mov['monto']) ?></strong></td>
            <td><?= htmlspecialchars((string)($mov['descripcion'] ?: '-')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$haySalidas): ?><tr><td colspan="5" class="empty-cell">No hay salidas de efectivo registradas durante este turno.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="profile-card wide-card">
  <div class="profile-card-header">
    <div class="profile-icon">💵</div>
    <div>
      <h2>Cerrar Turno <?= (int)($cajaGestion['numero_turno'] ?? 1) ?> · <?= htmlspecialchars(date('d/m/Y', strtotime((string)$cajaGestion['fecha_caja']))) ?></h2>
      <p>Cuenta los billetes físicos. El total contado se comparará contra el saldo teórico de <?= $moneyCaja((float)$resumenActual['saldo_teorico']) ?>.</p>
    </div>
  </div>

  <form method="post" action="index.php?mod=caja" class="profile-form" id="cierreCajaForm" data-saldo-teorico="<?= htmlspecialchars(number_format((float)$resumenActual['saldo_teorico'], 2, '.', '')) ?>">
    <input type="hidden" name="form" value="cerrar_caja">
    <input type="hidden" name="id_caja" value="<?= (int)$cajaGestion['id_caja'] ?>">

    <div class="table-responsive">
      <table class="result-table" id="tablaDenominacionesCaja">
        <thead><tr><th>Billete</th><th>Cantidad</th><th>Subtotal</th></tr></thead>
        <tbody>
          <?php foreach ($denominacionesCaja as $denominacion): ?>
            <tr data-denominacion="<?= (int)$denominacion ?>">
              <td><strong>$<?= number_format((int)$denominacion, 0, ',', '.') ?></strong></td>
              <td><input class="input cantidad-billete" type="number" min="0" step="1" value="0" name="cantidad_billete[<?= (int)$denominacion ?>]" inputmode="numeric"></td>
              <td class="subtotal-billete">$0,00</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="report-kpis report-summary-kpis" style="margin-top:14px">
      <article class="report-kpi"><span>Saldo teórico</span><strong><?= $moneyCaja((float)$resumenActual['saldo_teorico']) ?></strong><small>según apertura y movimientos</small></article>
      <article class="report-kpi"><span>Efectivo contado</span><strong id="totalContadoCaja">$0,00</strong><small>suma de billetes ingresados</small></article>
      <article class="report-kpi" id="kpiDiferenciaCaja"><span>Diferencia</span><strong id="diferenciaCaja">$0,00</strong><small id="estadoDiferenciaCaja">pendiente de conteo</small></article>
    </div>

    <div class="profile-section" style="margin-top:14px">
      <div class="form-group">
        <label class="label" for="observacion_cierre">Observación del cierre</label>
        <textarea class="input" id="observacion_cierre" name="observacion_cierre" maxlength="255" placeholder="Obligatoria si existe faltante o sobrante"></textarea>
        <small>Si la diferencia no es $0, el sistema exigirá una explicación antes de cerrar.</small>
      </div>
    </div>

    <div class="module-actions">
      <button class="btn-danger-solid" type="submit">Cerrar caja y guardar arqueo</button>
    </div>
  </form>
</div>

<div class="caja-modal" id="modalResumenCierre" hidden aria-hidden="true">
  <div class="caja-modal-backdrop" data-cerrar-modal></div>
  <section class="caja-modal-panel" role="dialog" aria-modal="true" aria-labelledby="tituloResumenCierre">
    <div class="caja-modal-header">
      <div>
        <p class="eyebrow">Confirmación final</p>
        <h2 id="tituloResumenCierre">Detalle de cierre de caja</h2>
        <p>Revisa todo el turno y el arqueo antes de guardar el cierre definitivamente.</p>
      </div>
      <button type="button" class="caja-modal-close" data-cerrar-modal aria-label="Cerrar">×</button>
    </div>

    <div class="caja-modal-body">
      <div class="profile-grid caja-modal-datos">
        <div class="form-group"><label class="label">Fecha de caja</label><div><?= htmlspecialchars(date('d/m/Y', strtotime((string)$cajaGestion['fecha_caja']))) ?></div></div>
        <div class="form-group"><label class="label">Turno</label><div>Turno <?= (int)($cajaGestion['numero_turno'] ?? 1) ?></div></div>
        <div class="form-group"><label class="label">Usuario de apertura</label><div><?= htmlspecialchars((string)($cajaGestion['usuario_apertura'] ?? '-')) ?></div></div>
        <div class="form-group"><label class="label">Hora de apertura</label><div><?= htmlspecialchars(date('H:i', strtotime((string)$cajaGestion['fecha_apertura']))) ?></div></div>
        <div class="form-group"><label class="label">Ventas realizadas</label><div><?= (int)$resumenActual['cantidad_ventas'] ?></div></div>
      </div>

      <div class="report-kpis report-summary-kpis caja-modal-kpis">
        <article class="report-kpi"><span>Fondo inicial</span><strong><?= $moneyCaja((float)$resumenActual['monto_apertura']) ?></strong><small>efectivo al comenzar</small></article>
        <article class="report-kpi"><span>Total vendido</span><strong><?= $moneyCaja((float)$resumenActual['total_ventas']) ?></strong><small>facturación registrada</small></article>
        <article class="report-kpi kpi-danger"><span>Reintegros en efectivo</span><strong><?= $moneyCaja((float)$resumenActual['reintegros_efectivo']) ?></strong><small><?= (int)$resumenActual['cantidad_reintegros'] ?> reintegro(s)</small></article>
        <article class="report-kpi kpi-danger"><span>Salidas de efectivo</span><strong><?= $moneyCaja((float)$resumenActual['salidas_jornada']) ?></strong><small>extracciones del turno</small></article>
        <article class="report-kpi"><span>Saldo teórico</span><strong><?= $moneyCaja((float)$resumenActual['saldo_teorico']) ?></strong><small>efectivo esperado</small></article>
        <article class="report-kpi"><span>Efectivo contado</span><strong id="modalTotalContado">$0,00</strong><small>según billetes ingresados</small></article>
        <article class="report-kpi" id="modalKpiDiferencia"><span>Diferencia</span><strong id="modalDiferencia">$0,00</strong><small id="modalEstadoDiferencia">pendiente</small></article>
      </div>

      <div class="caja-modal-section">
        <h3>Medios de pago</h3>
        <div class="table-responsive">
          <table class="result-table">
            <thead><tr><th>Medio de pago</th><th>Total cobrado</th></tr></thead>
            <tbody>
              <?php if (!empty($resumenActual['pagos_por_medio'])): ?>
                <?php foreach ($resumenActual['pagos_por_medio'] as $nombreMedio => $totalMedio): ?>
                  <tr><td><?= htmlspecialchars((string)$nombreMedio) ?></td><td><strong><?= $moneyCaja((float)$totalMedio) ?></strong></td></tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="2" class="empty-cell">No se registraron cobros durante este turno.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="caja-modal-section">
        <h3>Conteo de billetes</h3>
        <div class="table-responsive">
          <table class="result-table">
            <thead><tr><th>Billete</th><th>Cantidad</th><th>Subtotal</th></tr></thead>
            <tbody id="modalDetalleBilletes"></tbody>
          </table>
        </div>
      </div>

      <div class="caja-modal-section">
        <h3>Observación de cierre</h3>
        <div class="caja-modal-observacion" id="modalObservacionCierre">Sin observaciones.</div>
      </div>
    </div>

    <div class="caja-modal-actions">
      <button type="button" class="btn-secondary-link" data-cerrar-modal>Volver al arqueo</button>
      <button type="button" class="btn-danger-solid" id="confirmarCierreCaja">Confirmar cierre de caja</button>
    </div>
  </section>
</div>

<style>
.caja-modal[hidden]{display:none}
.caja-modal{position:fixed;inset:0;z-index:2000;display:flex;align-items:center;justify-content:center;padding:24px}
.caja-modal-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.58);backdrop-filter:blur(2px)}
.caja-modal-panel{position:relative;width:min(980px,96vw);max-height:92vh;overflow:auto;background:#fff;border-radius:18px;box-shadow:0 24px 70px rgba(15,23,42,.28);padding:24px}
.caja-modal-header{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;border-bottom:1px solid #e5e7eb;padding-bottom:16px;margin-bottom:18px}
.caja-modal-header h2{margin:2px 0 6px}.caja-modal-header p{margin:0;color:#667085}
.caja-modal-close{border:0;background:#f3f4f6;border-radius:10px;width:38px;height:38px;font-size:26px;line-height:1;cursor:pointer;color:#475467}
.caja-modal-body{display:grid;gap:18px}.caja-modal-datos{padding:14px;background:#f8fafc;border-radius:12px}
.caja-modal-kpis{margin:0}.caja-modal-section h3{margin:0 0 10px}.caja-modal-observacion{padding:13px 14px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;white-space:pre-wrap}
.caja-modal-actions{display:flex;justify-content:flex-end;gap:10px;padding-top:20px;margin-top:20px;border-top:1px solid #e5e7eb}
body.caja-modal-open{overflow:hidden}
@media (max-width:700px){.caja-modal{padding:10px}.caja-modal-panel{padding:16px;max-height:96vh}.caja-modal-actions{flex-direction:column-reverse}.caja-modal-actions button{width:100%}}
</style>

<script>
(() => {
  const form = document.getElementById('cierreCajaForm');
  const table = document.getElementById('tablaDenominacionesCaja');
  const totalEl = document.getElementById('totalContadoCaja');
  const diferenciaEl = document.getElementById('diferenciaCaja');
  const estadoEl = document.getElementById('estadoDiferenciaCaja');
  const observacion = document.getElementById('observacion_cierre');
  const modal = document.getElementById('modalResumenCierre');
  const confirmarBtn = document.getElementById('confirmarCierreCaja');
  const modalTotal = document.getElementById('modalTotalContado');
  const modalDiferencia = document.getElementById('modalDiferencia');
  const modalEstado = document.getElementById('modalEstadoDiferencia');
  const modalBilletes = document.getElementById('modalDetalleBilletes');
  const modalObservacion = document.getElementById('modalObservacionCierre');
  if (!form || !table || !totalEl || !diferenciaEl || !estadoEl || !observacion || !modal || !confirmarBtn || !modalTotal || !modalDiferencia || !modalEstado || !modalBilletes || !modalObservacion) return;

  let cierreConfirmado = false;
  let ultimoTotal = 0;
  let ultimaDiferencia = 0;
  const saldoTeorico = Number(form.dataset.saldoTeorico || 0);
  const money = value => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value || 0);
  const refresh = () => {
    let total = 0;
    table.querySelectorAll('tbody tr[data-denominacion]').forEach(row => {
      const denominacion = Number(row.dataset.denominacion || 0);
      const input = row.querySelector('.cantidad-billete');
      const cantidad = Math.max(0, parseInt(input?.value || '0', 10) || 0);
      const subtotal = denominacion * cantidad;
      total += subtotal;
      row.querySelector('.subtotal-billete').textContent = money(subtotal);
    });

    const diferencia = Math.round((total - saldoTeorico) * 100) / 100;
    ultimoTotal = total;
    ultimaDiferencia = diferencia;
    totalEl.textContent = money(total);
    diferenciaEl.textContent = money(diferencia);

    if (Math.abs(diferencia) < 0.01) {
      estadoEl.textContent = 'caja correcta';
      observacion.required = false;
    } else if (diferencia < 0) {
      estadoEl.textContent = 'faltante';
      observacion.required = true;
    } else {
      estadoEl.textContent = 'sobrante';
      observacion.required = true;
    }
  };

  const cerrarModal = () => {
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('caja-modal-open');
  };

  const abrirModal = () => {
    refresh();
    modalTotal.textContent = money(ultimoTotal);
    modalDiferencia.textContent = money(ultimaDiferencia);
    modalEstado.textContent = Math.abs(ultimaDiferencia) < 0.01 ? 'caja correcta' : (ultimaDiferencia < 0 ? 'faltante' : 'sobrante');
    modalObservacion.textContent = observacion.value.trim() || 'Sin observaciones.';

    modalBilletes.innerHTML = '';
    let hayBilletes = false;
    table.querySelectorAll('tbody tr[data-denominacion]').forEach(row => {
      const denominacion = Number(row.dataset.denominacion || 0);
      const input = row.querySelector('.cantidad-billete');
      const cantidad = Math.max(0, parseInt(input?.value || '0', 10) || 0);
      if (cantidad <= 0) return;
      hayBilletes = true;
      const tr = document.createElement('tr');
      const subtotal = denominacion * cantidad;
      tr.innerHTML = `<td><strong>${money(denominacion)}</strong></td><td>${cantidad}</td><td><strong>${money(subtotal)}</strong></td>`;
      modalBilletes.appendChild(tr);
    });
    if (!hayBilletes) {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td colspan="3" class="empty-cell">No se ingresaron billetes en el arqueo.</td>';
      modalBilletes.appendChild(tr);
    }

    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('caja-modal-open');
    confirmarBtn.focus();
  };

  form.addEventListener('submit', event => {
    if (cierreConfirmado) return;
    event.preventDefault();
    refresh();
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    abrirModal();
  });

  confirmarBtn.addEventListener('click', () => {
    cierreConfirmado = true;
    cerrarModal();
    form.requestSubmit();
  });

  modal.querySelectorAll('[data-cerrar-modal]').forEach(element => element.addEventListener('click', cerrarModal));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !modal.hidden) cerrarModal();
  });

  table.querySelectorAll('.cantidad-billete').forEach(input => input.addEventListener('input', refresh));
  refresh();
})();
</script>
<?php endif; ?>

<?php if ($cajaGestion && strtoupper((string)$cajaGestion['estado_caja']) === 'CERRADA'): ?>
<?php
  $disponibleRetiroHoy = max(
      0,
      round((float)$cajaGestion['efectivo_contado'] - (float)$totalesMovimientosActuales['retiros_post_cierre'], 2)
  );
?>
<div class="profile-card wide-card">
  <div class="profile-card-header">
    <div class="profile-icon">🏦</div>
    <div>
      <h2>Retirar efectivo de la caja cerrada</h2>
      <p>El retiro se registra aparte y no modifica el arqueo ya guardado.</p>
    </div>
  </div>
  <div class="report-kpis report-summary-kpis">
    <article class="report-kpi"><span>Contado al cierre</span><strong><?= $moneyCaja((float)$cajaGestion['efectivo_contado']) ?></strong><small>valor histórico inalterable</small></article>
    <article class="report-kpi"><span>Retiros posteriores</span><strong><?= $moneyCaja((float)$totalesMovimientosActuales['retiros_post_cierre']) ?></strong><small>registrados después del cierre</small></article>
    <article class="report-kpi"><span>Disponible para retirar</span><strong><?= $moneyCaja($disponibleRetiroHoy) ?></strong><small>efectivo restante según el sistema</small></article>
  </div>

  <?php if ($disponibleRetiroHoy > 0): ?>
  <form method="post" action="index.php?mod=caja" class="profile-form" data-confirm="¿Registrar este retiro posterior al cierre?">
    <input type="hidden" name="form" value="retiro_cierre">
    <input type="hidden" name="id_caja" value="<?= (int)$cajaGestion['id_caja'] ?>">
    <div class="profile-grid">
      <div class="form-group">
        <label class="label" for="monto_retiro">Monto a retirar</label>
        <input class="input" id="monto_retiro" name="monto" type="number" min="0.01" max="<?= htmlspecialchars(number_format($disponibleRetiroHoy, 2, '.', '')) ?>" step="0.01" required>
      </div>
      <div class="form-group">
        <label class="label" for="motivo_retiro">Motivo</label>
        <select class="input" id="motivo_retiro" name="motivo" required>
          <option value="">Seleccionar...</option>
          <option value="Resguardo de efectivo">Resguardo de efectivo</option>
          <option value="Deposito bancario">Depósito bancario</option>
          <option value="Pago posterior">Pago posterior</option>
          <option value="Otro">Otro</option>
        </select>
      </div>
      <div class="form-group full-row">
        <label class="label" for="descripcion_retiro">Descripción (opcional)</label>
        <textarea class="input" id="descripcion_retiro" name="descripcion" maxlength="255" placeholder="Ej.: retiro para resguardo de efectivo"></textarea>
      </div>
    </div>
    <div class="module-actions"><button class="btn" type="submit">Registrar retiro</button></div>
  </form>
  <?php else: ?>
    <div class="alert success">No queda efectivo contado disponible para nuevos retiros.</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="profile-card wide-card">
  <div class="profile-card-header">
    <div class="profile-icon">📚</div>
    <div><h2>Historial de cajas por turno</h2><p>Aperturas, turnos, saldos teóricos, conteos y diferencias registradas.</p></div>
    <div class="profile-actions">
      <a class="btn-icon" href="actions/export_cajas.php" title="Exportar historial a Excel" aria-label="Exportar historial a Excel">📊</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="result-table">
      <thead><tr><th>Fecha</th><th>Turno</th><th>Estado</th><th>Fondo inicial</th><th>Apertura</th><th>Cierre</th><th>Saldo teórico</th><th>Contado</th><th>Diferencia</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php foreach ($historialCajas as $fila): ?>
          <tr>
            <td><?= htmlspecialchars(date('d/m/Y', strtotime((string)$fila['fecha_caja']))) ?></td>
            <td><strong>Turno <?= (int)($fila['numero_turno'] ?? 1) ?></strong></td>
            <td><span class="status-badge <?= strtoupper((string)$fila['estado_caja']) === 'ABIERTA' ? 'status-used' : 'status-active' ?>"><?= htmlspecialchars((string)$fila['estado_caja']) ?></span></td>
            <td><?= $moneyCaja((float)$fila['monto_apertura']) ?></td>
            <td><?= htmlspecialchars((string)$fila['usuario_apertura']) ?><br><small><?= htmlspecialchars(date('H:i', strtotime((string)$fila['fecha_apertura']))) ?></small></td>
            <td><?= $fila['fecha_cierre'] ? htmlspecialchars((string)$fila['usuario_cierre']) . '<br><small>' . htmlspecialchars(date('H:i', strtotime((string)$fila['fecha_cierre']))) . '</small>' : '-' ?></td>
            <td><?= $moneyCaja((float)$fila['efectivo_esperado']) ?></td>
            <td><?= $moneyCaja((float)$fila['efectivo_contado']) ?></td>
            <td class="<?= (float)$fila['diferencia'] < 0 ? 'negative-amount' : '' ?>"><strong><?= $moneyCaja((float)$fila['diferencia']) ?></strong></td>
            <td class="actions-cell"><a class="btn-small" href="index.php?mod=caja&action=detail&id=<?= (int)$fila['id_caja'] ?>">Ver detalle</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$historialCajas): ?><tr><td colspan="10" class="empty-cell">Todavía no hay cajas registradas.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalCajas > 0): ?>
    <div class="pagination-bar">
      <div class="pagination-summary"><?= $totalCajas ?> turnos registrados · Página <?= $paginaActual ?> de <?= $totalPaginas ?></div>
      <nav class="pagination" aria-label="Paginación de cajas">
        <a class="page-link <?= $paginaActual <= 1 ? 'disabled' : '' ?>" href="<?= $paginaActual > 1 ? htmlspecialchars($paginaCajaUrl($paginaActual - 1)) : '#' ?>">‹ Anterior</a>
        <?php
          $inicio = max(1, $paginaActual - 2);
          $fin = min($totalPaginas, $paginaActual + 2);
          for ($p = $inicio; $p <= $fin; $p++):
        ?>
          <a class="page-link <?= $p === $paginaActual ? 'active' : '' ?>" href="<?= htmlspecialchars($paginaCajaUrl($p)) ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a class="page-link <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>" href="<?= $paginaActual < $totalPaginas ? htmlspecialchars($paginaCajaUrl($paginaActual + 1)) : '#' ?>">Siguiente ›</a>
      </nav>
    </div>
  <?php endif; ?>
</div>
