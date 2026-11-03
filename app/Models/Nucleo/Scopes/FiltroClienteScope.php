<?php

declare(strict_types=1);

namespace App\Models\Nucleo\Scopes;

use App\Services\Nucleo\ContextoAislamiento;
use App\Services\Nucleo\ContextoCliente;

final class FiltroClienteScope extends FiltroAislamientoScope
{
    protected function columna(): string
    {
        return 'id_cliente';
    }

    protected function contexto(): ContextoAislamiento
    {
        return app(ContextoCliente::class);
    }
}