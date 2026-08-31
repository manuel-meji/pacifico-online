<?php

namespace App\Services\M3_Catalogo;

use App\Contracts\M3_Catalogo\CatalogoServiceInterface;

class CatalogoService implements CatalogoServiceInterface
{
    public function obtenerDetalleProducto(int $productoId): ?array
    {
        return [
            'id' => $productoId,
            'comercio_id' => 1,
            'nombre' => 'Producto Ejemplo',
            'descripcion' => 'Detalle del producto',
            'variaciones' => [
                [
                    'id' => 101,
                    'sku' => 'SKU-001-AZUL',
                    'nombre' => 'Variación Azul',
                    'precio' => 15000.00,
                ]
            ]
        ];
    }

    public function obtenerVariacion(int $variacionId): ?array
    {
        return [
            'id' => $variacionId,
            'producto_id' => 1,
            'comercio_id' => 1,
            'sku' => 'SKU-001-AZUL',
            'precio' => 15000.00,
        ];
    }
}
