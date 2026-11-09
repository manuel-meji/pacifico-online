<?php

namespace App\Contracts\M1_Portal\Exceptions;

/**
 * La operación choca con el estado actual del destacado (ERR-M1-003,
 * contrato 1.4.0): el producto ya tiene una solicitud pendiente o ya está
 * destacado en ese periodo, o la solicitud ya fue resuelta.
 */
class ConflictoDestacadoException extends PortalPublicoException
{
    protected string $codigo = 'ERR-M1-003';

    protected function mensajePorDefecto(): string
    {
        return 'El producto ya está destacado o tiene una solicitud en curso.';
    }
}
