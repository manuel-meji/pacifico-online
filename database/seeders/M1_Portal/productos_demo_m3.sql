-- =============================================================================
-- Catálogo de prueba en las tablas de M3 (PostgreSQL) — autorizado por M3
-- =============================================================================
-- Carga categorías, marcas, productos publicados, variaciones con atributos y
-- productos relacionados para los 5 comercios reales de M2 (ids 1 a 5), para
-- probar el portal de M1 con datos reales.
--
-- IMPORTANTE:
-- - Escribe en tablas de M3 (categorias, marcas, productos, producto_variaciones,
--   atributos_variacion, productos_relacionados). Lo hace SG1 con autorización
--   de M3; el dueño de los datos sigue siendo M3.
-- - Estos productos solo se ven en el portal cuando M3 publique en
--   CatalogoServiceInterface buscarProductos, obtenerProducto, listarCategorias,
--   listarRelacionados y listarMarcas, y M1 use M1_M3_MODO=real.
-- - No carga existencias (tablas de M4): sin existencias, M4 los informa como
--   agotados y la portada no los muestra como destacados.
-- - No carga imágenes: el portal muestra un ícono de respaldo.
--
-- Marca de los datos de prueba (para borrarlos con limpiar_productos_demo_m3.sql):
--   created_at = '2000-01-01 00:00:00' en todas las filas, y SKU que empieza con 'DEMO-'.
-- Se puede ejecutar varias veces: si ya hay variaciones 'DEMO-%', no hace nada.
-- =============================================================================

BEGIN;

DO $$
DECLARE
    m constant timestamp := timestamp '2000-01-01 00:00:00';
    -- categorías
    c_alim bigint; c_cafe bigint;
    c_dep bigint;  c_calz bigint; c_ropad bigint; c_bal bigint;
    c_moda bigint; c_vest bigint; c_cam bigint;
    c_tec bigint;  c_aud bigint;  c_acc bigint;
    c_hogar bigint; c_ilu bigint; c_org bigint;
    -- marcas
    b_volcan bigint; b_pacifico bigint; b_eleg bigint; b_milenio bigint; b_temu bigint;
    -- productos
    p bigint; v bigint;
    p_cafe_grano bigint; p_cafe_molido bigint; p_tenis bigint; p_camiseta bigint;
    p_audif bigint; p_cargador bigint; p_lampara bigint; p_organizador bigint;
