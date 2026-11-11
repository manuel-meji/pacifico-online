<?php

declare(strict_types=1);

namespace App\Events\M2_Comercios;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Carbon;

class ComercioAprobado implements ShouldDispatchAfterCommit
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
