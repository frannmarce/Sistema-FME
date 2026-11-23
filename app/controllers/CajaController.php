<?php

/**
 * Denominaciones de billetes utilizadas en el arqueo de caja.
 */
function fmeCajaDenominaciones(): array
{
    return [100, 200, 500, 1000, 2000, 10000, 20000];
}

function fmeCajaParseMonto(string $raw): ?float
{
    $normalizado = str_replace(',', '.', trim($raw));
    if ($normalizado === '' || !is_numeric($normalizado)) {
        return null;
    }

    $monto = round((float)$normalizado, 2);
    if ($monto < 0 || $monto > 999999999999.99) {
        return null;
    }

    return $monto;
}

function obtenerCajaPorId(PDO $pdo, int $idCaja): ?array
{
    if ($idCaja <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT c.*,
                ua.nombre_usuario AS usuario_apertura,
                uc.nombre_usuario AS usuario_cierre
         FROM Caja c
         INNER JOIN Usuario ua ON ua.id_usuario = c.id_usuario_apertura
         LEFT JOIN Usuario uc ON uc.id_usuario = c.id_usuario_cierre
         WHERE c.id_caja = ?
         LIMIT 1"
    );
    $stmt->execute([$idCaja]);
    $caja = $stmt->fetch(PDO::FETCH_ASSOC);

    return $caja ?: null;
}

function obtenerMovimientosCaja(PDO $pdo, int $idCaja): array
{
    $stmt = $pdo->prepare(
        "SELECT cm.*, u.nombre_usuario
         FROM Caja_Movimiento cm
         INNER JOIN Usuario u ON u.id_usuario = cm.id_usuario
         WHERE cm.id_caja = ?
         ORDER BY cm.fecha_movimiento DESC, cm.id_movimiento DESC"
    );
    $stmt->execute([$idCaja]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerTotalesMovimientosCaja(PDO $pdo, int $idCaja): array
{
    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN tipo = 'SALIDA' THEN monto ELSE 0 END), 0) AS salidas_jornada,
            COALESCE(SUM(CASE WHEN tipo = 'RETIRO_CIERRE' THEN monto ELSE 0 END), 0) AS retiros_post_cierre
         FROM Caja_Movimiento
         WHERE id_caja = ?"
    );
    $stmt->execute([$idCaja]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'salidas_jornada' => round((float)($fila['salidas_jornada'] ?? 0), 2),
        'retiros_post_cierre' => round((float)($fila['retiros_post_cierre'] ?? 0), 2),
    ];
}

/**
 * Devuelve el estado operativo de caja que usa también Facturación/Ventas.
 */
