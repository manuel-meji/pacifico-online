<?php

namespace App\Http\Controllers\M3_Catalogo;

use App\Http\Controllers\Controller;
use App\Services\M3_Catalogo\CatalogoService;
use App\Services\M3_Catalogo\Excepciones\DatosInvalidosCatalogoException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Lecturas publicas del contrato M3 v1.3.0: buscarProductos, listarCategorias, listarMarcas, listarRelacionados. */
class CatalogoConsultaController extends Controller
{
    public function __construct(private CatalogoService $catalogo) {}

    // operationId: buscarProductos
    public function buscar(Request $request): JsonResponse
    {
        try {
            return response()->json($this->catalogo->buscarProductos($request->query()));
        } catch (DatosInvalidosCatalogoException $e) {
            return response()->json([
                'error' => [
                    'codigo' => $e->codigoError(),
                    'mensaje' => 'Los datos enviados no son validos.',
                    'detalles' => $e->detalles,
                ],
            ], 422);
        }
    }

    // operationId: listarCategorias
    public function categorias(): JsonResponse
    {
        return response()->json($this->catalogo->listarCategorias());
    }

    // operationId: listarMarcas
    public function marcas(Request $request): JsonResponse
    {
        $destacada = $request->has('destacada')
            ? filter_var($request->query('destacada'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;

        return response()->json($this->catalogo->listarMarcas($destacada));
    }

    // operationId: listarRelacionados
    public function relacionados(int $id_producto): JsonResponse
    {
        $relacionados = $this->catalogo->listarRelacionados($id_producto);

        if ($relacionados === null) {
            return response()->json([
                'error' => [
                    'codigo' => 'ERR-004',
                    'mensaje' => 'No se encontró lo que buscas.',
                    'detalles' => null,
                ],
            ], 404);
        }

        return response()->json($relacionados);
    }
}
