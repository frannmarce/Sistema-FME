CREATE DATABASE IF NOT EXISTS fme1
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE fme1;
SET NAMES utf8mb4;

-- ============================================================
-- 1. PERSONAS, ROLES Y USUARIOS
-- ============================================================

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
      ON DELETE SET NULL
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
    activo_usuario TINYINT(1) NOT NULL DEFAULT 1,
    email_verificado TINYINT(1) NOT NULL DEFAULT 0,
    token_email VARCHAR(64) NULL,
    token_email_expira DATETIME NULL,
    token_password VARCHAR(64) NULL,
    token_password_expira DATETIME NULL,
    CONSTRAINT uq_usuario_nombre UNIQUE (nombre_usuario),
    CONSTRAINT uq_usuario_correo UNIQUE (correo_usuario),
    CONSTRAINT fk_usuario_persona
      FOREIGN KEY (id_persona) REFERENCES Persona(id_persona)
      ON DELETE SET NULL,
    CONSTRAINT fk_usuario_rol
      FOREIGN KEY (id_rol) REFERENCES Tipo_Rol(id_rol),
    INDEX idx_usuario_activo (activo_usuario),
    INDEX idx_usuario_token_email (token_email),
    INDEX idx_usuario_token_password (token_password)
) ENGINE=InnoDB;

-- ============================================================
-- 2. PRODUCTOS Y PROVEEDORES
-- ============================================================

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
    activo_proveedor TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT uq_proveedor_nombre UNIQUE (nombre_proveedor),
    CONSTRAINT fk_proveedor_direccion
      FOREIGN KEY (id_direccion) REFERENCES Direccion(id_direccion)
      ON DELETE SET NULL,
    INDEX idx_proveedor_activo (activo_proveedor)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Producto (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    nombre_producto VARCHAR(80) NOT NULL,
    codigo_barras VARCHAR(64) NULL,
    precio_producto DECIMAL(10,2) NOT NULL,
    stock_producto INT NOT NULL DEFAULT 0,
    activo_producto TINYINT(1) NOT NULL DEFAULT 1,
    id_categoria INT NOT NULL,
    id_proveedor INT NOT NULL,
    CONSTRAINT uq_producto_codigo_barras UNIQUE (codigo_barras),
    CONSTRAINT fk_producto_categoria
      FOREIGN KEY (id_categoria) REFERENCES Categoria(id_categoria),
    CONSTRAINT fk_producto_proveedor
      FOREIGN KEY (id_proveedor) REFERENCES Proveedor(id_proveedor),
    INDEX idx_producto_activo (activo_producto)
) ENGINE=InnoDB;

-- ============================================================
-- 3. MOVIMIENTOS DE STOCK
-- ============================================================

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

-- ============================================================
-- 4. MEDIOS DE PAGO
-- ============================================================

CREATE TABLE IF NOT EXISTS Medio_pago (
    id_medio INT AUTO_INCREMENT PRIMARY KEY,
    nombre_medio VARCHAR(50) NOT NULL,
    CONSTRAINT uq_medio_pago_nombre UNIQUE (nombre_medio)
) ENGINE=InnoDB;

-- ============================================================
-- 5. MÓDULOS Y PERMISOS
-- ============================================================

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
      FOREIGN KEY (id_rol) REFERENCES Tipo_Rol(id_rol)
      ON DELETE CASCADE,
    CONSTRAINT fk_rol_modulo_modulo
      FOREIGN KEY (id_modulo) REFERENCES Modulo(id_modulo)
      ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 6. AUDITORÍA
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

-- ============================================================
-- 7. CAJA POR TURNOS
-- ============================================================

