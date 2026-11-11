<?php

namespace App\Http\Controllers\M4_Inventario;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\M4_Inventario\RegistrarAjusteRequest;
use Illuminate\Http\JsonResponse;

/**
 * POST /ajustes (registrarAjusteInventario, CU-M4-02). Encargado del comercio.
 */
class AjusteController extends Controller
{
    public function __construct(private readonly InventarioModuleInterface $inventario) {}

    public function registrar(RegistrarAjusteRequest $solicitud): JsonResponse
    {
        return response()->json($this->inventario->registrarAjusteInventario($solicitud->aSolicitud()), 201);
    }
}
