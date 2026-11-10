-- =============================================================================
-- Borra el catálogo de prueba de productos_demo_m3.sql (tablas de M3)
-- =============================================================================
-- Solo borra filas con la marca de prueba (created_at = '2000-01-01 00:00:00';
-- las variaciones además con SKU 'DEMO-%'). Antes borra lo que M1 haya creado
-- sobre esos productos (destacados, promociones y sus enlaces), porque tienen
-- llaves foráneas hacia ellos. No toca datos reales.
--
-- Si M5 (carritos o pedidos) u otro módulo ya usa alguno de estos productos, el
-- borrado falla por sus llaves foráneas y no se borra nada (transacción).
-- =============================================================================

BEGIN;

CREATE TEMP TABLE demo_productos ON COMMIT DROP AS
SELECT id_producto FROM productos WHERE created_at = timestamp '2000-01-01 00:00:00';

-- M1: lo que referencia a esos productos.
DELETE FROM noticia_promociones
 WHERE id_promocion IN (SELECT id_promocion FROM promociones WHERE id_producto IN (SELECT id_producto FROM demo_productos));
DELETE FROM promociones          WHERE id_producto IN (SELECT id_producto FROM demo_productos);
DELETE FROM cargos_destacado     WHERE id_producto IN (SELECT id_producto FROM demo_productos);
DELETE FROM productos_destacados WHERE id_producto IN (SELECT id_producto FROM demo_productos);
DELETE FROM solicitudes_destacado WHERE id_producto IN (SELECT id_producto FROM demo_productos);

-- M3: catálogo de prueba (variaciones, atributos y relacionados se borran en cascada).
DELETE FROM productos  WHERE id_producto IN (SELECT id_producto FROM demo_productos);
DELETE FROM marcas     WHERE created_at = timestamp '2000-01-01 00:00:00';
DELETE FROM categorias WHERE created_at = timestamp '2000-01-01 00:00:00' AND id_categoria_padre IS NOT NULL;
DELETE FROM categorias WHERE created_at = timestamp '2000-01-01 00:00:00';

COMMIT;
