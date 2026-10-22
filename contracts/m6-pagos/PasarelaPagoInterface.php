<?php

namespace App\Contracts\M6_Pagos;

interface PasarelaPagoInterface
{
    /**
     * Procesa un cobro de forma síncrona contra el simulador de la pasarela.
     *
     * @param string $referencia Identificador único del cobro en M6
     * @param float $monto Monto total a cobrar
     * @param array $datosPago Token/método de pago
     * @return array Resultado de la transacción con referencia_proveedor
     */
    public function procesarCobro(string $referencia, float $monto, array $datosPago): array;
}

