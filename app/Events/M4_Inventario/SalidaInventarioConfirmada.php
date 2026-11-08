<?php

namespace App\Events\M4_Inventario;

use App\Models\M4_Inventario\MovimientoInventario;
use App\Models\M4_Inventario\Reserva;

/**
 * SalidaInventarioConfirmada v1.0.0. Consumidor: M7 (costo de ventas, paso 8 del
 * recorrido). Se publica una vez por salida física de una reserva, después del commit.
 */
class SalidaInventarioConfirmada extends EventoInventario
{
    /** @var array<string, mixed> */
    private array $datos;

    public function __construct(MovimientoInventario $salida, Reserva $reserva)
    {
        parent::__construct();
        $this->datos = [
            'id_movimiento' => $salida->id_movimiento,
            'id_comercio' => $salida->id_comercio,
            'id_pedido' => $reserva->id_pedido,
            'id_subpedido' => $reserva->id_subpedido,
            'id_variacion' => $salida->id_variacion,
            'id_ubicacion' => $salida->id_ubicacion,
            'cantidad' => $salida->cantidad,
            'costo_unitario' => $salida->costo_unitario,
            'fecha_movimiento' => $salida->fecha_hora->toIso8601String(),
            'monto_total' => $salida->lineaKardex?->monto_total ?? round($salida->cantidad * $salida->costo_unitario, 2),
        ];
    }

    protected function datos(): array
    {
        return $this->datos;
    }
}
