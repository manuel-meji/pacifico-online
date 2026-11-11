<?php

namespace App\Services\M4_Inventario\Dominio\EstadosReserva;

use App\Enums\M4_Inventario\EstadoReservaCodigo;

/**
 * CONSUMIDA: estado final, no admite transiciones.
 */
class EstadoConsumida extends EstadoBase
{
    public function codigo(): EstadoReservaCodigo
    {
        return EstadoReservaCodigo::Consumida;
    }
}
