<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sedes físicas del comercio (tiendas o bodegas), propiedad de M2.
 * Contrato M2 v1.2.1 (SedeHabilitada): id_sede es el mismo identificador que M4
 * guarda como id_ubicacion (confirmado por M4 el 6/11/2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sedes_comercio', function (Blueprint $table) {
            $table->id('id_sede');
            $table->unsignedBigInteger('id_comercio');
            $table->string('nombre', 150);
            $table->string('direccion', 255);
            $table->boolean('habilitada')->default(true);
            $table->timestamps();

            $table->foreign('id_comercio', 'fk_sedes_comercio_id_comercio')
                ->references('id_comercio')->on('comercios')->restrictOnDelete();
            $table->index('id_comercio', 'idx_sedes_comercio_id_comercio');
        });

        // Los CHECK son de PostgreSQL; en SQLite (pruebas automatizadas) se omiten.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sedes_comercio ADD CONSTRAINT chk_sedes_nombre CHECK (length(trim(nombre)) > 0)');
            DB::statement('ALTER TABLE sedes_comercio ADD CONSTRAINT chk_sedes_direccion CHECK (length(trim(direccion)) > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sedes_comercio');
    }
};
