<?php

declare(strict_types=1);

namespace App\Events\M6_Pagos;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento PagoConfirmado (catálogo de eventos PC2; payload PagoConfirmadoEvent del contrato M6).
 * Consumidores: M5 (pedido → "Pagado"), M6 (factura) y M7 (asiento contable).
 *
 * idEvento es la clave de idempotencia para los consumidores: un mismo id_pago
 * nunca genera más de un PagoConfirmado (ADR-003).
 */
final class PagoConfirmado
{
    use Dispatchable;

    public function __construct(
        public readonly string $idEvento,
        public readonly int $idPedido,
        public readonly int $idPago,
        public readonly float $monto,
        public readonly string $fecha,
    ) {}

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'id_evento' => $this->idEvento,
            'id_pedido' => $this->idPedido,
            'id_pago' => $this->idPago,
            'monto' => $this->monto,
            'fecha' => $this->fecha,
        ];
    }
}
