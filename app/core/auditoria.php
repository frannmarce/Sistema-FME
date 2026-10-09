<?php

/**
 * Registra una acción relevante sin interrumpir la operación principal si
 * el registro de auditoría falla. Nunca guardar contraseñas, tokens ni
 * credenciales dentro de $detalle.
 */
function registrarAuditoria(
    PDO $pdo,
    string $modulo,
    string $accion,
    ?string $entidad = null,
    ?int $idRegistro = null,
    array|string|null $detalle = null,
    ?int $idUsuario = null,
    ?string $nombreUsuario = null
): void {
    $idUsuario = $idUsuario ?? (isset($_SESSION['auth_id']) ? (int) $_SESSION['auth_id'] : null);
    if ($idUsuario !== null && $idUsuario <= 0) {
        $idUsuario = null;
    }

    $nombreUsuario = trim((string) ($nombreUsuario ?? ($_SESSION['auth_user'] ?? 'Sistema')));
    if ($nombreUsuario === '') {
        $nombreUsuario = 'Sistema';
    }

    $modulo = trim($modulo);
    $accion = trim($accion);
    $entidad = $entidad !== null ? trim($entidad) : null;

    if ($modulo === '' || $accion === '') {
        error_log('FME - Auditoría omitida: módulo o acción vacíos.');
        return;
    }

    $detalleTexto = null;
    if (is_array($detalle)) {
        $json = json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $detalleTexto = $json !== false ? $json : null;
    } elseif ($detalle !== null) {
        $detalleTexto = trim($detalle) !== '' ? trim($detalle) : null;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    if ($ip !== null) {
        $ip = substr((string) $ip, 0, 45);
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO Auditoria
             (id_usuario, usuario_nombre, modulo, accion, entidad, id_registro, detalle, ip_origen, fecha_auditoria)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $idUsuario,
            mb_substr($nombreUsuario, 0, 50),
            mb_substr($modulo, 0, 50),
            mb_substr($accion, 0, 50),
            $entidad !== null && $entidad !== '' ? mb_substr($entidad, 0, 60) : null,
            $idRegistro !== null && $idRegistro > 0 ? $idRegistro : null,
            $detalleTexto,
            $ip,
        ]);
    } catch (Throwable $e) {
        // La auditoría es importante, pero un fallo suyo no debe repetir ni
        // revertir una venta, movimiento u otra operación ya confirmada.
        error_log('FME - Auditoría: ' . $e->getMessage());
    }
}
