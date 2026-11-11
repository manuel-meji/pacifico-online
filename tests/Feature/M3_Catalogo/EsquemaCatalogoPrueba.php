<?php

namespace Tests\Feature\M3_Catalogo;

use App\Services\M3_Catalogo\ProductoService;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea en SQLite las tablas de M3 con las mismas columnas y restricciones únicas
 * de las migraciones 2026_10_20_00000x (esas usan CHECK de PostgreSQL y no corren
 * en la base de pruebas en memoria). Si cambia una migración, cambiar aquí.
 *
 * Además deja listo el comercio de la sesión y el doble de M2, y ofrece ayudas
 * para armar productos de prueba usando el propio ProductoService.
 */
trait EsquemaCatalogoPrueba
{
    protected function prepararCatalogo(int $idComercio = 1): void
    {
        // Las pruebas siempre usan el doble de M2, aunque el .env diga 'real'.
        config(['integraciones.m3.m2.modo' => 'doble']);

        $this->crearEsquemaCatalogo();
        $this->comoComercio($idComercio);
    }

    protected function comoComercio(int $idComercio): void
    {
        app(ContextoComercio::class)->establecer($idComercio);
    }

    protected function servicioCatalogo(): ProductoService
    {
        return app(ProductoService::class);
    }

    /** Crea un producto en borrador del comercio de la sesión y devuelve su id. */
    protected function nuevoProducto(): int
    {
        $categoria = $this->servicioCatalogo()->crearCategoria(['nombre' => 'Categoría '.uniqid()]);

        return $this->servicioCatalogo()->crearProducto([
            'id_categoria' => $categoria['id_categoria'],
            'nombre' => 'Producto de prueba',
        ])['id_producto'];
    }

    /** @return array<string, mixed> */
    protected function datosVariacion(string $sku = 'SKU-001'): array
    {
        return ['sku' => $sku, 'precio' => 15000, 'tarifa_impuesto' => 13];
    }

    protected function crearEsquemaCatalogo(): void
    {
        if (Schema::hasTable('productos')) {
            return;
        }

        Schema::create('categorias', function (Blueprint $tabla) {
            $tabla->id('id_categoria');
            $tabla->unsignedBigInteger('id_categoria_padre')->nullable();
            $tabla->string('nombre', 100);
            $tabla->text('descripcion')->nullable();
            $tabla->timestamps();
        });

        Schema::create('marcas', function (Blueprint $tabla) {
            $tabla->id('id_marca');
            $tabla->string('nombre', 100)->unique();
            $tabla->string('logotipo_url', 500)->nullable();
            $tabla->boolean('destacada')->default(false);
            $tabla->timestamps();
        });

        Schema::create('productos', function (Blueprint $tabla) {
            $tabla->id('id_producto');
            $tabla->unsignedBigInteger('id_comercio');
            $tabla->unsignedBigInteger('id_categoria');
            $tabla->unsignedBigInteger('id_marca')->nullable();
            $tabla->string('nombre', 150);
            $tabla->text('descripcion')->nullable();
            $tabla->string('video_url', 500)->nullable();
            $tabla->boolean('destacado')->default(false);
            $tabla->string('estado_producto', 20)->default('borrador');
            $tabla->timestamps();
            $tabla->unique(['id_producto', 'id_comercio']);
        });

        Schema::create('producto_variaciones', function (Blueprint $tabla) {
            $tabla->id('id_variacion');
            $tabla->unsignedBigInteger('id_producto');
            $tabla->unsignedBigInteger('id_comercio');
            $tabla->string('sku', 100);
            $tabla->decimal('precio', 12, 2);
            $tabla->decimal('tarifa_impuesto', 5, 2);
            $tabla->string('codigo_barras', 14)->nullable();
            $tabla->string('codigo_fabricante', 100)->nullable();
            $tabla->boolean('disponible')->default(true);
            $tabla->timestamps();
            $tabla->unique(['id_comercio', 'sku']);
        });

        Schema::create('atributos_variacion', function (Blueprint $tabla) {
            $tabla->id('id_atributo');
            $tabla->unsignedBigInteger('id_variacion');
            $tabla->string('tipo_atributo', 50);
            $tabla->string('valor', 100);
            $tabla->timestamps();
            $tabla->unique(['id_variacion', 'tipo_atributo']);
        });

        Schema::create('imagenes_producto', function (Blueprint $tabla) {
            $tabla->id('id_imagen');
            $tabla->unsignedBigInteger('id_producto');
            $tabla->unsignedBigInteger('id_variacion')->nullable();
            $tabla->string('imagen_url', 500);
            $tabla->string('formato', 3)->nullable();
            $tabla->unsignedInteger('tamano_bytes')->nullable();
            $tabla->unsignedSmallInteger('orden')->default(1);
            $tabla->timestamps();
            $tabla->unique(['id_producto', 'orden']);
        });

        Schema::create('productos_relacionados', function (Blueprint $tabla) {
            $tabla->unsignedBigInteger('id_producto');
            $tabla->unsignedBigInteger('id_producto_relacionado');
            $tabla->unsignedBigInteger('id_comercio');
            $tabla->timestamps();
            $tabla->primary(['id_producto', 'id_producto_relacionado']);
        });
    }
}