<?php

namespace App\Services\M4_Inventario\DTO;

use App\Models\M4_Inventario\Reserva;

/**
 * Reserva creada o, en un reintento con la misma clave de idempotencia, la original.
 */
final readonly class ResultadoReserva
{
    public function __construct(public Reserva $reserva, public bool $esReintento) {}
}
