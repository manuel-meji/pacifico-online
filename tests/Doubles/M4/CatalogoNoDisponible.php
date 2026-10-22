<?php

namespace Tests\Doubles\M4;

use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;

/** M3 no responde: obtenerVariacion lanza ERR-006 (503). */
class CatalogoNoDisponible extends CatalogoDobleM4
{
    public function obtenerVariacion(int $idVariacion): ?array
    {
        throw ErrorInventarioException::dependenciaNoDisponible('M3');
    }
}
