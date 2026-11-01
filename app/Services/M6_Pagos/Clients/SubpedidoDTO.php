<?php

namespace App\Services\M6_Pagos\Clients;

/** DTOs de frontera: lo único que M6 conoce de los otros módulos. Ids BIGINT, igual que las migraciones. */
final class SubpedidoDTO
{
    public function __construct(
        public readonly int $idSubpedido,
        public readonly int $idComercio,
        public readonly float $subtotal,
        public readonly float $impuesto,
        public readonly float $envio,
    ) {}
}
