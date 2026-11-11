<?php

namespace App\Contracts\M7_Contabilidad;

interface ComerciosM7GatewayInterface
{
    /**
     * Obtener los datos fiscales de un comercio.
     */
    public function obtenerDatosFiscalesComercio(int $comercioId): array;

    /**
     * Obtener la comisión ya calculada para un subpedido.
     */
    public function obtenerComisionSubpedido(int $subpedidoId): float;
}
