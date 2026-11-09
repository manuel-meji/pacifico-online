<?php

namespace App\Http\Controllers\M1_Portal;

use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Http\Controllers\Controller;
use App\Services\M1_Portal\PortadaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Página principal del portal. Capa delgada: llama al contrato de M1.
 */
class PortadaController extends Controller
{
    public function __construct(private PortalPublicoContract $portal) {}

    /**
     * GET /api/m1/v1/destacados (listarDestacados). Si limite falta o no es
     * un entero se usa 12; si está fuera de 1 a 12, PortadaService lo ajusta.
     */
    public function listar(Request $request): JsonResponse
    {
        $limite = filter_var($request->query('limite'), FILTER_VALIDATE_INT);

        return response()->json($this->portal->listarDestacados(
            $limite === false ? PortadaService::LIMITE_POR_DEFECTO : $limite,
        ));
    }
}
