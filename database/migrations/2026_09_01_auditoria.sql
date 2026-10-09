-- ============================================================
-- SISTEMA FME - MIGRACIÓN DE AUDITORÍA
-- Ejecutar UNA VEZ sobre la base existente fme1.
-- ============================================================

CREATE TABLE IF NOT EXISTS Auditoria (
    id_auditoria BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NULL,
    usuario_nombre VARCHAR(50) NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    accion VARCHAR(50) NOT NULL,
    entidad VARCHAR(60) NULL,
    id_registro INT NULL,
    detalle TEXT NULL,
    ip_origen VARCHAR(45) NULL,
    fecha_auditoria DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_auditoria_usuario
      FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario)
      ON DELETE SET NULL,
    INDEX idx_auditoria_fecha (fecha_auditoria),
    INDEX idx_auditoria_usuario (id_usuario),
    INDEX idx_auditoria_modulo (modulo),
    INDEX idx_auditoria_accion (accion)
) ENGINE=InnoDB;

INSERT INTO Modulo
(codigo_modulo, nombre_modulo, icono_modulo, url_modulo, archivo_modulo, activo)
VALUES
('auditoria', 'Auditoría', '📜', 'index.php?mod=auditoria', 'auditoria.php', 1)
ON DUPLICATE KEY UPDATE
  nombre_modulo = VALUES(nombre_modulo),
  icono_modulo = VALUES(icono_modulo),
  url_modulo = VALUES(url_modulo),
  archivo_modulo = VALUES(archivo_modulo),
  activo = VALUES(activo);

-- Solo el Administrador principal recibe el módulo automáticamente.
INSERT IGNORE INTO Rol_Modulo (id_rol, id_modulo)
SELECT 1, id_modulo
FROM Modulo
WHERE codigo_modulo = 'auditoria';
