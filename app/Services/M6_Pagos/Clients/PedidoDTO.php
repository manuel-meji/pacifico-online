<?php

namespace App\Services\M6_Pagos\Clients;

final class PedidoDTO
{
    /** @param SubpedidoDTO[] $subpedidos */
    public function __construct(
        public readonly int $idPedido,
        public readonly int $idCliente,
        public readonly float $montoTotal,
        public readonly array $subpedidos,
    ) {}
}
