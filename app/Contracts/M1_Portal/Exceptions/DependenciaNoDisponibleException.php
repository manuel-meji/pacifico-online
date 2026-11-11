<?php

namespace App\Contracts\M1_Portal\Exceptions;

/**
 * M2, M3, M4 o M5 no respondieron en el tiempo máximo (ERR-006, RNF-M1-04).
 */
class DependenciaNoDisponibleException extends PortalPublicoException
{
    protected string $codigo = 'ERR-006';

    protected function mensajePorDefecto(): string
    {
        return 'Servicio temporalmente no disponible, intentar de nuevo.';
    }
}
