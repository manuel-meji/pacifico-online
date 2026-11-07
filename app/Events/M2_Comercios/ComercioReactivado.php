<?php

declare(strict_types=1);

namespace App\Events\M2_Comercios;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Carbon;

/** ComercioReactivado (contrato v1.2.1): se publica después de confirmar la reactivación (RN-017: comisión y membresía se mantienen). Lo consumen M1, M3, M4 y M5. */
class ComercioReactivado implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public string $id_evento,
        public Carbon $fecha_hora,
        public int $id_comercio,
        public string $estado,
        public string $nombre_comercial,
    ) {}
}
