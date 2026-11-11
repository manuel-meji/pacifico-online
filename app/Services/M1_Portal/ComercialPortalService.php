<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\ComercialPortalContract;

/**
 * Implementación de ComercialPortalContract (contrato M1 1.4.0, SC-08): lo
 * que otros módulos consultan desde su backend sobre destacados. Delega en
 * los servicios de M1.
 */
class ComercialPortalService implements ComercialPortalContract
{
    public function __construct(private DestacadosService $destacados) {}

    public function listarCargosDestacado(int $idComercio, string $desde, string $hasta): array
    {
        return $this->destacados->listarCargos($idComercio, $desde, $hasta);
    }
}
