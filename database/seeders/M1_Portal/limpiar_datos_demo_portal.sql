-- =============================================================================
-- M1 Portal público — Borra los datos de demostración de datos_demo_portal.sql
-- =============================================================================
-- Solo borra filas de tablas de M1 con la marca de demostración:
--   * noticias_portal con slug_noticia que empieza con 'demo-' (y sus enlaces a
--     promociones, que se borran en cascada).
--   * comercios_destacados y tarifas_destacado con created_at = '2000-01-01 00:00:00'.
-- No toca datos reales ni tablas de otros módulos.
--
-- Una tarifa de demostración que ya se usó en una solicitud de destacado no se
-- borra aunque tenga la marca: las solicitudes guardan su propio monto, pero
-- se conserva para no confundir a la administración. Revisarla a mano si hace falta.
-- =============================================================================

BEGIN;

DELETE FROM noticias_portal
 WHERE slug_noticia LIKE 'demo-%';

DELETE FROM comercios_destacados
 WHERE created_at = timestamp '2000-01-01 00:00:00';

DELETE FROM tarifas_destacado t
 WHERE t.created_at = timestamp '2000-01-01 00:00:00'
   AND NOT EXISTS (SELECT 1 FROM solicitudes_destacado s WHERE s.periodo_dias = t.periodo_dias);

COMMIT;
