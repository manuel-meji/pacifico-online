<?php

declare(strict_types=1);

namespace App\Services\Nucleo;

use Closure;

/**
 * Ámbito de aislamiento de la petición o proceso actual (RN-12, ADR-006).
 * Se registra como scoped en AppServiceProvider: una instancia por petición.
 */
abstract class ContextoAislamiento
{
    private ?int $id = null;

    private bool $modoSistema = false;

    public function establecer(int $id): void
    {
        $this->id = $id;
    }

    public function idActual(): ?int
    {
        return $this->id;
    }

    public function esModoSistema(): bool
    {
        return $this->modoSistema;
    }

    /**
     * Solo para trabajos en cola y comandos sin usuario.
     * Desactiva el filtro únicamente durante la operación.
     */
    public function ejecutarComoSistema(Closure $operacion): mixed
    {
        $anterior = $this->modoSistema;
        $this->modoSistema = true;

        try {
            return $operacion();
        } finally {
            $this->modoSistema = $anterior;
        }
    }

    /**
     * Permiso especial auditado del Encargado de la plataforma (PC3, sección 5.3):
     * desactiva el filtro de aislamiento para el resto de la petición.
     *
     * Quien invoque este método es responsable de registrar la concesión en la
     * bitácora de auditoría (ver EstablecerComercio). No debe activarse de forma
     * implícita para ningún otro rol.
     */
    public function concederAccesoAuditado(): void
    {
        $this->modoSistema = true;
    }
}
