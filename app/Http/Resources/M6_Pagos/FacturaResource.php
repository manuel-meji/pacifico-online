<?php

declare(strict_types=1);

namespace App\Http\Resources\M6_Pagos;

use App\Models\M6_Pagos\EstadoFacturaHacienda;
use App\Models\M6_Pagos\FacturaElectronica;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Esquema "Factura" del contrato OpenAPI de M6.
 * estado_hacienda se traduce del catálogo al contrato: pendiente_envio y
 * enviada se publican como "pendiente" (decisión abierta 1).
 *
 * @mixin FacturaElectronica
 */
class FacturaResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_factura_electronica,
            'id_pedido' => $this->pago?->id_pedido,
            'id_subpedido' => $this->id_subpedido,
            'id_comercio' => $this->id_comercio,
            'consecutivo' => $this->consecutivo,
            'clave_numerica' => $this->claveNumerica(),
            'monto_total' => (float) $this->monto_total,
            'xml' => $this->xml_documento,
            'estado_hacienda' => EstadoFacturaHacienda::valorContrato($this->codigoEstado()),
            'fecha_emision' => $this->fecha_emision?->toIso8601String(),
        ];
    }
}