CREATE TABLE IF NOT EXISTS Caja (
    id_caja INT AUTO_INCREMENT PRIMARY KEY,
    fecha_caja DATE NOT NULL,
    numero_turno INT NOT NULL DEFAULT 1,
    estado_caja VARCHAR(20) NOT NULL DEFAULT 'ABIERTA',
    fecha_apertura DATETIME NOT NULL,
    id_usuario_apertura INT NOT NULL,
    monto_apertura DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    observacion_apertura VARCHAR(255) NULL,
    fecha_cierre DATETIME NULL,
    id_usuario_cierre INT NULL,
    efectivo_esperado DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    efectivo_contado DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    diferencia DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    observacion_cierre VARCHAR(255) NULL,
    CONSTRAINT uq_caja_fecha_turno UNIQUE (fecha_caja, numero_turno),
    CONSTRAINT fk_caja_usuario_apertura
      FOREIGN KEY (id_usuario_apertura) REFERENCES Usuario(id_usuario),
    CONSTRAINT fk_caja_usuario_cierre
      FOREIGN KEY (id_usuario_cierre) REFERENCES Usuario(id_usuario)
      ON DELETE SET NULL,
    INDEX idx_caja_estado (estado_caja),
    INDEX idx_caja_fecha (fecha_caja)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Caja_Denominacion (
    id_caja INT NOT NULL,
    denominacion DECIMAL(12,2) NOT NULL,
    cantidad INT UNSIGNED NOT NULL DEFAULT 0,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id_caja, denominacion),
    CONSTRAINT fk_caja_denominacion_caja
      FOREIGN KEY (id_caja) REFERENCES Caja(id_caja)
      ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Caja_Movimiento (
    id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
    id_caja INT NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    monto DECIMAL(14,2) NOT NULL,
    motivo VARCHAR(80) NOT NULL,
    descripcion VARCHAR(255) NULL,
    id_usuario INT NOT NULL,
    fecha_movimiento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_caja_movimiento_caja
      FOREIGN KEY (id_caja) REFERENCES Caja(id_caja)
      ON DELETE CASCADE,
    CONSTRAINT fk_caja_movimiento_usuario
      FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario),
    INDEX idx_caja_movimiento_caja (id_caja),
    INDEX idx_caja_movimiento_tipo (tipo),
    INDEX idx_caja_movimiento_fecha (fecha_movimiento)
) ENGINE=InnoDB;

-- ============================================================
-- 8. VENTAS, PAGOS Y CANCELACIONES
-- ============================================================

CREATE TABLE IF NOT EXISTS Venta (
    id_venta INT AUTO_INCREMENT PRIMARY KEY,
    fecha_venta DATETIME NOT NULL,
    total_venta DECIMAL(12,2) NOT NULL,
    estado_venta VARCHAR(20) NOT NULL DEFAULT 'ACTIVA',
    id_usuario INT NOT NULL,
    id_medio INT NOT NULL,
    id_caja INT NULL,
    CONSTRAINT fk_venta_usuario
      FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario),
    CONSTRAINT fk_venta_medio
      FOREIGN KEY (id_medio) REFERENCES Medio_pago(id_medio),
    CONSTRAINT fk_venta_caja
      FOREIGN KEY (id_caja) REFERENCES Caja(id_caja)
      ON DELETE SET NULL,
    INDEX idx_venta_fecha (fecha_venta),
    INDEX idx_venta_estado (estado_venta),
    INDEX idx_venta_caja (id_caja)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Venta_Pago (
    id_venta INT NOT NULL,
    id_medio INT NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    monto_entregado DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id_venta, id_medio),
    CONSTRAINT fk_venta_pago_venta
      FOREIGN KEY (id_venta) REFERENCES Venta(id_venta)
      ON DELETE CASCADE,
    CONSTRAINT fk_venta_pago_medio
      FOREIGN KEY (id_medio) REFERENCES Medio_pago(id_medio),
    INDEX idx_venta_pago_medio (id_medio)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Detalle_venta (
    id_venta INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad_detalle INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id_venta, id_producto),
    CONSTRAINT fk_detalle_venta
      FOREIGN KEY (id_venta) REFERENCES Venta(id_venta)
      ON DELETE CASCADE,
    CONSTRAINT fk_detalle_producto
      FOREIGN KEY (id_producto) REFERENCES Producto(id_producto)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Cancelacion_Venta (
    id_cancelacion INT AUTO_INCREMENT PRIMARY KEY,
    id_venta INT NOT NULL,
    id_caja_reintegro INT NULL,
    fecha_cancelacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    monto_reintegrado DECIMAL(12,2) NOT NULL,
    motivo_cancelacion VARCHAR(255) NOT NULL,
    id_usuario INT NULL,
    CONSTRAINT uq_cancelacion_venta UNIQUE (id_venta),
    CONSTRAINT fk_cancelacion_venta
      FOREIGN KEY (id_venta) REFERENCES Venta(id_venta),
    CONSTRAINT fk_cancelacion_caja_reintegro
      FOREIGN KEY (id_caja_reintegro) REFERENCES Caja(id_caja)
      ON DELETE SET NULL,
    CONSTRAINT fk_cancelacion_usuario
      FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario)
      ON DELETE SET NULL,
    INDEX idx_cancelacion_fecha (fecha_cancelacion),
    INDEX idx_cancelacion_usuario (id_usuario),
    INDEX idx_cancelacion_caja_reintegro (id_caja_reintegro)
) ENGINE=InnoDB;

