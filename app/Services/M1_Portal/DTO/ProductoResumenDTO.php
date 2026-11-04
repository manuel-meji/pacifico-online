<?php

namespace App\Services\M1_Portal\DTO;

/**
 * Producto en una lista (búsqueda, destacados, micrositio, relacionados).
 * precio es el menor precio de sus variaciones y precioHasta el mayor, o nulo si son iguales.
 *
 * Objeto de transferencia (Vista 6 del PC3): solo atributos públicos, sin
 * métodos. No se persiste en M1.
 */
class ProductoResumenDTO
{
    public function __construct(
        public int $idProducto,
        public int $idComercio,
        public string $nombre,
        public ?string $imagenPrincipal,
        public float $precio,
        public ?float $precioHasta,
        public string $moneda,
        public string $nombreComercio,
        public Disponibilidad $disponibilidad,
    ) {}
}
