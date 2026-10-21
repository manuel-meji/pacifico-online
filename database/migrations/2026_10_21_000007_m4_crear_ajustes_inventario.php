<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M4 Inventario · PC3 sec. 6.4
 * Tabla ajustes_inventario.
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
SQL
        );
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS ajustes_inventario');
    }
};
