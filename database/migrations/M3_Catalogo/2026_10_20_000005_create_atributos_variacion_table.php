<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atributos_variacion', function (Blueprint $table) {
            $table->id('id_atributo');
            $table->foreignId('id_variacion')
                ->constrained('producto_variaciones', 'id_variacion')->cascadeOnDelete();
            $table->string('tipo_atributo', 50);
            $table->string('valor', 100);
            $table->timestamps();

            $table->unique(['id_variacion', 'tipo_atributo'], 'uq_atributos_variacion_tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atributos_variacion');
    }
};