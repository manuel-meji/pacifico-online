-- M4 Inventario multiempresa · PC3 · Secciones 6.4 y 6.5 (PostgreSQL)
-- Columnas inferidas de la 6.1/6.2 de Josué: confirmar antes de usar.

-- Funciones de los triggers -------------------------------------------------
CREATE FUNCTION fn_m4_bloquear_modificacion() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'La tabla % es inmutable: no se permite %', TG_TABLE_NAME, TG_OP
        USING ERRCODE = '23514';
END;
$$ LANGUAGE plpgsql;

CREATE FUNCTION fn_m4_reservas_transicion() RETURNS trigger AS $$
BEGIN
    IF NEW.id_estado_reserva = OLD.id_estado_reserva THEN
        RETURN NEW;
    END IF;
    IF (OLD.id_estado_reserva = 1 AND NEW.id_estado_reserva IN (2, 4, 5))
       OR (OLD.id_estado_reserva = 2 AND NEW.id_estado_reserva IN (3, 4)) THEN
        RETURN NEW;
    END IF;
    RAISE EXCEPTION 'Transicion de estado de reserva no permitida: % -> %',
        OLD.id_estado_reserva, NEW.id_estado_reserva
        USING ERRCODE = '23514';
END;
$$ LANGUAGE plpgsql;

-- Catálogos ------------------------------------------------------------------
CREATE TABLE estados_reserva (
    id_estado_reserva smallint     NOT NULL,
    codigo            varchar(30)  NOT NULL,
    nombre            varchar(60)  NOT NULL,
    descripcion       varchar(255),
    CONSTRAINT pk_estados_reserva PRIMARY KEY (id_estado_reserva),
    CONSTRAINT uq_estados_reserva_codigo UNIQUE (codigo)
);

CREATE TABLE motivos_ajuste (
    id_motivo_ajuste smallint     NOT NULL,
    codigo           varchar(30)  NOT NULL,
    nombre           varchar(60)  NOT NULL,
    descripcion      varchar(255),
    CONSTRAINT pk_motivos_ajuste PRIMARY KEY (id_motivo_ajuste),
    CONSTRAINT uq_motivos_ajuste_codigo UNIQUE (codigo)
);

INSERT INTO estados_reserva (id_estado_reserva, codigo, nombre) VALUES
    (1, 'activa',     'Activa'),
    (2, 'confirmada', 'Confirmada'),
    (3, 'consumida',  'Consumida'),
    (4, 'liberada',   'Liberada'),
    (5, 'vencida',    'Vencida');

INSERT INTO motivos_ajuste (id_motivo_ajuste, codigo, nombre) VALUES
    (1, 'merma',        'Merma'),
    (2, 'robo',         'Robo'),
    (3, 'danio',        'Daño'),
    (4, 'sobrante',     'Sobrante'),
    (5, 'error_conteo', 'Error de conteo'),
    (6, 'otro',         'Otro');

-- existencias ----------------------------------------------------------------
CREATE TABLE existencias (
    id_existencia      bigint GENERATED ALWAYS AS IDENTITY,
    id_comercio        bigint        NOT NULL,
    id_producto        bigint        NOT NULL,
    id_variacion       bigint        NOT NULL,
    id_ubicacion       bigint        NOT NULL,
    cantidad_fisica    numeric(14,3) NOT NULL DEFAULT 0,
    cantidad_reservada numeric(14,3) NOT NULL DEFAULT 0,
    cantidad_defectuosa numeric(14,3) NOT NULL DEFAULT 0,
    stock_minimo       numeric(14,3) NOT NULL DEFAULT 0,
    costo_promedio     numeric(14,4) NOT NULL DEFAULT 0,
    created_at         timestamptz,
    updated_at         timestamptz,
    CONSTRAINT pk_existencias PRIMARY KEY (id_existencia),
    CONSTRAINT uq_existencias_comercio_variacion_ubicacion
        UNIQUE (id_comercio, id_variacion, id_ubicacion),
    CONSTRAINT uq_existencias_comercio_producto_variacion_ubicacion
        UNIQUE (id_comercio, id_producto, id_variacion, id_ubicacion),
    CONSTRAINT uq_existencias_comercio_id UNIQUE (id_comercio, id_existencia),
    CONSTRAINT ck_existencias_fisica_no_negativa CHECK (cantidad_fisica >= 0),
    CONSTRAINT ck_existencias_reservada_no_negativa CHECK (cantidad_reservada >= 0),
    CONSTRAINT ck_existencias_disponible_no_negativo
        CHECK (cantidad_fisica - cantidad_reservada - cantidad_defectuosa >= 0),
    CONSTRAINT ck_existencias_stock_minimo_no_negativo CHECK (stock_minimo >= 0)
);

