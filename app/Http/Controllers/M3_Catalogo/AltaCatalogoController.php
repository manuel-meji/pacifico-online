<?php

declare(strict_types=1);

namespace App\Http\Controllers\M3_Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Requests\M3_Catalogo\AgregarImagenRequest;
use App\Http\Requests\M3_Catalogo\AsociarRelacionadoRequest;
use App\Http\Requests\M3_Catalogo\CambiarEstadoProductoRequest;
use App\Http\Requests\M3_Catalogo\CrearCategoriaRequest;
use App\Http\Requests\M3_Catalogo\CrearMarcaRequest;
use App\Http\Requests\M3_Catalogo\CrearProductoRequest;
use App\Http\Requests\M3_Catalogo\CrearVariacionRequest;
use App\Services\M3_Catalogo\ProductoService;
use Illuminate\Http\JsonResponse;

/**
 * Escritura del catálogo (contrato M3 v1.3.0). Capa delgada: recibe, llama al
 * servicio y responde. Sin lógica de negocio.
 */
class AltaCatalogoController extends Controller
{
    public function __construct(private readonly ProductoService $catalogo) {}

    // operationId: crearCategoria
    public function crearCategoria(CrearCategoriaRequest $request): JsonResponse
    {
        return response()->json($this->catalogo->crearCategoria($request->validated()), 201);
    }

    // operationId: crearMarca
    public function crearMarca(CrearMarcaRequest $request): JsonResponse
    {
        return response()->json($this->catalogo->crearMarca($request->validated()), 201);
    }

    // operationId: crearProducto
    public function crearProducto(CrearProductoRequest $request): JsonResponse
    {
        return response()->json($this->catalogo->crearProducto($request->validated()), 201);
    }

    // operationId: actualizarProducto
    public function actualizarProducto(CambiarEstadoProductoRequest $request, int $id_producto): JsonResponse
    {
        return response()->json($this->catalogo->cambiarEstado($id_producto, $request->validated()));
    }

    // operationId: crearVariacion
    public function crearVariacion(CrearVariacionRequest $request, int $id_producto): JsonResponse
    {
        return response()->json($this->catalogo->crearVariacion($id_producto, $request->validated()), 201);
    }

    // operationId: agregarImagen
    public function agregarImagen(AgregarImagenRequest $request, int $id_producto): JsonResponse
    {
        return response()->json($this->catalogo->agregarImagen($id_producto, $request->validated()), 201);
    }

    // operationId: asociarRelacionado
    public function asociarRelacionado(AsociarRelacionadoRequest $request, int $id_producto): JsonResponse
    {
        $relacionado = (int) $request->validated('id_producto_relacionado');

        return response()->json($this->catalogo->asociarRelacionado($id_producto, $relacionado), 201);
    }
}