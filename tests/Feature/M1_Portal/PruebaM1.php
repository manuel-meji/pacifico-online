<?php

namespace Tests\Feature\M1_Portal;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Base de las pruebas de M1. Antes de cada prueba deja los cuatro proveedores
 * (M2, M3, M4 y M5) en modo 'doble', sin importar lo que diga el .env de quien
 * las ejecuta (M1_M2_MODO, M1_M3_MODO, M1_M4_MODO, M1_M5_MODO). Así las
 * pruebas no dependen de que el módulo real tenga datos o esté disponible.
 *
 * Una prueba que necesite el servicio real lo pide de forma explícita con
 * config(['integraciones.m1.<proveedor>.modo' => 'real']).
 */
abstract class PruebaM1 extends TestCase
{
    private const PROVEEDORES = ['m2', 'm3', 'm4', 'm5'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::PROVEEDORES as $proveedor) {
            config(["integraciones.m1.{$proveedor}.modo" => 'doble']);
        }

        $this->crearTablasDePromociones();
    }

    /**
     * Desde 1.4.0 la búsqueda, la ficha, los destacados y el micrositio
     * consultan las promociones vigentes, así que todas las pruebas de M1
     * tienen estas tablas (versión simple para SQLite, sin los CHECK de
     * PostgreSQL de la migración).
     */
    private function crearTablasDePromociones(): void
    {
        if (! Schema::hasTable('promociones')) {
            Schema::create('promociones', function (Blueprint $tabla): void {
                $tabla->id('id_promocion');
                $tabla->unsignedBigInteger('id_comercio');
                $tabla->unsignedBigInteger('id_producto');
                $tabla->string('titulo', 120);
                $tabla->string('descripcion', 500)->nullable();
                $tabla->string('tipo', 20);
                $tabla->decimal('valor', 12, 2);
                $tabla->string('alcance', 20);
                $tabla->timestamp('fecha_inicio');
                $tabla->timestamp('fecha_fin');
                $tabla->boolean('activa')->default(true);
                $tabla->boolean('oculta')->default(false);
                $tabla->string('motivo_ocultamiento', 500)->nullable();
                $tabla->smallInteger('orden')->nullable();
                $tabla->unsignedBigInteger('id_usuario_crea');
                $tabla->timestamps();
            });
        }

        if (! Schema::hasTable('promocion_variaciones')) {
            Schema::create('promocion_variaciones', function (Blueprint $tabla): void {
                $tabla->id('id_promocion_variacion');
                $tabla->unsignedBigInteger('id_promocion');
                $tabla->unsignedBigInteger('id_variacion');
                $tabla->timestamps();
                $tabla->unique(['id_promocion', 'id_variacion']);
            });
        }
    }
}