-- movimientos_inventario -----------------------------------------------------
CREATE TABLE movimientos_inventario (
    id_movimiento      bigint GENERATED ALWAYS AS IDENTITY,
    id_comercio        bigint        NOT NULL,
    id_producto        bigint        NOT NULL,
    id_variacion       bigint        NOT NULL,
    id_ubicacion       bigint        NOT NULL,
    id_usuario         bigint        NOT NULL,
    id_autorizador     bigint,
    tipo               varchar(40)   NOT NULL,
    efecto             varchar(10)   NOT NULL,
    cantidad           numeric(14,3) NOT NULL,
    costo_unitario     numeric(14,4) NOT NULL DEFAULT 0,
    referencia         varchar(100),
    motivo             varchar(255)  NOT NULL,
    fecha_hora         timestamptz   NOT NULL DEFAULT now(),
    fecha_autorizacion timestamptz,
    CONSTRAINT pk_movimientos_inventario PRIMARY KEY (id_movimiento),
    CONSTRAINT uq_movimientos_comercio_id UNIQUE (id_comercio, id_movimiento),
    CONSTRAINT fk_movimientos_existencia
        FOREIGN KEY (id_comercio, id_producto, id_variacion, id_ubicacion)
        REFERENCES existencias (id_comercio, id_producto, id_variacion, id_ubicacion)
        ON DELETE RESTRICT,
    CONSTRAINT ck_movimientos_costo_no_negativo CHECK (costo_unitario >= 0),
    CONSTRAINT ck_movimientos_compensatorio_referencia
        CHECK (tipo <> 'ajuste_compensatorio' OR referencia IS NOT NULL),
    CONSTRAINT ck_movimientos_ajuste_autorizado
        CHECK (tipo NOT LIKE 'ajuste%'
               OR (id_autorizador IS NOT NULL AND fecha_autorizacion IS NOT NULL)),
    CONSTRAINT ck_movimientos_transferencia_autorizada
        CHECK (tipo NOT LIKE 'transferencia%' OR id_autorizador IS NOT NULL)
);

CREATE TRIGGER tr_movimientos_inmutable_update
    BEFORE UPDATE ON movimientos_inventario
    FOR EACH ROW EXECUTE FUNCTION fn_m4_bloquear_modificacion();
CREATE TRIGGER tr_movimientos_inmutable_delete
    BEFORE DELETE ON movimientos_inventario
    FOR EACH ROW EXECUTE FUNCTION fn_m4_bloquear_modificacion();

-- kardex_inventario ----------------------------------------------------------
CREATE TABLE kardex_inventario (
    id_kardex        bigint GENERATED ALWAYS AS IDENTITY,
    id_comercio      bigint        NOT NULL,
    id_movimiento    bigint        NOT NULL,
    saldo_cantidad   numeric(14,3) NOT NULL,
    saldo_reservado  numeric(14,3) NOT NULL DEFAULT 0,
    saldo_defectuoso numeric(14,3) NOT NULL DEFAULT 0,
    monto_total      numeric(16,2) NOT NULL,
    CONSTRAINT pk_kardex_inventario PRIMARY KEY (id_kardex),
    CONSTRAINT uq_kardex_movimiento UNIQUE (id_movimiento),
    CONSTRAINT fk_kardex_movimiento
        FOREIGN KEY (id_comercio, id_movimiento)
        REFERENCES movimientos_inventario (id_comercio, id_movimiento)
        ON DELETE RESTRICT,
    CONSTRAINT ck_kardex_monto_total_no_negativo CHECK (monto_total >= 0)
);

