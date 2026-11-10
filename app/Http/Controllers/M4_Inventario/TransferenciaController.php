<?php

namespace App\Http\Controllers\M4_Inventario;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\M4_Inventario\RegistrarTransferenciaRequest;
use App\Http\Resources\M4_Inventario\TransferenciaResource;
use Illuminate\Http\JsonResponse;

/**
 * POST /transferencias (registrarTransferencia, CU-M4-05). Encargado del comercio.
 */
class TransferenciaController extends Controller
{
    public function __construct(private readonly InventarioModuleInterface $inventario) {}

    public function registrar(RegistrarTransferenciaRequest $solicitud): JsonResponse
    {
        $transferencia = $this->inventario->registrarTransferencia($solicitud->aSolicitud());

        return (new TransferenciaResource($transferencia))->response()->setStatusCode(201);
    }
}