function obtenerEstadoOperativoCaja(PDO $pdo): array
{
    try {
        // Primero se bloquea cualquier operación nueva si quedó un turno abierto de un día anterior.
        $stmt = $pdo->query(
            "SELECT c.*,
                    ua.nombre_usuario AS usuario_apertura,
                    uc.nombre_usuario AS usuario_cierre
             FROM Caja c
             INNER JOIN Usuario ua ON ua.id_usuario = c.id_usuario_apertura
             LEFT JOIN Usuario uc ON uc.id_usuario = c.id_usuario_cierre
             WHERE c.estado_caja = 'ABIERTA'
               AND c.fecha_caja < CURDATE()
             ORDER BY c.fecha_caja ASC, c.numero_turno ASC
             LIMIT 1"
        );
        $anterior = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($anterior) {
            return [
                'codigo' => 'PENDIENTE_CIERRE_ANTERIOR',
                'habilita_ventas' => false,
                'caja' => $anterior,
                'ultima_caja' => $anterior,
                'turno_siguiente' => null,
                'mensaje' => 'Existe un turno de caja de un día anterior que todavía está abierto.',
            ];
        }

        // Si hoy ya existe un turno abierto, ese es el único turno operativo.
        $stmt = $pdo->query(
            "SELECT c.*,
                    ua.nombre_usuario AS usuario_apertura,
                    uc.nombre_usuario AS usuario_cierre
             FROM Caja c
             INNER JOIN Usuario ua ON ua.id_usuario = c.id_usuario_apertura
             LEFT JOIN Usuario uc ON uc.id_usuario = c.id_usuario_cierre
             WHERE c.fecha_caja = CURDATE()
               AND c.estado_caja = 'ABIERTA'
             ORDER BY c.numero_turno DESC, c.id_caja DESC
             LIMIT 1"
        );
        $abierta = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($abierta) {
            return [
                'codigo' => 'ABIERTA_HOY',
                'habilita_ventas' => true,
                'caja' => $abierta,
                'ultima_caja' => $abierta,
                'turno_siguiente' => null,
                'mensaje' => 'El turno ' . (int)$abierta['numero_turno'] . ' de caja está abierto.',
            ];
        }

        // No hay turno abierto: buscamos el último turno de hoy para decidir si corresponde
        // abrir el primero o iniciar un turno nuevo.
        $stmt = $pdo->query(
            "SELECT c.*,
                    ua.nombre_usuario AS usuario_apertura,
                    uc.nombre_usuario AS usuario_cierre
             FROM Caja c
             INNER JOIN Usuario ua ON ua.id_usuario = c.id_usuario_apertura
             LEFT JOIN Usuario uc ON uc.id_usuario = c.id_usuario_cierre
             WHERE c.fecha_caja = CURDATE()
             ORDER BY c.numero_turno DESC, c.id_caja DESC
             LIMIT 1"
        );
        $ultima = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ultima) {
            return [
                'codigo' => 'PENDIENTE_APERTURA',
                'habilita_ventas' => false,
                'caja' => null,
                'ultima_caja' => null,
                'turno_siguiente' => 1,
                'mensaje' => 'Todavía no se abrió el primer turno de caja de hoy.',
            ];
        }

        $turnoSiguiente = max(1, (int)($ultima['numero_turno'] ?? 1) + 1);
        return [
            'codigo' => 'PENDIENTE_NUEVO_TURNO',
            'habilita_ventas' => false,
            'caja' => null,
            'ultima_caja' => $ultima,
            'turno_siguiente' => $turnoSiguiente,
            'mensaje' => 'El turno ' . (int)$ultima['numero_turno'] . ' ya fue cerrado. Puedes abrir el turno ' . $turnoSiguiente . '.',
        ];
    } catch (Throwable $e) {
        error_log('FME - Estado de caja: ' . $e->getMessage());
        return [
            'codigo' => 'NO_CONFIGURADA',
            'habilita_ventas' => false,
            'caja' => null,
            'ultima_caja' => null,
            'turno_siguiente' => null,
            'mensaje' => 'El módulo Caja todavía no está configurado en la base de datos.',
        ];
    }
}

/**
 * Calcula los importes operativos de una caja abierta.
 *
 * Saldo teórico = fondo inicial + ventas en efectivo - reintegros en efectivo - salidas de efectivo.
 */
