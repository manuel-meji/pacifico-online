<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imagenes_producto', function (Blueprint $table) {
            $table->id('id_imagen');
            $table->foreignId('id_producto')
                ->constrained('productos', 'id_producto')->cascadeOnDelete();
            $table->foreignId('id_variacion')->nullable()
                ->constrained('producto_variaciones', 'id_variacion')->nullOnDelete();
            $table->string('imagen_url', 500);
            // Opcionales: el contrato no los recibe; la logica del modulo los valida.
            $table->string('formato', 3)->nullable();
            $table->unsignedInteger('tamano_bytes')->nullable();
            $table->unsignedSmallInteger('orden')->default(1);
            $table->timestamps();

            // RN-M3-09: orden entre 1 y 8 y unico por producto => maximo 8 imagenes.
            $table->unique(['id_producto', 'orden'], 'uq_imagenes_producto_orden');
        });

        DB::statement('ALTER TABLE imagenes_producto ADD CONSTRAINT ck_imagenes_orden CHECK (orden BETWEEN 1 AND 8)');
        DB::statement("ALTER TABLE imagenes_producto ADD CONSTRAINT ck_imagenes_formato CHECK (formato IS NULL OR formato IN ('jpg', 'png'))");
        DB::statement('ALTER TABLE imagenes_producto ADD CONSTRAINT ck_imagenes_tamano CHECK (tamano_bytes IS NULL OR (tamano_bytes > 0 AND tamano_bytes <= 5242880))');
    }

    public function down(): void
    {
        Schema::dropIfExists('imagenes_producto');
    }
};