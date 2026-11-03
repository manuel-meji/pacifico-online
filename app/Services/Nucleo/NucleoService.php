<?php

namespace App\Services\Nucleo;

use App\Contracts\Nucleo\NucleoServiceInterface;

class NucleoService implements NucleoServiceInterface
{
    public function __construct(private readonly BitacoraAuditoria $bitacoraAuditoria,) {
    }
    public function getComercioActualId(): ?int
    {
        /* Comentario de la persona 7: Martin Chavez Angulo
        TODO PERSONA 6:
         * Este valor actualmente utiliza una sesión genérica.
         * Cuando Persona 6 termine EstablecerComercio / aislamiento,
         * debe revisarse para utilizar el contexto definitivo del Núcleo.
         
         * CORRECCIÓN NECESARIA:
         * No debe existir un comercio por defecto = 1, porque podría
         * provocar acceso accidental a datos de otro comercio.
        */

        // Regla RN-12: Retornar ID del comercio de la sesión autenticada
        return session('comercio_id');
    }

    public function registrarAuditoria(string $accion, string $entidad, int $entidadId, array $detalles = []): void
    {
        $this->bitacoraAuditoria->registrar($accion,$entidad,$entidadId,$detalles);
    }
}
