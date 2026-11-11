<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos\Adaptadores;

use App\Contracts\M7_Contabilidad\ContabilidadServiceInterface;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Services\M6_Pagos\Clients\ContabilidadClient;

final class AdaptadorM7 implements ContabilidadClient
{
    public function __construct(
        private readonly ContabilidadServiceInterface $contabilidad,
    ) {
    }

    public function enviarEventoFinanciero(int $idPago, string $tipo): void
    {
        throw $this->pendiente();
    }

    public function consultarRegistroContablePago(int $idPago): bool
    {
        $resultado = $this->contabilidad
            ->consultarRegistroContablePago($idPago);

        return (bool) ($resultado['registrado'] ?? false);
    }

    private function pendiente(): ErrorNucleoException
    {
        return ErrorNucleoException::proveedorNoResponde(
            'M7',
            'M7 aún no implementa el procesamiento del evento financiero de pagos.'
        );
    }
}