<?php

namespace Tests\Doubles\M7;

use App\Contracts\M7_Contabilidad\CostosProductosVendidosGatewayInterface;
use RuntimeException;

class CostosProductosVendidosStub implements CostosProductosVendidosGatewayInterface
{
    private ?array $respuesta = null;

    private bool $costosNoDisponibles = false;

    public function configurarCostosDisponibles(array $respuesta): void
    {
        $this->respuesta = $respuesta;
        $this->costosNoDisponibles = false;
    }

    public function configurarCostosNoDisponibles(): void
    {
        $this->costosNoDisponibles = true;
    }

    public function obtenerCostosProductosVendidos(int $subpedidoId): array
    {
        if ($this->costosNoDisponibles) {
            throw new RuntimeException(
                'COSTOS_SUBPEDIDO_NO_DISPONIBLES',
                409
            );
        }

        if ($this->respuesta === null) {
            throw new RuntimeException(
                'No se configuró una respuesta para el doble de M4.',
                500
            );
        }

        if ($this->respuesta['id_subpedido'] !== $subpedidoId) {
            throw new RuntimeException(
                'El subpedido solicitado no coincide con la respuesta configurada.',
                404
            );
        }

        return $this->respuesta;
    }
}
