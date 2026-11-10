<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Destacar comercios: el encargado del comercio solicita una publicidad (7, 15 o 30 días)
 * y la plataforma la aprueba o la rechaza; la plataforma también puede destacar un comercio
 * directamente. Las publicidades que ya existían quedan como Aprobada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publicidad_comercio', function (Blueprint $table) {
            $table->string('estado', 20)->default('Aprobada');
            $table->unsignedSmallInteger('dias_solicitados')->nullable();
        });

        // Los CHECK son de PostgreSQL; en SQLite (pruebas automatizadas) se omiten.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE publicidad_comercio ADD CONSTRAINT chk_publicidad_estado CHECK (estado IN ('Pendiente', 'Aprobada', 'Rechazada'))");
            DB::statement('ALTER TABLE publicidad_comercio ADD CONSTRAINT chk_publicidad_dias CHECK (dias_solicitados IS NULL OR dias_solicitados IN (7, 15, 30))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE publicidad_comercio DROP CONSTRAINT IF EXISTS chk_publicidad_estado');
            DB::statement('ALTER TABLE publicidad_comercio DROP CONSTRAINT IF EXISTS chk_publicidad_dias');
        }
        Schema::table('publicidad_comercio', function (Blueprint $table) {
            $table->dropColumn(['estado', 'dias_solicitados']);
        });
    }
};
