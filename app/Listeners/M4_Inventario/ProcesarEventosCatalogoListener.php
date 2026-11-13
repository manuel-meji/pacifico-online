<?php

namespace App\Listeners\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Events\M4_Inventario\ExistenciaAgotada;
use App\Models\M4_Inventario\Existencia;
use App\Services\M4_Inventario\Repositorios\ExistenciaRepositoryInterface;
use App\Services\M4_Inventario\ValidadorPertenencia;

/**
 * ProductoPublicado (M3): crea, de forma idempotente, la existencia de la variación
 * en 0 en cada sede habilitada del comercio, sin inventar unidades físicas. Así la
 * variación aparece en el inventario del comercio desde que se publica y el portal la
 * muestra agotada hasta la primera entrada.
 *
 * Si la variación no tiene unidades disponibles en el comercio, publica ExistenciaAgotada
 * (acordado con M3): M3 la marca como no disponible desde la publicación, y la primera
 * entrada publica ExistenciaRepuesta (PublicadorDisponibilidad). Un ProductoPublicado
 * repetido (mismo id_evento) no vuelve a publicar nada.
 */
class ProcesarEventosCatalogoListener
{
    use ProcesaEventosUnaVez;

    public function __construct(
        private readonly NucleoDependencia $nucleo,
        private readonly ValidadorPertenencia $validador,
        private readonly ExistenciaRepositoryInterface $existencias,
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

            if ($this->existencias->disponibleTotal((int) $evento->id_comercio, (int) $evento->id_variacion) === 0) {
                event(new ExistenciaAgotada((int) $evento->id_comercio, (int) $evento->id_producto, (int) $evento->id_variacion));
            }
        });
    }
}
