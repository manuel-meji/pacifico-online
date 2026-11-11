<?php

declare(strict_types=1);

namespace App\Models\Nucleo\Concerns;

use App\Models\Nucleo\Scopes\FiltroComercioScope;
use App\Services\Nucleo\ContextoComercio;
use App\Services\Nucleo\VerificaAmbitoEscritura;
use Illuminate\Database\Eloquent\Model;

/**
 * Úsalo en todo modelo con la columna id_comercio (RN-12, ADR-006).
 */
trait PerteneceAComercio
{
    public static function bootPerteneceAComercio(): void
    {
        static::addGlobalScope(new FiltroComercioScope);

        static::creating(function (Model $modelo): void {
            VerificaAmbitoEscritura::alCrear($modelo, 'id_comercio', app(ContextoComercio::class));
        });

        static::updating(function (Model $modelo): void {
            VerificaAmbitoEscritura::alActualizar($modelo, 'id_comercio', app(ContextoComercio::class));
        });
    }
}
