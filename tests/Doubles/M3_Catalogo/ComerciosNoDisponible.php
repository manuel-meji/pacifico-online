<?php

namespace Tests\Doubles\M3_Catalogo;

use App\Services\M1_Portal\Dobles\ErrorProveedorException;

class ComerciosNoDisponible extends ComerciosStub
{
    public function obtenerMicrositio(int $comercioId): array
    {
        throw ErrorProveedorException::noDisponible('M2');
    }

    public function registrarComision(
        int $pedidoId,
        float $subtotalPedido
    ): bool {
        throw ErrorProveedorException::noDisponible('M2');
    }
}
