-- ============================================================
-- SISTEMA FME - BAJA LÓGICA / ARCHIVADO DE PRODUCTOS
-- Ejecutar UNA VEZ sobre la base fme1 actual.
--
-- Objetivo:
-- - Los productos dejan de eliminarse físicamente.
-- - activo_producto = 1: disponible para nuevas operaciones.
-- - activo_producto = 0: archivado; conserva ventas, movimientos e historial.
-- ============================================================

ALTER TABLE Producto
    ADD COLUMN activo_producto TINYINT(1) NOT NULL DEFAULT 1 AFTER stock_producto;

UPDATE Producto SET activo_producto = 1 WHERE activo_producto IS NULL;

CREATE INDEX idx_producto_activo ON Producto (activo_producto);

SELECT id_producto, nombre_producto, stock_producto, activo_producto
FROM Producto
ORDER BY id_producto;
