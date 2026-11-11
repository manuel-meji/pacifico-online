<?php

declare(strict_types=1);

namespace Tests\Unit\M3_Catalogo\Concerns;

use App\Services\Nucleo\ContextoComercio;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Esquema minimo de M3 para pruebas unitarias en SQLite en memoria (phpunit.xml).
 *
 * Las migraciones reales de M3 usan ALTER TABLE ... ADD CONSTRAINT y regex (~), que
 * SQLite no soporta, por eso se replican aqui las tablas con los CHECK dentro del
 * CREATE TABLE. Las reglas deben coincidir con las migraciones de database/migrations/M3_Catalogo.
 * No usa RefreshDatabase: cada prueba arranca con una base en memoria nueva.
 */
trait PreparaEsquemaM3
{
    protected function crearEsquemaM3(): void
    {
        DB::statement('DROP TABLE IF EXISTS producto_variaciones');
        DB::statement('DROP TABLE IF EXISTS productos');

        DB::statement("CREATE TABLE productos (
            id_producto INTEGER PRIMARY KEY AUTOINCREMENT,
            id_comercio INTEGER NOT NULL,
            id_categoria INTEGER NOT NULL,
            id_marca INTEGER NULL,
            nombre VARCHAR(150) NOT NULL,
            descripcion TEXT NULL,
            video_url VARCHAR(500) NULL,
            destacado BOOLEAN NOT NULL DEFAULT 0,
            estado_producto VARCHAR(20) NOT NULL DEFAULT 'borrador',
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            CONSTRAINT uq_productos_producto_comercio UNIQUE (id_producto, id_comercio),
            CONSTRAINT ck_productos_estado CHECK (estado_producto IN ('borrador', 'publicado', 'desactivado'))
        )");

        DB::statement("CREATE TABLE producto_variaciones (
            id_variacion INTEGER PRIMARY KEY AUTOINCREMENT,
            id_producto INTEGER NOT NULL,
            id_comercio INTEGER NOT NULL,
            sku VARCHAR(100) NOT NULL,
            precio NUMERIC NOT NULL,
            tarifa_impuesto NUMERIC NOT NULL,
            codigo_barras VARCHAR(14) NULL,
            codigo_fabricante VARCHAR(100) NULL,
            disponible BOOLEAN NOT NULL DEFAULT 1,
            tipo_descuento VARCHAR(20) NOT NULL DEFAULT 'ninguno',
            valor_descuento NUMERIC NOT NULL DEFAULT 0,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            CONSTRAINT fk_variaciones_producto_comercio FOREIGN KEY (id_producto, id_comercio)
                REFERENCES productos (id_producto, id_comercio),
            CONSTRAINT uq_variaciones_comercio_sku UNIQUE (id_comercio, sku),
            CONSTRAINT ck_variaciones_precio CHECK (precio >= 0),
            CONSTRAINT ck_variaciones_tarifa CHECK (tarifa_impuesto >= 0 AND tarifa_impuesto <= 100),
            CONSTRAINT chk_variacion_tipo_descuento CHECK (tipo_descuento IN ('ninguno', 'porcentaje', 'monto_fijo')),
            CONSTRAINT chk_variacion_valor_descuento CHECK (
                valor_descuento >= 0
                AND (tipo_descuento <> 'ninguno' OR valor_descuento = 0)
                AND (tipo_descuento <> 'porcentaje' OR valor_descuento <= 100)
                AND (tipo_descuento <> 'monto_fijo' OR valor_descuento <= precio)
            )
        )");
    }

    /** Ejecuta sin el filtro de aislamiento del nucleo, para preparar o revisar datos de varios comercios. */
    protected function comoSistema(Closure $operacion): mixed
    {
        return app(ContextoComercio::class)->ejecutarComoSistema($operacion);
    }
}