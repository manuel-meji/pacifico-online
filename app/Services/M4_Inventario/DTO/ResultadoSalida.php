<?php

namespace App\Services\M4_Inventario\DTO;

use App\Models\M4_Inventario\MovimientoInventario;
use App\Models\M4_Inventario\Reserva;

/**
 * Salida por despacho registrada o, si se repite, la salida original (sin duplicar).
 */
final readonly class ResultadoSalida
{
    public function __construct(public MovimientoInventario $movimiento, public Reserva $reserva, public bool $esReintento) {}

    /**
     * Esquema SalidaReserva del contrato.
     *
     * @return array<string, mixed>
     */
    public function aContrato(): array
    {
        return [
            'id_movimiento' => $this->movimiento->id_movimiento,
            'id_kardex' => $this->movimiento->lineaKardex?->id_kardex,
            'id_reserva' => $this->reserva->id_reserva,
            'id_comercio' => $this->movimiento->id_comercio,
            'id_variacion' => $this->movimiento->id_variacion,
            'cantidad' => $this->movimiento->cantidad,
            'costo_unitario' => $this->movimiento->costo_unitario,
            'monto_total' => $this->movimiento->lineaKardex?->monto_total,
            'fecha_movimiento' => $this->movimiento->fecha_hora->toIso8601String(),
        ];
    }
}
