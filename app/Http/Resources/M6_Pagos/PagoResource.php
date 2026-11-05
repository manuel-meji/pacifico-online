<?php

declare(strict_types=1);

namespace App\Http\Resources\M6_Pagos;

use App\Models\M6_Pagos\Pago;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Esquema "Pago" del contrato OpenAPI de M6.
 *
 * @mixin Pago
 */
class PagoResource extends JsonResource
{
    /** Sin envoltura "data": el contrato devuelve el objeto directamente. */
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_pago,
            'id_pedido' => $this->id_pedido,
            'medio_pago' => $this->medio?->nombre_medio,
            'monto_total' => (float) $this->monto,
            'estado' => $this->estado?->codigo,
            'referencia_proveedor' => $this->referencia_proveedor,
            'fecha_creacion' => $this->fecha_creacion?->toIso8601String(),
            'fecha_confirmacion' => $this->fecha_confirmacion?->toIso8601String(),
        ];
    }
}
