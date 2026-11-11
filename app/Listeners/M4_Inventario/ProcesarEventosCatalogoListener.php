<?php

namespace App\Listeners\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Models\M4_Inventario\Existencia;
use App\Services\M4_Inventario\ValidadorPertenencia;

/**
 * ProductoPublicado (M3): crea, de forma idempotente, la existencia de la variación
 * en 0 en cada sede habilitada del comercio, sin inventar unidades físicas. Así la
 * variación aparece en el inventario del comercio desde que se publica y el portal la
 * muestra agotada hasta la primera entrada.
 */
class ProcesarEventosCatalogoListener
{
    use ProcesaEventosUnaVez;

    public function __construct(
        private readonly NucleoDependencia $nucleo,
        private readonly ValidadorPertenencia $validador,
    ) {}

    public function alPublicarProducto(object $evento): void
    {
        if (! $this->cargaValida($evento, 'ProductoPublicado', ['id_evento', 'id_comercio', 'id_producto', 'id_variacion'])) {
            return;
        }

        $this->procesarUnaVez($this->nucleo, (string) $evento->id_evento, 'ProductoPublicado', function () use ($evento): void {
            foreach ($this->validador->ubicacionesHabilitadas((int) $evento->id_comercio) as $idUbicacion) {
                Existencia::query()->firstOrCreate([
                    'id_comercio' => (int) $evento->id_comercio,
                    'id_variacion' => (int) $evento->id_variacion,
                    'id_ubicacion' => $idUbicacion,
                ], ['id_producto' => (int) $evento->id_producto]);
            }
        });
    }
}
