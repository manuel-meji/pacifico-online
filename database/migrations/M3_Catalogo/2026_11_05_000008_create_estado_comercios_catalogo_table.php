<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Proyeccion local del estado del comercio (alimentada por eventos de M2). Sin FK a M2.
        Schema::create('estado_comercios_catalogo', function (Blueprint $table) {
            $table->unsignedBigInteger('id_comercio')->primary();
            $table->boolean('activo')->default(false);
            $table->timestampTz('fecha_ultimo_evento')->nullable(); // para ignorar eventos viejos
            $table->uuid('id_ultimo_evento')->nullable();            // idempotencia
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estado_comercios_catalogo');
    }
};