-- ============================================================
-- 9. HISTORIAL DE MOVIMIENTOS DE STOCK
-- ============================================================

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
    CONSTRAINT fk_movimiento_venta
      FOREIGN KEY (id_venta) REFERENCES Venta(id_venta)
      ON DELETE SET NULL,
    INDEX idx_movimiento_fecha (fecha_movimiento),
    INDEX idx_movimiento_origen (origen_movimiento),
    INDEX idx_movimiento_venta (id_venta)
) ENGINE=InnoDB;

-- ============================================================
-- 10. DATOS MAESTROS
-- ============================================================

-- Los roles 1 y 2 se conservan porque el registro público usa el rol 2
-- y el Administrador principal se identifica como rol 1.
INSERT IGNORE INTO Tipo_Rol
  (id_rol, nombre_rol, descripcion_rol, activo)
VALUES
  (1, 'Administrador', 'Acceso total al sistema', 1),
  (2, 'Usuario', 'Acceso operativo al sistema', 1);

INSERT IGNORE INTO Categoria
  (nombre_categoria, descripcion_categoria)
VALUES
  ('Iluminación LED', 'Lámparas y tecnología LED'),
  ('Iluminación Tradicional', 'Halógenas e incandescentes'),
  ('Cableado y Tomacorrientes', 'Tomacorrientes, fichas y cables'),
  ('Artefactos y Plafones', 'Plafones, paneles y apliques'),
  ('Control e Interruptores', 'Interruptores, dimmers y sensores'),
  ('Fuentes y Drivers', 'Drivers y fuentes 12/24V');

INSERT IGNORE INTO Tipo_movimiento
  (nombre_tipo, descripcion_tipo)
VALUES
  ('Entrada', 'Ingreso de stock'),
  ('Salida', 'Egreso de stock');

INSERT IGNORE INTO Motivo_movimiento
  (nombre_motivo, descripcion_motivo)
VALUES
  ('Reposición', 'Compra o reposición de stock'),
  ('Venta', 'Salida automática de stock por venta'),
  ('Ajuste', 'Corrección manual de stock'),
  ('Cancelación de venta', 'Entrada automática de stock por anulación de una venta');

-- "Mixto" es interno: los importes reales se guardan en Venta_Pago.
INSERT IGNORE INTO Medio_pago (nombre_medio)
VALUES
  ('Efectivo'),
  ('Débito'),
  ('Crédito'),
  ('Transferencia'),
  ('Mixto');

INSERT INTO Modulo
  (codigo_modulo, nombre_modulo, icono_modulo, url_modulo, archivo_modulo, activo)
VALUES
  ('panel', 'Panel de control', '🏠', 'index.php', 'panel.php', 1),
  ('config_usuario', 'Configuración de usuario', '👤', 'index.php?mod=config_usuario', 'config_usuario.php', 1),
  ('usuarios', 'Gestión de usuarios', '👥', 'index.php?mod=usuarios', 'usuarios.php', 1),
  ('perfiles', 'Gestión de perfiles', '🛡️', 'index.php?mod=perfiles', 'perfiles.php', 1),
  ('productos', 'Productos', '📦', 'index.php?mod=productos', 'productos.php', 1),
  ('movimientos', 'Movimientos', '📑', 'index.php?mod=movimientos', 'movimientos.php', 1),
  ('ventas', 'Facturación / Ventas', '🧾', 'index.php?mod=ventas', 'ventas.php', 1),
  ('proveedores', 'Proveedores', '🤝', 'index.php?mod=proveedores', 'proveedores.php', 1),
  ('reportes', 'Reportes', '📄', 'index.php?mod=reportes', 'reportes.php', 1),
  ('auditoria', 'Auditoría', '📜', 'index.php?mod=auditoria', 'auditoria.php', 1),
  ('caja', 'Caja', '💵', 'index.php?mod=caja', 'caja.php', 1)
ON DUPLICATE KEY UPDATE
  nombre_modulo = VALUES(nombre_modulo),
  icono_modulo = VALUES(icono_modulo),
  url_modulo = VALUES(url_modulo),
  archivo_modulo = VALUES(archivo_modulo),
  activo = VALUES(activo);

