<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M4 Inventario · PC3 sec. 6.4
 * Funciones de trigger de M4 (inmutabilidad y transición de reservas).
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
SQL
        );
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS fn_m4_reservas_transicion()');
        DB::unprepared('DROP FUNCTION IF EXISTS fn_m4_bloquear_modificacion()');
    }
};
