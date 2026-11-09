<?php

namespace Tests\Feature\M1_Portal\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de noticias de M1 (contrato 1.4.0, SC-08) en el SQLite en memoria de
 * las pruebas. Las migraciones usan CHECK de PostgreSQL y llaves foráneas
 * hacia el núcleo que no corren en SQLite, así que se crea una versión simple
 * con las mismas columnas; las restricciones se validan contra el ambiente.
 */
trait CreaTablasDeNoticias
{
    protected function prepararNoticias(): void
    {
        Schema::create('noticias_portal', function (Blueprint $tabla): void {
            $tabla->id('id_noticia_portal');
            $tabla->unsignedBigInteger('id_usuario_autor');
            $tabla->string('titulo_noticia', 150);
            $tabla->string('slug_noticia', 170)->unique();
            $tabla->string('resumen_noticia', 300);
            $tabla->text('contenido_noticia');
            $tabla->string('imagen_url', 255)->nullable();
            $tabla->string('estado_noticia', 20)->default('borrador');
            $tabla->timestamp('fecha_inicio');
            $tabla->timestamp('fecha_fin');
            $tabla->timestamps();
        });

        Schema::create('noticia_promociones', function (Blueprint $tabla): void {
            $tabla->id('id_noticia_promocion');
            $tabla->unsignedBigInteger('id_noticia_portal');
            $tabla->unsignedBigInteger('id_promocion');
            $tabla->timestamps();
            $tabla->unique(['id_noticia_portal', 'id_promocion']);
        });
    }
}
