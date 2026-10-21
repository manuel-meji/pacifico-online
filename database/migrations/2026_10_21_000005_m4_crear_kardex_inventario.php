<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M4 Inventario · PC3 sec. 6.4
 * Tabla kardex_inventario (inmutable).
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
SQL
        );
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS kardex_inventario');
    }
};
