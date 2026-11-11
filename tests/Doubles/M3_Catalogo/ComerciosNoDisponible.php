<?php

namespace Tests\Doubles\M3_Catalogo;

use App\Exceptions\M3_Catalogo\ErrorCatalogoException;

/** Simula que M2 no responde (ERR-006, 503). */
class ComerciosNoDisponible extends ComerciosStub
{
    public function obtenerComercioActivo(int $idComercio): array
    {
        throw ErrorCatalogoException::proveedorNoDisponible('M2');
    }
}