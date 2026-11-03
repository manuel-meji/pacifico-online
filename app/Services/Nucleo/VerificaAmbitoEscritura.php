<?php

declare(strict_types=1);

namespace App\Services\Nucleo;

use App\Exceptions\Nucleo\ErrorNucleoException;
use Illuminate\Database\Eloquent\Model;

/**
 * Impide crear o mover registros hacia un comercio o cliente distinto al del ámbito.
 */
final class VerificaAmbitoEscritura
{
    public static function alCrear(Model $modelo, string $columna, ContextoAislamiento $contexto): void
    {
        if ($contexto->esModoSistema()) {
            return;
        }

        if ($modelo->getAttribute($columna) === null) {
            $modelo->setAttribute($columna, $contexto->idActual());
        }

        self::exigirMismoAmbito($modelo, $columna, $contexto);
    }

    public static function alActualizar(Model $modelo, string $columna, ContextoAislamiento $contexto): void
    {
        if ($contexto->esModoSistema() || ! $modelo->isDirty($columna)) {
            return;
        }

        self::exigirMismoAmbito($modelo, $columna, $contexto);
    }

    private static function exigirMismoAmbito(Model $modelo, string $columna, ContextoAislamiento $contexto): void
    {
        $idActual = $contexto->idActual();

        if ($idActual === null || (int) $modelo->getAttribute($columna) !== $idActual) {
            throw ErrorNucleoException::recursoNoAccesible();
        }
    }
}