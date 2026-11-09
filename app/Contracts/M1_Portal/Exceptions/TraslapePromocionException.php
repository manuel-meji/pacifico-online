<?php

namespace App\Contracts\M1_Portal\Exceptions;

/**
 * El producto o alguna de las variaciones ya tiene otra promoción activa en
 * fechas que se cruzan (ERR-M1-004, contrato 1.4.0).
 */
class TraslapePromocionException extends PortalPublicoException
{
    protected string $codigo = 'ERR-M1-004';

    protected function mensajePorDefecto(): string
    {
        return 'El producto o una de sus variaciones ya tiene otra promoción activa en esas fechas.';
    }
}
