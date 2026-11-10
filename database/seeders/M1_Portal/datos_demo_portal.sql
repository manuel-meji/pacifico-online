-- =============================================================================
-- M1 Portal público — Datos ficticios de demostración (PostgreSQL)
-- =============================================================================
-- Carga datos para ver en el portal las partes de M1 que hoy funcionan con la
-- base compartida (M2 y M4 en modo real, M3 en modo doble):
--   1. Comercios destacados de la página principal (sobre los comercios reales de M2).
--   2. Noticias de la plataforma: publicadas (se ven en el portal), un borrador
--      y una archivada (solo se ven en la administración).
--   3. Tarifas de producto destacado (7, 15 y 30 días), para poder solicitar destacados.
--
-- NO carga productos destacados ni promociones: el portal obtiene los productos
-- de M3, que sigue en modo doble, y la base rechaza ids de productos que no
-- existen (llaves foráneas). Esas secciones se podrán sembrar cuando M3 publique
-- su interfaz (M1_M3_MODO=real).
--
-- Reglas:
-- - Solo escribe en tablas de M1 (comercios_destacados, noticias_portal,
--   tarifas_destacado). Lee usuarios (núcleo) y comercios (M2) solo para usar
--   ids que existan y no violar las llaves foráneas.
-- - Se puede ejecutar varias veces: no duplica filas (marca y comprobaciones).
-- - Marca de los datos de demostración, para borrarlos con limpiar_datos_demo_portal.sql:
--     * noticias: slug_noticia que empieza con 'demo-'.
--     * comercios destacados y tarifas: created_at = '2000-01-01 00:00:00'.
-- - Las fechas son relativas al momento de ejecutarlo (now()), así siempre se ven vigentes.
--
-- Cómo ejecutarlo (base compartida de Supabase):
--   - Editor SQL de Supabase: pegar el archivo y ejecutar.
--   - psql: psql "<cadena de conexión>" -f database/seeders/M1_Portal/datos_demo_portal.sql
-- =============================================================================

BEGIN;

DO $$
DECLARE
    v_admin   bigint;
    v_marca   constant timestamp := timestamp '2000-01-01 00:00:00';
