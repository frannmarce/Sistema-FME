<?php

date_default_timezone_set('America/Argentina/Buenos_Aires');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Guarda o consume un mensaje flash de sesión.
 *
 * flash('success', 'Guardado'); // guardar
 * $mensaje = flash('success');  // leer y eliminar
 */
function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }

    if (!empty($_SESSION['flash'][$key])) {
        $message = (string) $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $message;
    }

    return null;
}
