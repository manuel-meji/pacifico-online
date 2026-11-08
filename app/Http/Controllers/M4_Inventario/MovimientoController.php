<?php

namespace App\Http\Controllers\M4_Inventario;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\M4_Inventario\RegistrarMovimientoRequest;
use App\Http\Resources\M4_Inventario\MovimientoResource;
use Illuminate\Http\JsonResponse;

/**
 * POST /movimientos (registrarMovimientoManual, CU-M4-01). Encargado del comercio.
 */
class MovimientoController extends Controller
{
    public function __construct(private readonly InventarioModuleInterface $inventario) {}

    public function registrar(RegistrarMovimientoRequest $solicitud): JsonResponse
    {
        $movimiento = $this->inventario->registrarMovimientoManual($solicitud->aSolicitud());

        return (new MovimientoResource($movimiento))->response()->setStatusCode(201);
    }
}
