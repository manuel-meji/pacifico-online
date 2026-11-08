<?php

namespace App\Enums\M4_Inventario;

/**
 * Estados de la reserva (catálogo estados_reserva, RN-M4-007). El valor es el
 * id_estado_reserva de la tabla; codigo() es el texto del contrato (estado_reserva).
 */
enum EstadoReservaCodigo: int
{
    case Activa = 1;
    case Confirmada = 2;
    case Consumida = 3;
    case Liberada = 4;
    case Vencida = 5;

    public function codigo(): string
    {
        return strtolower($this->name);
    }

    public function esCerrada(): bool
    {
        return in_array($this, [self::Consumida, self::Liberada, self::Vencida], true);
    }

    /** Estados que comprometen unidades (cantidad_reservada). */
    public function comprometeUnidades(): bool
    {
        return $this === self::Activa || $this === self::Confirmada;
    }
}
