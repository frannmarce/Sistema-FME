-- ============================================================
-- SISTEMA FME - CÓDIGO DE BARRAS PARA PRODUCTOS / VENTAS
-- Ejecutar UNA VEZ sobre la base fme1 actual.
--
-- El paginado no requiere cambios de base: se implementa con LIMIT/OFFSET.
-- Este script agrega el dato que permite localizar productos con lector.
-- ============================================================

ALTER TABLE Producto
    ADD COLUMN codigo_barras VARCHAR(64) NULL AFTER nombre_producto,
    ADD CONSTRAINT uq_producto_codigo_barras UNIQUE (codigo_barras);

-- Los productos existentes quedan sin código hasta que se les asigne uno
-- desde Gestión de productos. MySQL permite múltiples NULL en un índice UNIQUE.
SELECT id_producto, nombre_producto, codigo_barras
FROM Producto
ORDER BY id_producto;
