<?php

namespace App\Services\M1_Portal\DTO;

/**
 * Ficha completa de un producto; compraHabilitada es verdadero si alguna
 * variación es comprable.
 *
 * Objeto de transferencia (Vista 6 del PC3): solo atributos públicos, sin
 * métodos. No se persiste en M1.
 */
class FichaProductoDTO
{
    public function __construct(
        public int $idProducto,
        public string $nombre,
        public ?string $descripcion,
        public ?string $marca,
        public ?string $videoUrl,
        public string $moneda,
        /** @var list<string> */
        public array $imagenes,
        /** @var list<VariacionDTO> */
        public array $variaciones,
        public ComercioDTO $comercio,
        /** @var list<ProductoResumenDTO> */
        public array $relacionados,
        public bool $compraHabilitada,
    ) {}
}
