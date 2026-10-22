<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M1 Portal público — Filtros usados en cada búsqueda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filtros_aplicados', function (Blueprint $table) {
            $table->id('id_filtro_aplicado');
            $table->foreignId('id_busqueda_usuario')
                  ->constrained('busquedas_usuario', 'id_busqueda_usuario')
                  ->cascadeOnDelete();
            $table->string('tipo_filtro', 30);
            $table->string('valor_filtro', 100);

            
            $table->unique(['id_busqueda_usuario', 'tipo_filtro', 'valor_filtro'], 'uq_filtros_aplicados_busqueda_tipo_valor');
        });

        DB::statement("ALTER TABLE filtros_aplicados ADD CONSTRAINT chk_filtros_aplicados_tipo
                       CHECK (tipo_filtro IN ('categoria', 'subcategoria', 'marca', 'comercio',
                                              'precio_minimo', 'precio_maximo', 'disponibilidad', 'orden'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('filtros_aplicados');
    }
};