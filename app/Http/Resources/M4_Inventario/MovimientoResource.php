<?php

namespace App\Http\Resources\M4_Inventario;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Esquema Movimiento del contrato, tal como lo entrega InventarioModuleInterface
 * (sin envoltura «data»).
 *
 * @property array<string, mixed> $resource
 */
class MovimientoResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
