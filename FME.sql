CREATE DATABASE fme1;
USE fme1;

CREATE TABLE Direccion (
    id_direccion INT AUTO_INCREMENT PRIMARY KEY,
    nombre_pais VARCHAR(50) NOT NULL,
    nombre_ciudad VARCHAR(50) NOT NULL,
    num_direccion VARCHAR(100) NOT NULL
);

CREATE TABLE Persona (
    id_persona INT AUTO_INCREMENT PRIMARY KEY,
    nombre_persona VARCHAR(50) NOT NULL,
    apellido_persona VARCHAR(50) NOT NULL,
    CUIL_persona VARCHAR(20) NOT NULL,
    telefono_persona VARCHAR(20),
    id_direccion INT,
    FOREIGN KEY (id_direccion) REFERENCES Direccion(id_direccion)
);

CREATE TABLE Tipo_Rol (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL,
    descripcion_rol VARCHAR(100)
);

CREATE TABLE Usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre_usuario VARCHAR(50) NOT NULL,
    correo_usuario VARCHAR(80) NOT NULL,
    contraseña_usuario VARCHAR(100) NOT NULL,
    id_persona INT,
    id_rol INT,
    FOREIGN KEY (id_persona) REFERENCES Persona(id_persona),
    FOREIGN KEY (id_rol) REFERENCES Tipo_Rol(id_rol)
);

CREATE TABLE Categoria (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre_categoria VARCHAR(50) NOT NULL,
    descripcion_categoria VARCHAR(100)
);

CREATE TABLE Proveedor (
    id_proveedor INT AUTO_INCREMENT PRIMARY KEY,
    nombre_proveedor VARCHAR(80) NOT NULL,
    telefono_proveedor VARCHAR(30),
    correo_proveedor VARCHAR(80),
    id_direccion INT,
    FOREIGN KEY (id_direccion) REFERENCES Direccion(id_direccion)
);

CREATE TABLE Producto (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    nombre_producto VARCHAR(80) NOT NULL,
    precio_producto DECIMAL(10,2) NOT NULL,
    stock_producto INT NOT NULL,
    id_categoria INT,
    id_proveedor INT,
    FOREIGN KEY (id_categoria) REFERENCES Categoria(id_categoria),
    FOREIGN KEY (id_proveedor) REFERENCES Proveedor(id_proveedor)
);

CREATE TABLE Tipo_movimiento (
    id_tipo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_tipo VARCHAR(50) NOT NULL,
    descripcion_tipo VARCHAR(100)
);

CREATE TABLE Motivo_movimiento (
    id_motivo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_motivo VARCHAR(50) NOT NULL,
    descripcion_motivo VARCHAR(100)
);

CREATE TABLE Movimiento (
    id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
    cantidad_movimiento INT NOT NULL,
    fecha_movimiento DATETIME NOT NULL,
    id_producto INT,
    id_usuario INT,
    id_tipo INT,
    id_motivo INT,
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto),
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario),
    FOREIGN KEY (id_tipo) REFERENCES Tipo_movimiento(id_tipo),
    FOREIGN KEY (id_motivo) REFERENCES Motivo_movimiento(id_motivo)
);

CREATE TABLE Medio_pago (
    id_medio INT AUTO_INCREMENT PRIMARY KEY,
    nombre_medio VARCHAR(50) NOT NULL
);

CREATE TABLE Venta (
    id_venta INT AUTO_INCREMENT PRIMARY KEY,
    fecha_venta DATETIME NOT NULL,
    total_venta DECIMAL(12,2) NOT NULL,
    id_usuario INT,
    id_medio INT,
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario),
    FOREIGN KEY (id_medio) REFERENCES Medio_pago(id_medio)
);

CREATE TABLE Detalle_venta (
    id_venta INT,
    id_producto INT,
    cantidad_detalle INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id_venta, id_producto),
    FOREIGN KEY (id_venta) REFERENCES Venta(id_venta),
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto)
);

INSERT INTO Categoria (nombre_categoria, descripcion_categoria) VALUES
('Iluminación LED',        'Lámparas y tecnología LED'),
('Iluminación Tradicional','Halógenas e incandescentes'),
('Cableado y Tomacorrientes','Tomacorrientes, fichas y cables'),
('Artefactos y Plafones',  'Plafones, paneles, apliques'),
('Control e Interruptores','Interruptores, dimmers, sensores'),
('Fuentes y Drivers',      'Drivers y fuentes 12/24V');

INSERT INTO Proveedor (nombre_proveedor, telefono_proveedor, correo_proveedor, id_direccion) VALUES
('Distribuidora Norte', '3704898795', 'norte@gmail.com', 1),
('Proveeduría Sur', '3704785215', 'sur@gmail.com', 2),
('ElectroFME', '37047458658', 'electro@gmail.com', 3),
('OfiCenter', '3704333215', 'ofi@gmail.com', 4),
('FerreMax', '3704225487', 'ferre@gmail.com', 5),
('AlimFormosa', '3704665721', 'alim@gmail.com', 6);

INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion) VALUES
('Argentina', 'Formosa', 'Av. 25 de Mayo 1350'),
('Argentina', 'Formosa', 'Rivadavia 1020'),
('Argentina', 'Formosa', 'San Martín 780'),
('Argentina', 'Resistencia', 'Av. Alberdi 1580'),        
('Argentina', 'Corrientes', 'Junín 845'),                
('Argentina', 'Posadas', 'Av. Uruguay 2900');            

