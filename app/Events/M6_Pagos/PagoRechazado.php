<?php

declare(strict_types=1);

namespace App\Events\M6_Pagos;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento PagoRechazado (payload PagoRechazadoEvent del contrato M6).
 * Consumidores: M5 (pedido → "Cancelado por pago fallido") y M4 (libera la reserva).
 */
final class PagoRechazado
{
    use Dispatchable;

    public function __construct(
        public readonly string $idEvento,
        public readonly int $idPedido,
        public readonly int $idPago,
        public readonly ?string $motivo,
        public readonly string $fecha,
    ) {}

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'id_evento' => $this->idEvento,
            'id_pedido' => $this->idPedido,
            'id_pago' => $this->idPago,
            'motivo' => $this->motivo,
            'fecha' => $this->fecha,
        ];
    }
}
