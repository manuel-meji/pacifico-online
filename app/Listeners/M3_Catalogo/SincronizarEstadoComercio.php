<?php

namespace App\Listeners\M3_Catalogo;

use App\Listeners\M3_Catalogo\Concerns\AtiendeEventosSinInterrumpir;
use App\Models\M3_Catalogo\EstadoComercioCatalogo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * M2 -> M3. Mantiene estado_comercios_catalogo (tabla propia de M3), de la que
 * sale la visibilidad publica de los productos. No consulta a M2.
 *
 *  - ComercioAprobado y ComercioReactivado: activo = true (upsert).
 *  - ComercioSuspendido: activo = false.
 *
 * Idempotencia y desorden: se guarda id_ultimo_evento y fecha_ultimo_evento.
 * Se descarta el evento si es el mismo que el ultimo procesado, o si su
 * fecha_hora es anterior a fecha_ultimo_evento (los eventos pueden llegar
 * desordenados).
 */
class SincronizarEstadoComercio
{
    use AtiendeEventosSinInterrumpir;

    public function alAprobar(object $evento): void
    {
        $this->sincronizar($evento, true, 'ComercioAprobado');
    }

    public function alReactivar(object $evento): void
    {
        $this->sincronizar($evento, true, 'ComercioReactivado');
    }

    public function alSuspender(object $evento): void
    {
        $this->sincronizar($evento, false, 'ComercioSuspendido');
    }

    private function sincronizar(object $evento, bool $activo, string $nombreEvento): void
    {
        $this->atender($nombreEvento, $evento, function () use ($evento, $activo, $nombreEvento): void {
            $idComercio = $this->propiedad($evento, 'id_comercio');
            $idEvento = $this->propiedad($evento, 'id_evento');
            $fecha = $this->propiedad($evento, 'fecha_hora', 'fecha_evento');

            if ($idComercio === null) {
                Log::warning("M3: {$nombreEvento} ignorado, falta id_comercio.", ['id_evento' => $idEvento]);

                return;
            }

            $fecha = $fecha === null ? Carbon::now() : Carbon::parse($fecha);

            DB::transaction(function () use ($idComercio, $idEvento, $fecha, $activo, $nombreEvento): void {
                $estado = EstadoComercioCatalogo::query()
                    ->where('id_comercio', $idComercio)
                    ->lockForUpdate()
                    ->first();

                if ($estado === null) {
                    $estado = new EstadoComercioCatalogo;
                    $estado->id_comercio = $idComercio;
                } else {
                    if ($idEvento !== null && $estado->id_ultimo_evento === $idEvento) {
                        Log::info("M3: {$nombreEvento} repetido, se descarta.", [
                            'id_evento' => $idEvento,
                            'id_comercio' => $idComercio,
                        ]);

                        return;
                    }

                    if ($estado->fecha_ultimo_evento !== null && $fecha->lt(Carbon::parse($estado->fecha_ultimo_evento))) {
                        Log::info("M3: {$nombreEvento} anterior al ultimo procesado, se descarta.", [
                            'id_evento' => $idEvento,
                            'id_comercio' => $idComercio,
                            'fecha_hora' => (string) $fecha,
                        ]);

                        return;
                    }
                }

                $estado->activo = $activo;
                $estado->id_ultimo_evento = $idEvento;
                $estado->fecha_ultimo_evento = $fecha;
                $estado->save();
            });
        });
    }
}
