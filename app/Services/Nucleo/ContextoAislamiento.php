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
}