BEGIN
    -- Autor de las noticias y de las tarifas: la primera cuenta activa con rol
    -- encargado_plataforma (las noticias solo las publica la administración).
    SELECT id_usuario INTO v_admin
      FROM usuarios
     WHERE rol = 'encargado_plataforma' AND activo
     ORDER BY id_usuario
     LIMIT 1;

    IF v_admin IS NULL THEN
        RAISE EXCEPTION 'No hay una cuenta activa con rol encargado_plataforma: no se cargó ningún dato.';
    END IF;

    -- -------------------------------------------------------------------------
    -- 1. Tarifas de producto destacado. Si ya hay una tarifa para el periodo,
    --    no se cambia (puede ser la real que definió la administración).
    -- -------------------------------------------------------------------------
    INSERT INTO tarifas_destacado (periodo_dias, monto, moneda, id_usuario_actualiza, created_at, updated_at)
    VALUES (7,  15000.00, 'CRC', v_admin, v_marca, now()),
           (15, 25000.00, 'CRC', v_admin, v_marca, now()),
           (30, 40000.00, 'CRC', v_admin, v_marca, now())
    ON CONFLICT (periodo_dias) DO NOTHING;

    -- -------------------------------------------------------------------------
    -- 2. Comercios destacados de la página principal, por posición. Solo los
    --    comercios que existen en M2 y que no tienen ya un destacado vigente.
    --    La portada muestra solo los que M2 devuelve como Activo.
    -- -------------------------------------------------------------------------
    INSERT INTO comercios_destacados (id_comercio, posicion, fecha_inicio, fecha_fin, activo, created_at, updated_at)
    SELECT d.id_comercio, d.posicion, now() - interval '1 day', now() + interval '30 days', true, v_marca, now()
      FROM (VALUES (5, 1),   -- Café Volcán
                   (4, 2),   -- Deportes Pacífico
                   (3, 3),   -- Elegancia
                   (1, 4),   -- Soluciones del Milenio
                   (2, 5))   -- Temulandia
           AS d (id_comercio, posicion)
     WHERE EXISTS (SELECT 1 FROM comercios c WHERE c.id_comercio = d.id_comercio)
       AND NOT EXISTS (SELECT 1
                         FROM comercios_destacados cd
                        WHERE cd.id_comercio = d.id_comercio
                          AND cd.activo
                          AND cd.fecha_fin >= now());

    -- -------------------------------------------------------------------------
    -- 3. Noticias de la plataforma. fecha_inicio es la fecha de publicación; el
    --    portal las muestra de la más reciente a la más antigua, solo publicadas
    --    y dentro de su periodo.
    -- -------------------------------------------------------------------------
    INSERT INTO noticias_portal (id_usuario_autor, titulo_noticia, slug_noticia, resumen_noticia,
                                 contenido_noticia, imagen_url, estado_noticia, fecha_inicio, fecha_fin,
                                 created_at, updated_at)
    SELECT v_admin, n.titulo, n.slug, n.resumen, n.contenido, NULL, n.estado,
           now() - n.antiguedad, now() - n.antiguedad + n.vigencia, now(), now()
      FROM (VALUES
        ('Llega la Feria Navideña 2026 a Pacíficos Online',
         'demo-feria-navidena-2026',
         'Del 1 al 24 de diciembre, los comercios de Guanacaste ofrecen sus mejores productos para estas fiestas.',
         'Este diciembre celebramos la Feria Navideña 2026. Durante todo el mes vas a encontrar regalos, '
         || 'artesanías, café de altura y mucho más de los comercios de la región. Revisá la sección de '
         || 'promociones de la página principal: cada semana se suman nuevas ofertas por tiempo limitado.'
         || E'\n\nComprá desde un solo carrito a varios comercios y recibí tu pedido en todo Costa Rica.',
         'publicada', interval '1 day', interval '45 days'),

        ('Damos la bienvenida a Café Volcán',
         'demo-bienvenida-cafe-volcan',
         'Café de especialidad cultivado en las faldas del volcán, ahora disponible en el portal.',
         'Café Volcán se une a Pacíficos Online con su café tostado artesanalmente, en grano y molido. '
         || 'Visitá su micrositio para conocer su historia, sus horarios y sus presentaciones.',
         'publicada', interval '3 days', interval '60 days'),

        ('Nuevas políticas de envío para todo el país',
         'demo-nuevas-politicas-de-envio',
         'Actualizamos las políticas de envío: más zonas de cobertura y tiempos de entrega más claros.',
         'A partir de este mes, cada comercio muestra en su micrositio sus políticas de envío y devolución. '
         || 'El costo de envío se calcula por comercio en el carrito, antes de confirmar el pedido.'
         || E'\n\nSi tenés dudas, escribinos a soporte desde la página principal.',
         'publicada', interval '6 days', interval '90 days'),

        ('Mantenimiento programado del portal',
         'demo-mantenimiento-programado',
         'El sábado de 1:00 a 3:00 a. m. el portal puede presentar intermitencias por mantenimiento.',
         'Estaremos mejorando la plataforma para que tus búsquedas sean más rápidas. Durante el '
         || 'mantenimiento podrías ver avisos de servicio no disponible por unos minutos. '
         || 'Tus carritos y pedidos no se ven afectados.',
         'publicada', interval '9 days', interval '30 days'),

        ('Deportes Pacífico y Elegancia se suman al portal',
         'demo-nuevos-comercios-noviembre',
         'Dos comercios más para encontrar ropa, calzado y artículos deportivos en un solo lugar.',
         'Deportes Pacífico y Elegancia ya forman parte de Pacíficos Online. Explorá sus micrositios '
         || 'desde la sección de comercios destacados de la página principal.',
         'publicada', interval '14 days', interval '60 days'),

        -- No se ve en el portal: es un borrador (solo en la administración).
        ('Borrador: campaña de verano 2027',
         'demo-borrador-campana-verano',
         'Borrador de la campaña de verano; todavía no está publicada.',
         'Contenido en preparación para la campaña de verano 2027.',
         'borrador', interval '0 days', interval '120 days'),

        -- No se ve en el portal: está archivada (solo en la administración).
        ('Archivada: horario especial de Semana Santa',
         'demo-archivada-semana-santa',
         'Aviso de horario especial que ya terminó.',
         'Durante Semana Santa los comercios atendieron con horario especial.',
         'archivada', interval '200 days', interval '10 days')
      ) AS n (titulo, slug, resumen, contenido, estado, antiguedad, vigencia)
     WHERE NOT EXISTS (SELECT 1 FROM noticias_portal np WHERE np.slug_noticia = n.slug);

    RAISE NOTICE 'Datos de demostración de M1 cargados (autor/administración: usuario %).', v_admin;
END $$;

COMMIT;

-- Verificación rápida (opcional):
-- SELECT periodo_dias, monto FROM tarifas_destacado ORDER BY periodo_dias;
-- SELECT id_comercio, posicion, fecha_fin FROM comercios_destacados ORDER BY posicion;
-- SELECT slug_noticia, estado_noticia, fecha_inicio FROM noticias_portal WHERE slug_noticia LIKE 'demo-%' ORDER BY fecha_inicio DESC;
