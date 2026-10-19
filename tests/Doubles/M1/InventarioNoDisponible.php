<?php

namespace Tests\Doubles\M1;

use App\Services\M1_Portal\Dobles\ErrorProveedorException;
use App\Services\M1_Portal\Dobles\InventarioStub;

/**
 * M4 no responde: todas las operaciones que consume M1 lanzan ERR-006.
 */
class InventarioNoDisponible extends InventarioStub
{
    public function consultarDisponibilidadLote(array $items): array
    {
        throw ErrorProveedorException::noDisponible('M4');
    }

    public function consultarDisponibilidadProductoLote(array $items): array
    {
        throw ErrorProveedorException::noDisponible('M4');
    }
}