function calcularResumenCaja(PDO $pdo, int $idCaja, string $fechaCaja): array
{
    $stmt = $pdo->prepare(
        "SELECT monto_apertura
         FROM Caja
         WHERE id_caja = ?
         LIMIT 1"
    );
    $stmt->execute([$idCaja]);
    $montoApertura = round((float)$stmt->fetchColumn(), 2);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS cantidad_ventas,
                COALESCE(SUM(v.total_venta), 0) AS total_ventas
         FROM Venta v
         WHERE v.id_caja = ?"
    );
    $stmt->execute([$idCaja]);
    $ventas = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Desglose de lo cobrado por medio de pago durante la jornada.
    // En pagos mixtos cada parte aparece bajo su medio real en Venta_Pago.
    $stmt = $pdo->prepare(
        "SELECT mp.nombre_medio, COALESCE(SUM(vp.monto), 0) AS total_medio
         FROM Venta_Pago vp
         INNER JOIN Venta v ON v.id_venta = vp.id_venta
         INNER JOIN Medio_pago mp ON mp.id_medio = vp.id_medio
         WHERE v.id_caja = ?
         GROUP BY mp.id_medio, mp.nombre_medio
         ORDER BY mp.nombre_medio ASC"
    );
    $stmt->execute([$idCaja]);
    $pagosPorMedio = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $filaPago) {
        $nombreMedio = trim((string)($filaPago['nombre_medio'] ?? ''));
        if ($nombreMedio === '') {
            continue;
        }
        $pagosPorMedio[$nombreMedio] = round((float)($filaPago['total_medio'] ?? 0), 2);
    }

    // Solo la parte efectivamente cobrada en Efectivo ingresa a la caja física.
    $ventasEfectivo = 0.0;
    foreach ($pagosPorMedio as $nombreMedio => $totalMedio) {
        if (mb_strtolower($nombreMedio, 'UTF-8') === 'efectivo') {
            $ventasEfectivo = round((float)$totalMedio, 2);
            break;
        }
    }

    // Una cancelación es total: el reintegro en efectivo equivale a la parte original
    // pagada en efectivo, pero se descuenta del turno donde efectivamente se hizo el reintegro.
    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT cv.id_cancelacion) AS cantidad_reintegros,
                COALESCE(SUM(vp.monto), 0) AS reintegros_efectivo
         FROM Cancelacion_Venta cv
         INNER JOIN Venta v ON v.id_venta = cv.id_venta
         INNER JOIN Venta_Pago vp ON vp.id_venta = v.id_venta
         INNER JOIN Medio_pago mp ON mp.id_medio = vp.id_medio
         WHERE cv.id_caja_reintegro = ?
           AND LOWER(mp.nombre_medio) = 'efectivo'"
    );
    $stmt->execute([$idCaja]);
    $reintegros = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $movimientos = obtenerTotalesMovimientosCaja($pdo, $idCaja);

    $reintegrosEfectivo = round((float)($reintegros['reintegros_efectivo'] ?? 0), 2);
    $salidasJornada = (float)$movimientos['salidas_jornada'];
    $saldoTeorico = round($montoApertura + $ventasEfectivo - $reintegrosEfectivo - $salidasJornada, 2);

    return [
        'monto_apertura' => $montoApertura,
        'cantidad_ventas' => (int)($ventas['cantidad_ventas'] ?? 0),
        'total_ventas' => round((float)($ventas['total_ventas'] ?? 0), 2),
        'pagos_por_medio' => $pagosPorMedio,
        'ventas_efectivo' => $ventasEfectivo,
        'cantidad_reintegros' => (int)($reintegros['cantidad_reintegros'] ?? 0),
        'reintegros_efectivo' => $reintegrosEfectivo,
        'salidas_jornada' => $salidasJornada,
        'retiros_post_cierre' => (float)$movimientos['retiros_post_cierre'],
        'saldo_teorico' => $saldoTeorico,
        // Se conserva esta clave por compatibilidad con partes del módulo ya existentes.
        'efectivo_esperado' => $saldoTeorico,
    ];
}

