<?php

/* =========================
   ABM DE PERFILES / ROLES (POST)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $modActual === 'perfiles') {
    if (!isset($modulosPermitidosConArchivo['perfiles'])) {
        flash('error', 'No tienes permiso para administrar perfiles.');
        header('Location: index.php');
        exit;
    }

    $form = $_POST['form'] ?? '';

    if ($form === 'add_perfil' || $form === 'edit_perfil') {
        $idRol = (int) ($_POST['id_rol'] ?? 0);
        $nombreRol = trim((string) ($_POST['nombre_rol'] ?? ''));
        $descripcionRol = trim((string) ($_POST['descripcion_rol'] ?? ''));

        $errores = [];
        if ($form === 'edit_perfil' && $idRol <= 0) $errores[] = 'ID de perfil inválido.';
        if ($nombreRol === '') $errores[] = 'Debe ingresar el nombre del perfil.';
        if (mb_strlen($nombreRol) > 50) $errores[] = 'El nombre del perfil no puede superar los 50 caracteres.';
        if (mb_strlen($descripcionRol) > 100) $errores[] = 'La descripción no puede superar los 100 caracteres.';

        if (!$errores && $form === 'edit_perfil') {
            $stmt = $pdo->prepare('SELECT 1 FROM Tipo_Rol WHERE id_rol = ? LIMIT 1');
            $stmt->execute([$idRol]);
            if (!$stmt->fetchColumn()) {
                $errores[] = 'El perfil a editar no existe.';
            }
        }

        if (!$errores) {
            $sql = 'SELECT id_rol FROM Tipo_Rol WHERE nombre_rol = ?';
            $params = [$nombreRol];
            if ($form === 'edit_perfil') {
                $sql .= ' AND id_rol <> ?';
                $params[] = $idRol;
            }
            $sql .= ' LIMIT 1';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetchColumn()) {
                $errores[] = 'Ya existe un perfil con ese nombre.';
            }
        }

        if ($errores) {
            flash('error', implode(' ', $errores));
            $destino = $form === 'edit_perfil'
                ? 'index.php?mod=perfiles&action=edit&id=' . $idRol
                : 'index.php?mod=perfiles&action=add';
            header('Location: ' . $destino);
            exit;
        }

        try {
            $pdo->beginTransaction();

            if ($form === 'add_perfil') {
                $stmt = $pdo->prepare(
                    'INSERT INTO Tipo_Rol (nombre_rol, descripcion_rol, activo)
                     VALUES (?, ?, 1)'
                );
                $stmt->execute([$nombreRol, $descripcionRol !== '' ? $descripcionRol : null]);
                $idRol = (int) $pdo->lastInsertId();
                flash('success', 'Perfil creado correctamente. Ahora puedes asignarle módulos.');
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE Tipo_Rol
                     SET nombre_rol = ?, descripcion_rol = ?
                     WHERE id_rol = ?'
                );
                $stmt->execute([$nombreRol, $descripcionRol !== '' ? $descripcionRol : null, $idRol]);

                if ($idRol === 1) {
                    // Regla de negocio: el Administrador conserva acceso total.
                    $stmt = $pdo->query('SELECT id_modulo FROM Modulo WHERE activo = 1 ORDER BY id_modulo');
                    $modulosValidos = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
                } else {
                    $seleccionados = $_POST['modulos'] ?? [];
                    if (!is_array($seleccionados)) {
                        $seleccionados = [];
                    }

                    $idsSolicitados = array_values(array_unique(array_filter(
                        array_map('intval', $seleccionados),
                        static fn (int $id): bool => $id > 0
                    )));

                    $modulosValidos = [];
                    if ($idsSolicitados) {
                        $placeholders = implode(',', array_fill(0, count($idsSolicitados), '?'));
                        $stmt = $pdo->prepare(
                            "SELECT id_modulo
                             FROM Modulo
                             WHERE activo = 1
                               AND id_modulo IN ($placeholders)"
                        );
                        $stmt->execute($idsSolicitados);
                        $modulosValidos = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
                    }
                }

                $stmt = $pdo->prepare('DELETE FROM Rol_Modulo WHERE id_rol = ?');
                $stmt->execute([$idRol]);

                if ($modulosValidos) {
                    $stmt = $pdo->prepare('INSERT INTO Rol_Modulo (id_rol, id_modulo) VALUES (?, ?)');
                    foreach ($modulosValidos as $idModulo) {
                        $stmt->execute([$idRol, $idModulo]);
                    }
                }

                flash('success', 'Perfil actualizado correctamente.');
            }

            $pdo->commit();
            registrarAuditoria(
                $pdo,
                'perfiles',
                $form === 'add_perfil' ? 'CREAR' : 'MODIFICAR',
                'Perfil',
                $idRol,
                [
                    'nombre' => $nombreRol,
                    'modulos_asignados' => $form === 'add_perfil' ? 0 : count($modulosValidos),
                ]
            );
            header('Location: index.php?mod=perfiles');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('FME - Perfil: ' . $e->getMessage());
            flash('error', 'No se pudo guardar el perfil.');
            header('Location: index.php?mod=perfiles');
            exit;
        }
    }

    if ($form === 'activate_perfil') {
        $idRol = (int) ($_POST['id_rol'] ?? 0);
        if ($idRol <= 0) {
            flash('error', 'ID de perfil inválido.');
            header('Location: index.php?mod=perfiles');
            exit;
        }

        try {
            $stmt = $pdo->prepare('UPDATE Tipo_Rol SET activo = 1 WHERE id_rol = ?');
            $stmt->execute([$idRol]);
            if ($stmt->rowCount() > 0) {
                registrarAuditoria($pdo, 'perfiles', 'ACTIVAR', 'Perfil', $idRol);
            }
            flash($stmt->rowCount() > 0 ? 'success' : 'error', $stmt->rowCount() > 0
                ? 'Perfil activado correctamente.'
                : 'No se encontró el perfil a activar.');
        } catch (Throwable $e) {
            error_log('FME - Activar perfil: ' . $e->getMessage());
            flash('error', 'No se pudo activar el perfil.');
        }

        header('Location: index.php?mod=perfiles');
        exit;
    }

    if ($form === 'delete_perfil') {
        $idRol = (int) ($_POST['id_rol'] ?? 0);

        if ($idRol <= 0) {
            flash('error', 'ID de perfil inválido.');
            header('Location: index.php?mod=perfiles');
            exit;
        }

        if ($idRol === 1) {
            flash('error', 'No se puede desactivar el perfil Administrador principal.');
            header('Location: index.php?mod=perfiles');
            exit;
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM Usuario WHERE id_rol = ?');
        $stmt->execute([$idRol]);
        if ((int) $stmt->fetchColumn() > 0) {
            flash('error', 'No se puede desactivar el perfil porque hay usuarios asignados.');
            header('Location: index.php?mod=perfiles');
            exit;
        }

        try {
            $stmt = $pdo->prepare('UPDATE Tipo_Rol SET activo = 0 WHERE id_rol = ?');
            $stmt->execute([$idRol]);
            if ($stmt->rowCount() > 0) {
                registrarAuditoria($pdo, 'perfiles', 'DESACTIVAR', 'Perfil', $idRol);
            }
            flash($stmt->rowCount() > 0 ? 'success' : 'error', $stmt->rowCount() > 0
                ? 'Perfil desactivado correctamente.'
                : 'No se encontró el perfil a desactivar.');
        } catch (Throwable $e) {
            error_log('FME - Desactivar perfil: ' . $e->getMessage());
            flash('error', 'No se pudo desactivar el perfil.');
        }

        header('Location: index.php?mod=perfiles');
        exit;
    }
}
