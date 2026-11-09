<?php

namespace App\Contracts\M1_Portal\Exceptions;

/**
 * El comercio ya tiene 3 productos destacados activos en ese periodo
 * (ERR-M1-002, contrato 1.4.0). Cuentan los pagados y los que puso la
 * administración.
 */
class LimiteDestacadosException extends PortalPublicoException
{
    protected string $codigo = 'ERR-M1-002';

    protected function mensajePorDefecto(): string
    {
        return 'El comercio ya tiene el máximo de 3 productos destacados activos en ese periodo.';
    }
}
