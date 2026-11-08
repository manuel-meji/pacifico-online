<?php

namespace App\Http\Controllers\M4_Inventario;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Existencias y disponibilidad (figura 4.1b del PC3). Controlador delgado: valida
 * la forma de la petición y llama a InventarioModuleInterface; las reglas de
 * negocio y el aislamiento por comercio están en el servicio y en el núcleo.
 */
class ExistenciaController extends Controller
{
    public function __construct(private readonly InventarioModuleInterface $inventario) {}

    /** GET /existencias/disponibilidad (consultarStockDisponible). Pública. */
    public function disponibilidad(Request $peticion): JsonResponse
    {
        $datos = $peticion->validate([
            'id_comercio' => ['required', 'integer'],
            'id_variacion' => ['required', 'integer'],
            'id_ubicacion' => ['nullable', 'integer'],
        ]);

        return response()->json($this->inventario->consultarStockDisponible(
            (int) $datos['id_comercio'],
            (int) $datos['id_variacion'],
            isset($datos['id_ubicacion']) ? (int) $datos['id_ubicacion'] : null,
        ));
    }

    /** POST /existencias/disponibilidad/lote (consultarDisponibilidadLote). Pública. */
    public function disponibilidadLote(Request $peticion): JsonResponse
    {
        $peticion->validate(['items' => ['required', 'array']]);

        return response()->json($this->inventario->consultarDisponibilidadLote($peticion->input('items')));
    }

    /** GET /existencias/disponibilidad/producto (consultarDisponibilidadProducto). Pública. */
    public function disponibilidadProducto(Request $peticion): JsonResponse
    {
        $datos = $peticion->validate([
            'id_comercio' => ['required', 'integer'],
            'id_producto' => ['required', 'integer'],
        ]);

        return response()->json($this->inventario->consultarDisponibilidadProducto(
            (int) $datos['id_comercio'],
            (int) $datos['id_producto'],
        ));
    }

    /** POST /existencias/disponibilidad/producto/lote (consultarDisponibilidadProductoLote). Pública. */
    public function disponibilidadProductoLote(Request $peticion): JsonResponse
    {
        $peticion->validate(['items' => ['required', 'array']]);

        return response()->json($this->inventario->consultarDisponibilidadProductoLote($peticion->input('items')));
    }
}
