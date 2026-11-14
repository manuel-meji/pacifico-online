<?php

declare(strict_types=1);

namespace App\Contracts\M7_Contabilidad;

interface SubpedidosM7GatewayInterface
{
    public function obtenerSubpedido(int $subpedidoId): array;
}