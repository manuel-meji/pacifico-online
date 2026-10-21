<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_variaciones', function (Blueprint $table) {
            $table->id('id_variacion');
            $table->unsignedBigInteger('id_producto');
            // Copia controlada de productos.id_comercio para garantizar
            // SKU unico por comercio (RN-M3-01). La FK compuesta impide que difiera.
            $table->unsignedBigInteger('id_comercio');
            $table->string('sku', 100);
            $table->decimal('precio', 12, 2);
            $table->decimal('tarifa_impuesto', 5, 2);
            $table->string('codigo_barras', 14)->nullable();       // RN-M3-11
            $table->string('codigo_fabricante', 100)->nullable();  // RN-M3-11
            $table->boolean('disponible')->default(true);          // RN-M3-03/04
            $table->timestamps();

            $table->foreign(['id_producto', 'id_comercio'], 'fk_variaciones_producto_comercio')
                ->references(['id_producto', 'id_comercio'])->on('productos')
                ->cascadeOnDelete();
            $table->unique(['id_comercio', 'sku'], 'uq_variaciones_comercio_sku');
            $table->index('id_producto', 'ix_variaciones_producto');
        });

        DB::statement('ALTER TABLE producto_variaciones ADD CONSTRAINT ck_variaciones_precio CHECK (precio >= 0)');
        DB::statement('ALTER TABLE producto_variaciones ADD CONSTRAINT ck_variaciones_tarifa CHECK (tarifa_impuesto >= 0 AND tarifa_impuesto <= 100)');
        DB::statement("ALTER TABLE producto_variaciones ADD CONSTRAINT ck_variaciones_codigo_barras CHECK (codigo_barras IS NULL OR codigo_barras ~ '^[0-9]{8,14}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_variaciones');
    }
};