<?php

namespace App\Http\Controllers\M3_Catalogo;

use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class VariacionController extends Controller
{
    public function __construct(private CatalogoServiceInterface $catalogo)
    {
    }

    // operationId: obtenerVariacion
    public function show(int $id_variacion): JsonResponse
    {
        $variacion = $this->catalogo->obtenerVariacion($id_variacion);

        if ($variacion === null) {
            return response()->json([
                'error' => [
                    'codigo'   => 'ERR-004',
                    'mensaje'  => 'No se encontró lo que buscas.',
                    'detalles' => null,
                ],
            ], 404);
        }

        return response()->json($variacion);
    }
}