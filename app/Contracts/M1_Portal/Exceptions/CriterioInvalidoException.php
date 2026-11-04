<?php

namespace App\Contracts\M1_Portal\Exceptions;

/**
 * Criterio de búsqueda inválido (ERR-005, RN-M1-08).
 */
class CriterioInvalidoException extends PortalPublicoException
{
    protected string $codigo = 'ERR-005';

    protected function mensajePorDefecto(): string
    {
        return 'El criterio de búsqueda no es válido.';
    }
}
