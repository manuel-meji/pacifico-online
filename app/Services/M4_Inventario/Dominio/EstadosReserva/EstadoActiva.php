<?php

namespace App\Services\M4_Inventario\Dominio\EstadosReserva;

use App\Enums\M4_Inventario\EstadoReservaCodigo;
use App\Enums\M4_Inventario\MotivoCierre;
use App\Models\M4_Inventario\Reserva;

/**
 * ACTIVA: puede confirmarse, liberarse o vencer.
 */
class EstadoActiva extends EstadoBase
{
    public function codigo(): EstadoReservaCodigo
    {
        return EstadoReservaCodigo::Activa;
    }

    public function confirmar(Reserva $reserva, int $idPedido, int $idSubpedido): void
    {
        $reserva->id_pedido = $idPedido;
        $reserva->id_subpedido = $idSubpedido;
        $reserva->cambiarA(EstadoReservaCodigo::Confirmada, "pedido {$idPedido}, subpedido {$idSubpedido}");
    }

    public function liberar(Reserva $reserva, MotivoCierre $motivo): void
    {
        $reserva->cambiarA(EstadoReservaCodigo::Liberada, $motivo->value);
    }

    public function vencer(Reserva $reserva): void
    {
        $reserva->cambiarA(EstadoReservaCodigo::Vencida, MotivoCierre::Vencimiento->value);
    }
}
