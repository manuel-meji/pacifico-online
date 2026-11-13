<?php

declare(strict_types=1);

namespace App\Events\M6_Pagos;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento ReembolsoProcesado (catálogo de eventos PC2; payload ReembolsoProcesadoEvent del contrato M6).
 * Consumidores: M5 (estado del pedido) y M7 (reversión contable).
 *
 * Es la ÚNICA fuente de la reversión contable en M7. Se publica una sola vez,
 * después de emitir la nota de crédito. idEvento es la clave de idempotencia
 * y es estable por id_reembolso (IdentificadorEventoM6).
 *
 * Contrato M6 v1.4.0 (conexión M6–M7): se agrega id_pago al final, opcional,
 * para que M7 relacione la reversión con el asiento del pago. Compatible hacia atrás.
 */
final class ReembolsoProcesado
{
    use Dispatchable;

    public function __construct(
        public readonly string $idEvento,
        public readonly int $idPedido,
        public readonly int $idSubpedido,
        public readonly int $idComercio,
        public readonly int $idReembolso,
        public readonly float $monto,
        public readonly int $notaCreditoId,
        public readonly string $fecha,
        public readonly ?int $idPago = null,
    ) {}

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'id_evento' => $this->idEvento,
            'id_pedido' => $this->idPedido,
            'id_subpedido' => $this->idSubpedido,
            'id_comercio' => $this->idComercio,
            'id_reembolso' => $this->idReembolso,
            'monto' => $this->monto,
            'nota_credito_id' => $this->notaCreditoId,
            'fecha' => $this->fecha,
            'id_pago' => $this->idPago,
        ];
    }
}
