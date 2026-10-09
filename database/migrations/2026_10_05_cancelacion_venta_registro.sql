-- ============================================================
-- SISTEMA FME - CANCELACIÓN COMO REGISTRO INDEPENDIENTE
-- Ejecutar UNA VEZ sobre una base que ya tenga aplicada:
--   2026_09_23_cancelacion_ventas.sql
--
-- Objetivo:
-- - La Venta conserva su total, detalle y número original.
-- - Venta.estado_venta indica ACTIVA / CANCELADA.
-- - Cada anulación crea UN registro nuevo en Cancelacion_Venta.
-- - Una venta no puede tener más de una cancelación (UNIQUE id_venta).
-- ============================================================

CREATE TABLE IF NOT EXISTS Cancelacion_Venta (
    id_cancelacion INT AUTO_INCREMENT PRIMARY KEY,
    id_venta INT NOT NULL,
    fecha_cancelacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    monto_reintegrado DECIMAL(12,2) NOT NULL,
    motivo_cancelacion VARCHAR(255) NOT NULL,
    id_usuario INT NULL,
    CONSTRAINT uq_cancelacion_venta UNIQUE (id_venta),
    CONSTRAINT fk_cancelacion_venta
      FOREIGN KEY (id_venta) REFERENCES Venta(id_venta),
    CONSTRAINT fk_cancelacion_usuario
      FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario)
      ON DELETE SET NULL,
    INDEX idx_cancelacion_fecha (fecha_cancelacion),
    INDEX idx_cancelacion_usuario (id_usuario)
) ENGINE=InnoDB;

-- Migrar cancelaciones creadas con la versión anterior para no perder historial.
INSERT INTO Cancelacion_Venta
    (id_venta, fecha_cancelacion, monto_reintegrado, motivo_cancelacion, id_usuario)
SELECT
    v.id_venta,
    COALESCE(v.fecha_cancelacion, v.fecha_venta),
    CASE
      WHEN v.monto_reintegrado > 0 THEN v.monto_reintegrado
      ELSE v.total_venta
    END,
    COALESCE(NULLIF(TRIM(v.motivo_cancelacion), ''), 'Cancelación migrada desde la versión anterior'),
    v.id_usuario_cancelacion
FROM Venta v
WHERE UPPER(v.estado_venta) = 'CANCELADA'
ON DUPLICATE KEY UPDATE
    fecha_cancelacion = VALUES(fecha_cancelacion),
    monto_reintegrado = VALUES(monto_reintegrado),
    motivo_cancelacion = VALUES(motivo_cancelacion),
    id_usuario = VALUES(id_usuario);

-- Desde esta versión los detalles de la cancelación viven únicamente en
-- Cancelacion_Venta. Venta conserva solo el estado para consultas rápidas.
ALTER TABLE Venta
  DROP FOREIGN KEY fk_venta_usuario_cancelacion,
  DROP INDEX idx_venta_usuario_cancelacion,
  DROP COLUMN fecha_cancelacion,
  DROP COLUMN id_usuario_cancelacion,
  DROP COLUMN motivo_cancelacion,
  DROP COLUMN monto_reintegrado;

-- Comprobaciones útiles tras ejecutar la migración.
SELECT * FROM Cancelacion_Venta ORDER BY id_cancelacion DESC;
SELECT id_venta, fecha_venta, total_venta, estado_venta FROM Venta ORDER BY id_venta DESC;
