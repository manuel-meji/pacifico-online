<?php

declare(strict_types=1);

namespace App\Contracts\M7_Contabilidad;

interface ProcesadorCuentaPagarComercioInterface
{
    public function procesar(
        string $eventoId,
        int $pedidoId,
        int $subpedidoId,
        int $comercioId,
        string $fecha,
    ): bool;
}