BEGIN
    IF EXISTS (SELECT 1 FROM producto_variaciones WHERE sku LIKE 'DEMO-%') THEN
        RAISE NOTICE 'El catálogo de prueba ya está cargado: no se hizo nada.';
        RETURN;
    END IF;

    IF (SELECT count(*) FROM comercios WHERE id_comercio IN (1, 2, 3, 4, 5)) < 5 THEN
        RAISE EXCEPTION 'Faltan comercios 1 a 5 en M2: no se cargó nada.';
    END IF;

    -- -------------------------------------------------------------------------
    -- Categorías (principal → subcategorías)
    -- -------------------------------------------------------------------------
    INSERT INTO categorias (nombre, descripcion, created_at, updated_at) VALUES ('Alimentos y bebidas', 'Productos de la región', m, now()) RETURNING id_categoria INTO c_alim;
    INSERT INTO categorias (id_categoria_padre, nombre, created_at, updated_at) VALUES (c_alim, 'Café', m, now()) RETURNING id_categoria INTO c_cafe;
    INSERT INTO categorias (nombre, descripcion, created_at, updated_at) VALUES ('Deportes', 'Calzado, ropa y equipo deportivo', m, now()) RETURNING id_categoria INTO c_dep;
    INSERT INTO categorias (id_categoria_padre, nombre, created_at, updated_at) VALUES (c_dep, 'Calzado deportivo', m, now()) RETURNING id_categoria INTO c_calz;
    INSERT INTO categorias (id_categoria_padre, nombre, created_at, updated_at) VALUES (c_dep, 'Ropa deportiva', m, now()) RETURNING id_categoria INTO c_ropad;
    INSERT INTO categorias (id_categoria_padre, nombre, created_at, updated_at) VALUES (c_dep, 'Balones', m, now()) RETURNING id_categoria INTO c_bal;
    INSERT INTO categorias (nombre, descripcion, created_at, updated_at) VALUES ('Moda', 'Ropa y accesorios', m, now()) RETURNING id_categoria INTO c_moda;
    INSERT INTO categorias (id_categoria_padre, nombre, created_at, updated_at) VALUES (c_moda, 'Vestidos', m, now()) RETURNING id_categoria INTO c_vest;
    INSERT INTO categorias (id_categoria_padre, nombre, created_at, updated_at) VALUES (c_moda, 'Camisas', m, now()) RETURNING id_categoria INTO c_cam;
    INSERT INTO categorias (nombre, descripcion, created_at, updated_at) VALUES ('Tecnología', 'Electrónica y accesorios', m, now()) RETURNING id_categoria INTO c_tec;
    INSERT INTO categorias (id_categoria_padre, nombre, created_at, updated_at) VALUES (c_tec, 'Audio', m, now()) RETURNING id_categoria INTO c_aud;
    INSERT INTO categorias (id_categoria_padre, nombre, created_at, updated_at) VALUES (c_tec, 'Accesorios', m, now()) RETURNING id_categoria INTO c_acc;
    INSERT INTO categorias (nombre, descripcion, created_at, updated_at) VALUES ('Hogar', 'Artículos para la casa', m, now()) RETURNING id_categoria INTO c_hogar;
    INSERT INTO categorias (id_categoria_padre, nombre, created_at, updated_at) VALUES (c_hogar, 'Iluminación', m, now()) RETURNING id_categoria INTO c_ilu;
    INSERT INTO categorias (id_categoria_padre, nombre, created_at, updated_at) VALUES (c_hogar, 'Organización', m, now()) RETURNING id_categoria INTO c_org;

    -- -------------------------------------------------------------------------
    -- Marcas (ficticias)
    -- -------------------------------------------------------------------------
    INSERT INTO marcas (nombre, destacada, created_at, updated_at) VALUES ('Volcán Origen', true, m, now()) RETURNING id_marca INTO b_volcan;
    INSERT INTO marcas (nombre, destacada, created_at, updated_at) VALUES ('Pacífico Sport', true, m, now()) RETURNING id_marca INTO b_pacifico;
    INSERT INTO marcas (nombre, destacada, created_at, updated_at) VALUES ('Elegancia Atelier', false, m, now()) RETURNING id_marca INTO b_eleg;
    INSERT INTO marcas (nombre, destacada, created_at, updated_at) VALUES ('Milenio Tech', false, m, now()) RETURNING id_marca INTO b_milenio;
    INSERT INTO marcas (nombre, destacada, created_at, updated_at) VALUES ('Casa Temu', false, m, now()) RETURNING id_marca INTO b_temu;

    -- -------------------------------------------------------------------------
    -- Comercio 5: Café Volcán
    -- -------------------------------------------------------------------------
    INSERT INTO productos (id_comercio, id_categoria, id_marca, nombre, descripcion, destacado, estado_producto, created_at, updated_at)
    VALUES (5, c_cafe, b_volcan, 'Café de altura en grano 500 g', 'Café 100 % arábica cultivado a 1 400 m en las faldas del volcán, tueste medio.', true, 'publicado', m, now())
    RETURNING id_producto INTO p_cafe_grano;
    INSERT INTO producto_variaciones (id_producto, id_comercio, sku, precio, tarifa_impuesto, disponible, created_at, updated_at)
    VALUES (p_cafe_grano, 5, 'DEMO-CV-GRANO-MED', 6500.00, 1.00, true, m, now()) RETURNING id_variacion INTO v;
    INSERT INTO atributos_variacion (id_variacion, tipo_atributo, valor, created_at, updated_at) VALUES (v, 'Tueste', 'Medio', m, now());
    INSERT INTO producto_variaciones (id_producto, id_comercio, sku, precio, tarifa_impuesto, disponible, created_at, updated_at)
    VALUES (p_cafe_grano, 5, 'DEMO-CV-GRANO-OSC', 6800.00, 1.00, true, m, now()) RETURNING id_variacion INTO v;
    INSERT INTO atributos_variacion (id_variacion, tipo_atributo, valor, created_at, updated_at) VALUES (v, 'Tueste', 'Oscuro', m, now());

    INSERT INTO productos (id_comercio, id_categoria, id_marca, nombre, descripcion, destacado, estado_producto, created_at, updated_at)
    VALUES (5, c_cafe, b_volcan, 'Café molido para chorreador 340 g', 'Molienda media, ideal para chorreador y cafetera de goteo.', false, 'publicado', m, now())
    RETURNING id_producto INTO p_cafe_molido;
    INSERT INTO producto_variaciones (id_producto, id_comercio, sku, precio, tarifa_impuesto, disponible, created_at, updated_at)
    VALUES (p_cafe_molido, 5, 'DEMO-CV-MOLIDO-340', 4200.00, 1.00, true, m, now()) RETURNING id_variacion INTO v;
    INSERT INTO atributos_variacion (id_variacion, tipo_atributo, valor, created_at, updated_at) VALUES (v, 'Presentación', 'Molido', m, now());

    -- -------------------------------------------------------------------------
    -- Comercio 4: Deportes Pacífico
    -- -------------------------------------------------------------------------
    INSERT INTO productos (id_comercio, id_categoria, id_marca, nombre, descripcion, destacado, estado_producto, created_at, updated_at)
    VALUES (4, c_calz, b_pacifico, 'Tenis para correr Ola', 'Tenis livianos con suela amortiguada para correr en asfalto y playa.', true, 'publicado', m, now())
    RETURNING id_producto INTO p_tenis;
    INSERT INTO producto_variaciones (id_producto, id_comercio, sku, precio, tarifa_impuesto, disponible, created_at, updated_at)
    VALUES (p_tenis, 4, 'DEMO-DP-TENIS-40', 38900.00, 13.00, true, m, now()) RETURNING id_variacion INTO v;
    INSERT INTO atributos_variacion (id_variacion, tipo_atributo, valor, created_at, updated_at) VALUES (v, 'Talla', '40', m, now());
    INSERT INTO producto_variaciones (id_producto, id_comercio, sku, precio, tarifa_impuesto, disponible, created_at, updated_at)
    VALUES (p_tenis, 4, 'DEMO-DP-TENIS-42', 38900.00, 13.00, true, m, now()) RETURNING id_variacion INTO v;
    INSERT INTO atributos_variacion (id_variacion, tipo_atributo, valor, created_at, updated_at) VALUES (v, 'Talla', '42', m, now());

    INSERT INTO productos (id_comercio, id_categoria, id_marca, nombre, descripcion, destacado, estado_producto, created_at, updated_at)
    VALUES (4, c_ropad, b_pacifico, 'Camiseta deportiva Brisa', 'Camiseta de secado rápido con protección UV.', false, 'publicado', m, now())
    RETURNING id_producto INTO p_camiseta;
    INSERT INTO producto_variaciones (id_producto, id_comercio, sku, precio, tarifa_impuesto, disponible, created_at, updated_at)
    VALUES (p_camiseta, 4, 'DEMO-DP-CAM-M-AZ', 12500.00, 13.00, true, m, now()) RETURNING id_variacion INTO v;
    INSERT INTO atributos_variacion (id_variacion, tipo_atributo, valor, created_at, updated_at) VALUES (v, 'Talla', 'M', m, now()), (v, 'Color', 'Azul', m, now());
    INSERT INTO producto_variaciones (id_producto, id_comercio, sku, precio, tarifa_impuesto, disponible, created_at, updated_at)
    VALUES (p_camiseta, 4, 'DEMO-DP-CAM-L-BL', 12500.00, 13.00, true, m, now()) RETURNING id_variacion INTO v;
    INSERT INTO atributos_variacion (id_variacion, tipo_atributo, valor, created_at, updated_at) VALUES (v, 'Talla', 'L', m, now()), (v, 'Color', 'Blanco', m, now());

    INSERT INTO productos (id_comercio, id_categoria, id_marca, nombre, descripcion, destacado, estado_producto, created_at, updated_at)
    VALUES (4, c_bal, b_pacifico, 'Balón de fútbol playa', 'Balón cosido a mano, tamaño 5, resistente al agua salada.', false, 'publicado', m, now())
    RETURNING id_producto INTO p;
    INSERT INTO producto_variaciones (id_producto, id_comercio, sku, precio, tarifa_impuesto, disponible, created_at, updated_at)
    VALUES (p, 4, 'DEMO-DP-BALON-5', 9900.00, 13.00, true, m, now()) RETURNING id_variacion INTO v;
    INSERT INTO atributos_variacion (id_variacion, tipo_atributo, valor, created_at, updated_at) VALUES (v, 'Tamaño', '5', m, now());

    -- -------------------------------------------------------------------------
    -- Comercio 3: Elegancia
    -- -------------------------------------------------------------------------
    INSERT INTO productos (id_comercio, id_categoria, id_marca, nombre, descripcion, destacado, estado_producto, created_at, updated_at)
    VALUES (3, c_vest, b_eleg, 'Vestido de lino Atardecer', 'Vestido fresco de lino, corte midi, ideal para clima cálido.', true, 'publicado', m, now())
    RETURNING id_producto INTO p;
    INSERT INTO producto_variaciones (id_producto, id_comercio, sku, precio, tarifa_impuesto, disponible, created_at, updated_at)
    VALUES (p, 3, 'DEMO-EL-VEST-S', 29500.00, 13.00, true, m, now()) RETURNING id_variacion INTO v;
    INSERT INTO atributos_variacion (id_variacion, tipo_atributo, valor, created_at, updated_at) VALUES (v, 'Talla', 'S', m, now());
    INSERT INTO producto_variaciones (id_producto, id_comercio, sku, precio, tarifa_impuesto, disponible, created_at, updated_at)
    VALUES (p, 3, 'DEMO-EL-VEST-M', 29500.00, 13.00, true, m, now()) RETURNING id_variacion INTO v;
    INSERT INTO atributos_variacion (id_variacion, tipo_atributo, valor, created_at, updated_at) VALUES (v, 'Talla', 'M', m, now());

    INSERT INTO productos (id_comercio, id_categoria, id_marca, nombre, descripcion, destacado, estado_producto, created_at, updated_at)
    VALUES (3, c_cam, b_eleg, 'Camisa formal de algodón', 'Camisa manga larga de algodón peinado.', false, 'publicado', m, now())
    RETURNING id_producto INTO p;
    INSERT INTO producto_variaciones (id_producto, id_comercio, sku, precio, tarifa_impuesto, disponible, created_at, updated_at)
    VALUES (p, 3, 'DEMO-EL-CAM-M-BL', 18900.00, 13.00, true, m, now()) RETURNING id_variacion INTO v;
    INSERT INTO atributos_variacion (id_variacion, tipo_atributo, valor, created_at, updated_at) VALUES (v, 'Talla', 'M', m, now()), (v, 'Color', 'Blanco', m, now());
    INSERT INTO producto_variaciones (id_producto, id_comercio, sku, precio, tarifa_impuesto, disponible, created_at, updated_at)
    VALUES (p, 3, 'DEMO-EL-CAM-L-CE', 19900.00, 13.00, true, m, now()) RETURNING id_variacion INTO v;
    INSERT INTO atributos_variacion (id_variacion, tipo_atributo, valor, created_at, updated_at) VALUES (v, 'Talla', 'L', m, now()), (v, 'Color', 'Celeste', m, now());

    -- -------------------------------------------------------------------------
    -- Comercio 1: Soluciones del Milenio
    -- -------------------------------------------------------------------------
    INSERT INTO productos (id_comercio, id_categoria, id_marca, nombre, descripcion, destacado, estado_producto, created_at, updated_at)
    VALUES (1, c_aud, b_milenio, 'Audífonos inalámbricos Pulso', 'Audífonos Bluetooth con cancelación de ruido y 30 horas de batería.', true, 'publicado', m, now())
    RETURNING id_producto INTO p_audif;
    INSERT INTO producto_variaciones (id_producto, id_comercio, sku, precio, tarifa_impuesto, disponible, created_at, updated_at)
    VALUES (p_audif, 1, 'DEMO-SM-AUD-NEG', 24900.00, 13.00, true, m, now()) RETURNING id_variacion INTO v;
    INSERT INTO atributos_variacion (id_variacion, tipo_atributo, valor, created_at, updated_at) VALUES (v, 'Color', 'Negro', m, now());
    INSERT INTO producto_variaciones (id_producto, id_comercio, sku, precio, tarifa_impuesto, disponible, created_at, updated_at)
    VALUES (p_audif, 1, 'DEMO-SM-AUD-BLA', 24900.00, 13.00, true, m, now()) RETURNING id_variacion INTO v;
    INSERT INTO atributos_variacion (id_variacion, tipo_atributo, valor, created_at, updated_at) VALUES (v, 'Color', 'Blanco', m, now());

    INSERT INTO productos (id_comercio, id_categoria, id_marca, nombre, descripcion, destacado, estado_producto, created_at, updated_at)
    VALUES (1, c_acc, b_milenio, 'Cargador rápido USB-C 30 W', 'Cargador compacto compatible con teléfonos y tabletas.', false, 'publicado', m, now())
    RETURNING id_producto INTO p_cargador;
    INSERT INTO producto_variaciones (id_producto, id_comercio, sku, precio, tarifa_impuesto, disponible, created_at, updated_at)
    VALUES (p_cargador, 1, 'DEMO-SM-CARG-30W', 8900.00, 13.00, true, m, now()) RETURNING id_variacion INTO v;
    INSERT INTO atributos_variacion (id_variacion, tipo_atributo, valor, created_at, updated_at) VALUES (v, 'Potencia', '30 W', m, now());

    -- -------------------------------------------------------------------------
    -- Comercio 2: Temulandia
    -- -------------------------------------------------------------------------
    INSERT INTO productos (id_comercio, id_categoria, id_marca, nombre, descripcion, destacado, estado_producto, created_at, updated_at)
    VALUES (2, c_ilu, b_temu, 'Lámpara de escritorio LED', 'Lámpara con brazo flexible y tres niveles de intensidad.', true, 'publicado', m, now())
    RETURNING id_producto INTO p_lampara;
    INSERT INTO producto_variaciones (id_producto, id_comercio, sku, precio, tarifa_impuesto, disponible, created_at, updated_at)
    VALUES (p_lampara, 2, 'DEMO-TE-LAMP-BLA', 11900.00, 13.00, true, m, now()) RETURNING id_variacion INTO v;
    INSERT INTO atributos_variacion (id_variacion, tipo_atributo, valor, created_at, updated_at) VALUES (v, 'Color', 'Blanco', m, now());

    INSERT INTO productos (id_comercio, id_categoria, id_marca, nombre, descripcion, destacado, estado_producto, created_at, updated_at)
    VALUES (2, c_org, b_temu, 'Organizador de cajones (set de 6)', 'Set de 6 cajas plásticas apilables para ropa y accesorios.', false, 'publicado', m, now())
    RETURNING id_producto INTO p_organizador;
    INSERT INTO producto_variaciones (id_producto, id_comercio, sku, precio, tarifa_impuesto, disponible, created_at, updated_at)
    VALUES (p_organizador, 2, 'DEMO-TE-ORG-6', 7500.00, 13.00, true, m, now()) RETURNING id_variacion INTO v;
    INSERT INTO atributos_variacion (id_variacion, tipo_atributo, valor, created_at, updated_at) VALUES (v, 'Piezas', '6', m, now());

    -- -------------------------------------------------------------------------
    -- Productos relacionados (siempre del mismo comercio, RN-M3-12)
    -- -------------------------------------------------------------------------
    INSERT INTO productos_relacionados (id_producto, id_producto_relacionado, id_comercio, created_at, updated_at)
    VALUES (p_cafe_grano, p_cafe_molido, 5, m, now()),
           (p_cafe_molido, p_cafe_grano, 5, m, now()),
           (p_tenis, p_camiseta, 4, m, now()),
           (p_audif, p_cargador, 1, m, now()),
           (p_lampara, p_organizador, 2, m, now());

    RAISE NOTICE 'Catálogo de prueba cargado: 15 categorías, 5 marcas, 11 productos publicados.';
END $$;

COMMIT;
