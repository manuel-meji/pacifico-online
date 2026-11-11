<?php

namespace App\Events\M4_Inventario;

/**
 * ExistenciaReservada v1.0.0. Consumidor: M5. Se publica después del commit de la reserva.
 */
class ExistenciaReservada extends EventoInventario
{
    /**
     * @param  array<string, mixed>  $reserva  Reserva del contrato.
     */
    public function __construct(public readonly array $reserva)
    {
        parent::__construct();
    }

    protected function datos(): array
    {
        return [
            'id_reserva' => $this->reserva['id_reserva'],
            'id_comercio' => $this->reserva['id_comercio'],
            'id_variacion' => $this->reserva['id_variacion'],
            'id_ubicacion' => $this->reserva['id_ubicacion'],
            'cantidad' => $this->reserva['cantidad'],
            'fecha_expiracion' => $this->reserva['fecha_expiracion'],
        ];
    }
}
