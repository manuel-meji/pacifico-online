<?php

namespace App\Contracts\M1_Portal\Exceptions;

/**
 * Ya existe un cupón con ese código (ERR-M1-007, contrato 1.5.0, SC-11). El
 * código es único en toda la plataforma, sin importar quién lo creó.
 */
class CuponDuplicadoException extends PortalPublicoException
{
    protected string $codigo = 'ERR-M1-007';

    protected function mensajePorDefecto(): string
    {
        return 'Ya existe un cupón con ese código.';
    }
}
