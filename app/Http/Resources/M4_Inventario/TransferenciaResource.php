<?php

namespace App\Http\Resources\M4_Inventario;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Esquema TransferenciaRespuesta del contrato: id_transferencia y los dos movimientos
 * (salida del origen y entrada al destino), tal como los entrega InventarioModuleInterface.
 *
 * @property array{id_transferencia: string, movimientos: list<array<string, mixed>>} $resource
 */
class TransferenciaResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id_transferencia' => $this->resource['id_transferencia'],
            'movimientos' => array_map(
                fn (array $movimiento): array => (new MovimientoResource($movimiento))->toArray($request),
                $this->resource['movimientos'],
            ),
        ];
    }
}
