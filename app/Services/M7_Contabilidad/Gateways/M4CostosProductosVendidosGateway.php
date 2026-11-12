<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad\Gateways;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Contracts\M7_Contabilidad\CostosProductosVendidosGatewayInterface;

final class M4CostosProductosVendidosGateway implements CostosProductosVendidosGatewayInterface
{
    public function __construct(
        private readonly InventarioModuleInterface $inventario,
    ) {}

    public function obtenerCostosProductosVendidos(
        int $subpedidoId
    ): array {
        return $this->inventario
            ->obtenerCostoProductosVendidos($subpedidoId);
    }
}
