<?php

namespace App\Services\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\ContabilidadServiceInterface;
use Illuminate\Support\Facades\Log;

class ContabilidadService implements ContabilidadServiceInterface
{
    public function registrarAsientoDespacho(int $pedidoId, float $totalVenta, float $costoVentas): bool
    {
        /*
         * Según el PC3 colectivo, PedidoConfirmado originará el asiento de venta
         * y SubpedidoDespachado el de costo. Su integración definitiva se
         * coordinará con M5 y M4. Se conserva el retorno anterior hasta acordar
         * y versionar el reemplazo del contrato; no implica persistencia.
         */
        Log::warning('M7: registrarAsientoDespacho conserva el retorno anterior por compatibilidad temporal; no genera asientos. Su reemplazo requiere coordinación y versionado del contrato.', [
            'id_pedido' => $pedidoId,
        ]);

        return true;
    }
}
