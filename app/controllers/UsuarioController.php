<?php

/* =========================
   EDICIÓN DE USUARIO (POST)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $modActual === 'usuarios') {
    $form = $_POST['form'] ?? '';

    if ($form === 'edit_usuario') {
        if (!isset($modulosPermitidosConArchivo['usuarios'])) {
            flash('error', 'No tienes permiso para editar usuarios.');
            header('Location: index.php');
            exit;
        }

        $idUsuario     = (int) ($_POST['id_usuario'] ?? 0);
        $nombreUsuario = trim((string) ($_POST['nombre_usuario'] ?? ''));
        $correoUsuario = trim((string) ($_POST['correo_usuario'] ?? ''));
        $cuilPersona   = trim((string) ($_POST['CUIL_persona'] ?? ''));
        $idRol         = (int) ($_POST['id_rol'] ?? 0);

        $errores = [];
        if ($idUsuario <= 0) $errores[] = 'ID de usuario inválido.';
        if ($nombreUsuario === '') $errores[] = 'Debe ingresar un nombre de usuario.';
        if (mb_strlen($nombreUsuario) > 50) $errores[] = 'El nombre de usuario no puede superar los 50 caracteres.';
        if ($correoUsuario === '') $errores[] = 'Debe ingresar un correo.';
        if (mb_strlen($correoUsuario) > 80) $errores[] = 'El correo no puede superar los 80 caracteres.';
        if ($correoUsuario !== '' && !filter_var($correoUsuario, FILTER_VALIDATE_EMAIL)) $errores[] = 'Correo inválido.';
        if ($cuilPersona !== '' && mb_strlen($cuilPersona) > 20) $errores[] = 'El CUIL/DNI no puede superar los 20 caracteres.';
        if ($idRol <= 0) $errores[] = 'Debe seleccionar un perfil válido.';

        $rolActualUsuario = 0;
        $idPersonaUsuario = 0;

        if (!$errores) {
            $stmt = $pdo->prepare('SELECT id_rol, id_persona, nombre_usuario FROM Usuario WHERE id_usuario = ? LIMIT 1');
            $stmt->execute([$idUsuario]);
            $usuarioActual = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuarioActual) {
                $errores[] = 'El usuario a editar no existe.';
            } else {
                $rolActualUsuario = (int) $usuarioActual['id_rol'];
                $idPersonaUsuario = (int) ($usuarioActual['id_persona'] ?? 0);
                $nombreUsuarioAnterior = (string) ($usuarioActual['nombre_usuario'] ?? '');
            }
        }

        if (!$errores) {
            $stmt = $pdo->prepare('SELECT id_rol FROM Tipo_Rol WHERE id_rol = ? AND activo = 1 LIMIT 1');
            $stmt->execute([$idRol]);
            if (!$stmt->fetchColumn()) {
                $errores[] = 'El perfil seleccionado no existe o está inactivo.';
            }
        }

        // El sistema nunca puede quedar sin administradores.
        if (!$errores && $rolActualUsuario === 1 && $idRol !== 1) {
            $stmt = $pdo->query('SELECT COUNT(*) FROM Usuario WHERE id_rol = 1');
            if ((int) $stmt->fetchColumn() <= 1) {
                $errores[] = 'No se puede cambiar el perfil del único administrador existente.';
            }
        }

        // Unicidad excluyendo el registro que se está editando.
        if (!$errores) {
            $stmt = $pdo->prepare(
                'SELECT id_usuario
                 FROM Usuario
                 WHERE nombre_usuario = ? AND id_usuario <> ?
                 LIMIT 1'
            );
            $stmt->execute([$nombreUsuario, $idUsuario]);
            if ($stmt->fetchColumn()) {
                $errores[] = 'Ese nombre de usuario ya está en uso.';
            }
        }

        if (!$errores) {
            $stmt = $pdo->prepare(
                'SELECT id_usuario
                 FROM Usuario
                 WHERE correo_usuario = ? AND id_usuario <> ?
                 LIMIT 1'
            );
            $stmt->execute([$correoUsuario, $idUsuario]);
            if ($stmt->fetchColumn()) {
                $errores[] = 'Ese correo ya está en uso.';
            }
        }

        if (!$errores && $cuilPersona !== '') {
            $stmt = $pdo->prepare(
                'SELECT id_persona
                 FROM Persona
                 WHERE CUIL_persona = ?
                   AND id_persona <> ?
                 LIMIT 1'
            );
            $stmt->execute([$cuilPersona, $idPersonaUsuario]);
            if ($stmt->fetchColumn()) {
                $errores[] = 'Ese CUIL/DNI ya está registrado.';
            }
        }

        if ($errores) {
            flash('error', implode(' ', $errores));
            header('Location: index.php?mod=usuarios&action=edit&id=' . $idUsuario);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'UPDATE Usuario
                 SET nombre_usuario = ?, correo_usuario = ?, id_rol = ?
                 WHERE id_usuario = ?'
            );
            $stmt->execute([$nombreUsuario, $correoUsuario, $idRol, $idUsuario]);

            if ($cuilPersona !== '' && $idPersonaUsuario > 0) {
                $stmt = $pdo->prepare('UPDATE Persona SET CUIL_persona = ? WHERE id_persona = ?');
                $stmt->execute([$cuilPersona, $idPersonaUsuario]);
            }

            $pdo->commit();

            registrarAuditoria($pdo, 'usuarios', 'MODIFICAR', 'Usuario', $idUsuario, [
                'usuario_anterior' => $nombreUsuarioAnterior ?? $nombreUsuario,
                'usuario_actual' => $nombreUsuario,
                'perfil_anterior_id' => $rolActualUsuario,
                'perfil_actual_id' => $idRol,
            ]);

            // Si el usuario edita su propia cuenta, la sesión se sincroniza de inmediato.
            if ($idUsuario === $userId) {
                $_SESSION['auth_user'] = $nombreUsuario;
                $_SESSION['auth_rol'] = $idRol;

                if ($rolActualUsuario !== $idRol) {
                    flash('success', 'Tu perfil fue actualizado. Los permisos se recalcularon correctamente.');
                    header('Location: index.php');
                    exit;
                }
            }

            flash('success', 'Usuario actualizado correctamente.');
            header('Location: index.php?mod=usuarios');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('FME - Usuario: ' . $e->getMessage());
            flash('error', 'No se pudo actualizar el usuario.');
            header('Location: index.php?mod=usuarios&action=edit&id=' . $idUsuario);
            exit;
        }
    }
}
