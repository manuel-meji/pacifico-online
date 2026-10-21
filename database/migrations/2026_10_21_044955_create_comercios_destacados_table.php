<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M1 Portal público — Productos de M3 destacados en la página principal (RN-M1-05, RN-M1-11).
 * No guarda id_comercio: se obtiene del producto por contrato con M3 (evita dependencia transitiva).
 * La llave foránea hacia productos (M3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_destacados', function (Blueprint $table) {
            $table->id('id_producto_destacado');
            $table->unsignedBigInteger('id_producto');
            $table->smallInteger('posicion');
            $table->timestamp('fecha_inicio');
            $table->timestamp('fecha_fin');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE productos_destacados ADD CONSTRAINT chk_productos_destacados_posicion
                       CHECK (posicion > 0)');
        DB::statement('ALTER TABLE productos_destacados ADD CONSTRAINT chk_productos_destacados_vigencia
                       CHECK (fecha_fin > fecha_inicio)');
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_destacados');
    }
};