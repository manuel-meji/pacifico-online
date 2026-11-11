<?php

namespace App\Listeners\M3_Catalogo\Concerns;

use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Utilidades de los listeners de M3.
 *
 * Un fallo de M3 al atender un evento no debe interrumpir al modulo que lo publico ni
 * a los demas listeners del mismo evento (M1, M4): se registra en el log y se continua.
 */
trait AtiendeEventosSinInterrumpir
{
    /** Lee una propiedad del evento probando varios nombres (camelCase o snake_case). */
    protected function propiedad(object $evento, string ...$nombres): mixed
    {
        foreach ($nombres as $nombre) {
            if (isset($evento->{$nombre})) {
                return $evento->{$nombre};
            }
        }

        return null;
    }

    protected function atender(string $nombreEvento, object $evento, Closure $accion): void
    {
        try {
            $accion();
        } catch (Throwable $error) {
            Log::error("M3: no se pudo procesar {$nombreEvento}.", [
                'evento' => $evento::class,
                'error' => $error->getMessage(),
            ]);
        }
    }
}
