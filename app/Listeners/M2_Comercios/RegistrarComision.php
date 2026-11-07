<?php

declare(strict_types=1);

namespace App\Listeners\M2_Comercios;

use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class RegistrarComision implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly ComerciosService $comercios
    ) {}

    public function handle(SubpedidoDespachado $evento): void
    {
        $this->comercios->registrarComisionDespacho(
            $evento->id_subpedido,
            $evento->id_evento
        );
    }
}