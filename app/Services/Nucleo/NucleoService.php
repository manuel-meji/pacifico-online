<?php

namespace App\Services\Nucleo;

use App\Contracts\Nucleo\NucleoServiceInterface;

class NucleoService implements NucleoServiceInterface
{
    public function getComercioActualId(): ?int
    {
        // Regla RN-12: Retornar ID del comercio de la sesión autenticada
        return session('comercio_id', 1);
    }

    public function registrarAuditoria(string $accion, string $entidad, int $entidadId, array $detalles = []): void
    {
        // Registro en tabla auditoria_logs
    }
}
