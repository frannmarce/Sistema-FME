<?php

/**
 * Carga variables desde un archivo .env ubicado en la raíz del proyecto.
 *
 * No requiere librerías externas. Las variables ya definidas en el entorno
 * del sistema tienen prioridad sobre los valores del archivo .env.
 */
function loadEnv(string $path): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $loaded = true;

    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }

        $key = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));

        if ($key === '' || !preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
            continue;
        }

        if (getenv($key) !== false || array_key_exists($key, $_ENV)) {
            continue;
        }

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
}


/**
 * Obtiene una variable de entorno obligatoria.
 *
 * A diferencia de env(), no acepta valores por defecto: si la variable no
 * existe, la configuración se considera incompleta. $allowEmpty permite
 * declarar variables que deben existir aunque su valor sea vacío (por
 * ejemplo DB_PASS en una instalación local de XAMPP sin contraseña).
 */
function envRequired(string $key, bool $allowEmpty = false): string
{
    $hasEnvValue = array_key_exists($key, $_ENV);
    $systemValue = getenv($key);
    $exists = $hasEnvValue || $systemValue !== false;

    if (!$exists) {
        throw new RuntimeException("Falta la variable de entorno obligatoria: {$key}");
    }

    $value = $hasEnvValue ? $_ENV[$key] : $systemValue;
    $value = (string) $value;

    if (!$allowEmpty && trim($value) === '') {
        throw new RuntimeException("La variable de entorno {$key} no puede estar vacía.");
    }

    return $value;
}

loadEnv(dirname(__DIR__, 2) . '/.env');
