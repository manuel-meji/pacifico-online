<?php

namespace App\Contracts\M1_Portal\Exceptions;

/**
 * Producto o comercio inexistente, no publicado o no activo (ERR-004).
 */
class RecursoNoEncontradoException extends PortalPublicoException
{
    protected string $codigo = 'ERR-004';

    protected function mensajePorDefecto(): string
    {
        return 'El recurso no existe o no está disponible.';
    }
}
