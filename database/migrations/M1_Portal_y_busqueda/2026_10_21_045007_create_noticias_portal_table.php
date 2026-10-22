<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M1 Portal público — Noticias y avisos publicados por el Encargado de la plataforma (RN-M1-11).
 * RN-M1-10 exige fecha de inicio y de fin también para las noticias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('noticias_portal', function (Blueprint $table) {
            $table->id('id_noticia_portal');
            $table->unsignedBigInteger('id_usuario_autor');
            $table->string('titulo_noticia', 150);
            $table->string('slug_noticia', 170);
            $table->string('resumen_noticia', 300);
            $table->text('contenido_noticia');
            $table->string('imagen_url', 255)->nullable();
            $table->string('estado_noticia', 20)->default('borrador');
            $table->timestamp('fecha_inicio');
            $table->timestamp('fecha_fin');
            $table->timestamps();

            $table->unique('slug_noticia', 'uq_noticias_portal_slug');
        });

        DB::statement("ALTER TABLE noticias_portal ADD CONSTRAINT chk_noticias_portal_estado
                       CHECK (estado_noticia IN ('borrador', 'publicada', 'archivada'))");
        DB::statement('ALTER TABLE noticias_portal ADD CONSTRAINT chk_noticias_portal_vigencia
                       CHECK (fecha_fin > fecha_inicio)');
        DB::statement("ALTER TABLE noticias_portal ADD CONSTRAINT chk_noticias_portal_slug
                       CHECK (slug_noticia ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");
        DB::statement("ALTER TABLE noticias_portal ADD CONSTRAINT chk_noticias_portal_titulo
                       CHECK (btrim(titulo_noticia) <> '')");
    }

    public function down(): void
    {
        Schema::dropIfExists('noticias_portal');
    }
};