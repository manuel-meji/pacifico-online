<?php

// M2 — Micrositios y administración de comercios (SG2)
// Pautas de publicidad del comercio; con vigencia definen los destacados (contrato v1.2.0).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publicidad_comercio', function (Blueprint $table) {
            $table->id('id_publicidad_comercio');
            $table->unsignedBigInteger('id_comercio');
            $table->string('ubicacion', 50);
            $table->date('inicio');
            $table->date('fin');
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->foreign('id_comercio', 'fk_publicidad_comercio_id_comercio')->references('id_comercio')->on('comercios')->restrictOnDelete();
        });

        // Restricciones que implementan reglas de negocio (sección 6.3)
        DB::statement("ALTER TABLE publicidad_comercio ADD CONSTRAINT chk_publicidad_fechas CHECK (fin >= inicio)"); // Vigencia coherente
    }

    public function down(): void
    {
        Schema::dropIfExists('publicidad_comercio');
    }
};
