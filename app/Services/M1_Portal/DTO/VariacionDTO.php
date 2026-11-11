<?php

namespace App\Services\M1_Portal\DTO;

/**
 * Variación de un producto; ventaHabilitada viene de M3 y disponibilidad de M4.
 * Es comprable si tiene la venta habilitada y unidades disponibles.
 *
 * Objeto de transferencia (Vista 6 del PC3): solo atributos públicos, sin
 * métodos. No se persiste en M1.
 */
class VariacionDTO
{
    public function __construct(
        public int $idVariacion,
        public string $sku,
        /** @var list<array{tipo_atributo: string, valor: string}> */
        public array $atributos,
        public float $precio,
        /** @var list<string> */
        public array $imagenes,
        public bool $ventaHabilitada,
        public Disponibilidad $disponibilidad,
        public bool $comprable,
    ) {}
}
