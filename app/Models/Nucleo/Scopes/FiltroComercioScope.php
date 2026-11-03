<?php

declare(strict_types=1);

namespace App\Models\Nucleo\Scopes;

use App\Services\Nucleo\ContextoAislamiento;
use App\Services\Nucleo\ContextoComercio;

final class FiltroComercioScope extends FiltroAislamientoScope
{
    protected function columna(): string
    {
        return 'id_comercio';
    }

    protected function contexto(): ContextoAislamiento
    {
        return app(ContextoComercio::class);
    }
}