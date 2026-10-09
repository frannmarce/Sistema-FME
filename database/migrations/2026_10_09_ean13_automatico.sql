-- ============================================================
-- SISTEMA FME - EAN-13 AUTOMÁTICO PARA PRODUCTOS EXISTENTES
-- Fecha: 2026-10-09
--
-- Requisito previo:
--   Haber ejecutado 2026_10_06_codigo_barras_paginado.sql
--
-- Esta migración NO agrega ni modifica columnas.
-- Solo completa codigo_barras en productos que todavía lo tengan NULL/vacío.
-- Los códigos ya cargados se conservan exactamente como están.
--
-- FME usa primero el prefijo interno 20. Si ese candidato ya estuviera
-- ocupado, prueba 21...29. Todos pertenecen al rango interno reservado
-- para este uso dentro del sistema; no representan un GTIN comercial GS1.
-- ============================================================

DROP TEMPORARY TABLE IF EXISTS tmp_fme_ean13_candidatos;
DROP TEMPORARY TABLE IF EXISTS tmp_fme_ean13_asignacion;

CREATE TEMPORARY TABLE tmp_fme_ean13_candidatos (
    id_producto INT NOT NULL,
    prefijo CHAR(2) NOT NULL,
    base12 CHAR(12) NOT NULL,
    codigo_ean13 CHAR(13) NULL,
    PRIMARY KEY (id_producto, prefijo)
) ENGINE=InnoDB;

INSERT INTO tmp_fme_ean13_candidatos (id_producto, prefijo, base12)
SELECT
    p.id_producto,
    pref.prefijo,
    CONCAT(pref.prefijo, LPAD(p.id_producto, 10, '0')) AS base12
FROM Producto p
CROSS JOIN (
    SELECT '20' AS prefijo UNION ALL
    SELECT '21' UNION ALL
    SELECT '22' UNION ALL
    SELECT '23' UNION ALL
    SELECT '24' UNION ALL
    SELECT '25' UNION ALL
    SELECT '26' UNION ALL
    SELECT '27' UNION ALL
    SELECT '28' UNION ALL
    SELECT '29'
) pref
WHERE p.codigo_barras IS NULL OR TRIM(p.codigo_barras) = '';

-- Dígito verificador EAN-13:
-- (10 - ((d1 + 3*d2 + d3 + 3*d4 + ... + 3*d12) MOD 10)) MOD 10
UPDATE tmp_fme_ean13_candidatos
SET codigo_ean13 = CONCAT(
    base12,
    MOD(
        10 - MOD(
            CAST(SUBSTRING(base12, 1, 1) AS UNSIGNED) +
            3 * CAST(SUBSTRING(base12, 2, 1) AS UNSIGNED) +
            CAST(SUBSTRING(base12, 3, 1) AS UNSIGNED) +
            3 * CAST(SUBSTRING(base12, 4, 1) AS UNSIGNED) +
            CAST(SUBSTRING(base12, 5, 1) AS UNSIGNED) +
            3 * CAST(SUBSTRING(base12, 6, 1) AS UNSIGNED) +
            CAST(SUBSTRING(base12, 7, 1) AS UNSIGNED) +
            3 * CAST(SUBSTRING(base12, 8, 1) AS UNSIGNED) +
            CAST(SUBSTRING(base12, 9, 1) AS UNSIGNED) +
            3 * CAST(SUBSTRING(base12, 10, 1) AS UNSIGNED) +
            CAST(SUBSTRING(base12, 11, 1) AS UNSIGNED) +
            3 * CAST(SUBSTRING(base12, 12, 1) AS UNSIGNED),
            10
        ),
        10
    )
);

CREATE TEMPORARY TABLE tmp_fme_ean13_asignacion (
    id_producto INT NOT NULL PRIMARY KEY,
    codigo_ean13 CHAR(13) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Para cada producto toma el primer prefijo 20-29 cuyo EAN-13 no esté
-- ocupado por otro producto.
INSERT INTO tmp_fme_ean13_asignacion (id_producto, codigo_ean13)
SELECT
    c.id_producto,
    MIN(c.codigo_ean13) AS codigo_ean13
FROM tmp_fme_ean13_candidatos c
LEFT JOIN Producto existente
    ON existente.codigo_barras = c.codigo_ean13
   AND existente.id_producto <> c.id_producto
WHERE existente.id_producto IS NULL
GROUP BY c.id_producto;

UPDATE Producto p
INNER JOIN tmp_fme_ean13_asignacion a
    ON a.id_producto = p.id_producto
SET p.codigo_barras = a.codigo_ean13
WHERE p.codigo_barras IS NULL OR TRIM(p.codigo_barras) = '';

-- Resultado de control: lo normal es que "sin_codigo" sea 0.
SELECT COUNT(*) AS productos_actualizados
FROM tmp_fme_ean13_asignacion;

SELECT COUNT(*) AS sin_codigo
FROM Producto
WHERE codigo_barras IS NULL OR TRIM(codigo_barras) = '';

SELECT id_producto, nombre_producto, codigo_barras
FROM Producto
ORDER BY id_producto;

DROP TEMPORARY TABLE IF EXISTS tmp_fme_ean13_asignacion;
DROP TEMPORARY TABLE IF EXISTS tmp_fme_ean13_candidatos;
