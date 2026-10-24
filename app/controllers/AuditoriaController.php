<?php

$auditorias = [];
$auditoriaModulos = [];
$auditoriaAcciones = [];
$auditoriaError = null;
$auditoriaPorPagina = 15;
$auditoriaPaginaActual = 1;
$auditoriaTotalRegistros = 0;
$auditoriaTotalPaginas = 1;
$auditoriaPaginaUrl = static function (int $pagina): string {
    $query = $_GET;
    $query['mod'] = 'auditoria';
    $query['pagina'] = max(1, $pagina);
    unset($query['action'], $query['id']);
    return 'index.php?' . http_build_query($query);
};

if ($modActual === 'auditoria') {
    $fUsuario = trim((string) ($_GET['f_usuario'] ?? ''));
    $fModulo  = trim((string) ($_GET['f_modulo'] ?? ''));
    $fAccion  = trim((string) ($_GET['f_accion'] ?? ''));
    $fDesde   = trim((string) ($_GET['f_desde'] ?? ''));
    $fHasta   = trim((string) ($_GET['f_hasta'] ?? ''));
    $auditoriaPaginaActual = max(1, (int) ($_GET['pagina'] ?? 1));

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

        $where = ' WHERE 1 = 1';
        $params = [];

        if ($fUsuario !== '') {
            $where .= ' AND usuario_nombre LIKE ?';
            $params[] = '%' . $fUsuario . '%';
        }
        if ($fModulo !== '') {
            $where .= ' AND modulo = ?';
            $params[] = $fModulo;
        }
        if ($fAccion !== '') {
            $where .= ' AND accion = ?';
            $params[] = $fAccion;
        }
        if ($fDesde !== '') {
            $where .= ' AND fecha_auditoria >= ?';
            $params[] = $fDesde . ' 00:00:00';
        }
        if ($fHasta !== '') {
            $where .= ' AND fecha_auditoria <= ?';
            $params[] = $fHasta . ' 23:59:59';
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM Auditoria' . $where);
        $countStmt->execute($params);
        $auditoriaTotalRegistros = (int) $countStmt->fetchColumn();
        $auditoriaTotalPaginas = max(1, (int) ceil($auditoriaTotalRegistros / $auditoriaPorPagina));

        if ($auditoriaPaginaActual > $auditoriaTotalPaginas) {
            $auditoriaPaginaActual = $auditoriaTotalPaginas;
        }

        $offset = ($auditoriaPaginaActual - 1) * $auditoriaPorPagina;

        $sql = 'SELECT id_auditoria, id_usuario, usuario_nombre, modulo, accion,
                       entidad, id_registro, detalle, ip_origen, fecha_auditoria
                FROM Auditoria' . $where . '
                ORDER BY fecha_auditoria DESC, id_auditoria DESC
                LIMIT ' . $auditoriaPorPagina . ' OFFSET ' . $offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $auditorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('FME - Consulta auditoría: ' . $e->getMessage());
        $auditoriaError = 'No se pudo consultar la auditoría. Verifica que la migración de auditoría esté aplicada.';
    }
}
