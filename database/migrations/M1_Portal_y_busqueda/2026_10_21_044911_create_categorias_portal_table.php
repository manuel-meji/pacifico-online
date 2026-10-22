<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M1 Portal público — Presentación de las categorías de M3 en el menú del portal.
 * La categoría (nombre y jerarquía) pertenece a M3; M1 solo guarda orden, visibilidad e ícono.
 * La llave foránea hacia categorias (M3) se agrega en la migración 100010.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_portal', function (Blueprint $table) {
            $table->id('id_categoria_portal');
            $table->unsignedBigInteger('id_categoria');
            $table->smallInteger('orden_visualizacion');
            $table->boolean('visible_en_menu')->default(true);
            $table->string('icono_url', 255)->nullable();
            $table->timestamps();

            
            $table->unique('id_categoria', 'uq_categorias_portal_id_categoria');
        });

        DB::statement('ALTER TABLE categorias_portal ADD CONSTRAINT chk_categorias_portal_orden
                       CHECK (orden_visualizacion > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_portal');
    }
};