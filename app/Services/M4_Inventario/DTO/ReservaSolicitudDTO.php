<?php

namespace App\Services\M4_Inventario\DTO;

/**
 * ReservaSolicitud del contrato (POST /reservas).
 */
final readonly class ReservaSolicitudDTO
{
    public function __construct(
        public int $idComercio,
        public int $idVariacion,
        public int $cantidad,
        public ?int $idUbicacion,
        public int $minutosExpiracion,
    ) {}
}
