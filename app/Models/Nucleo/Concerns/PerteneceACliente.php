<?php

declare(strict_types=1);

namespace App\Models\Nucleo\Concerns;

use App\Models\Nucleo\Scopes\FiltroClienteScope;
use App\Services\Nucleo\ContextoCliente;
use App\Services\Nucleo\VerificaAmbitoEscritura;
use Illuminate\Database\Eloquent\Model;

/**
 * Úsalo en todo modelo con la columna id_cliente (RN-12, ADR-006).
 */
trait PerteneceACliente
{
    public static function bootPerteneceACliente(): void
    {
        static::addGlobalScope(new FiltroClienteScope());

        static::creating(function (Model $modelo): void {
            VerificaAmbitoEscritura::alCrear($modelo, 'id_cliente', app(ContextoCliente::class));
        });

        static::updating(function (Model $modelo): void {
            VerificaAmbitoEscritura::alActualizar($modelo, 'id_cliente', app(ContextoCliente::class));
        });
    }
}