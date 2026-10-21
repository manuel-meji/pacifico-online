<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_relacionados', function (Blueprint $table) {
            $table->unsignedBigInteger('id_producto');
            $table->unsignedBigInteger('id_producto_relacionado');
            // Ambos productos del mismo comercio (RN-M3-12).
            $table->unsignedBigInteger('id_comercio');
            $table->timestamps();

            $table->primary(['id_producto', 'id_producto_relacionado'], 'pk_productos_relacionados');
            $table->foreign(['id_producto', 'id_comercio'], 'fk_relacionados_origen')
                ->references(['id_producto', 'id_comercio'])->on('productos')->cascadeOnDelete();
            $table->foreign(['id_producto_relacionado', 'id_comercio'], 'fk_relacionados_destino')
                ->references(['id_producto', 'id_comercio'])->on('productos')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE productos_relacionados ADD CONSTRAINT ck_relacionados_distintos CHECK (id_producto <> id_producto_relacionado)');
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_relacionados');
    }
};