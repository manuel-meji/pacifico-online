<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Este trigger solo aplica a PostgreSQL.
         * Las pruebas locales que utilicen SQLite deben omitir esta parte.
         */
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION impedir_modificacion_bitacora_auditoria()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION
                    'La bitácora de auditoría es inmutable: UPDATE y DELETE no están permitidos.';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_bitacora_auditoria_inmutable
            BEFORE UPDATE OR DELETE
            ON bitacora_auditoria
            FOR EACH ROW
            EXECUTE FUNCTION impedir_modificacion_bitacora_auditoria();
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'DROP TRIGGER IF EXISTS trg_bitacora_auditoria_inmutable
             ON bitacora_auditoria'
        );

        DB::statement(
            'DROP FUNCTION IF EXISTS impedir_modificacion_bitacora_auditoria()'
        );
    }
};