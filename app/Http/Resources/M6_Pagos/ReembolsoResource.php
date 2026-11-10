<?php

declare(strict_types=1);

namespace App\Http\Resources\M6_Pagos;

use App\Models\M6_Pagos\EstadoReembolso;
use App\Models\M6_Pagos\Reembolso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Esquema "Reembolso" del contrato OpenAPI de M6.
 *
 * @mixin Reembolso
 */
class ReembolsoResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_reembolso,
            'id_pedido' => $this->pago->id_pedido,
            'id_subpedido' => $this->id_subpedido,
            'monto' => (float) $this->monto,
            'motivo' => $this->motivo,
            'estado' => EstadoReembolso::valorContrato($this->codigoEstado()),
            'nota_credito_id' => $this->id_nota_credito,
            'fecha' => ($this->fecha_procesado ?? $this->fecha_solicitud)?->toIso8601String(),
        ];
    }
}
