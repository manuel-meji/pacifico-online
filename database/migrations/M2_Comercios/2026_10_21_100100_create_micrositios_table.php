<?php

// M2 — Micrositios y administración de comercios (SG2)
// Micrositio público de cada comercio (0..1 por comercio).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('micrositios', function (Blueprint $table) {
            $table->id('id_micrositio');
            $table->unsignedBigInteger('id_comercio');
            $table->text('descripcion')->nullable();
            $table->string('logotipo', 255)->nullable();
            $table->timestamps();
            $table->unique(['id_comercio'], 'uq_micrositios_comercio');
            $table->foreign('id_comercio', 'fk_micrositios_id_comercio')->references('id_comercio')->on('comercios')->restrictOnDelete();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('micrositios');
    }
};
