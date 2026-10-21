<?php

namespace App\Services\M3_Catalogo;

use App\Contracts\M3_Catalogo\CatalogoServiceInterface;

class CatalogoService implements CatalogoServiceInterface
{
    // Datos fijos para la prueba de concepto (PC3). En el PC4 se leen de la base de datos.
    // Cualquier id mayor a 1000 se trata como inexistente, para poder probar el 404.

    public function obtenerDetalleProducto(int $productoId): ?array
    {
        if ($productoId > 1000) {
            return null;
        }

        return [
            'id_producto'     => $productoId,
            'id_comercio'     => 1,
            'id_categoria'    => 1,
            'id_marca'        => null,
            'nombre'          => 'Producto Ejemplo',
            'descripcion'     => 'Detalle del producto',
            'video_url'       => null,
            'destacado'       => false,
            'estado_producto' => 'publicado',
            'created_at'      => '2026-10-20T00:00:00Z',
            'variaciones'     => [[
                'id_variacion'    => 101,
                'id_producto'     => $productoId,
                'sku'             => 'SKU-001-AZUL',
                'precio'          => 15000.00,
                'tarifa_impuesto' => 13.0,
                'disponible'      => true,
                'atributos'       => [['tipo_atributo' => 'Color', 'valor' => 'Azul']],
            ]],
            'imagenes'        => [],
        ];
    }

    public function obtenerVariacion(int $variacionId): ?array
    {
        if ($variacionId > 1000) {
            return null;
        }

        return [
            'id_variacion'    => $variacionId,
            'id_producto'     => 1,
            'sku'             => 'SKU-001-AZUL',
            'precio'          => 15000.00,
            'tarifa_impuesto' => 13.0,
            'disponible'      => true,
            'atributos'       => [['tipo_atributo' => 'Color', 'valor' => 'Azul']],
            'producto'        => [
                'id_producto'     => 1,
                'id_comercio'     => 1,
                'id_categoria'    => 1,
                'nombre'          => 'Producto Ejemplo',
                'estado_producto' => 'publicado',
                'created_at'      => '2026-10-20T00:00:00Z',
            ],
        ];
    }
}