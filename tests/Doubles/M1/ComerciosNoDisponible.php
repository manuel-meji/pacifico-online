<?php

namespace Tests\Doubles\M1;

use App\Services\M1_Portal\Dobles\ComerciosStub;
use App\Services\M1_Portal\Dobles\ErrorProveedorException;

/**
 * M2 no responde: todas las operaciones que consume M1 lanzan ERR-006.
 */
class ComerciosNoDisponible extends ComerciosStub
{
    public function obtenerDatosComercioPublico(int $idComercio): array
    {
        throw ErrorProveedorException::noDisponible('M2');
    }

    public function obtenerResumenComerciosLote(array $idsComercio): array
    {
        throw ErrorProveedorException::noDisponible('M2');
    }

    public function listarComerciosHabilitados(int $pagina = 1, int $porPagina = 20, bool $destacado = false): array
    {
        throw ErrorProveedorException::noDisponible('M2');
    }
}
