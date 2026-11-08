<?php

declare(strict_types=1);

namespace App\Listeners\M2_Comercios;

use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegistrarComision implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly ComerciosService $comercios
    ) {}

    public function handle(SubpedidoDespachado $evento): void
    {
        try {
            $this->comercios->registrarComisionDespacho(
                $evento->id_subpedido,
                $evento->id_evento
            );
        } catch (Throwable $e) {
            report($e);

            Log::error('M2: no se pudo registrar la comisión del subpedido.', [
                'id_subpedido' => $evento->id_subpedido,
                'id_evento' => $evento->id_evento,
            ]);
        }
    }
}