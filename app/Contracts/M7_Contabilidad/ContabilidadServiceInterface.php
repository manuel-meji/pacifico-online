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
     * Consulta el cobro global de PagoConfirmado en el libro de plataforma.
     * Los subpedidos informan distribuciones de FacturaGenerada, no otros cobros.
     *
     * @return array{
     *     id_pago:int,
     *     registrado:bool,
     *     asiento_id:?int,
     *     fecha_registro:?string,
     *     subpedidos_registrados?:list<int>,
     *     libro?:string,
     *     estado_cuadre?:string,
     *     monto_cobro_centimos?:int,
     *     monto_facturado_centimos?:?int
     * }
     */
    public function consultarRegistroContablePago(int $idPago): array;
}
