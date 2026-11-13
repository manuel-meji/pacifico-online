<?php

namespace App\Http\Controllers\M3_Catalogo;

use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\M3_Catalogo\ActualizarDisponibilidadVariacionRequest;
use App\Services\M3_Catalogo\ProductoService;
use Illuminate\Http\JsonResponse;

class VariacionController extends Controller
{
    public function __construct(
        private CatalogoServiceInterface $catalogo,
        private ProductoService $productos
    ) {}

    // operationId: obtenerVariacion
    public function show(int $id_variacion): JsonResponse
    {
        $variacion = $this->catalogo->obtenerVariacion($id_variacion);

        if ($variacion === null) {
            return response()->json([
                'error' => [
                    'codigo' => 'ERR-004',
                    'mensaje' => 'No se encontró lo que buscas.',
                    'detalles' => null,
                ],
            ], 404);
        }

        return response()->json($variacion);
    }

    // operationId: actualizarDisponibilidadVariacion
    public function updateDisponibilidad(
        ActualizarDisponibilidadVariacionRequest $request,
        int $id_variacion
    ): JsonResponse {
        $variacion = $this->productos->actualizarDisponibilidadVariacion(
            $id_variacion,
            $request->validated()['disponible']
        );

        return response()->json($variacion);
    }
}
