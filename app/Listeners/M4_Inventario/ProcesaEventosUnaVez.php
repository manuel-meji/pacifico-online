<?php

namespace App\Listeners\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Models\M4_Inventario\EventoProcesadoInventario;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Idempotencia de los listeners de M4 por id_evento (sección 4.1 del PC3): el efecto
 * y el registro del evento se guardan en la misma transacción, así un evento
 * reenviado no se aplica dos veces. Un listener nunca interrumpe al módulo que
 * publicó el evento: los errores quedan en el log.
 */
trait ProcesaEventosUnaVez
{
    /**
     * @param  Closure(): void  $efecto
     * @return bool true si se aplicó; false si ya se había procesado o falló.
     */
    protected function procesarUnaVez(NucleoDependencia $nucleo, string $idEvento, string $nombreEvento, Closure $efecto): bool
    {
        try {
            return $nucleo->ejecutarEnAmbito(fn (): bool => DB::transaction(function () use ($idEvento, $nombreEvento, $efecto): bool {
                if (EventoProcesadoInventario::query()->whereKey($idEvento)->lockForUpdate()->exists()) {
                    return false;
                }

                $efecto();

                EventoProcesadoInventario::query()->create([
                    'id_evento' => $idEvento,
                    'nombre_evento' => $nombreEvento,
                    'fecha_procesado' => Carbon::now(),
                ]);

                return true;
            }));
        } catch (Throwable $e) {
            Log::warning("M4 no pudo procesar {$nombreEvento} {$idEvento}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Aplica la operación a una reserva; si esa reserva no admite el cambio (otro
     * comercio, ya cerrada, inexistente), lo registra y sigue con las demás.
     *
     * @param  Closure(): mixed  $operacion
     */
    protected function porReserva(string $nombreEvento, string $idReserva, Closure $operacion): void
    {
        try {
            $operacion();
        } catch (ErrorInventarioException $e) {
            Log::notice("M4 {$nombreEvento}: la reserva {$idReserva} no se modificó ({$e->codigo}: {$e->getMessage()}).");
        }
    }
}
