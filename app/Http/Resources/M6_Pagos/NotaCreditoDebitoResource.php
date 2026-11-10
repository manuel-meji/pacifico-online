<?php

declare(strict_types=1);

namespace App\Http\Resources\M6_Pagos;

use App\Models\M6_Pagos\NotaCredito;
use App\Models\M6_Pagos\NotaDebito;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Esquema "NotaCreditoDebito" del contrato OpenAPI de M6.
 *
 * @mixin NotaCredito|NotaDebito
 */
class NotaCreditoDebitoResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'id_factura' => $this->id_factura_electronica,
            'tipo' => $this->resource instanceof NotaCredito ? 'credito' : 'debito',
            'monto' => (float) $this->monto,
            'motivo' => $this->motivo,
            'xml' => $this->xml_documento,
            'fecha_emision' => $this->fecha_emision?->toIso8601String(),
        ];
    }
}
