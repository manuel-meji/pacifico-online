<?php

declare(strict_types=1);

namespace App\Events\M3_Catalogo;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use InvalidArgumentException;
use Illuminate\Support\Str;

/**
 * ProductoPublicado v1.0.0 (catálogo de eventos 4.3 del PC3).
 * Productor: M3. Consumidores: M1 y M4.
 *
 * Se publica UN evento por cada variación del producto, cuando el producto pasa
 * de borrador a publicado. ShouldDispatchAfterCommit hace que Laravel lo entregue
 * después de confirmar la transacción: si hay rollback, nadie recibe el evento.
 *
 * Las propiedades usan snake_case porque el listener de M4
 * (ProcesarEventosCatalogoListener) lee $evento->id_evento, ->id_comercio,
 * ->id_producto y ->id_variacion.
 */
class ProductoPublicado implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public readonly string $id_evento;

    public readonly string $fecha_evento;

    public function __construct(
        public readonly int $id_comercio,
        public readonly int $id_producto,
        public readonly int $id_variacion,
        ?string $id_evento = null,
        ?string $fecha_evento = null,
    ) {
        foreach (['id_comercio' => $id_comercio, 'id_producto' => $id_producto, 'id_variacion' => $id_variacion] as $campo => $valor) {
            if ($valor < 1) {
                throw new InvalidArgumentException("{$campo} debe ser un entero mayor o igual a 1.");
            }
        }

        $this->id_evento = $id_evento ?? (string) Str::uuid();
        $this->fecha_evento = $fecha_evento ?? now()->toIso8601String();
    }

    /**
     * Carga útil exacta acordada con M4.
     *
     * @return array{id_evento: string, fecha_evento: string, id_comercio: int, id_producto: int, id_variacion: int}
     */
    public function payload(): array
    {
        return [
            'id_evento'    => $this->id_evento,
            'fecha_evento' => $this->fecha_evento,
            'id_comercio'  => $this->id_comercio,
            'id_producto'  => $this->id_producto,
            'id_variacion' => $this->id_variacion,
        ];
    }
}