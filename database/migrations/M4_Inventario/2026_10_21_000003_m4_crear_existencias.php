<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M4 Inventario · PC3 sec. 6.4
 * Tabla existencias.
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
SQL
        );
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS existencias');
    }
};
