<?php

namespace App\Services\M4_Inventario\Dominio\EstadosReserva;

use App\Enums\M4_Inventario\EstadoReservaCodigo;
use App\Enums\M4_Inventario\MotivoCierre;
use App\Models\M4_Inventario\Reserva;

/**
 * Estado de una reserva (patrón State, figura 4.3c y sección 7.1 del PC3).
 * Cada estado decide qué transiciones admite; las no permitidas lanzan
 * TransicionNoPermitida (ERR-M4-006). Coincide con el trigger tr_reservas_transicion:
 * 1→2, 1→4, 1→5, 2→3 y 2→4.
 */
interface EstadoReserva
{
    public function codigo(): EstadoReservaCodigo;

    public function confirmar(Reserva $reserva, int $idPedido, int $idSubpedido): void;

    public function consumir(Reserva $reserva): void;

    public function liberar(Reserva $reserva, MotivoCierre $motivo): void;

    public function vencer(Reserva $reserva): void;
}
