<?php

namespace App\Jobs\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Services\M4_Inventario\ReservaService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Vencimiento automático de reservas (RNF-M4-007): cada 60 s libera, una sola vez,
 * las reservas ACTIVA cuyo plazo se cumplió y publica ReservaVencida. Lo programa
 * InventarioServiceProvider; corre con `php artisan schedule:work` (o el cron de
 * `schedule:run`). Es un proceso del sistema: corre en el modo sistema del núcleo.
 */
class VencerReservasJob
{
    use Dispatchable;

    public function handle(ReservaService $reservas, NucleoDependencia $nucleo): int
    {
        $vencidas = $nucleo->ejecutarEnAmbito(fn (): int => $reservas->vencerReservasExpiradas(Carbon::now()));

        if ($vencidas > 0) {
            Log::info("M4: {$vencidas} reservas vencidas liberadas.");
        }

        return $vencidas;
    }
}
