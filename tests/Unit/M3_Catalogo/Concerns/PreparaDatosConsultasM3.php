<?php

declare(strict_types=1);

namespace Tests\Unit\M3_Catalogo\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Esquema y datos minimos para probar las consultas de CatalogoService en SQLite en memoria.
 * Reutiliza PreparaEsquemaM3 (productos y producto_variaciones) y agrega las demas tablas de M3.
 * No hay factories en el repositorio: los datos se insertan directamente.
 */
trait PreparaDatosConsultasM3
{
    use PreparaEsquemaM3;

    protected function crearEsquemaConsultasM3(): void
    {
        $this->crearEsquemaM3();

        foreach (['productos_relacionados', 'imagenes_producto', 'atributos_variacion', 'estado_comercios_catalogo', 'marcas', 'categorias'] as $tabla) {
            DB::statement("DROP TABLE IF EXISTS {$tabla}");
        }

        DB::statement('CREATE TABLE categorias (
            id_categoria INTEGER PRIMARY KEY AUTOINCREMENT,
            id_categoria_padre INTEGER NULL,
            nombre VARCHAR(100) NOT NULL,
            descripcion TEXT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
        DB::statement('CREATE TABLE marcas (
            id_marca INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(100) NOT NULL,
            logotipo_url VARCHAR(500) NULL,
            destacada BOOLEAN NOT NULL DEFAULT 0,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
        DB::statement('CREATE TABLE atributos_variacion (
            id_atributo INTEGER PRIMARY KEY AUTOINCREMENT,
            id_variacion INTEGER NOT NULL,
            tipo_atributo VARCHAR(50) NOT NULL,
            valor VARCHAR(100) NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
        DB::statement('CREATE TABLE imagenes_producto (
            id_imagen INTEGER PRIMARY KEY AUTOINCREMENT,
            id_producto INTEGER NOT NULL,
            id_variacion INTEGER NULL,
            imagen_url VARCHAR(500) NOT NULL,
            formato VARCHAR(3) NULL,
            tamano_bytes INTEGER NULL,
            orden INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
        DB::statement('CREATE TABLE productos_relacionados (
            id_producto INTEGER NOT NULL,
            id_producto_relacionado INTEGER NOT NULL,
            id_comercio INTEGER NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id_producto, id_producto_relacionado)
        )');
        DB::statement('CREATE TABLE estado_comercios_catalogo (
            id_comercio INTEGER PRIMARY KEY,
            activo BOOLEAN NOT NULL DEFAULT 0,
            fecha_ultimo_evento DATETIME NULL,
            id_ultimo_evento VARCHAR(36) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
    }

    protected function comercioActivo(int $idComercio, bool $activo = true): void
    {
        DB::table('estado_comercios_catalogo')->insert([
            'id_comercio' => $idComercio,
            'activo' => $activo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function categoria(string $nombre, ?int $padre = null): int
    {
        return (int) DB::table('categorias')->insertGetId([
            'id_categoria_padre' => $padre,
            'nombre' => $nombre,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_categoria');
    }

    protected function producto(int $idComercio, int $idCategoria, string $nombre, string $estado = 'publicado'): int
    {
        return (int) DB::table('productos')->insertGetId([
            'id_comercio' => $idComercio,
            'id_categoria' => $idCategoria,
            'nombre' => $nombre,
            'estado_producto' => $estado,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_producto');
    }

    protected function variacion(int $idProducto, int $idComercio, string $sku, float $precio = 10000, bool $disponible = true): int
    {
        return (int) DB::table('producto_variaciones')->insertGetId([
            'id_producto' => $idProducto,
            'id_comercio' => $idComercio,
            'sku' => $sku,
            'precio' => $precio,
            'tarifa_impuesto' => 13,
            'disponible' => $disponible,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_variacion');
    }
}