INSERT INTO Tipo_movimiento (nombre_tipo, descripcion_tipo) VALUES
('Entrada', 'Ingreso de stock'),
('Salida', 'Egreso de stock');

INSERT INTO Motivo_movimiento (nombre_motivo, descripcion_motivo) VALUES
('Reposición', 'Compra o reposición de stock'),
('Venta', 'Salida por venta'),
('Ajuste', 'Corrección de stock');

INSERT INTO Venta (fecha_venta, total_venta)
VALUES
('2025-06-06 10:00:00', 9000.00),
('2025-11-23 16:30:00', 3000.00),
('2025-07-05 11:15:00', 7500.00),
('2025-07-19 09:30:00', 15000.00),
('2025-02-07 20:45:00', 25000.00),
('2025-12-10 21:10:00', 6000.00),
('2025-05-20 17:35:00', 19000.00);

INSERT INTO Tipo_Rol (nombre_rol, descripcion_rol) VALUES
('Administrador', 'Acceso total al sistema'),
('Usuario', 'Acceso limitado al sistema');

INSERT INTO Direccion (nombre_pais, nombre_ciudad, num_direccion) VALUES
('Argentina', 'Formosa', 'Av.Gutnisky 590');

INSERT INTO Persona (nombre_persona, apellido_persona, CUIL_persona, telefono_persona, id_direccion) VALUES
('Franco Marcelo', 'Gimenez', '204333511957', '3704610331', 8);

INSERT INTO Usuario (nombre_usuario, correo_usuario, contraseña_usuario, id_persona, id_rol) VALUES
('Fgimenez', 'fgimenez@gmail.com', 'admin123', 1, 1);

UPDATE Usuario
SET contraseña_usuario = '$2y$10$rPIlHBXAWbo2pdfxx3rMCOvMGSPM0kilf96Hrb7AhM1cyWt1sITRG'
WHERE nombre_usuario = 'Fgimenez';

DELETE FROM Usuario
WHERE nombre_usuario = 'Lgomez';

ALTER TABLE Usuario
ADD email_verificado TINYINT(1) NOT NULL DEFAULT 0,
ADD token_email VARCHAR(64) NULL,
ADD token_email_expira DATETIME NULL,
ADD token_password VARCHAR(64) NULL,
ADD token_password_expira DATETIME NULL,
ADD INDEX idx_usuario_token_email (token_email),
ADD INDEX idx_usuario_token_password (token_password);

UPDATE Usuario
SET email_verificado = 1
WHERE email_verificado = 0;

SELECT id_usuario, nombre_usuario, id_persona, email_verificado
FROM Usuario
WHERE nombre_usuario = 'Mgimenez';

ALTER TABLE Tipo_Rol
ADD activo TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS Modulo (
  id_modulo INT AUTO_INCREMENT PRIMARY KEY,
  codigo_modulo VARCHAR(50) NOT NULL UNIQUE,
  nombre_modulo VARCHAR(80) NOT NULL,
  icono_modulo VARCHAR(10),
  url_modulo VARCHAR(150) NOT NULL,
  archivo_modulo VARCHAR(80) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS Rol_Modulo (
  id_rol INT NOT NULL,
  id_modulo INT NOT NULL,
  PRIMARY KEY (id_rol, id_modulo),
  FOREIGN KEY (id_rol) REFERENCES Tipo_Rol(id_rol),
  FOREIGN KEY (id_modulo) REFERENCES Modulo(id_modulo)
);

INSERT INTO Modulo (codigo_modulo, nombre_modulo, icono_modulo, url_modulo, archivo_modulo, activo) VALUES
('panel', 'Panel de control', '🏠', 'index.php', 'panel.php', 1),
('config_usuario', 'Configuración de usuario', '👤', 'index.php?mod=config_usuario', 'config_usuario.php', 1),
('usuarios', 'Gestión de usuarios', '👥', 'index.php?mod=usuarios', 'usuarios.php', 1),
('perfiles', 'Gestión de perfiles', '🛡️', 'index.php?mod=perfiles', 'perfiles.php', 1),
('productos', 'Productos', '📦', 'index.php?mod=productos', 'productos.php', 1),
('movimientos', 'Movimientos', '📑', 'index.php?mod=movimientos', 'movimientos.php', 1),
('ventas', 'Facturación / Ventas', '🧾', 'index.php?mod=ventas', 'ventas.php', 1),
('proveedores', 'Proveedores', '🤝', '#', NULL, 1),
('reportes', 'Reportes', '📄', '#', NULL, 1)
ON DUPLICATE KEY UPDATE
  nombre_modulo = VALUES(nombre_modulo),
  icono_modulo = VALUES(icono_modulo),
  url_modulo = VALUES(url_modulo),
  archivo_modulo = VALUES(archivo_modulo),
  activo = VALUES(activo);

INSERT IGNORE INTO Rol_Modulo (id_rol, id_modulo)
SELECT 1, id_modulo FROM Modulo;

INSERT IGNORE INTO Rol_Modulo (id_rol, id_modulo)
SELECT 2, id_modulo
FROM Modulo
WHERE codigo_modulo IN ('panel', 'config_usuario', 'productos', 'movimientos', 'ventas');

SELECT * FROM Modulo;
SELECT * FROM Rol_Modulo;




