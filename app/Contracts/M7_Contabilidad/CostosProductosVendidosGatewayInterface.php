<?php

namespace App\Contracts\M7_Contabilidad;

interface CostosProductosVendidosGatewayInterface
{
    /**
     * Obtener el costo de los productos vendidos de un subpedido.
     *
     * La implementación debe respetar el contrato OpenAPI de M4.
     *
     * @return array{
     *     id_subpedido: int,
     *     id_comercio: int,
     *     variaciones: array<int, array{
     *         id_variacion: int,
     *         cantidad: int,
     *         costo_unitario: float,
     *         costo_total: float
     *     }>,
     *     costo_total: float
     * }
     *
     * @throws \RuntimeException Si los costos todavía no están disponibles
     *                           o la consulta no puede completarse.
     */
    public function obtenerCostosProductosVendidos(
        int $subpedidoId
    ): array;
}
