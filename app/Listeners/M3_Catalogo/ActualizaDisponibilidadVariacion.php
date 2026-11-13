<?php

namespace App\Listeners\M3_Catalogo;

use App\Listeners\M3_Catalogo\Concerns\AtiendeEventosSinInterrumpir;
use App\Models\M3_Catalogo\Producto;
use App\Models\M3_Catalogo\ProductoVariacion;
use App\Services\Nucleo\ContextoComercio;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Logica comun de los eventos de existencia de M4 (ExistenciaAgotada y ExistenciaRepuesta).
 *
 * Idempotente: procesar el mismo evento dos veces deja el mismo resultado.
 * Orden de llegada: se guarda fecha_ultimo_evento e id_ultimo_evento en la variacion. Un
 * evento con fecha anterior a la del ultimo aplicado se descarta (los eventos pueden llegar
 * desordenados), igual que SincronizarEstadoComercio con los eventos de M2.
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

            $fecha = $this->fechaDelEvento($evento);

            app(ContextoComercio::class)->ejecutarComoSistema(fn () => DB::transaction(function () use ($idEvento, $idVariacion, $idComercioEvento, $disponible, $nombreEvento, $fecha): void {
                $variacion = ProductoVariacion::query()
                    ->where('id_variacion', $idVariacion)
                    ->lockForUpdate()
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

                // Un evento sin fecha se aplica como antes, sin tocar las marcas del ultimo evento.
                // Lo mismo si la tabla todavia no tiene las columnas (migracion sin aplicar).
                if ($fecha !== null && array_key_exists('fecha_ultimo_evento', $variacion->getAttributes())) {
                    if ($idEvento !== null && $variacion->id_ultimo_evento === $idEvento) {
                        Log::info("M3: {$nombreEvento} repetido, se descarta.", [
                            'id_evento' => $idEvento,
                            'id_variacion' => $idVariacion,
                        ]);

                        return;
                    }

                    if ($variacion->fecha_ultimo_evento !== null && $fecha->lt(Carbon::parse($variacion->fecha_ultimo_evento))) {
                        Log::info("M3: {$nombreEvento} anterior al ultimo aplicado, se descarta.", [
                            'id_evento' => $idEvento,
                            'id_variacion' => $idVariacion,
                            'fecha_evento' => $fecha->toIso8601String(),
                        ]);

                        return;
                    }

                    // Texto en UTC con desplazamiento: lo entienden igual PostgreSQL (timestamptz) y SQLite.
                    $variacion->fecha_ultimo_evento = $fecha->format('Y-m-d H:i:sP');
                    $variacion->id_ultimo_evento = $idEvento;
                }

                $variacion->disponible = $disponible;
                $variacion->save();
            }));
        });
    }

    /** Fecha del evento en UTC, o null si no la trae o no se puede interpretar. */
    private function fechaDelEvento(object $evento): ?Carbon
    {
        $valor = $this->propiedad($evento, 'fechaEvento', 'fecha_evento');

        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor)->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
