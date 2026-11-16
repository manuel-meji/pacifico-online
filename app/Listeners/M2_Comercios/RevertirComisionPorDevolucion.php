<?php

declare(strict_types=1);

namespace App\Listeners\M2_Comercios;

use App\Events\M5_Pedidos\DevolucionAprobada;
use App\Services\M2_Comercios\ReversionComisionService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * DevolucionAprobada (M5) → reversión de la comisión del subpedido en M2 (RF-021).
 * Nunca lanza excepciones al publicador (mismo criterio que RegistrarComision).
 */
class RevertirComisionPorDevolucion
{
    public function __construct(
        private readonly ReversionComisionService $reversiones,
    ) {}

    public function alAprobarDevolucion(DevolucionAprobada $evento): void
    {
        try {
            $this->reversiones->revertirPorDevolucion(
                $evento->id_subpedido,
                $evento->id_devolucion,
                $evento->id_evento,
                $evento->subtotal_devuelto,
            );
        } catch (Throwable $e) {
            report($e);

            Log::error('M2: no se pudo revertir la comisión por la devolución.', [
                'id_subpedido' => $evento->id_subpedido,
                'id_devolucion' => $evento->id_devolucion,
            ]);
        }
    }
}
