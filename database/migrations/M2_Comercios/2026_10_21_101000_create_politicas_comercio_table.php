<?php

// M2 — Micrositios y administración de comercios (SG2)
// Políticas del comercio; las de envío guardan sus tarifas.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('politicas_comercio', function (Blueprint $table) {
            $table->id('id_politica');
            $table->unsignedBigInteger('id_comercio');
            $table->string('tipo', 30);
            $table->text('contenido');
            $table->jsonb('configuracion_envio')->nullable();
            $table->timestamps();
            $table->foreign('id_comercio', 'fk_politicas_comercio_id_comercio')->references('id_comercio')->on('comercios')->restrictOnDelete();
        });

        // Restricciones que implementan reglas de negocio (sección 6.3)
        DB::statement("ALTER TABLE politicas_comercio ADD CONSTRAINT chk_politicas_tipo CHECK (tipo IN ('envio', 'devolucion', 'garantia', 'privacidad', 'otra'))"); // Tipos de política
        DB::statement("ALTER TABLE politicas_comercio ADD CONSTRAINT chk_politicas_configuracion_envio CHECK (tipo = 'envio' OR configuracion_envio IS NULL)"); // Solo las políticas de envío tienen tarifas
    }

    public function down(): void
    {
        Schema::dropIfExists('politicas_comercio');
    }
};
