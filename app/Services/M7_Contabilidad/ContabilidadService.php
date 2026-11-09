<?php

namespace App\Services\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\ContabilidadServiceInterface;
use LogicException;

class ContabilidadService implements ContabilidadServiceInterface
{
    public function registrarAsientoDespacho(int $pedidoId, float $totalVenta, float $costoVentas): bool
    {
        /*
         * Según el PC3 colectivo, PedidoConfirmado originará el asiento de venta
         * y SubpedidoDespachado el de costo. Su integración definitiva se
         * coordinará con M5 y M4; esta operación anterior aún no los procesa.
         */
        throw new LogicException('La operación anterior no incluye comercio, subpedido ni evento; utilice los eventos contables de M7.');
    }
}
