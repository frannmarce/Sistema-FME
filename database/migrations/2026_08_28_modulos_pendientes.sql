-- Ejecutar sobre una base EXISTENTE después de seleccionar la base correcta
-- en MySQL Workbench/phpMyAdmin (por ejemplo fme1 o fme_prueba).

UPDATE Modulo
SET url_modulo = 'index.php?mod=proveedores', archivo_modulo = 'proveedores.php', activo = 1
WHERE codigo_modulo = 'proveedores';

UPDATE Modulo
SET url_modulo = 'index.php?mod=reportes', archivo_modulo = 'reportes.php', activo = 1
WHERE codigo_modulo = 'reportes';

UPDATE Modulo
SET url_modulo = 'index.php?mod=ventas', archivo_modulo = 'ventas.php', activo = 1
WHERE codigo_modulo = 'ventas';

INSERT INTO Modulo (codigo_modulo, nombre_modulo, icono_modulo, url_modulo, archivo_modulo, activo)
SELECT 'proveedores', 'Proveedores', '🤝', 'index.php?mod=proveedores', 'proveedores.php', 1
WHERE NOT EXISTS (SELECT 1 FROM Modulo WHERE codigo_modulo = 'proveedores');

INSERT INTO Modulo (codigo_modulo, nombre_modulo, icono_modulo, url_modulo, archivo_modulo, activo)
SELECT 'reportes', 'Reportes', '📄', 'index.php?mod=reportes', 'reportes.php', 1
WHERE NOT EXISTS (SELECT 1 FROM Modulo WHERE codigo_modulo = 'reportes');

INSERT INTO Modulo (codigo_modulo, nombre_modulo, icono_modulo, url_modulo, archivo_modulo, activo)
SELECT 'ventas', 'Facturación / Ventas', '🧾', 'index.php?mod=ventas', 'ventas.php', 1
WHERE NOT EXISTS (SELECT 1 FROM Modulo WHERE codigo_modulo = 'ventas');

INSERT IGNORE INTO Rol_Modulo (id_rol, id_modulo)
SELECT 1, id_modulo FROM Modulo WHERE codigo_modulo IN ('proveedores', 'reportes', 'ventas');

INSERT IGNORE INTO Rol_Modulo (id_rol, id_modulo)
SELECT 2, id_modulo FROM Modulo WHERE codigo_modulo = 'ventas';

INSERT INTO Medio_pago (nombre_medio)
SELECT 'Efectivo' WHERE NOT EXISTS (SELECT 1 FROM Medio_pago WHERE LOWER(nombre_medio) = 'efectivo');
INSERT INTO Medio_pago (nombre_medio)
SELECT 'Débito' WHERE NOT EXISTS (SELECT 1 FROM Medio_pago WHERE LOWER(nombre_medio) IN ('débito', 'debito'));
INSERT INTO Medio_pago (nombre_medio)
SELECT 'Crédito' WHERE NOT EXISTS (SELECT 1 FROM Medio_pago WHERE LOWER(nombre_medio) IN ('crédito', 'credito'));
INSERT INTO Medio_pago (nombre_medio)
SELECT 'Transferencia' WHERE NOT EXISTS (SELECT 1 FROM Medio_pago WHERE LOWER(nombre_medio) = 'transferencia');
