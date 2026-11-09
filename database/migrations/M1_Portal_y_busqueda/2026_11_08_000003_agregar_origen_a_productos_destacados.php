<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M1 Portal público — productos_destacados guarda de dónde viene cada
 * destacado (contrato 1.4.0, SC-08):
 * - origen: 'solicitud' (pagado por el comercio) o 'plataforma' (sin costo).
 * - id_solicitud_destacado: la solicitud aprobada que lo creó, si la hay.
 * - id_comercio: dueño del producto al destacarlo. Se guarda para aplicar el
 *   máximo de 3 destacados activos por comercio con un bloqueo en la base, sin
 *   depender de M3 en cada conteo. Es nulo en las filas anteriores a 1.4.0.
 *
 * Las filas existentes quedan con origen 'plataforma' (no tienen cargo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos_destacados', function (Blueprint $table) {
            $table->unsignedBigInteger('id_comercio')->nullable();
            $table->string('origen', 20)->default('plataforma');
            $table->unsignedBigInteger('id_solicitud_destacado')->nullable();

            $table->unique('id_solicitud_destacado', 'uq_productos_destacados_solicitud');
            $table->index(['id_comercio', 'activo'], 'ix_productos_destacados_comercio');
            $table->foreign('id_comercio', 'fk_productos_destacados_comercios')
                ->references('id_comercio')->on('comercios');
            $table->foreign('id_solicitud_destacado', 'fk_productos_destacados_solicitudes')
                ->references('id_solicitud_destacado')->on('solicitudes_destacado');
        });

        DB::statement("ALTER TABLE productos_destacados ADD CONSTRAINT chk_productos_destacados_origen
                       CHECK (origen IN ('solicitud', 'plataforma'))");
        DB::statement("ALTER TABLE productos_destacados ADD CONSTRAINT chk_productos_destacados_solicitud
                       CHECK (origen <> 'solicitud' OR id_solicitud_destacado IS NOT NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE productos_destacados DROP CONSTRAINT IF EXISTS chk_productos_destacados_solicitud');
        DB::statement('ALTER TABLE productos_destacados DROP CONSTRAINT IF EXISTS chk_productos_destacados_origen');

        Schema::table('productos_destacados', function (Blueprint $table) {
            $table->dropForeign('fk_productos_destacados_solicitudes');
            $table->dropForeign('fk_productos_destacados_comercios');
            $table->dropIndex('ix_productos_destacados_comercio');
            $table->dropUnique('uq_productos_destacados_solicitud');
            $table->dropColumn(['id_comercio', 'origen', 'id_solicitud_destacado']);
        });
    }
};
