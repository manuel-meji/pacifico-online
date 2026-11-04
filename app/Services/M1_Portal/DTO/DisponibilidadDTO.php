<?php

namespace App\Services\M1_Portal\DTO;

/**
 * Estado de disponibilidad que entrega M4, por producto (idVariacion nulo)
 * o por variación.
 *
 * Objeto de transferencia (Vista 6 del PC3): solo atributos públicos, sin
 * métodos. No se persiste en M1.
 */
class DisponibilidadDTO
{
    public function __construct(
        public int $idProducto,
        public ?int $idVariacion,
        public Disponibilidad $estado,
    ) {}
}
