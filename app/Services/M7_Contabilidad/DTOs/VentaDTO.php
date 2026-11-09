<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad\DTOs;

final readonly class VentaDTO
{
    public function __construct(
        public string $eventoId,
        public string $fecha,
        public int $pedidoId,
        public int $subpedidoId,
        public int $comercioId,
        public string $subtotal,
    ) {}
}
