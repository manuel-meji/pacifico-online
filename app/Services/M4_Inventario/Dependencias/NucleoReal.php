<?php

namespace App\Services\M4_Inventario\Dependencias;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Contracts\Nucleo\NucleoServiceInterface;

/**
 * Núcleo real, llamado por NucleoServiceInterface dentro del mismo proceso.
 */
class NucleoReal implements NucleoDependencia
{
    public function __construct(private readonly NucleoServiceInterface $nucleo) {}

    public function comercioActual(): ?int
    {
        return $this->nucleo->getComercioActualId();
    }

    public function registrarAuditoria(string $accion, string $entidad, int $entidadId, array $detalles = []): void
    {
        $this->nucleo->registrarAuditoria($accion, $entidad, $entidadId, $detalles);
    }
}
