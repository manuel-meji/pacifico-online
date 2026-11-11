<?php

namespace Tests\Doubles\M4;

use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;

/** M2 no responde: las dos operaciones que consume M4 lanzan ERR-006 (503). */
class ComerciosNoDisponible extends ComerciosDobleM4
{
    public function obtenerComercioActivo(int $idComercio): array
    {
        throw ErrorInventarioException::dependenciaNoDisponible('M2');
    }

    public function obtenerSedesComercio(int $idComercio): array
    {
        throw ErrorInventarioException::dependenciaNoDisponible('M2');
    }
}
