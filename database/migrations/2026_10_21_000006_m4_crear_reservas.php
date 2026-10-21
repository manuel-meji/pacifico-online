<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M4 Inventario · PC3 sec. 6.4
 * Tabla reservas con transición de estados.
 *
 * El SQL es el de la sección 6.5 (PostgreSQL). Los nombres de restricciones
 * siguen la convención de la sección 6.3. No usar migrate:fresh ni migrate:reset:
 * la base de datos de Supabase es compartida por todo el curso.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
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
SQL
        );
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS reservas');
    }
};
