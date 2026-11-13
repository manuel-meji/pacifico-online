<?php

namespace App\Services\M6_Pagos\Clients;

/** Puerto de M6 hacia M7. */
interface ContabilidadClient
{
    public function enviarEventoFinanciero(int $idPago, string $tipo): void;

    public function consultarRegistroContablePago(int $idPago): bool;

    /**
     * Subpedidos del pago cuyo cobro ya está registrado en M7 (conexión M6–M7,
     * pago multicomercio). M6 los compara con sus facturas del pago.
     *
     * @return list<int>|null null si M7 todavía no informa el detalle por subpedido.
     */
    public function subpedidosConCobro(int $idPago): ?array;
}
