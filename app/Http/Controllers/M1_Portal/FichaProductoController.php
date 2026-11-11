<?php

namespace App\Http\Controllers\M1_Portal;

use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Ficha de producto del portal. Capa delgada: llama al contrato de M1. Los
 * errores los convierte el manejador del núcleo.
 */
class FichaProductoController extends Controller
{
    public function __construct(private PortalPublicoContract $portal) {}

    /**
     * GET /api/m1/v1/productos/{id_producto} (CU-003 Consultar ficha de producto).
     */
    public function mostrar(int $idProducto): JsonResponse
    {
        return response()->json($this->portal->obtenerFichaProducto($idProducto));
    }
}
