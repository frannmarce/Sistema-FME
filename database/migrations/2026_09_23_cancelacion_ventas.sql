-- ============================================================
-- SISTEMA FME - CANCELACIÓN DE VENTAS Y TRAZABILIDAD DE MOVIMIENTOS
-- Ejecutar UNA VEZ sobre la base existente fme1.
-- ============================================================

-- 1) La venta conserva su importe original y suma datos de anulación/reintegro.
ALTER TABLE Venta
  ADD COLUMN estado_venta VARCHAR(20) NOT NULL DEFAULT 'ACTIVA' AFTER total_venta,
  ADD COLUMN fecha_cancelacion DATETIME NULL AFTER id_medio,
  ADD COLUMN id_usuario_cancelacion INT NULL AFTER fecha_cancelacion,
  ADD COLUMN motivo_cancelacion VARCHAR(255) NULL AFTER id_usuario_cancelacion,
  ADD COLUMN monto_reintegrado DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER motivo_cancelacion,
  ADD INDEX idx_venta_estado (estado_venta),
  ADD INDEX idx_venta_usuario_cancelacion (id_usuario_cancelacion),
  ADD CONSTRAINT fk_venta_usuario_cancelacion
    FOREIGN KEY (id_usuario_cancelacion) REFERENCES Usuario(id_usuario)
    ON DELETE SET NULL;

-- 2) Los movimientos indican si fueron manuales o generados por ventas.
ALTER TABLE Movimiento
  ADD COLUMN origen_movimiento VARCHAR(30) NOT NULL DEFAULT 'MANUAL' AFTER id_motivo,
  ADD COLUMN id_venta INT NULL AFTER origen_movimiento,
  ADD COLUMN detalle_movimiento VARCHAR(255) NULL AFTER id_venta,
  ADD INDEX idx_movimiento_origen (origen_movimiento),
  ADD INDEX idx_movimiento_venta (id_venta),
  ADD CONSTRAINT fk_movimiento_venta
    FOREIGN KEY (id_venta) REFERENCES Venta(id_venta)
    ON DELETE SET NULL;

-- 3) Motivo específico para la reposición automática de stock al cancelar.
INSERT INTO Motivo_movimiento (nombre_motivo, descripcion_motivo)
SELECT 'Cancelación de venta', 'Entrada automática de stock por anulación de una venta'
WHERE NOT EXISTS (
  SELECT 1
  FROM Motivo_movimiento
  WHERE LOWER(nombre_motivo) IN ('cancelación de venta', 'cancelacion de venta')
);

-- 4) Los movimientos históricos con motivo Venta fueron automáticos aunque
--    la versión anterior todavía no guardaba el número de venta relacionado.
UPDATE Movimiento m
INNER JOIN Motivo_movimiento mm ON mm.id_motivo = m.id_motivo
SET m.origen_movimiento = 'VENTA',
    m.detalle_movimiento = COALESCE(m.detalle_movimiento, 'Movimiento automático histórico de venta')
WHERE LOWER(mm.nombre_motivo) = 'venta';