/* =========================
   CAJA (POST)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $modActual === 'caja') {
    if (!isset($modulosPermitidosConArchivo['caja'])) {
        flash('error', 'No tienes permiso para operar con Caja.');
        header('Location: index.php');
        exit;
    }

    $form = trim((string)($_POST['form'] ?? ''));

    if ($form === 'abrir_caja') {
        $montoApertura = fmeCajaParseMonto((string)($_POST['monto_apertura'] ?? ''));
        $observacionApertura = trim((string)($_POST['observacion_apertura'] ?? ''));

        if ($montoApertura === null) {
            flash('error', 'Debes indicar un monto inicial válido para abrir la caja.');
            header('Location: index.php?mod=caja');
            exit;
        }
        if (mb_strlen($observacionApertura) > 255) {
            flash('error', 'La observación de apertura no puede superar los 255 caracteres.');
            header('Location: index.php?mod=caja');
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Solo puede existir un turno abierto a la vez, sin importar la fecha.
            $stmt = $pdo->query(
                "SELECT id_caja, fecha_caja, numero_turno
                 FROM Caja
                 WHERE estado_caja = 'ABIERTA'
                 ORDER BY fecha_caja ASC, numero_turno ASC
                 LIMIT 1
                 FOR UPDATE"
            );
            $pendiente = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($pendiente) {
                $fechaPendiente = date('d/m/Y', strtotime((string)$pendiente['fecha_caja']));
                throw new RuntimeException(
                    'Antes de abrir un nuevo turno debes cerrar el turno ' .
                    (int)$pendiente['numero_turno'] . ' del ' . $fechaPendiente . '.'
                );
            }

            // El número de turno se genera secuencialmente por día.
            $stmt = $pdo->query(
                "SELECT id_caja, numero_turno
                 FROM Caja
                 WHERE fecha_caja = CURDATE()
                 ORDER BY numero_turno DESC, id_caja DESC
                 LIMIT 1
                 FOR UPDATE"
            );
            $ultimoTurno = $stmt->fetch(PDO::FETCH_ASSOC);
            $numeroTurno = $ultimoTurno ? ((int)$ultimoTurno['numero_turno'] + 1) : 1;

            $stmt = $pdo->prepare(
                "INSERT INTO Caja
                 (fecha_caja, numero_turno, estado_caja, fecha_apertura, id_usuario_apertura, monto_apertura, observacion_apertura)
                 VALUES (CURDATE(), ?, 'ABIERTA', NOW(), ?, ?, ?)"
            );
            $stmt->execute([
                $numeroTurno,
                $userId,
                $montoApertura,
                $observacionApertura !== '' ? $observacionApertura : null,
            ]);
            $idCaja = (int)$pdo->lastInsertId();

            if ($idCaja <= 0) {
                throw new RuntimeException('No se pudo crear la apertura de caja.');
            }

            $pdo->commit();

            registrarAuditoria($pdo, 'caja', 'ABRIR', 'Caja', $idCaja, [
                'fecha_caja' => date('Y-m-d'),
                'numero_turno' => $numeroTurno,
                'estado' => 'ABIERTA',
                'monto_apertura' => $montoApertura,
            ]);

            flash('success', 'Turno ' . $numeroTurno . ' de caja abierto correctamente con un fondo inicial de $' . number_format($montoApertura, 2, ',', '.') . '. Facturación / Ventas quedó habilitado.');
            header('Location: index.php?mod=caja');
            exit;
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', $e->getMessage());
            header('Location: index.php?mod=caja');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('FME - Apertura de caja: ' . $e->getMessage());
            flash('error', 'No se pudo abrir la caja.');
            header('Location: index.php?mod=caja');
            exit;
        }
    }

    if ($form === 'salida_efectivo') {
        $idCaja = (int)($_POST['id_caja'] ?? 0);
        $monto = fmeCajaParseMonto((string)($_POST['monto'] ?? ''));
        $motivo = trim((string)($_POST['motivo'] ?? ''));
        $descripcion = trim((string)($_POST['descripcion'] ?? ''));

        if ($idCaja <= 0 || $monto === null || $monto <= 0) {
            flash('error', 'Debes indicar una salida de efectivo válida mayor a $0.');
            header('Location: index.php?mod=caja');
            exit;
        }
        if ($motivo === '' || mb_strlen($motivo) > 80) {
            flash('error', 'Debes indicar un motivo válido para la salida de efectivo.');
            header('Location: index.php?mod=caja');
            exit;
        }
        if (mb_strlen($descripcion) > 255) {
            flash('error', 'La descripción no puede superar los 255 caracteres.');
            header('Location: index.php?mod=caja');
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT id_caja, fecha_caja, numero_turno, estado_caja
                 FROM Caja
                 WHERE id_caja = ?
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute([$idCaja]);
            $caja = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$caja || strtoupper((string)$caja['estado_caja']) !== 'ABIERTA') {
                throw new RuntimeException('Solo se pueden registrar salidas mientras la caja está abierta.');
            }
            if ((string)$caja['fecha_caja'] !== date('Y-m-d')) {
                throw new RuntimeException('La caja corresponde a una jornada anterior. Debes cerrarla antes de registrar nuevos movimientos.');
            }

            $resumen = calcularResumenCaja($pdo, $idCaja, (string)$caja['fecha_caja']);
            $saldoDisponible = (float)$resumen['saldo_teorico'];
            if ($monto > $saldoDisponible + 0.001) {
                throw new RuntimeException('La salida supera el saldo teórico disponible en caja.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO Caja_Movimiento
                 (id_caja, tipo, monto, motivo, descripcion, id_usuario, fecha_movimiento)
                 VALUES (?, 'SALIDA', ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $idCaja,
                $monto,
                $motivo,
                $descripcion !== '' ? $descripcion : null,
                $userId,
            ]);
            $idMovimiento = (int)$pdo->lastInsertId();

            $pdo->commit();

            registrarAuditoria($pdo, 'caja', 'SALIDA_EFECTIVO', 'Caja_Movimiento', $idMovimiento, [
                'id_caja' => $idCaja,
                'monto' => $monto,
                'motivo' => $motivo,
            ]);

            flash('success', 'Salida de efectivo registrada correctamente por $' . number_format($monto, 2, ',', '.') . '.');
            header('Location: index.php?mod=caja');
            exit;
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', $e->getMessage());
            header('Location: index.php?mod=caja');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('FME - Salida de efectivo: ' . $e->getMessage());
            flash('error', 'No se pudo registrar la salida de efectivo.');
            header('Location: index.php?mod=caja');
            exit;
        }
    }

    if ($form === 'cerrar_caja') {
        $idCaja = (int)($_POST['id_caja'] ?? 0);
        $cantidades = $_POST['cantidad_billete'] ?? [];
        $observacion = trim((string)($_POST['observacion_cierre'] ?? ''));

        if ($idCaja <= 0) {
            flash('error', 'La caja seleccionada no es válida.');
            header('Location: index.php?mod=caja');
            exit;
        }

        if (mb_strlen($observacion) > 255) {
            flash('error', 'La observación no puede superar los 255 caracteres.');
            header('Location: index.php?mod=caja');
            exit;
        }

        if (!is_array($cantidades)) {
            $cantidades = [];
        }

        $detalleConteo = [];
        $efectivoContado = 0.0;
        foreach (fmeCajaDenominaciones() as $denominacion) {
            $clave = (string)$denominacion;
            $raw = trim((string)($cantidades[$clave] ?? '0'));
            if ($raw === '') {
                $raw = '0';
            }
            if (!ctype_digit($raw)) {
                flash('error', 'Las cantidades de billetes deben ser números enteros iguales o mayores a cero.');
                header('Location: index.php?mod=caja');
                exit;
            }

            $cantidad = (int)$raw;
            if ($cantidad < 0 || $cantidad > 1000000) {
                flash('error', 'La cantidad indicada para una denominación no es válida.');
                header('Location: index.php?mod=caja');
                exit;
            }

            $subtotal = round($denominacion * $cantidad, 2);
            $efectivoContado = round($efectivoContado + $subtotal, 2);
            $detalleConteo[] = [
                'denominacion' => $denominacion,
                'cantidad' => $cantidad,
                'subtotal' => $subtotal,
            ];
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT id_caja, fecha_caja, numero_turno, estado_caja
                 FROM Caja
                 WHERE id_caja = ?
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute([$idCaja]);
            $caja = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$caja) {
                throw new RuntimeException('La caja seleccionada ya no existe.');
            }
            if (strtoupper((string)$caja['estado_caja']) !== 'ABIERTA') {
                throw new RuntimeException('La caja seleccionada ya se encuentra cerrada.');
            }

            $resumen = calcularResumenCaja($pdo, $idCaja, (string)$caja['fecha_caja']);
            $saldoTeorico = (float)$resumen['saldo_teorico'];
            $diferencia = round($efectivoContado - $saldoTeorico, 2);

            if (abs($diferencia) >= 0.01 && $observacion === '') {
                throw new RuntimeException('La observación del cierre es obligatoria cuando existe un faltante o sobrante de caja.');
            }

            $pdo->prepare('DELETE FROM Caja_Denominacion WHERE id_caja = ?')->execute([$idCaja]);
            $stmtDetalle = $pdo->prepare(
                'INSERT INTO Caja_Denominacion (id_caja, denominacion, cantidad, subtotal) VALUES (?, ?, ?, ?)'
            );
            foreach ($detalleConteo as $item) {
                $stmtDetalle->execute([
                    $idCaja,
                    $item['denominacion'],
                    $item['cantidad'],
                    $item['subtotal'],
                ]);
            }

            $stmt = $pdo->prepare(
                "UPDATE Caja
                 SET estado_caja = 'CERRADA',
                     fecha_cierre = NOW(),
                     id_usuario_cierre = ?,
                     efectivo_esperado = ?,
                     efectivo_contado = ?,
                     diferencia = ?,
                     observacion_cierre = ?
                 WHERE id_caja = ?"
            );
            $stmt->execute([
                $userId,
                $saldoTeorico,
                $efectivoContado,
                $diferencia,
                $observacion !== '' ? $observacion : null,
                $idCaja,
            ]);

            $pdo->commit();

            registrarAuditoria($pdo, 'caja', 'CERRAR', 'Caja', $idCaja, [
                'fecha_caja' => (string)$caja['fecha_caja'],
                'numero_turno' => (int)($caja['numero_turno'] ?? 1),
                'monto_apertura' => (float)$resumen['monto_apertura'],
                'saldo_teorico' => $saldoTeorico,
                'efectivo_contado' => $efectivoContado,
                'diferencia' => $diferencia,
                'cantidad_ventas' => (int)$resumen['cantidad_ventas'],
                'ventas_efectivo' => (float)$resumen['ventas_efectivo'],
                'reintegros_efectivo' => (float)$resumen['reintegros_efectivo'],
                'salidas_jornada' => (float)$resumen['salidas_jornada'],
            ]);

            flash(
                'success',
                'Turno ' . (int)($caja['numero_turno'] ?? 1) . ' cerrado correctamente. Efectivo contado: $' . number_format($efectivoContado, 2, ',', '.') .
                ' · Diferencia: $' . number_format($diferencia, 2, ',', '.') . '.'
            );
            header('Location: index.php?mod=caja');
            exit;
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', $e->getMessage());
            header('Location: index.php?mod=caja');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('FME - Cierre de caja: ' . $e->getMessage());
            flash('error', 'No se pudo cerrar la caja. No se guardó el arqueo.');
            header('Location: index.php?mod=caja');
            exit;
        }
    }

    if ($form === 'retiro_cierre') {
        $idCaja = (int)($_POST['id_caja'] ?? 0);
        $monto = fmeCajaParseMonto((string)($_POST['monto'] ?? ''));
        $motivo = trim((string)($_POST['motivo'] ?? ''));
        $descripcion = trim((string)($_POST['descripcion'] ?? ''));
        $volverDetalle = (int)($_POST['volver_detalle'] ?? 0) === 1;

        if ($idCaja <= 0 || $monto === null || $monto <= 0) {
            flash('error', 'Debes indicar un retiro válido mayor a $0.');
            header('Location: index.php?mod=caja');
            exit;
        }
        if ($motivo === '' || mb_strlen($motivo) > 80) {
            flash('error', 'Debes indicar un motivo válido para el retiro.');
            header('Location: index.php?mod=caja');
            exit;
        }
        if (mb_strlen($descripcion) > 255) {
            flash('error', 'La descripción no puede superar los 255 caracteres.');
            header('Location: index.php?mod=caja');
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT id_caja, estado_caja, efectivo_contado
                 FROM Caja
                 WHERE id_caja = ?
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute([$idCaja]);
            $caja = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$caja || strtoupper((string)$caja['estado_caja']) !== 'CERRADA') {
                throw new RuntimeException('El retiro posterior solo puede registrarse sobre una caja cerrada.');
            }

            $movimientos = obtenerTotalesMovimientosCaja($pdo, $idCaja);
            $disponible = round((float)$caja['efectivo_contado'] - (float)$movimientos['retiros_post_cierre'], 2);
            if ($monto > $disponible + 0.001) {
                throw new RuntimeException('El retiro supera el efectivo disponible luego del cierre.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO Caja_Movimiento
                 (id_caja, tipo, monto, motivo, descripcion, id_usuario, fecha_movimiento)
                 VALUES (?, 'RETIRO_CIERRE', ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $idCaja,
                $monto,
                $motivo,
                $descripcion !== '' ? $descripcion : null,
                $userId,
            ]);
            $idMovimiento = (int)$pdo->lastInsertId();

            $pdo->commit();

            registrarAuditoria($pdo, 'caja', 'RETIRO_POST_CIERRE', 'Caja_Movimiento', $idMovimiento, [
                'id_caja' => $idCaja,
                'monto' => $monto,
                'motivo' => $motivo,
            ]);

            flash('success', 'Retiro posterior al cierre registrado correctamente por $' . number_format($monto, 2, ',', '.') . '.');
            $destino = $volverDetalle ? 'index.php?mod=caja&action=detail&id=' . $idCaja : 'index.php?mod=caja';
            header('Location: ' . $destino);
            exit;
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', $e->getMessage());
            header('Location: index.php?mod=caja');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('FME - Retiro posterior al cierre: ' . $e->getMessage());
            flash('error', 'No se pudo registrar el retiro posterior al cierre.');
            header('Location: index.php?mod=caja');
            exit;
        }
    }
}
