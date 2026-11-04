<?php

namespace App\Contracts\M1_Portal\Exceptions;

/**
 * Correo ya registrado (ERR-M1-001, RN-M1-14).
 */
class CorreoYaRegistradoException extends PortalPublicoException
{
    protected string $codigo = 'ERR-M1-001';

    protected function mensajePorDefecto(): string
    {
        return 'El correo electrónico ya está registrado.';
    }
}