-- Administrador: acceso total.
INSERT IGNORE INTO Rol_Modulo (id_rol, id_modulo)
SELECT 1, id_modulo
FROM Modulo
WHERE activo = 1;

-- Usuario/Operador: operación diaria.
-- Caja se incluye porque todo perfil que puede vender necesita administrar su turno.
INSERT IGNORE INTO Rol_Modulo (id_rol, id_modulo)
SELECT 2, id_modulo
FROM Modulo
WHERE codigo_modulo IN (
  'panel',
  'config_usuario',
  'productos',
  'movimientos',
  'ventas',
  'caja'
);

-- ============================================================
-- 11. DATOS INICIALES DEL PROYECTO
-- ============================================================

-- Proveedores de ejemplo. Las direcciones se crean primero para respetar las FK.

INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion)
VALUES ('Argentina', 'Formosa', 'Av. 25 de Mayo 1350');
SET @dir_norte = LAST_INSERT_ID();
INSERT INTO Proveedor (nombre_proveedor, telefono_proveedor, correo_proveedor, id_direccion)
VALUES ('Distribuidora Norte', '3704898795', 'norte@gmail.com', @dir_norte);

INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion)
VALUES ('Argentina', 'Formosa', 'Rivadavia 1020');
SET @dir_sur = LAST_INSERT_ID();
INSERT INTO Proveedor (nombre_proveedor, telefono_proveedor, correo_proveedor, id_direccion)
VALUES ('Proveeduría Sur', '3704785215', 'sur@gmail.com', @dir_sur);

INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion)
VALUES ('Argentina', 'Formosa', 'San Martín 780');
SET @dir_electro = LAST_INSERT_ID();
INSERT INTO Proveedor (nombre_proveedor, telefono_proveedor, correo_proveedor, id_direccion)
VALUES ('ElectroFME', '37047458658', 'electro@gmail.com', @dir_electro);

INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion)
VALUES ('Argentina', 'Resistencia', 'Av. Alberdi 1580');
SET @dir_ofi = LAST_INSERT_ID();
INSERT INTO Proveedor (nombre_proveedor, telefono_proveedor, correo_proveedor, id_direccion)
VALUES ('OfiCenter', '3704333215', 'ofi@gmail.com', @dir_ofi);

INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion)
VALUES ('Argentina', 'Corrientes', 'Junín 845');
SET @dir_ferre = LAST_INSERT_ID();
INSERT INTO Proveedor (nombre_proveedor, telefono_proveedor, correo_proveedor, id_direccion)
VALUES ('FerreMax', '3704225487', 'ferre@gmail.com', @dir_ferre);

INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion)
VALUES ('Argentina', 'Posadas', 'Av. Uruguay 2900');
SET @dir_alim = LAST_INSERT_ID();
INSERT INTO Proveedor (nombre_proveedor, telefono_proveedor, correo_proveedor, id_direccion)
VALUES ('AlimFormosa', '3704665721', 'alim@gmail.com', @dir_alim);

INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion)
VALUES ('Argentina', 'Formosa', 'Av. Gutnisky 590');
SET @dir_admin = LAST_INSERT_ID();

INSERT INTO Persona
  (nombre_persona, apellido_persona, CUIL_persona, telefono_persona, id_direccion)
VALUES
  ('Franco Marcelo', 'Gimenez', '204333511957', '3704610331', @dir_admin);

SET @persona_admin = LAST_INSERT_ID();

INSERT INTO Usuario
  (nombre_usuario, correo_usuario, contraseña_usuario, id_persona, id_rol, email_verificado)
VALUES
  (
    'Fgimenez',
    'fgimenez@gmail.com',
    '$2y$12$Fi.ng2mlaDH3VicBN2PCpe70WS93gefPHf/qfsfewNIKb06hk7T7m',
    @persona_admin,
    1,
    1
  );

-- ============================================================
-- FIN
-- ============================================================
-- Estado final incorporado directamente:
--   * verificación de email y recuperación de contraseña;
--   * perfiles y permisos;
--   * baja lógica y código de barras de productos;
--   * auditoría;
--   * cancelaciones/reintegros;
--   * caja por turnos;
--   * pagos mixtos y vuelto;
--   * reintegros asociados al turno donde se efectúan.
--
-- No contiene migraciones históricas, SELECT de control ni ventas ficticias antiguas.
-- ============================================================