<?php

namespace App\Contracts\Nucleo;

interface NucleoServiceInterface
{
    /**
     * Obtener el ID del comercio autenticado en la sesión actual (Regla RN-12).
     */
    public function getComercioActualId(): ?int;

    /**
     * Registrar evento en la bitácora central de auditoría.
     */
    public function registrarAuditoria(string $accion, string $entidad, int $entidadId, array $detalles = []): void;
}
