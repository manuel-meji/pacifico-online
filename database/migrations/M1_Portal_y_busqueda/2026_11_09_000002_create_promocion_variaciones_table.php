<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1 Portal público — Variaciones específicas a las que se aplica una
 * promoción con alcance 'variaciones' (por ejemplo, solo la talla M)
 * (contrato 1.4.0, SC-08). Una promoción con alcance 'producto' no tiene
 * filas aquí: se aplica a todas las variaciones del producto.
 *
 * Llave foránea hacia producto_variaciones (M3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promocion_variaciones', function (Blueprint $table) {
            $table->id('id_promocion_variacion');
            $table->unsignedBigInteger('id_promocion');
            $table->unsignedBigInteger('id_variacion');
            $table->timestamps();

            $table->unique(['id_promocion', 'id_variacion'], 'uq_promocion_variaciones');
            $table->index('id_variacion', 'ix_promocion_variaciones_variacion');
            $table->foreign('id_promocion', 'fk_promocion_variaciones_promociones')
                ->references('id_promocion')->on('promociones')->cascadeOnDelete();
            $table->foreign('id_variacion', 'fk_promocion_variaciones_variaciones')
                ->references('id_variacion')->on('producto_variaciones');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promocion_variaciones');
    }
};
