<?php

namespace App\Http\Controllers\M3_Catalogo;

use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ProductoController extends Controller
{
    public function __construct(private CatalogoServiceInterface $catalogo)
    {
    }

    // operationId: obtenerProducto
    public function show(int $id_producto): JsonResponse
    {
        $producto = $this->catalogo->obtenerDetalleProducto($id_producto);

        if ($producto === null) {
            return response()->json([
                'error' => [
                    'codigo'   => 'ERR-004',
                    'mensaje'  => 'No se encontró lo que buscas.',
                    'detalles' => null,
                ],
            ], 404);
        }

        return response()->json($producto);
    }
}