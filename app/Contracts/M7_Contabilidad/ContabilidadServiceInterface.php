<?php

namespace App\Contracts\M7_Contabilidad;

interface ContabilidadServiceInterface
{
    /**
     * Generar asiento contable automático de venta y costo de ventas al despachar.
     * Invocado en el paso 8 del recorrido transversal.
     */
    public function registrarAsientoDespacho(int $pedidoId, float $totalVenta, float $costoVentas): bool;
}
