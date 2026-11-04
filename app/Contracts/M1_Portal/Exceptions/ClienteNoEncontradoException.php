<?php

namespace App\Contracts\M1_Portal\Exceptions;

/**
 * Cliente inexistente (ERR-004).
 */
class ClienteNoEncontradoException extends PortalPublicoException
{
    protected string $codigo = 'ERR-004';

    protected function mensajePorDefecto(): string
    {
        return 'El cliente no existe.';
    }
}
