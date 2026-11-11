<?php

namespace App\Events\M4_Inventario;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

/**
 * Base de los eventos que publica M4 (catálogo 4.3 del PC3, v1.0.0).
 * Cada evento lleva id_evento (UUID) para que los consumidores sean idempotentes.
 * ShouldDispatchAfterCommit: si se publica dentro de una transacción, Laravel lo
 * entrega después del commit (un rollback no deja eventos de cambios que no ocurrieron).
 */
abstract class EventoInventario implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public const VERSION = '1.0.0';

    public readonly string $idEvento;

    public readonly string $fechaEvento;

    public function __construct()
    {
        $this->idEvento = (string) Str::uuid();
        $this->fechaEvento = now()->toIso8601String();
    }

    /**
     * Carga útil del evento según el esquema del contrato.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'id_evento' => $this->idEvento,
            'version_evento' => self::VERSION,
            'fecha_evento' => $this->fechaEvento,
        ] + $this->datos();
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function datos(): array;
}
