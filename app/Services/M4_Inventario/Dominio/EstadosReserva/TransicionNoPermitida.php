<?php

namespace App\Services\M4_Inventario\Dominio\EstadosReserva;

use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;

/**
 * Transición de estado que la reserva no admite (ERR-M4-006, 409).
 */
class TransicionNoPermitida extends ErrorInventarioException
{
    public function __construct(public readonly string $desde, public readonly string $hacia)
    {
        parent::__construct('ERR-M4-006', 409, 'La reserva no está en un estado que permita esta operación.', [
            'estado_actual' => $desde,
            'operacion' => $hacia,
        ]);
    }
}