CREATE TRIGGER tr_kardex_inmutable_update
    BEFORE UPDATE ON kardex_inventario
    FOR EACH ROW EXECUTE FUNCTION fn_m4_bloquear_modificacion();
CREATE TRIGGER tr_kardex_inmutable_delete
    BEFORE DELETE ON kardex_inventario
    FOR EACH ROW EXECUTE FUNCTION fn_m4_bloquear_modificacion();

-- reservas -------------------------------------------------------------------
CREATE TABLE reservas (
    id_reserva         uuid          NOT NULL DEFAULT gen_random_uuid(),
    id_comercio        bigint        NOT NULL,
    id_existencia      bigint        NOT NULL,
    id_estado_reserva  smallint      NOT NULL DEFAULT 1,
    id_pedido          bigint,
    id_subpedido       bigint,
    cantidad           numeric(14,3) NOT NULL,
    fecha_creacion     timestamptz   NOT NULL DEFAULT now(),
    fecha_vencimiento  timestamptz   NOT NULL,
    clave_idempotencia varchar(100)  NOT NULL,
    historial          jsonb         NOT NULL DEFAULT '[]'::jsonb,
    CONSTRAINT pk_reservas PRIMARY KEY (id_reserva),
    CONSTRAINT uq_reservas_comercio_clave_idempotencia
        UNIQUE (id_comercio, clave_idempotencia),
    CONSTRAINT fk_reservas_existencia
        FOREIGN KEY (id_comercio, id_existencia)
        REFERENCES existencias (id_comercio, id_existencia)
        ON DELETE RESTRICT,
    CONSTRAINT fk_reservas_estado
        FOREIGN KEY (id_estado_reserva)
        REFERENCES estados_reserva (id_estado_reserva)
        ON DELETE RESTRICT,
    CONSTRAINT ck_reservas_cantidad_positiva CHECK (cantidad > 0),
    CONSTRAINT ck_reservas_vencimiento_posterior CHECK (fecha_vencimiento > fecha_creacion),
    CONSTRAINT ck_reservas_confirmada_con_pedido
        CHECK (id_estado_reserva NOT IN (2, 3) OR id_pedido IS NOT NULL)
);

CREATE TRIGGER tr_reservas_transicion
    BEFORE UPDATE ON reservas
    FOR EACH ROW EXECUTE FUNCTION fn_m4_reservas_transicion();
CREATE TRIGGER tr_reservas_no_delete
    BEFORE DELETE ON reservas
    FOR EACH ROW EXECUTE FUNCTION fn_m4_bloquear_modificacion();

-- ajustes_inventario ---------------------------------------------------------
CREATE TABLE ajustes_inventario (
    id_ajuste        bigint GENERATED ALWAYS AS IDENTITY,
    id_comercio      bigint        NOT NULL,
    id_existencia    bigint        NOT NULL,
    id_motivo_ajuste smallint      NOT NULL,
    id_movimiento    bigint,
    id_usuario       bigint        NOT NULL,
    cantidad_contada numeric(14,3) NOT NULL,
    created_at       timestamptz   NOT NULL DEFAULT now(),
    CONSTRAINT pk_ajustes_inventario PRIMARY KEY (id_ajuste),
    CONSTRAINT uq_ajustes_movimiento UNIQUE (id_movimiento),
    CONSTRAINT fk_ajustes_existencia
        FOREIGN KEY (id_comercio, id_existencia)
        REFERENCES existencias (id_comercio, id_existencia)
        ON DELETE RESTRICT,
    CONSTRAINT fk_ajustes_motivo
        FOREIGN KEY (id_motivo_ajuste)
        REFERENCES motivos_ajuste (id_motivo_ajuste)
        ON DELETE RESTRICT,
    CONSTRAINT fk_ajustes_movimiento
        FOREIGN KEY (id_comercio, id_movimiento)
        REFERENCES movimientos_inventario (id_comercio, id_movimiento)
        ON DELETE RESTRICT
);
