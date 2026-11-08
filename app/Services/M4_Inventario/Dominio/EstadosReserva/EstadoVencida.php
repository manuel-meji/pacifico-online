<?php

namespace App\Services\M4_Inventario\Dominio\EstadosReserva;

use App\Enums\M4_Inventario\EstadoReservaCodigo;
use App\Models\M4_Inventario\Reserva;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;

/**
 * VENCIDA: estado final, no admite transiciones. Confirmarla responde ERR-M4-005 (RN-M4-014).
 */
class EstadoVencida extends EstadoBase
{
    public function codigo(): EstadoReservaCodigo
    {
        return EstadoReservaCodigo::Vencida;
    }

    public function confirmar(Reserva $reserva, int $idPedido, int $idSubpedido): void
    {
        throw ErrorInventarioException::reservaVencida();
    }
}
