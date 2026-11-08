<?php

namespace App\Services\Nucleo;

use App\Contracts\Nucleo\NucleoServiceInterface;

class NucleoService implements NucleoServiceInterface
{
    public function __construct(
        private readonly BitacoraAuditoria $bitacoraAuditoria,
        private readonly ContextoComercio $contextoComercio,
    ) {}

    /**
     * Regla RN-12: comercio del ámbito de aislamiento de la petición actual
     * (lo establece el middleware EstablecerComercio). Devuelve null cuando no hay
     * comercio en el ámbito: nunca se asume un comercio por defecto.
     */
    public function getComercioActualId(): ?int
    {
        return $this->contextoComercio->idActual();
    }

    public function registrarAuditoria(string $accion, string $entidad, int $entidadId, array $detalles = []): void
    {
        $this->bitacoraAuditoria->registrar($accion, $entidad, $entidadId, $detalles);
    }
}
