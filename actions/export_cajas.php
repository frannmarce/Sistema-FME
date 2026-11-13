<?php

require_once __DIR__ . '/../app/core/helpers.php';
require_once __DIR__ . '/../app/config/db.php';

if (!isset($_SESSION['auth_id'], $_SESSION['auth_rol'])) {
    header('Location: ../login.php');
    exit;
}

$rolId = (int)$_SESSION['auth_rol'];
$stmt = $pdo->prepare(
    "SELECT 1
     FROM Rol_Modulo rm
     INNER JOIN Modulo m ON rm.id_modulo = m.id_modulo
     WHERE rm.id_rol = ?
       AND m.codigo_modulo = 'caja'
       AND m.activo = 1
     LIMIT 1"
);
$stmt->execute([$rolId]);
if (!$stmt->fetchColumn()) {
    flash('error', 'No tienes permiso para exportar cajas.');
    header('Location: ../index.php');
    exit;
}

try {
    $sql = "SELECT c.id_caja, c.fecha_caja, c.estado_caja, c.monto_apertura,
                   c.fecha_apertura, c.fecha_cierre, c.efectivo_esperado,
                   c.efectivo_contado, c.diferencia,
                   c.observacion_apertura, c.observacion_cierre,
                   ua.nombre_usuario AS usuario_apertura,
                   uc.nombre_usuario AS usuario_cierre,
                   COALESCE(SUM(CASE WHEN cm.tipo = 'SALIDA' THEN cm.monto ELSE 0 END), 0) AS salidas_jornada,
                   COALESCE(SUM(CASE WHEN cm.tipo = 'RETIRO_CIERRE' THEN cm.monto ELSE 0 END), 0) AS retiros_cierre
            FROM Caja c
            INNER JOIN Usuario ua ON ua.id_usuario = c.id_usuario_apertura
            LEFT JOIN Usuario uc ON uc.id_usuario = c.id_usuario_cierre
            LEFT JOIN Caja_Movimiento cm ON cm.id_caja = c.id_caja
            GROUP BY c.id_caja, c.fecha_caja, c.estado_caja, c.monto_apertura,
                     c.fecha_apertura, c.fecha_cierre, c.efectivo_esperado,
                     c.efectivo_contado, c.diferencia,
                     c.observacion_apertura, c.observacion_cierre,
                     ua.nombre_usuario, uc.nombre_usuario
            ORDER BY c.fecha_caja DESC, c.id_caja DESC";
    $stmt = $pdo->query($sql);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="cajas_fme.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $output = fopen('php://output', 'w');
    if ($output === false) throw new RuntimeException('No se pudo abrir la salida CSV.');
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        'ID Caja', 'Fecha', 'Estado', 'Fondo inicial', 'Usuario apertura', 'Fecha apertura',
        'Usuario cierre', 'Fecha cierre', 'Salidas durante jornada', 'Saldo teorico al cierre',
        'Efectivo contado', 'Diferencia', 'Retiros posteriores',
        'Observacion apertura', 'Observacion cierre'
    ], ';');

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id_caja'],
            $row['fecha_caja'],
            $row['estado_caja'],
            $row['monto_apertura'],
            $row['usuario_apertura'] ?: '-',
            $row['fecha_apertura'],
            $row['usuario_cierre'] ?: '-',
            $row['fecha_cierre'] ?: '',
            $row['salidas_jornada'],
            $row['efectivo_esperado'],
            $row['efectivo_contado'],
            $row['diferencia'],
            $row['retiros_cierre'],
            $row['observacion_apertura'] ?: '',
            $row['observacion_cierre'] ?: '',
        ], ';');
    }

    fclose($output);
    exit;
} catch (Throwable $e) {
    error_log('FME - Exportación de cajas: ' . $e->getMessage());
    flash('error', 'No se pudo generar el archivo de cajas.');
    header('Location: ../index.php?mod=caja');
    exit;
}
