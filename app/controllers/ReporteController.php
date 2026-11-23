<?php

$reporteAnios = [];
$reporteAnio = (int) date('Y');
$ventasMensuales = [];
$reporteVentasTotal = 0.0;
$reporteVentasCantidad = 0;
$reporteTicketPromedio = 0.0;
$reporteMaxMensual = 0.0;
$reporteVentasError = null;

$reporteDesde = trim((string) ($_GET['desde'] ?? ''));
$reporteHasta = trim((string) ($_GET['hasta'] ?? ''));
$productosMasVendidosGrafico = [];
$reporteMaxProductoUnidades = 0;
$reporteProductosError = null;
$stockPorCategoria = [];
$reporteMaxStockCategoria = 0;
$reporteStockCategoriaError = null;
$ventasPorMedioPago = [];
$reporteMaxMedioPago = 0.0;
$reporteTotalMediosPago = 0.0;
$reporteMediosPagoError = null;
$ventasPorCategoria = [];
$reporteVentasCategoriaTotal = 0;
$reporteVentasCategoriaError = null;
$estadoInventario = [];
$reporteInventarioTotal = 0;
$reporteInventarioError = null;
$estadoVentas = [];
$reporteEstadoVentasTotal = 0;
$reporteEstadoVentasError = null;
$reporteCancelacionesCantidad = 0;
$reporteReintegrosTotal = 0.0;
$reporteCancelaciones = [];
$reporteCancelacionesError = null;
$ventasHoyPorMedio = [];
$ventasHoyTotal = 0.0;
$ventasHoyCantidad = 0;
$ventasHoyError = null;

