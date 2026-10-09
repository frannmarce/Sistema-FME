<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auditoria.php';

/* =========================
   SEGURIDAD / AUTENTICACIÓN
   ========================= */
if (!isset($_SESSION['auth_id'], $_SESSION['auth_rol'])) {
    header('Location: login.php');
    exit;
}

$usuario = (string) ($_SESSION['auth_user'] ?? 'Usuario');
$rolId   = (int) $_SESSION['auth_rol'];
$userId  = (int) $_SESSION['auth_id'];

$modActual = trim((string) ($_GET['mod'] ?? 'panel'));
$action    = trim((string) ($_GET['action'] ?? 'list'));

/* =========================
   PERFIL ACTUAL
   ========================= */
$stmt = $pdo->prepare('SELECT nombre_rol, activo FROM Tipo_Rol WHERE id_rol = ? LIMIT 1');
$stmt->execute([$rolId]);
$rolActual = $stmt->fetch(PDO::FETCH_ASSOC);

// Si el perfil fue desactivado o ya no existe, la sesión deja de ser válida.
if (!$rolActual || (int) $rolActual['activo'] !== 1) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

$rolNombre = (string) $rolActual['nombre_rol'];

/* =========================
   PERFIL COMPLETO
   ========================= */
$stmt = $pdo->prepare('SELECT id_persona FROM Usuario WHERE id_usuario = ? LIMIT 1');
$stmt->execute([$userId]);
$rowUsuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rowUsuario) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

$needsProfile = $rowUsuario['id_persona'] === null;
$_SESSION['needs_profile'] = $needsProfile;

/* =========================
   MÓDULOS PERMITIDOS POR PERFIL
   ========================= */
$modulos = [];
$modulosPermitidosPorCodigo = [];
$modulosPermitidosConArchivo = [];
$moduloActualInfo = null;

try {
    $stmt = $pdo->prepare(
        'SELECT m.id_modulo, m.codigo_modulo, m.nombre_modulo, m.icono_modulo,
                m.url_modulo, m.archivo_modulo
         FROM Rol_Modulo rm
         INNER JOIN Modulo m ON rm.id_modulo = m.id_modulo
         WHERE rm.id_rol = ?
           AND m.activo = 1
         ORDER BY m.id_modulo ASC'
    );
    $stmt->execute([$rolId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $modulo) {
        $codigo = (string) $modulo['codigo_modulo'];
        $item = [
            'id' => $codigo,
            'icon' => $modulo['icono_modulo'] ?: '📌',
            'titulo' => (string) $modulo['nombre_modulo'],
            'url' => $modulo['url_modulo'] ?: '#',
            'archivo' => $modulo['archivo_modulo'],
        ];

        $modulos[] = $item;
        $modulosPermitidosPorCodigo[$codigo] = $item;

        if (!empty($modulo['archivo_modulo'])) {
            $modulosPermitidosConArchivo[$codigo] = $item;
        }
    }
} catch (Throwable $e) {
    // Seguridad fail-closed: un error de permisos nunca debe otorgar accesos extra.
    error_log('FME - Error al cargar permisos: ' . $e->getMessage());
    $modulos = [];
    $modulosPermitidosPorCodigo = [];
    $modulosPermitidosConArchivo = [];
}

// Panel y configuración personal permanecen accesibles con una sesión válida.
$modulosSiemprePermitidos = [
    'panel' => [
        'id' => 'panel',
        'icon' => '🏠',
        'titulo' => 'Panel de control',
        'url' => 'index.php',
        'archivo' => 'panel.php',
    ],
    'config_usuario' => [
        'id' => 'config_usuario',
        'icon' => '👤',
        'titulo' => 'Configuración de usuario',
        'url' => 'index.php?mod=config_usuario',
        'archivo' => 'config_usuario.php',
    ],
    'ayuda' => [
        'id' => 'ayuda',
        'icon' => '❓',
        'titulo' => 'Ayuda',
        'url' => 'index.php?mod=ayuda',
        'archivo' => 'ayuda.php',
    ],
];

foreach ($modulosSiemprePermitidos as $codigo => $item) {
    if (!isset($modulosPermitidosPorCodigo[$codigo])) {
        $modulos[] = $item;
        $modulosPermitidosPorCodigo[$codigo] = $item;
        $modulosPermitidosConArchivo[$codigo] = $item;
    }
}

if (isset($modulosSiemprePermitidos[$modActual])) {
    $moduloActualInfo = $modulosPermitidosConArchivo[$modActual] ?? $modulosSiemprePermitidos[$modActual];
} else {
    $moduloActualInfo = $modulosPermitidosConArchivo[$modActual] ?? null;
}

// Validación central de ruta: ocultar el menú no es suficiente.
if ($moduloActualInfo === null) {
    flash('error', 'No tienes permiso para acceder a ese módulo.');
    header('Location: index.php');
    exit;
}

/* =========================
   CONTROLADORES
   ========================= */
require_once __DIR__ . '/../controllers/ConfigUsuarioController.php';
require_once __DIR__ . '/../controllers/UsuarioController.php';
require_once __DIR__ . '/../controllers/PerfilController.php';
require_once __DIR__ . '/../controllers/ProductoController.php';
require_once __DIR__ . '/../controllers/MovimientoController.php';
require_once __DIR__ . '/../controllers/ProveedorController.php';
require_once __DIR__ . '/../controllers/VentaController.php';
require_once __DIR__ . '/../controllers/ReporteController.php';
require_once __DIR__ . '/../controllers/AuditoriaController.php';
require_once __DIR__ . '/../controllers/DashboardController.php';
