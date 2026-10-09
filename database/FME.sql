-- ============================================================
-- Sistema FME - Base completa para una instalación nueva
-- ============================================================
-- IMPORTANTE:
-- 1) Este archivo está pensado para una base nueva/vacía.
-- 2) Si quieres usar "fme_prueba", cambia fme1 en las dos líneas siguientes.
-- 3) Para una base existente usa los scripts de database/migrations.
-- ============================================================

CREATE DATABASE IF NOT EXISTS fme1
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE fme1;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS Direccion (
    id_direccion INT AUTO_INCREMENT PRIMARY KEY,
    nombre_pais VARCHAR(50) NOT NULL,
    nombre_ciudad VARCHAR(50) NOT NULL,
    num_direccion VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Persona (
    id_persona INT AUTO_INCREMENT PRIMARY KEY,
    nombre_persona VARCHAR(50) NOT NULL,
    apellido_persona VARCHAR(50) NOT NULL,
    CUIL_persona VARCHAR(20) NOT NULL,
    telefono_persona VARCHAR(20) NULL,
    id_direccion INT NULL,
    CONSTRAINT uq_persona_cuil UNIQUE (CUIL_persona),
    CONSTRAINT fk_persona_direccion
      FOREIGN KEY (id_direccion) REFERENCES Direccion(id_direccion)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Tipo_Rol (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL,
    descripcion_rol VARCHAR(100) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT uq_tipo_rol_nombre UNIQUE (nombre_rol)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre_usuario VARCHAR(50) NOT NULL,
    correo_usuario VARCHAR(80) NOT NULL,
    contraseña_usuario VARCHAR(255) NOT NULL,
    id_persona INT NULL,
    id_rol INT NOT NULL,
    email_verificado TINYINT(1) NOT NULL DEFAULT 0,
    token_email VARCHAR(64) NULL,
    token_email_expira DATETIME NULL,
    token_password VARCHAR(64) NULL,
    token_password_expira DATETIME NULL,
    CONSTRAINT uq_usuario_nombre UNIQUE (nombre_usuario),
    CONSTRAINT uq_usuario_correo UNIQUE (correo_usuario),
    CONSTRAINT fk_usuario_persona
      FOREIGN KEY (id_persona) REFERENCES Persona(id_persona),
    CONSTRAINT fk_usuario_rol
      FOREIGN KEY (id_rol) REFERENCES Tipo_Rol(id_rol),
    INDEX idx_usuario_token_email (token_email),
    INDEX idx_usuario_token_password (token_password)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Categoria (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre_categoria VARCHAR(50) NOT NULL,
    descripcion_categoria VARCHAR(100) NULL,
    CONSTRAINT uq_categoria_nombre UNIQUE (nombre_categoria)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Proveedor (
    id_proveedor INT AUTO_INCREMENT PRIMARY KEY,
    nombre_proveedor VARCHAR(80) NOT NULL,
    telefono_proveedor VARCHAR(30) NULL,
    correo_proveedor VARCHAR(80) NULL,
    id_direccion INT NULL,
    CONSTRAINT uq_proveedor_nombre UNIQUE (nombre_proveedor),
    CONSTRAINT fk_proveedor_direccion
      FOREIGN KEY (id_direccion) REFERENCES Direccion(id_direccion)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Producto (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    nombre_producto VARCHAR(80) NOT NULL,
    codigo_barras CHAR(13) NULL,
    precio_producto DECIMAL(10,2) NOT NULL,
    stock_producto INT NOT NULL DEFAULT 0,
    activo_producto TINYINT(1) NOT NULL DEFAULT 1,
    id_categoria INT NOT NULL,
    id_proveedor INT NOT NULL,
    CONSTRAINT fk_producto_categoria
      FOREIGN KEY (id_categoria) REFERENCES Categoria(id_categoria),
    CONSTRAINT fk_producto_proveedor
      FOREIGN KEY (id_proveedor) REFERENCES Proveedor(id_proveedor),
    CONSTRAINT uq_producto_codigo_barras UNIQUE (codigo_barras),
    INDEX idx_producto_activo (activo_producto)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Tipo_movimiento (
    id_tipo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_tipo VARCHAR(50) NOT NULL,
    descripcion_tipo VARCHAR(100) NULL,
    CONSTRAINT uq_tipo_movimiento_nombre UNIQUE (nombre_tipo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Motivo_movimiento (
    id_motivo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_motivo VARCHAR(50) NOT NULL,
    descripcion_motivo VARCHAR(100) NULL,
    CONSTRAINT uq_motivo_movimiento_nombre UNIQUE (nombre_motivo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Movimiento (
    id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
    cantidad_movimiento INT NOT NULL,
    fecha_movimiento DATETIME NOT NULL,
    id_producto INT NOT NULL,
    id_usuario INT NOT NULL,
    id_tipo INT NOT NULL,
    id_motivo INT NOT NULL,
    origen_movimiento VARCHAR(30) NOT NULL DEFAULT 'MANUAL',
    id_venta INT NULL,
    detalle_movimiento VARCHAR(255) NULL,
    CONSTRAINT fk_movimiento_producto
      FOREIGN KEY (id_producto) REFERENCES Producto(id_producto),
    CONSTRAINT fk_movimiento_usuario
      FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario),
    CONSTRAINT fk_movimiento_tipo
      FOREIGN KEY (id_tipo) REFERENCES Tipo_movimiento(id_tipo),
    CONSTRAINT fk_movimiento_motivo
      FOREIGN KEY (id_motivo) REFERENCES Motivo_movimiento(id_motivo),
    INDEX idx_movimiento_fecha (fecha_movimiento),
    INDEX idx_movimiento_origen (origen_movimiento),
    INDEX idx_movimiento_venta (id_venta)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Medio_pago (
    id_medio INT AUTO_INCREMENT PRIMARY KEY,
    nombre_medio VARCHAR(50) NOT NULL,
    CONSTRAINT uq_medio_pago_nombre UNIQUE (nombre_medio)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Venta (
    id_venta INT AUTO_INCREMENT PRIMARY KEY,
    fecha_venta DATETIME NOT NULL,
    total_venta DECIMAL(12,2) NOT NULL,
    estado_venta VARCHAR(20) NOT NULL DEFAULT 'ACTIVA',
    id_usuario INT NOT NULL,
    id_medio INT NOT NULL,
    CONSTRAINT fk_venta_usuario
      FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario),
    CONSTRAINT fk_venta_medio
      FOREIGN KEY (id_medio) REFERENCES Medio_pago(id_medio),
    INDEX idx_venta_fecha (fecha_venta),
    INDEX idx_venta_estado (estado_venta)
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS Detalle_venta (
    id_venta INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad_detalle INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id_venta, id_producto),
    CONSTRAINT fk_detalle_venta
      FOREIGN KEY (id_venta) REFERENCES Venta(id_venta),
    CONSTRAINT fk_detalle_producto
      FOREIGN KEY (id_producto) REFERENCES Producto(id_producto)
) ENGINE=InnoDB;

ALTER TABLE Movimiento
  ADD CONSTRAINT fk_movimiento_venta
    FOREIGN KEY (id_venta) REFERENCES Venta(id_venta)
    ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS Modulo (
    id_modulo INT AUTO_INCREMENT PRIMARY KEY,
    codigo_modulo VARCHAR(50) NOT NULL,
    nombre_modulo VARCHAR(80) NOT NULL,
    icono_modulo VARCHAR(10) NULL,
    url_modulo VARCHAR(150) NOT NULL,
    archivo_modulo VARCHAR(80) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT uq_modulo_codigo UNIQUE (codigo_modulo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Rol_Modulo (
    id_rol INT NOT NULL,
    id_modulo INT NOT NULL,
    PRIMARY KEY (id_rol, id_modulo),
    CONSTRAINT fk_rol_modulo_rol
      FOREIGN KEY (id_rol) REFERENCES Tipo_Rol(id_rol),
    CONSTRAINT fk_rol_modulo_modulo
      FOREIGN KEY (id_modulo) REFERENCES Modulo(id_modulo)
) ENGINE=InnoDB;

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

-- ============================================================
-- DATOS MAESTROS
-- ============================================================

INSERT IGNORE INTO Tipo_Rol (id_rol, nombre_rol, descripcion_rol, activo) VALUES
(1, 'Administrador', 'Acceso total al sistema', 1),
(2, 'Usuario', 'Acceso operativo a ventas, movimientos y productos', 1);

INSERT IGNORE INTO Categoria (nombre_categoria, descripcion_categoria) VALUES
('Iluminación LED', 'Lámparas y tecnología LED'),
('Iluminación Tradicional', 'Halógenas e incandescentes'),
('Cableado y Tomacorrientes', 'Tomacorrientes, fichas y cables'),
('Artefactos y Plafones', 'Plafones, paneles y apliques'),
('Control e Interruptores', 'Interruptores, dimmers y sensores'),
('Fuentes y Drivers', 'Drivers y fuentes 12/24V');

INSERT IGNORE INTO Tipo_movimiento (nombre_tipo, descripcion_tipo) VALUES
('Entrada', 'Ingreso de stock'),
('Salida', 'Egreso de stock');

INSERT IGNORE INTO Motivo_movimiento (nombre_motivo, descripcion_motivo) VALUES
('Reposición', 'Compra o reposición de stock'),
('Venta', 'Salida por venta'),
('Ajuste', 'Corrección de stock'),
('Cancelación de venta', 'Entrada automática de stock por anulación de una venta');

INSERT IGNORE INTO Medio_pago (nombre_medio) VALUES
('Efectivo'),
('Débito'),
('Crédito'),
('Transferencia');

INSERT INTO Modulo (codigo_modulo, nombre_modulo, icono_modulo, url_modulo, archivo_modulo, activo) VALUES
('panel', 'Panel de control', '🏠', 'index.php', 'panel.php', 1),
('config_usuario', 'Configuración de usuario', '👤', 'index.php?mod=config_usuario', 'config_usuario.php', 1),
('usuarios', 'Gestión de usuarios', '👥', 'index.php?mod=usuarios', 'usuarios.php', 1),
('perfiles', 'Gestión de perfiles', '🛡️', 'index.php?mod=perfiles', 'perfiles.php', 1),
('productos', 'Productos', '📦', 'index.php?mod=productos', 'productos.php', 1),
('movimientos', 'Movimientos', '📑', 'index.php?mod=movimientos', 'movimientos.php', 1),
('ventas', 'Facturación / Ventas', '🧾', 'index.php?mod=ventas', 'ventas.php', 1),
('proveedores', 'Proveedores', '🤝', 'index.php?mod=proveedores', 'proveedores.php', 1),
('reportes', 'Reportes', '📄', 'index.php?mod=reportes', 'reportes.php', 1),
('auditoria', 'Auditoría', '📜', 'index.php?mod=auditoria', 'auditoria.php', 1)
ON DUPLICATE KEY UPDATE
  nombre_modulo = VALUES(nombre_modulo),
  icono_modulo = VALUES(icono_modulo),
  url_modulo = VALUES(url_modulo),
  archivo_modulo = VALUES(archivo_modulo),
  activo = VALUES(activo);

-- Administrador: acceso total.
INSERT IGNORE INTO Rol_Modulo (id_rol, id_modulo)
SELECT 1, id_modulo FROM Modulo WHERE activo = 1;

-- Usuario/Operador: operación diaria.
INSERT IGNORE INTO Rol_Modulo (id_rol, id_modulo)
SELECT 2, id_modulo
FROM Modulo
WHERE codigo_modulo IN ('panel', 'config_usuario', 'productos', 'movimientos', 'ventas');

-- ============================================================
-- DATOS INICIALES DE PRUEBA
-- ============================================================

-- Proveedores y sus direcciones (en orden correcto para respetar FK).
INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion)
VALUES ('Argentina', 'Formosa', 'Av. 25 de Mayo 1350');
SET @dir_norte = LAST_INSERT_ID();
INSERT IGNORE INTO Proveedor (nombre_proveedor, telefono_proveedor, correo_proveedor, id_direccion)
VALUES ('Distribuidora Norte', '3704898795', 'norte@gmail.com', @dir_norte);

INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion)
VALUES ('Argentina', 'Formosa', 'Rivadavia 1020');
SET @dir_sur = LAST_INSERT_ID();
INSERT IGNORE INTO Proveedor (nombre_proveedor, telefono_proveedor, correo_proveedor, id_direccion)
VALUES ('Proveeduría Sur', '3704785215', 'sur@gmail.com', @dir_sur);

INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion)
VALUES ('Argentina', 'Formosa', 'San Martín 780');
SET @dir_electro = LAST_INSERT_ID();
INSERT IGNORE INTO Proveedor (nombre_proveedor, telefono_proveedor, correo_proveedor, id_direccion)
VALUES ('ElectroFME', '37047458658', 'electro@gmail.com', @dir_electro);

INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion)
VALUES ('Argentina', 'Resistencia', 'Av. Alberdi 1580');
SET @dir_ofi = LAST_INSERT_ID();
INSERT IGNORE INTO Proveedor (nombre_proveedor, telefono_proveedor, correo_proveedor, id_direccion)
VALUES ('OfiCenter', '3704333215', 'ofi@gmail.com', @dir_ofi);

INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion)
VALUES ('Argentina', 'Corrientes', 'Junín 845');
SET @dir_ferre = LAST_INSERT_ID();
INSERT IGNORE INTO Proveedor (nombre_proveedor, telefono_proveedor, correo_proveedor, id_direccion)
VALUES ('FerreMax', '3704225487', 'ferre@gmail.com', @dir_ferre);

INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion)
VALUES ('Argentina', 'Posadas', 'Av. Uruguay 2900');
SET @dir_alim = LAST_INSERT_ID();
INSERT IGNORE INTO Proveedor (nombre_proveedor, telefono_proveedor, correo_proveedor, id_direccion)
VALUES ('AlimFormosa', '3704665721', 'alim@gmail.com', @dir_alim);

-- Productos de ejemplo para poder probar stock, movimientos, ventas y reportes.
INSERT INTO Producto (nombre_producto, precio_producto, stock_producto, id_categoria, id_proveedor)
SELECT 'Lámpara LED 12W', 4500.00, 20, c.id_categoria, p.id_proveedor
FROM Categoria c, Proveedor p
WHERE c.nombre_categoria = 'Iluminación LED' AND p.nombre_proveedor = 'Distribuidora Norte'
  AND NOT EXISTS (SELECT 1 FROM Producto WHERE nombre_producto = 'Lámpara LED 12W');

INSERT INTO Producto (nombre_producto, precio_producto, stock_producto, id_categoria, id_proveedor)
SELECT 'Cable 2,5 mm x metro', 1200.00, 80, c.id_categoria, p.id_proveedor
FROM Categoria c, Proveedor p
WHERE c.nombre_categoria = 'Cableado y Tomacorrientes' AND p.nombre_proveedor = 'ElectroFME'
  AND NOT EXISTS (SELECT 1 FROM Producto WHERE nombre_producto = 'Cable 2,5 mm x metro');

INSERT INTO Producto (nombre_producto, precio_producto, stock_producto, id_categoria, id_proveedor)
SELECT 'Interruptor simple', 2800.00, 30, c.id_categoria, p.id_proveedor
FROM Categoria c, Proveedor p
WHERE c.nombre_categoria = 'Control e Interruptores' AND p.nombre_proveedor = 'FerreMax'
  AND NOT EXISTS (SELECT 1 FROM Producto WHERE nombre_producto = 'Interruptor simple');

-- EAN-13 interno para productos iniciales que todavía no tengan código.
UPDATE Producto p
JOIN (
  SELECT z.id_producto,
         CONCAT(z.base12, MOD(10 - MOD(
           CAST(SUBSTRING(z.base12, 1, 1) AS UNSIGNED) +
           3 * CAST(SUBSTRING(z.base12, 2, 1) AS UNSIGNED) +
           CAST(SUBSTRING(z.base12, 3, 1) AS UNSIGNED) +
           3 * CAST(SUBSTRING(z.base12, 4, 1) AS UNSIGNED) +
           CAST(SUBSTRING(z.base12, 5, 1) AS UNSIGNED) +
           3 * CAST(SUBSTRING(z.base12, 6, 1) AS UNSIGNED) +
           CAST(SUBSTRING(z.base12, 7, 1) AS UNSIGNED) +
           3 * CAST(SUBSTRING(z.base12, 8, 1) AS UNSIGNED) +
           CAST(SUBSTRING(z.base12, 9, 1) AS UNSIGNED) +
           3 * CAST(SUBSTRING(z.base12, 10, 1) AS UNSIGNED) +
           CAST(SUBSTRING(z.base12, 11, 1) AS UNSIGNED) +
           3 * CAST(SUBSTRING(z.base12, 12, 1) AS UNSIGNED)
         , 10), 10)) AS ean13
  FROM (
    SELECT id_producto, CONCAT('20', LPAD(id_producto, 10, '0')) AS base12
    FROM Producto
    WHERE codigo_barras IS NULL OR TRIM(codigo_barras) = ''
  ) z
) codes ON codes.id_producto = p.id_producto
SET p.codigo_barras = codes.ean13
WHERE p.codigo_barras IS NULL OR TRIM(p.codigo_barras) = '';

-- Administrador inicial local.
-- Usuario: Fgimenez
-- Contraseña: Admin123!
INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion)
VALUES ('Argentina', 'Formosa', 'Av. Gutnisky 590');
SET @dir_admin = LAST_INSERT_ID();

INSERT INTO Persona (nombre_persona, apellido_persona, CUIL_persona, telefono_persona, id_direccion)
SELECT 'Franco Marcelo', 'Gimenez', '204333511957', '3704610331', @dir_admin
WHERE NOT EXISTS (SELECT 1 FROM Persona WHERE CUIL_persona = '204333511957');

SET @persona_admin = (SELECT id_persona FROM Persona WHERE CUIL_persona = '204333511957' LIMIT 1);

INSERT IGNORE INTO Usuario
(nombre_usuario, correo_usuario, contraseña_usuario, id_persona, id_rol, email_verificado)
VALUES
('Fgimenez', 'fgimenez@gmail.com', '$2y$12$Fi.ng2mlaDH3VicBN2PCpe70WS93gefPHf/qfsfewNIKb06hk7T7m', @persona_admin, 1, 1);
