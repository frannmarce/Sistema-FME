<?php

/* =========================
   CONFIGURACIÓN DE USUARIO (POST)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $modActual === 'config_usuario') {
    $nombre    = trim((string) ($_POST['nombre_persona'] ?? ''));
    $apellido  = trim((string) ($_POST['apellido_persona'] ?? ''));
    $cuil      = trim((string) ($_POST['CUIL_persona'] ?? ''));
    $telefono  = trim((string) ($_POST['telefono_persona'] ?? ''));
    $pais      = trim((string) ($_POST['nombre_pais'] ?? ''));
    $ciudad    = trim((string) ($_POST['nombre_ciudad'] ?? ''));
    $direccion = trim((string) ($_POST['num_direccion'] ?? ''));

    $errores = [];
    if ($nombre === '')    $errores[] = 'Falta el nombre.';
    if ($apellido === '')  $errores[] = 'Falta el apellido.';
    if ($cuil === '')      $errores[] = 'Falta el CUIL/DNI.';
    if ($pais === '')      $errores[] = 'Falta el país.';
    if ($ciudad === '')    $errores[] = 'Falta la ciudad.';
    if ($direccion === '') $errores[] = 'Falta la dirección.';

    if (mb_strlen($nombre) > 50) $errores[] = 'El nombre no puede superar los 50 caracteres.';
    if (mb_strlen($apellido) > 50) $errores[] = 'El apellido no puede superar los 50 caracteres.';
    if (mb_strlen($cuil) > 20) $errores[] = 'El CUIL/DNI no puede superar los 20 caracteres.';
    if (mb_strlen($telefono) > 20) $errores[] = 'El teléfono no puede superar los 20 caracteres.';

    $stmt = $pdo->prepare('SELECT id_persona FROM Usuario WHERE id_usuario = ? LIMIT 1');
    $stmt->execute([$userId]);
    $idPersonaActual = (int) ($stmt->fetchColumn() ?: 0);

    if (!$errores) {
        $sql = 'SELECT id_persona FROM Persona WHERE CUIL_persona = ?';
        $params = [$cuil];
        if ($idPersonaActual > 0) {
            $sql .= ' AND id_persona <> ?';
            $params[] = $idPersonaActual;
        }
        $sql .= ' LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn()) {
            $errores[] = 'Ese CUIL/DNI ya está registrado.';
        }
    }

    if ($errores) {
        flash('error', implode(' ', $errores));
        header('Location: index.php?mod=config_usuario');
        exit;
    }

    try {
        $pdo->beginTransaction();

        if ($idPersonaActual > 0) {
            $stmt = $pdo->prepare('SELECT id_direccion FROM Persona WHERE id_persona = ? LIMIT 1');
            $stmt->execute([$idPersonaActual]);
            $idDireccion = (int) ($stmt->fetchColumn() ?: 0);

            if ($idDireccion > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE Direccion
                     SET nombre_pais = ?, nombre_ciudad = ?, num_direccion = ?
                     WHERE id_direccion = ?'
                );
                $stmt->execute([$pais, $ciudad, $direccion, $idDireccion]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion)
                     VALUES (?, ?, ?)'
                );
                $stmt->execute([$pais, $ciudad, $direccion]);
                $idDireccion = (int) $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare(
                'UPDATE Persona
                 SET nombre_persona = ?, apellido_persona = ?, CUIL_persona = ?,
                     telefono_persona = ?, id_direccion = ?
                 WHERE id_persona = ?'
            );
            $stmt->execute([
                $nombre,
                $apellido,
                $cuil,
                $telefono !== '' ? $telefono : null,
                $idDireccion,
                $idPersonaActual,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion)
                 VALUES (?, ?, ?)'
            );
            $stmt->execute([$pais, $ciudad, $direccion]);
            $idDireccion = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO Persona
                 (nombre_persona, apellido_persona, CUIL_persona, telefono_persona, id_direccion)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $nombre,
                $apellido,
                $cuil,
                $telefono !== '' ? $telefono : null,
                $idDireccion,
            ]);
            $idPersonaActual = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare('UPDATE Usuario SET id_persona = ? WHERE id_usuario = ?');
            $stmt->execute([$idPersonaActual, $userId]);
        }

        $pdo->commit();
        $_SESSION['needs_profile'] = false;

        registrarAuditoria($pdo, 'config_usuario', 'MODIFICAR', 'Persona', $idPersonaActual, [
            'resultado' => 'Datos personales actualizados',
        ]);

        flash('success', 'La información se guardó correctamente.');
        header('Location: index.php?mod=config_usuario');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('FME - Configuración de usuario: ' . $e->getMessage());
        flash('error', 'No se pudo guardar la información.');
        header('Location: index.php?mod=config_usuario');
        exit;
    }
}
