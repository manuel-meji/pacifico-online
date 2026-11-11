<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M4 Inventario · SC-M4-02 (SC-05 Correcciones PC2 M4)
 * Retira el stock defectuoso, que quedó fuera del alcance de M4:
 *  - existencias.cantidad_defectuosa
 *  - kardex_inventario.saldo_defectuoso
 * y redefine el disponible como cantidad_fisica - cantidad_reservada.
 *
 * Migración de alteración: no se edita ninguna migración ya ejecutada.
 *
 * ORDEN DE DESPLIEGUE: InventarioService todavía resta cantidad_defectuosa.
 * Esta migración solo debe correrse en Supabase DESPUÉS de que el cambio del
 * servicio (que deja de usar la columna) esté en la rama desarrollo; si la
 * columna se borra antes, las consultas de M4 fallan. La ejecuta el coordinador
 * con el procedimiento de conexión directa (puerto 5432), nunca migrate:fresh.
 *
 * Si alguna fila tiene cantidad_defectuosa distinta de cero, la migración se
 * detiene con un mensaje: ese stock no se descarta en silencio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = current_schema()
                 AND table_name = 'existencias'
                 AND column_name = 'cantidad_defectuosa') THEN
        IF EXISTS (SELECT 1 FROM existencias WHERE cantidad_defectuosa <> 0) THEN
            RAISE EXCEPTION 'Hay existencias con cantidad_defectuosa distinta de cero. Revisarlas antes de retirar la columna.';
        END IF;
    END IF;
END $$;

ALTER TABLE existencias DROP CONSTRAINT IF EXISTS ck_existencias_disponible_no_negativo;
ALTER TABLE existencias DROP COLUMN IF EXISTS cantidad_defectuosa;
ALTER TABLE existencias
    ADD CONSTRAINT ck_existencias_disponible_no_negativo
    CHECK (cantidad_fisica - cantidad_reservada >= 0);

ALTER TABLE kardex_inventario DROP COLUMN IF EXISTS saldo_defectuoso;
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
ALTER TABLE existencias DROP CONSTRAINT IF EXISTS ck_existencias_disponible_no_negativo;
ALTER TABLE existencias ADD COLUMN IF NOT EXISTS cantidad_defectuosa numeric(14,3) NOT NULL DEFAULT 0;
ALTER TABLE existencias
    ADD CONSTRAINT ck_existencias_disponible_no_negativo
    CHECK (cantidad_fisica - cantidad_reservada - cantidad_defectuosa >= 0);

ALTER TABLE kardex_inventario ADD COLUMN IF NOT EXISTS saldo_defectuoso numeric(14,3) NOT NULL DEFAULT 0;
SQL
        );
    }
};
