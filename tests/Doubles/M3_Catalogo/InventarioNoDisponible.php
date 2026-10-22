<?php

namespace Tests\Doubles\M3_Catalogo;

use App\Services\M1_Portal\Dobles\ErrorProveedorException;

class InventarioNoDisponible extends InventarioStub
{
    public function consultarExistencias(int $variacionId): int
    {
        throw ErrorProveedorException::noDisponible('M4');
    }

    public function solicitarReserva(
        int $variacionId,
        int $cantidad,
        int $pedidoId
    ): array {
        throw ErrorProveedorException::noDisponible('M4');
    }

    public function confirmarSalidaPorDespacho(int $reservaId): bool
    {
        throw ErrorProveedorException::noDisponible('M4');
    }
}