if ($modActual === 'reportes') {
    /* =========================
       RESUMEN DE CANCELACIONES
       El período se aplica sobre fecha_cancelacion, porque el reintegro
       impacta financieramente cuando la venta es anulada.
       El monto se conserva positivo en la base y se representa como
       negativo únicamente en la interfaz/reportes.
       ========================= */
    try {
        $whereCancelacion = ' WHERE 1=1';
        $paramsCancelacion = [];

        if ($reporteDesde !== '') {
            $whereCancelacion .= ' AND DATE(cv.fecha_cancelacion) >= ?';
            $paramsCancelacion[] = $reporteDesde;
        }
        if ($reporteHasta !== '') {
            $whereCancelacion .= ' AND DATE(cv.fecha_cancelacion) <= ?';
            $paramsCancelacion[] = $reporteHasta;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS cantidad_cancelaciones,
                    COALESCE(SUM(cv.monto_reintegrado), 0) AS total_reintegrado
             FROM Cancelacion_Venta cv' . $whereCancelacion
        );
        $stmt->execute($paramsCancelacion);
        $resumenCancelaciones = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $reporteCancelacionesCantidad = (int) ($resumenCancelaciones['cantidad_cancelaciones'] ?? 0);
        $reporteReintegrosTotal = (float) ($resumenCancelaciones['total_reintegrado'] ?? 0);

        $stmt = $pdo->prepare(
            "SELECT cv.id_cancelacion,
                    cv.id_venta,
                    cv.fecha_cancelacion,
                    cv.monto_reintegrado,
                    cv.motivo_cancelacion,
                    COALESCE(u.nombre_usuario, 'Usuario no disponible') AS usuario_cancelacion
             FROM Cancelacion_Venta cv
             LEFT JOIN Usuario u ON cv.id_usuario = u.id_usuario" . $whereCancelacion . "
             ORDER BY cv.fecha_cancelacion DESC, cv.id_cancelacion DESC
             LIMIT 10"
        );
        $stmt->execute($paramsCancelacion);
        $reporteCancelaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('FME - Resumen de cancelaciones: ' . $e->getMessage());
        $reporteCancelacionesError = 'No se pudo generar el resumen de cancelaciones.';
    }

    /* =========================
       VENTAS DE HOY POR MEDIO DE PAGO
       Muestra facturación, no ganancia. En pagos mixtos cada porción
       se atribuye al medio que realmente recibió ese importe.
       ========================= */
    try {
        $stmt = $pdo->query(
            "SELECT mp.nombre_medio AS medio,
                    COUNT(DISTINCT v.id_venta) AS cantidad_ventas,
                    COALESCE(SUM(vp.monto), 0) AS total
             FROM Venta_Pago vp
             INNER JOIN Venta v ON v.id_venta = vp.id_venta
             INNER JOIN Medio_pago mp ON mp.id_medio = vp.id_medio
             WHERE DATE(v.fecha_venta) = CURDATE()
               AND v.estado_venta = 'ACTIVA'
             GROUP BY mp.id_medio, mp.nombre_medio
             ORDER BY total DESC, mp.nombre_medio ASC"
        );
        $ventasHoyPorMedio = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $idsVentasHoy = [];
        foreach ($ventasHoyPorMedio as &$filaHoy) {
            $filaHoy['cantidad_ventas'] = (int)$filaHoy['cantidad_ventas'];
            $filaHoy['total'] = (float)$filaHoy['total'];
            $ventasHoyTotal += $filaHoy['total'];
        }
        unset($filaHoy);

        $stmt = $pdo->query("SELECT COUNT(*) FROM Venta WHERE DATE(fecha_venta) = CURDATE() AND estado_venta = 'ACTIVA'");
        $ventasHoyCantidad = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('FME - Ventas de hoy por medio: ' . $e->getMessage());
        $ventasHoyError = 'No se pudo generar el resumen de ventas de hoy.';
    }

    try {
        $reporteAnios = array_map(
            'intval',
            $pdo->query(
                "SELECT DISTINCT YEAR(fecha_venta) AS anio
                 FROM Venta
                 WHERE fecha_venta IS NOT NULL
                   AND estado_venta = 'ACTIVA'
                 ORDER BY anio DESC"
            )->fetchAll(PDO::FETCH_COLUMN)
        );

        $anioSolicitado = filter_input(INPUT_GET, 'anio', FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 2000,
                'max_range' => 2100,
            ],
        ]);

        if ($anioSolicitado !== false && $anioSolicitado !== null) {
            $reporteAnio = (int) $anioSolicitado;
        } elseif ($reporteAnios) {
            // Por defecto mostramos el año más reciente que tenga ventas.
            $reporteAnio = (int) $reporteAnios[0];
        }

        // Permitimos consultar un año válido aunque todavía no tenga ventas.
        if (!in_array($reporteAnio, $reporteAnios, true)) {
            $reporteAnios[] = $reporteAnio;
            rsort($reporteAnios, SORT_NUMERIC);
        }

        $stmt = $pdo->prepare(
            "SELECT MONTH(fecha_venta) AS mes,
                    COUNT(*) AS cantidad_ventas,
                    COALESCE(SUM(total_venta), 0) AS total_ventas
             FROM Venta
             WHERE YEAR(fecha_venta) = ?
               AND estado_venta = 'ACTIVA'
             GROUP BY MONTH(fecha_venta)
             ORDER BY mes ASC"
        );
        $stmt->execute([$reporteAnio]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $porMes = [];
        foreach ($filas as $fila) {
            $mes = (int) $fila['mes'];
            $porMes[$mes] = [
                'cantidad' => (int) $fila['cantidad_ventas'],
                'total' => (float) $fila['total_ventas'],
            ];
        }

        $nombresMeses = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
        ];

        for ($mes = 1; $mes <= 12; $mes++) {
            $cantidad = $porMes[$mes]['cantidad'] ?? 0;
            $total = $porMes[$mes]['total'] ?? 0.0;

            $ventasMensuales[] = [
                'mes' => $mes,
                'nombre' => $nombresMeses[$mes],
                'cantidad' => $cantidad,
                'total' => $total,
            ];

            $reporteVentasCantidad += $cantidad;
            $reporteVentasTotal += $total;
            $reporteMaxMensual = max($reporteMaxMensual, $total);
        }

        if ($reporteVentasCantidad > 0) {
            $reporteTicketPromedio = $reporteVentasTotal / $reporteVentasCantidad;
        }
    } catch (Throwable $e) {
        error_log('FME - Reporte de ventas mensuales: ' . $e->getMessage());
        $reporteVentasError = 'No se pudo generar el gráfico de ventas mensuales.';
    }

    /* =========================
       INFORME GRÁFICO 2
       Productos más vendidos
       ========================= */
    try {
        $where = " WHERE v.estado_venta = 'ACTIVA'";
        $params = [];

        if ($reporteDesde !== '') {
            $where .= ' AND DATE(v.fecha_venta) >= ?';
            $params[] = $reporteDesde;
        }
        if ($reporteHasta !== '') {
            $where .= ' AND DATE(v.fecha_venta) <= ?';
            $params[] = $reporteHasta;
        }

        $stmt = $pdo->prepare(
            'SELECT p.id_producto,
                    p.nombre_producto,
                    COALESCE(SUM(dv.cantidad_detalle), 0) AS unidades,
                    COALESCE(SUM(dv.cantidad_detalle * dv.precio_unitario), 0) AS importe
             FROM Venta v
             INNER JOIN Detalle_venta dv ON v.id_venta = dv.id_venta
             INNER JOIN Producto p ON dv.id_producto = p.id_producto' . $where . '
             GROUP BY p.id_producto, p.nombre_producto
             ORDER BY unidades DESC, importe DESC
             LIMIT 5'
        );
        $stmt->execute($params);
        $productosMasVendidosGrafico = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($productosMasVendidosGrafico as &$producto) {
            $producto['unidades'] = (int) $producto['unidades'];
            $producto['importe'] = (float) $producto['importe'];
            $reporteMaxProductoUnidades = max($reporteMaxProductoUnidades, $producto['unidades']);
        }
        unset($producto);
    } catch (Throwable $e) {
        error_log('FME - Reporte de productos más vendidos: ' . $e->getMessage());
        $reporteProductosError = 'No se pudo generar el gráfico de productos más vendidos.';
    }

    /* =========================
       INFORME GRÁFICO 3
       Stock por categoría
       ========================= */
    try {
        $stmt = $pdo->query(
            "SELECT COALESCE(c.nombre_categoria, 'Sin categoría') AS categoria,
                    COUNT(p.id_producto) AS cantidad_productos,
                    COALESCE(SUM(p.stock_producto), 0) AS stock_total
             FROM Producto p
             LEFT JOIN Categoria c ON p.id_categoria = c.id_categoria
             WHERE p.activo_producto = 1
             GROUP BY c.id_categoria, c.nombre_categoria
             ORDER BY stock_total DESC, categoria ASC"
        );
        $stockPorCategoria = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($stockPorCategoria as &$categoria) {
            $categoria['cantidad_productos'] = (int) $categoria['cantidad_productos'];
            $categoria['stock_total'] = (int) $categoria['stock_total'];
            $reporteMaxStockCategoria = max($reporteMaxStockCategoria, $categoria['stock_total']);
        }
        unset($categoria);
    } catch (Throwable $e) {
        error_log('FME - Reporte de stock por categoría: ' . $e->getMessage());
        $reporteStockCategoriaError = 'No se pudo generar el gráfico de stock por categoría.';
    }

    /* =========================
       INFORME GRÁFICO 4
       Ventas por medio de pago
       ========================= */
    try {
        $wherePago = " WHERE v.estado_venta = 'ACTIVA'";
        $paramsPago = [];

        if ($reporteDesde !== '') {
            $wherePago .= ' AND DATE(v.fecha_venta) >= ?';
            $paramsPago[] = $reporteDesde;
        }
        if ($reporteHasta !== '') {
            $wherePago .= ' AND DATE(v.fecha_venta) <= ?';
            $paramsPago[] = $reporteHasta;
        }

        $stmt = $pdo->prepare(
            "SELECT mp.nombre_medio AS medio,
                    COUNT(DISTINCT v.id_venta) AS cantidad_ventas,
                    COALESCE(SUM(vp.monto), 0) AS total_facturado
             FROM Venta_Pago vp
             INNER JOIN Venta v ON v.id_venta = vp.id_venta
             INNER JOIN Medio_pago mp ON mp.id_medio = vp.id_medio" . $wherePago . "
             GROUP BY mp.id_medio, mp.nombre_medio
             ORDER BY total_facturado DESC, cantidad_ventas DESC, medio ASC"
        );
        $stmt->execute($paramsPago);
        $ventasPorMedioPago = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($ventasPorMedioPago as &$medioPago) {
            $medioPago['cantidad_ventas'] = (int) $medioPago['cantidad_ventas'];
            $medioPago['total_facturado'] = (float) $medioPago['total_facturado'];
            $reporteMaxMedioPago = max($reporteMaxMedioPago, $medioPago['total_facturado']);
            $reporteTotalMediosPago += $medioPago['total_facturado'];
        }
        unset($medioPago);
    } catch (Throwable $e) {
        error_log('FME - Reporte de ventas por medio de pago: ' . $e->getMessage());
        $reporteMediosPagoError = 'No se pudo generar el gráfico de ventas por medio de pago.';
    }

    /* =========================
       INFORME GRÁFICO 5
       Distribución de ventas por categoría
       ========================= */
    try {
        $whereCategoria = " WHERE v.estado_venta = 'ACTIVA'";
        $paramsCategoria = [];
        if ($reporteDesde !== '') {
            $whereCategoria .= ' AND DATE(v.fecha_venta) >= ?';
            $paramsCategoria[] = $reporteDesde;
        }
        if ($reporteHasta !== '') {
            $whereCategoria .= ' AND DATE(v.fecha_venta) <= ?';
            $paramsCategoria[] = $reporteHasta;
        }

        $stmt = $pdo->prepare(
            "SELECT COALESCE(c.nombre_categoria, 'Sin categoría') AS categoria,
                    COALESCE(SUM(dv.cantidad_detalle), 0) AS unidades,
                    COALESCE(SUM(dv.cantidad_detalle * dv.precio_unitario), 0) AS importe
             FROM Venta v
             INNER JOIN Detalle_venta dv ON dv.id_venta = v.id_venta
             INNER JOIN Producto p ON p.id_producto = dv.id_producto
             LEFT JOIN Categoria c ON c.id_categoria = p.id_categoria" . $whereCategoria . "
             GROUP BY c.id_categoria, c.nombre_categoria
             ORDER BY unidades DESC, importe DESC, categoria ASC"
        );
        $stmt->execute($paramsCategoria);
        $ventasPorCategoria = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ventasPorCategoria as &$categoriaVenta) {
            $categoriaVenta['unidades'] = (int) $categoriaVenta['unidades'];
            $categoriaVenta['importe'] = (float) $categoriaVenta['importe'];
            $reporteVentasCategoriaTotal += $categoriaVenta['unidades'];
        }
        unset($categoriaVenta);
    } catch (Throwable $e) {
        error_log('FME - Reporte de ventas por categoría: ' . $e->getMessage());
        $reporteVentasCategoriaError = 'No se pudo generar el gráfico de ventas por categoría.';
    }

    /* =========================
       INFORME GRÁFICO 6
       Estado general del inventario actual
       ========================= */
    try {
        $stmt = $pdo->query(
            "SELECT
                COALESCE(SUM(CASE WHEN stock_producto > 5 THEN 1 ELSE 0 END), 0) AS normal,
                COALESCE(SUM(CASE WHEN stock_producto BETWEEN 1 AND 5 THEN 1 ELSE 0 END), 0) AS bajo,
                COALESCE(SUM(CASE WHEN stock_producto = 0 THEN 1 ELSE 0 END), 0) AS sin_stock
             FROM Producto
             WHERE activo_producto = 1"
        );
        $inventarioFila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $estadoInventario = [
            ['estado' => 'Stock normal', 'cantidad' => (int) ($inventarioFila['normal'] ?? 0)],
            ['estado' => 'Stock bajo', 'cantidad' => (int) ($inventarioFila['bajo'] ?? 0)],
            ['estado' => 'Sin stock', 'cantidad' => (int) ($inventarioFila['sin_stock'] ?? 0)],
        ];
        foreach ($estadoInventario as $estadoInventarioFila) {
            $reporteInventarioTotal += (int) $estadoInventarioFila['cantidad'];
        }
    } catch (Throwable $e) {
        error_log('FME - Reporte de estado del inventario: ' . $e->getMessage());
        $reporteInventarioError = 'No se pudo generar el gráfico de estado del inventario.';
    }

    /* =========================
       INFORME GRÁFICO 7
       Estado de las ventas (activas vs canceladas)
       El período se aplica sobre fecha_venta para comparar el mismo universo.
       ========================= */
    try {
        $whereEstadoVenta = ' WHERE 1=1';
        $paramsEstadoVenta = [];
        if ($reporteDesde !== '') {
            $whereEstadoVenta .= ' AND DATE(v.fecha_venta) >= ?';
            $paramsEstadoVenta[] = $reporteDesde;
        }
        if ($reporteHasta !== '') {
            $whereEstadoVenta .= ' AND DATE(v.fecha_venta) <= ?';
            $paramsEstadoVenta[] = $reporteHasta;
        }

        $stmt = $pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN v.estado_venta = 'ACTIVA' THEN 1 ELSE 0 END), 0) AS activas,
                COALESCE(SUM(CASE WHEN v.estado_venta = 'CANCELADA' THEN 1 ELSE 0 END), 0) AS canceladas
             FROM Venta v" . $whereEstadoVenta
        );
        $stmt->execute($paramsEstadoVenta);
        $estadoVentaFila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $estadoVentas = [
            ['estado' => 'Ventas activas', 'cantidad' => (int) ($estadoVentaFila['activas'] ?? 0)],
            ['estado' => 'Ventas canceladas', 'cantidad' => (int) ($estadoVentaFila['canceladas'] ?? 0)],
        ];
        foreach ($estadoVentas as $estadoVenta) {
            $reporteEstadoVentasTotal += (int) $estadoVenta['cantidad'];
        }
    } catch (Throwable $e) {
        error_log('FME - Reporte de estado de ventas: ' . $e->getMessage());
        $reporteEstadoVentasError = 'No se pudo generar el gráfico de estado de las ventas.';
    }
}
