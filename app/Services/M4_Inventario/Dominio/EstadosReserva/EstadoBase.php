<?php

namespace App\Services\M4_Inventario\Dominio\EstadosReserva;

use App\Enums\M4_Inventario\MotivoCierre;
use App\Models\M4_Inventario\Reserva;

/**
 * Por defecto ninguna transición está permitida; cada estado sobrescribe las suyas.
 */
abstract class EstadoBase implements EstadoReserva
{
    public function confirmar(Reserva $reserva, int $idPedido, int $idSubpedido): void
    {
        throw new TransicionNoPermitida($this->codigo()->codigo(), 'confirmar');
    }

    public function consumir(Reserva $reserva): void
    {
        throw new TransicionNoPermitida($this->codigo()->codigo(), 'consumir');
    }

    public function liberar(Reserva $reserva, MotivoCierre $motivo): void
    {
        throw new TransicionNoPermitida($this->codigo()->codigo(), 'liberar');
    }

    public function vencer(Reserva $reserva): void
    {
        throw new TransicionNoPermitida($this->codigo()->codigo(), 'vencer');
    }
}
