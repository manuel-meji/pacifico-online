<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M4 Inventario · PC3 sec. 6.4
 * Tabla movimientos_inventario (inmutable).
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
SQL
        );
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS movimientos_inventario');
    }
};
