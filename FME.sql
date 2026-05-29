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



