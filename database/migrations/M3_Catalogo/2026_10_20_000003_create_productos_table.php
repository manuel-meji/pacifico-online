<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id('id_producto');
            // Referencia logica a M2 (sin FK fisica, decision 1.9 del Comite).
            $table->unsignedBigInteger('id_comercio');
            $table->foreignId('id_categoria')
                ->constrained('categorias', 'id_categoria')->restrictOnDelete();
            $table->foreignId('id_marca')->nullable()
                ->constrained('marcas', 'id_marca')->nullOnDelete();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->string('video_url', 500)->nullable();   // RN-M3-10: opcional
            $table->boolean('destacado')->default(false);   // RN-M3-14
            $table->timestamps();

            // Habilita las FK compuestas (id_producto, id_comercio) de otras tablas.
            $table->unique(['id_producto', 'id_comercio'], 'uq_productos_producto_comercio');
            $table->index('id_comercio', 'ix_productos_comercio');
            $table->index('id_categoria', 'ix_productos_categoria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};