<?php

$auditorias = [];
$auditoriaModulos = [];
$auditoriaAcciones = [];
$auditoriaError = null;

if ($modActual === 'auditoria') {
    $fUsuario = trim((string) ($_GET['f_usuario'] ?? ''));
    $fModulo  = trim((string) ($_GET['f_modulo'] ?? ''));
    $fAccion  = trim((string) ($_GET['f_accion'] ?? ''));
    $fDesde   = trim((string) ($_GET['f_desde'] ?? ''));
    $fHasta   = trim((string) ($_GET['f_hasta'] ?? ''));

    $fechaValida = static function (string $fecha): bool {
        if ($fecha === '') {
            return true;
        }
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        return $d instanceof DateTime && $d->format('Y-m-d') === $fecha;
    };

    if (!$fechaValida($fDesde) || !$fechaValida($fHasta)) {
        flash('error', 'Las fechas del filtro no son válidas.');
        header('Location: index.php?mod=auditoria');
        exit;
    }

    try {
        $auditoriaModulos = $pdo->query(
            "SELECT DISTINCT modulo FROM Auditoria WHERE modulo <> '' ORDER BY modulo ASC"
        )->fetchAll(PDO::FETCH_COLUMN);

        $auditoriaAcciones = $pdo->query(
            "SELECT DISTINCT accion FROM Auditoria WHERE accion <> '' ORDER BY accion ASC"
        )->fetchAll(PDO::FETCH_COLUMN);

        $sql = 'SELECT id_auditoria, id_usuario, usuario_nombre, modulo, accion,
                       entidad, id_registro, detalle, ip_origen, fecha_auditoria
                FROM Auditoria
                WHERE 1 = 1';
        $params = [];

        if ($fUsuario !== '') {
            $sql .= ' AND usuario_nombre LIKE ?';
            $params[] = '%' . $fUsuario . '%';
        }
        if ($fModulo !== '') {
            $sql .= ' AND modulo = ?';
            $params[] = $fModulo;
        }
        if ($fAccion !== '') {
            $sql .= ' AND accion = ?';
            $params[] = $fAccion;
        }
        if ($fDesde !== '') {
            $sql .= ' AND fecha_auditoria >= ?';
            $params[] = $fDesde . ' 00:00:00';
        }
        if ($fHasta !== '') {
            $sql .= ' AND fecha_auditoria <= ?';
            $params[] = $fHasta . ' 23:59:59';
        }

        $sql .= ' ORDER BY fecha_auditoria DESC, id_auditoria DESC LIMIT 200';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $auditorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('FME - Consulta auditoría: ' . $e->getMessage());
        $auditoriaError = 'No se pudo consultar la auditoría. Verifica que la migración de auditoría esté aplicada.';
    }
}
