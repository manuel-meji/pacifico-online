<?php

namespace App\Http\Controllers\M4_Inventario;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/m4/v1/health (verificarEstado). Pública.
 */
class EstadoController extends Controller
{
    public function __construct(private readonly InventarioModuleInterface $inventario) {}

    public function __invoke(): JsonResponse
    {
        return response()->json($this->inventario->verificarEstado());
    }
}
