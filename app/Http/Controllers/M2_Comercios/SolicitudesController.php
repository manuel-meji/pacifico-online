<?php

declare(strict_types=1);

namespace App\Http\Controllers\M2_Comercios;

use App\Http\Controllers\Controller;
use App\Models\M2_Comercios\SolicitudComercio;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SolicitudesController extends Controller
{
    public function __construct(private readonly ComerciosService $comercios) {}

    public function listar(Request $request): JsonResponse
    {
        Gate::authorize('revisar', new SolicitudComercio);

        $datos = $request->validate([
            'estado' => ['nullable', Rule::in([SolicitudComercio::PENDIENTE, SolicitudComercio::APROBADA, SolicitudComercio::RECHAZADA])],
            'pagina' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:'.ComerciosService::MAXIMO_POR_PAGINA],
        ]);

        return response()->json($this->comercios->listarSolicitudes(
            $datos['estado'] ?? null,
            (int) ($datos['pagina'] ?? 1),
            (int) ($datos['por_pagina'] ?? 20),
        ));
    }

    public function consultar(int $idSolicitud): JsonResponse
    {
        Gate::authorize('revisar', new SolicitudComercio);

        return response()->json($this->comercios->consultarSolicitud($idSolicitud));
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
