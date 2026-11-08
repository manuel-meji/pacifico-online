<?php

namespace App\Enums\M4_Inventario;

/**
 * Tipo de movimiento de inventario (columna movimientos_inventario.tipo y
 * tipo_movimiento del contrato). Los de reserva no cambian el físico: trazan el
 * compromiso de unidades en el kardex (RN-M4-003).
 */
enum TipoMovimiento: string
{
    case Entrada = 'entrada';
    case Salida = 'salida';
    case Ajuste = 'ajuste';
    case AjusteCompensatorio = 'ajuste_compensatorio';
    case TransferenciaSalida = 'transferencia_salida';
    case TransferenciaEntrada = 'transferencia_entrada';
    case Reingreso = 'reingreso';
    case Reserva = 'reserva';
    case ConfirmacionReserva = 'confirmacion_reserva';
    case LiberacionReserva = 'liberacion_reserva';
    case VencimientoReserva = 'vencimiento_reserva';

    public function esFisico(): bool
    {
        return ! in_array($this, [self::Reserva, self::ConfirmacionReserva, self::LiberacionReserva, self::VencimientoReserva], true);
    }

    /** referencia_tipo del contrato. */
    public function referenciaTipo(): string
    {
        return match ($this) {
            self::Entrada, self::Salida => 'manual',
            self::Ajuste, self::AjusteCompensatorio => 'conteo',
            self::TransferenciaSalida, self::TransferenciaEntrada => 'transferencia',
            default => 'reserva',
        };
    }
}
