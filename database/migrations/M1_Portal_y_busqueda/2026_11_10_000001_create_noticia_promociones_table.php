<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1 Portal público — Promociones que enlaza una noticia (contrato 1.4.0,
 * SC-08). La noticia es una entidad aparte: esta tabla solo guarda el enlace,
 * y las dos tablas son de M1. El portal muestra solo las promociones del
 * enlace que estén vigentes y visibles.
 *
 * No correr migrate:fresh, reset, rollback ni db:wipe (base compartida).
 * Correr solo este archivo:
 *     php artisan migrate --path=database/migrations/M1_Portal_y_busqueda/2026_11_10_000001_create_noticia_promociones_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('noticia_promociones', function (Blueprint $table) {
            $table->id('id_noticia_promocion');
            $table->unsignedBigInteger('id_noticia_portal');
            $table->unsignedBigInteger('id_promocion');
            $table->timestamps();

            $table->unique(['id_noticia_portal', 'id_promocion'], 'uq_noticia_promociones');
            $table->index('id_promocion', 'ix_noticia_promociones_promocion');
            $table->foreign('id_noticia_portal', 'fk_noticia_promociones_noticias')
                ->references('id_noticia_portal')->on('noticias_portal')->cascadeOnDelete();
            $table->foreign('id_promocion', 'fk_noticia_promociones_promociones')
                ->references('id_promocion')->on('promociones')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noticia_promociones');
    }
};
