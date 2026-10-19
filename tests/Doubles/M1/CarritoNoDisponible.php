<?php

namespace Tests\Doubles\M1;

use App\Services\M1_Portal\Dobles\CarritoFake;
use App\Services\M1_Portal\Dobles\ErrorProveedorException;

/**
 * M5 no responde: todas las operaciones que consume M1 lanzan ERR-006.
 */
class CarritoNoDisponible extends CarritoFake
{
    public function obtenerCarrito(int $idCliente): array
    {
        throw ErrorProveedorException::noDisponible('M5');
    }

    public function agregarItemCarrito(int $idCliente, array $datos): array
    {
        throw ErrorProveedorException::noDisponible('M5');
    }
}
