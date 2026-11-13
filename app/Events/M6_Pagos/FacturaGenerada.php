<?php

declare(strict_types=1);

namespace App\Events\M6_Pagos;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento FacturaGenerada (diagrama DS-03 del PC3). Consumidor previsto: M7.
 * Se publica una sola vez por factura: la tabla tiene UNIQUE(id_subpedido) y el
 * evento sale solo cuando la factura se crea (decisión abierta 7: confirmar con M7).
 * idEvento es estable por id_factura (IdentificadorEventoM6).
 *
 * Contrato M6 v1.4.0 (conexión M6–M7): se agregan al final, opcionales, id_pago y
 * el desglose del monto (subtotal + impuesto + envio = monto) para que M7 tenga
 * el importe por comercio. Compatible hacia atrás.
 */
final class FacturaGenerada
{
    use Dispatchable;

    public function __construct(
        public readonly string $idEvento,
        public readonly int $idFactura,
        public readonly int $idPedido,
        public readonly int $idSubpedido,
        public readonly int $idComercio,
        public readonly float $monto,
        public readonly string $xml,
        public readonly string $fecha,
        public readonly ?int $idPago = null,
        public readonly ?float $subtotal = null,
        public readonly ?float $impuesto = null,
        public readonly ?float $envio = null,
    ) {}

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'id_evento' => $this->idEvento,
            'id_factura' => $this->idFactura,
            'id_pedido' => $this->idPedido,
            'id_subpedido' => $this->idSubpedido,
            'id_comercio' => $this->idComercio,
            'monto' => $this->monto,
            'xml' => $this->xml,
            'fecha' => $this->fecha,
            'id_pago' => $this->idPago,
            'subtotal' => $this->subtotal,
            'impuesto' => $this->impuesto,
            'envio' => $this->envio,
        ];
    }
}
