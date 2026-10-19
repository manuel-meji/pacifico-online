<?php

namespace Tests\Doubles\M1;

use App\Services\M1_Portal\Dobles\CatalogoFake;
use App\Services\M1_Portal\Dobles\ErrorProveedorException;

/**
 * M3 no responde: todas las operaciones que consume M1 lanzan ERR-006.
 */
class CatalogoNoDisponible extends CatalogoFake
{
    public function listarCategorias(): array
    {
        throw ErrorProveedorException::noDisponible('M3');
    }

    public function listarMarcas(): array
    {
        throw ErrorProveedorException::noDisponible('M3');
    }

    public function buscarProductos(array $filtros = []): array
    {
        throw ErrorProveedorException::noDisponible('M3');
    }

    public function obtenerProducto(int $idProducto): array
    {
        throw ErrorProveedorException::noDisponible('M3');
    }

    public function listarRelacionados(int $idProducto, int $limite = 4): array
    {
        throw ErrorProveedorException::noDisponible('M3');
    }
}
