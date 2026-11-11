<?php

namespace App\Services\M6_Pagos\Clients;

/** Puerto de M6 hacia M7. */
interface ContabilidadClient
{
    public function enviarEventoFinanciero(int $idPago, string $tipo): void;

    public function consultarRegistroContablePago(int $idPago): bool;
}
