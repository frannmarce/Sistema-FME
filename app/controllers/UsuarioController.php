<?php

/* =========================
   EDICIÓN DE USUARIO (POST)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $modActual === 'usuarios') {
    $form = $_POST['form'] ?? '';

    if ($form === 'toggle_usuario_estado') {
        if (!isset($modulosPermitidosConArchivo['usuarios'])) {
            flash('error', 'No tienes permiso para gestionar usuarios.');
            header('Location: index.php');
            exit;
        }

        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        $nuevoEstado = (int) ($_POST['nuevo_estado'] ?? -1);

        if ($idUsuario <= 0 || !in_array($nuevoEstado, [0, 1], true)) {
            flash('error', 'Solicitud de estado de usuario inválida.');
            header('Location: index.php?mod=usuarios');
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT id_usuario, nombre_usuario, id_rol, activo_usuario
                 FROM Usuario
                 WHERE id_usuario = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([$idUsuario]);
            $usuarioEstado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuarioEstado) {
                $pdo->rollBack();
                flash('error', 'El usuario seleccionado no existe.');
                header('Location: index.php?mod=usuarios');
                exit;
            }

            $estadoAnterior = (int) $usuarioEstado['activo_usuario'];
            $rolUsuario = (int) $usuarioEstado['id_rol'];
            $nombreUsuarioEstado = (string) $usuarioEstado['nombre_usuario'];

            if ($estadoAnterior === $nuevoEstado) {
                $pdo->commit();
                flash('success', $nuevoEstado === 1 ? 'El usuario ya se encuentra activo.' : 'El usuario ya se encuentra inactivo.');
                header('Location: index.php?mod=usuarios');
                exit;
            }

            // Un usuario nunca puede darse de baja lógica a sí mismo.
            if ($nuevoEstado === 0 && $idUsuario === $userId) {
                $pdo->rollBack();
                flash('error', 'No puedes desactivar tu propio usuario mientras tienes la sesión iniciada.');
                header('Location: index.php?mod=usuarios');
                exit;
            }

            // El sistema siempre debe conservar al menos un administrador activo.
            if ($nuevoEstado === 0 && $rolUsuario === 1 && $estadoAnterior === 1) {
                $stmt = $pdo->query(
                    'SELECT id_usuario
                     FROM Usuario
                     WHERE id_rol = 1 AND activo_usuario = 1
                     FOR UPDATE'
                );
                $administradoresActivos = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (count($administradoresActivos) <= 1) {
                    $pdo->rollBack();
                    flash('error', 'No se puede desactivar al único administrador activo del sistema.');
                    header('Location: index.php?mod=usuarios');
                    exit;
                }
            }

            $stmt = $pdo->prepare('UPDATE Usuario SET activo_usuario = ? WHERE id_usuario = ?');
            $stmt->execute([$nuevoEstado, $idUsuario]);

            $pdo->commit();

            registrarAuditoria(
                $pdo,
                'usuarios',
                $nuevoEstado === 1 ? 'REACTIVAR' : 'DESACTIVAR',
                'Usuario',
                $idUsuario,
                [
                    'usuario' => $nombreUsuarioEstado,
                    'estado_anterior' => $estadoAnterior === 1 ? 'Activo' : 'Inactivo',
                    'estado_actual' => $nuevoEstado === 1 ? 'Activo' : 'Inactivo',
                ]
            );

            flash('success', $nuevoEstado === 1 ? 'Usuario reactivado correctamente.' : 'Usuario desactivado correctamente.');
            header('Location: index.php?mod=usuarios');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('FME - Estado de usuario: ' . $e->getMessage());
            flash('error', 'No se pudo actualizar el estado del usuario.');
            header('Location: index.php?mod=usuarios');
            exit;
        }
    }

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
        $activoActualUsuario = 1;
        $idPersonaUsuario = 0;
        $cuilPersonaActual = '';

        // Comparar CUIL/DNI sin guiones ni espacios para detectar si realmente cambió.
        $normalizarCuil = static function (string $valor): string {
            return preg_replace('/\D+/', '', $valor) ?? '';
        };

        if (!$errores) {
            $stmt = $pdo->prepare(
                'SELECT u.id_rol, u.id_persona, u.nombre_usuario, u.activo_usuario, p.CUIL_persona
                 FROM Usuario u
                 LEFT JOIN Persona p ON p.id_persona = u.id_persona
                 WHERE u.id_usuario = ?
                 LIMIT 1'
            );
            $stmt->execute([$idUsuario]);
            $usuarioActual = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuarioActual) {
                $errores[] = 'El usuario a editar no existe.';
            } else {
                $rolActualUsuario = (int) $usuarioActual['id_rol'];
                $activoActualUsuario = (int) ($usuarioActual['activo_usuario'] ?? 1);
                $idPersonaUsuario = (int) ($usuarioActual['id_persona'] ?? 0);
                $nombreUsuarioAnterior = (string) ($usuarioActual['nombre_usuario'] ?? '');
                $cuilPersonaActual = trim((string) ($usuarioActual['CUIL_persona'] ?? ''));
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
        if (!$errores && $activoActualUsuario === 1 && $rolActualUsuario === 1 && $idRol !== 1) {
            $stmt = $pdo->query('SELECT COUNT(*) FROM Usuario WHERE id_rol = 1 AND activo_usuario = 1');
            if ((int) $stmt->fetchColumn() <= 1) {
                $errores[] = 'No se puede cambiar el perfil del único administrador activo.';
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

        $cuilCambio = $normalizarCuil($cuilPersona) !== $normalizarCuil($cuilPersonaActual);

        // Solo validar unicidad si el usuario realmente cambió su CUIL/DNI.
        // Así un cambio de perfil, correo o nombre no se bloquea por el CUIL que ya tenía asignado.
        if (!$errores && $cuilPersona !== '' && $cuilCambio) {
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

            // Revalidación definitiva dentro de la transacción: un perfil inactivo
            // nunca puede asignarse aunque el formulario esté desactualizado o manipulado.
            $stmt = $pdo->prepare(
                'SELECT activo
                 FROM Tipo_Rol
                 WHERE id_rol = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([$idRol]);
            $rolActivoEnGuardado = $stmt->fetchColumn();

            if ((int) $rolActivoEnGuardado !== 1) {
                $pdo->rollBack();
                flash('error', 'El perfil seleccionado está inactivo y no puede asignarse a un usuario.');
                header('Location: index.php?mod=usuarios&action=edit&id=' . $idUsuario);
                exit;
            }

            $stmt = $pdo->prepare(
                'UPDATE Usuario
                 SET nombre_usuario = ?, correo_usuario = ?, id_rol = ?
                 WHERE id_usuario = ?'
            );
            $stmt->execute([$nombreUsuario, $correoUsuario, $idRol, $idUsuario]);

            if ($cuilPersona !== '' && $idPersonaUsuario > 0 && $cuilCambio) {
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
