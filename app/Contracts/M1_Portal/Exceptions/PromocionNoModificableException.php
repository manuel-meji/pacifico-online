<?php

namespace App\Contracts\M1_Portal\Exceptions;

/**
 * La promoción ya terminó o fue desactivada y no se puede modificar
 * (ERR-M1-005, contrato 1.4.0). Una promoción iniciada solo admite adelantar
 * su fin o desactivarse; eso se informa como ERR-005.
 */
class PromocionNoModificableException extends PortalPublicoException
{
    protected string $codigo = 'ERR-M1-005';

    protected function mensajePorDefecto(): string
    {
        return 'La promoción ya terminó o fue desactivada y no se puede modificar.';
    }
}
