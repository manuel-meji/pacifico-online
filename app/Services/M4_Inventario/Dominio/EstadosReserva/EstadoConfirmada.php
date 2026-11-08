<?php

namespace App\Services\M4_Inventario\Dominio\EstadosReserva;

use App\Enums\M4_Inventario\EstadoReservaCodigo;
use App\Enums\M4_Inventario\MotivoCierre;
use App\Models\M4_Inventario\Reserva;

/**
 * CONFIRMADA: no vence (RN-M4-010); se consume con el despacho o se libera.
 */
class EstadoConfirmada extends EstadoBase
{
    public function codigo(): EstadoReservaCodigo
    {
        return EstadoReservaCodigo::Confirmada;
    }

    public function consumir(Reserva $reserva): void
    {
        $reserva->cambiarA(EstadoReservaCodigo::Consumida, 'despacho');
    }

    public function liberar(Reserva $reserva, MotivoCierre $motivo): void
    {
        $reserva->cambiarA(EstadoReservaCodigo::Liberada, $motivo->value);
    }
}
