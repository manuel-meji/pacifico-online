<?php

namespace App\Listeners\M3_Catalogo;

use App\Listeners\M3_Catalogo\Concerns\AtiendeEventosSinInterrumpir;
use App\Models\M3_Catalogo\Producto;
use App\Models\M3_Catalogo\ProductoVariacion;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Facades\Log;

/**
 * Logica comun de los eventos de existencia de M4 (ExistenciaAgotada y ExistenciaRepuesta).
 *
 * Idempotente: procesar el mismo evento dos veces deja el mismo resultado.
 * RN-12: si el id_comercio del evento no es el dueno de la variacion, se ignora y se
 * deja registrado en el log (sin lanzar excepcion).
 * Corre como sistema porque no hay un comercio en sesion al atender un evento.
 */
abstract class ActualizaDisponibilidadVariacion
{
    use AtiendeEventosSinInterrumpir;

    protected function aplicar(object $evento, bool $disponible, string $nombreEvento): void
    {
        $this->atender($nombreEvento, $evento, function () use ($evento, $disponible, $nombreEvento): void {
            $idEvento = $this->propiedad($evento, 'idEvento', 'id_evento');
            $idVariacion = $this->propiedad($evento, 'idVariacion', 'id_variacion');
            $idComercioEvento = $this->propiedad($evento, 'idComercio', 'id_comercio');

            if ($idVariacion === null || $idComercioEvento === null) {
                Log::warning("M3: {$nombreEvento} ignorado, faltan id_variacion o id_comercio.", [
                    'id_evento' => $idEvento,
                ]);

                return;
            }

            app(ContextoComercio::class)->ejecutarComoSistema(function () use ($idEvento, $idVariacion, $idComercioEvento, $disponible, $nombreEvento): void {
                $variacion = ProductoVariacion::query()
                    ->where('id_variacion', $idVariacion)
                    ->first();

                if ($variacion === null) {
                    Log::warning("M3: {$nombreEvento} ignorado, la variacion no existe.", [
                        'id_evento' => $idEvento,
                        'id_variacion' => $idVariacion,
                    ]);

                    return;
                }

                $idComercioDueno = Producto::query()
                    ->where('id_producto', $variacion->id_producto)
                    ->value('id_comercio');

                if ((int) $idComercioDueno !== (int) $idComercioEvento) {
                    Log::warning("M3: {$nombreEvento} ignorado, el comercio del evento no es dueno de la variacion.", [
                        'id_evento' => $idEvento,
                        'id_variacion' => $idVariacion,
                        'id_comercio_evento' => $idComercioEvento,
                        'id_comercio_dueno' => $idComercioDueno,
                    ]);

                    return;
                }

                $variacion->disponible = $disponible;
                $variacion->save();
            });
        });
    }
}
