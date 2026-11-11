<?php

namespace App\Services\M4_Inventario\Dominio\EstadosReserva;

use App\Enums\M4_Inventario\EstadoReservaCodigo;

/**
 * LIBERADA: estado final, no admite transiciones.
 */
class EstadoLiberada extends EstadoBase
{
    public function codigo(): EstadoReservaCodigo
    {
        return EstadoReservaCodigo::Liberada;
    }
}
