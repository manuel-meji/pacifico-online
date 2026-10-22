<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M1 Portal público — Promociones del portal (carrusel y bloques de la página principal).
 * No calcula descuentos: precios y cupones son de M3 y M5.
 * Las llaves foráneas hacia productos, comercios y categorias. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promociones_portal', function (Blueprint $table) {
            $table->id('id_promocion_portal');
            $table->string('titulo_promocion', 120);
            $table->string('descripcion_promocion', 500)->nullable();
            $table->string('imagen_url', 255);
            $table->string('tipo_destino', 20);
            $table->unsignedBigInteger('id_producto')->nullable();
            $table->unsignedBigInteger('id_comercio')->nullable();
            $table->unsignedBigInteger('id_categoria')->nullable();
            $table->string('enlace_url', 255)->nullable();
            $table->smallInteger('orden_visualizacion');
            $table->timestamp('fecha_inicio');               
            $table->timestamp('fecha_fin');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE promociones_portal ADD CONSTRAINT chk_promociones_portal_tipo_destino
                       CHECK (tipo_destino IN ('producto', 'comercio', 'categoria', 'enlace'))");

        
        DB::statement("ALTER TABLE promociones_portal ADD CONSTRAINT chk_promociones_portal_destino
                       CHECK (
                           (tipo_destino = 'producto'  AND id_producto  IS NOT NULL AND id_comercio IS NULL AND id_categoria IS NULL AND enlace_url IS NULL) OR
                           (tipo_destino = 'comercio'  AND id_comercio  IS NOT NULL AND id_producto IS NULL AND id_categoria IS NULL AND enlace_url IS NULL) OR
                           (tipo_destino = 'categoria' AND id_categoria IS NOT NULL AND id_producto IS NULL AND id_comercio  IS NULL AND enlace_url IS NULL) OR
                           (tipo_destino = 'enlace'    AND enlace_url   IS NOT NULL AND id_producto IS NULL AND id_comercio  IS NULL AND id_categoria IS NULL)
                       )");

        DB::statement('ALTER TABLE promociones_portal ADD CONSTRAINT chk_promociones_portal_vigencia
                       CHECK (fecha_fin > fecha_inicio)');
        DB::statement('ALTER TABLE promociones_portal ADD CONSTRAINT chk_promociones_portal_orden
                       CHECK (orden_visualizacion > 0)');
        DB::statement("ALTER TABLE promociones_portal ADD CONSTRAINT chk_promociones_portal_titulo
                       CHECK (btrim(titulo_promocion) <> '')");
    }

    public function down(): void
    {
        Schema::dropIfExists('promociones_portal');
    }
};