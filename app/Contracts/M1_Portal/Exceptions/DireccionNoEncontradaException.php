<?php

namespace App\Contracts\M1_Portal\Exceptions;

/**
 * Dirección inexistente o de otro cliente (ERR-004). Se lanza también cuando la
 * dirección no pertenece al cliente indicado, para que M5 no use la de otra persona.
 */
class DireccionNoEncontradaException extends PortalPublicoException
{
    protected string $codigo = 'ERR-004';

    protected function mensajePorDefecto(): string
    {
        return 'La dirección no existe o no pertenece al cliente.';
    }
}
