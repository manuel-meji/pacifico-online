<?php

namespace App\Contracts\M7_Contabilidad;

interface ContabilidadServiceInterface
{
    /**
     * Generar asiento contable automático de venta y costo de ventas al despachar.
     * Se mantiene temporalmente por compatibilidad con consumidores anteriores.
     */
    public function registrarAsientoDespacho(
        int $pedidoId,
        float $totalVenta,
        float $costoVentas
    ): bool;

    /**
     * Consulta si un pago ya tiene registro contable en M7.
     *
     * @return array{
     *     id_pago:int,
     *     registrado:bool,
     *     asiento_id:?int,
     *     fecha_registro:?string,
     *     subpedidos_registrados?:list<int>
     * }
     */
    public function consultarRegistroContablePago(int $idPago): array;
}
