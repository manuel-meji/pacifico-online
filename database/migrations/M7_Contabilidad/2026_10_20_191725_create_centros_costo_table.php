<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centros_costo', function (Blueprint $table) {
            $table->id('id_centro_costo');

            // Clave compartida de M2.
            $table->unsignedBigInteger('id_comercio');

            $table->string('codigo', 30);
            $table->string('nombre', 150);
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->unique(
                ['id_comercio', 'codigo'],
                'uq_centros_costo_comercio_codigo'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centros_costo');
    }
};