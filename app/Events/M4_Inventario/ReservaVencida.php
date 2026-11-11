<?php

namespace App\Events\M4_Inventario;

use App\Models\M4_Inventario\Reserva;

/**
 * ReservaVencida v1.0.0. Consumidor: M5. La publica el vencimiento automático
 * (cada 60 s, RNF-M4-007) o una reserva que se encuentra vencida al operar.
 */
class ReservaVencida extends EventoInventario
{
    /** @var array<string, mixed> */
    private array $datos;

    public function __construct(Reserva $reserva)
    {
        parent::__construct();
        $this->datos = [
            'id_reserva' => $reserva->id_reserva,
            'id_comercio' => $reserva->id_comercio,
            'id_pedido' => $reserva->id_pedido,
            'id_subpedido' => $reserva->id_subpedido,
            'fecha_expiracion' => $reserva->fecha_vencimiento->toIso8601String(),
        ];
    }

    protected function datos(): array
    {
        return $this->datos;
    }
}
