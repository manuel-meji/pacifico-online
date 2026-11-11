<?php

declare(strict_types=1);

namespace App\Events\M6_Pagos;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento FacturaGenerada (diagrama DS-03 del PC3). Consumidor previsto: M7.
 * Se publica una sola vez por factura: la tabla tiene UNIQUE(id_subpedido) y el
 * evento sale solo cuando la factura se crea (decisión abierta 7: confirmar con M7).
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
        ];
    }
}
