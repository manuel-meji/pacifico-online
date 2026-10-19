<?php

namespace Tests\Doubles\M1;

use App\Services\M1_Portal\Dobles\CatalogoFake;
use App\Services\M1_Portal\Dobles\ErrorProveedorException;

/**
 * M3 responde, salvo listarRelacionados, que lanza ERR-006. Sirve para decidir
 * si la ficha de producto se degrada o falla cuando solo faltan los relacionados.
 */
class CatalogoRelacionadosNoDisponible extends CatalogoFake
{
    public function listarRelacionados(int $idProducto, int $limite = 4): array
    {
        throw ErrorProveedorException::noDisponible('M3');
    }
}
