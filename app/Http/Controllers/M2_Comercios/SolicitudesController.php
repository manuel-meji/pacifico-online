<?php

declare(strict_types=1);

namespace App\Http\Controllers\M2_Comercios;

use App\Http\Controllers\Controller;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SolicitudesController extends Controller
{
    public function __construct(private readonly ComerciosService $comercios) {}

    public function listar(Request $request): JsonResponse
    {
        return response()->json(['mensaje' => 'Pendiente de implementar'], 501);
    }

    public function consultar(int $idSolicitud): JsonResponse
    {
        return response()->json(['mensaje' => 'Pendiente de implementar'], 501);
    }

    public function aprobar(int $idSolicitud, Request $request): JsonResponse
    {
        return response()->json(['mensaje' => 'Pendiente de implementar'], 501);
    }

    public function rechazar(int $idSolicitud, Request $request): JsonResponse
    {
        return response()->json(['mensaje' => 'Pendiente de implementar'], 501);
    }

    public function evaluarDocumento(int $idSolicitud, int $idDocumento, Request $request): JsonResponse
    {
        return response()->json(['mensaje' => 'Pendiente de implementar'], 501);
    }

    public function registrar(Request $request): JsonResponse
    {
        return response()->json(['mensaje' => 'Pendiente de implementar'], 501);
    }

    public function corregir(int $idSolicitud, Request $request): JsonResponse
    {
        return response()->json(['mensaje' => 'Pendiente de implementar'], 501);
    }
}
