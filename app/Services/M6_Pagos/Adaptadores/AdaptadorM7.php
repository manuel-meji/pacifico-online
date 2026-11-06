<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos\Adaptadores;

use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Services\M6_Pagos\Clients\ContabilidadClient;

final class AdaptadorM7 implements ContabilidadClient
{
    public function enviarEventoFinanciero(int $idPago, string $tipo): void
    {
        throw $this->pendiente();
    }

    public function consultarRegistroContablePago(int $idPago): bool
    {
        throw $this->pendiente();
    }

    private function pendiente(): ErrorNucleoException
    {
        return ErrorNucleoException::proveedorNoResponde(
            'M7',
            'El contrato de M7 aún no publica las operaciones financieras de pagos.'
        );
    }
}
