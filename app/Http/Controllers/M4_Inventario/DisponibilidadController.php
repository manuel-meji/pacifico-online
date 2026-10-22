<?php

namespace App\Http\Controllers\M4_Inventario;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Http\Controllers\Controller;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Consultas públicas de disponibilidad del contrato de M4 v1.2.0 (sin sesión ni
 * filtro RN-12, CS-01). Capa delgada: valida la entrada y llama a la interfaz.
 * Los errores salen en el formato del núcleo (ErrorInventarioException::render).
 */
class DisponibilidadController extends Controller
{
    public function __construct(private readonly InventarioModuleInterface $inventario) {}

    // operationId: consultarStockDisponible
    public function individual(Request $request): JsonResponse
    {
        $d = $this->validar($request->query(), [
            'id_comercio' => 'required|integer|min:1',
            'id_variacion' => 'required|integer|min:1',
            'id_ubicacion' => 'nullable|integer|min:1',
        ]);

        return response()->json($this->inventario->consultarStockDisponible(
            (int) $d['id_comercio'],
            (int) $d['id_variacion'],
            isset($d['id_ubicacion']) ? (int) $d['id_ubicacion'] : null,
        ));
    }

    // operationId: consultarDisponibilidadLote
    public function lote(Request $request): JsonResponse
    {
        $d = $this->validar($request->json()->all(), [
            'items' => 'required|array|min:1|max:100',
            'items.*.id_comercio' => 'required|integer|min:1',
            'items.*.id_variacion' => 'required|integer|min:1',
            'items.*.id_ubicacion' => 'nullable|integer|min:1',
        ]);

        $items = array_map(
            fn (array $item): array => array_filter($item, fn ($valor): bool => $valor !== null),
            $d['items'],
        );

        return response()->json(['datos' => $this->inventario->consultarDisponibilidadLote($items)]);
    }

    // operationId: consultarDisponibilidadProducto
    public function producto(Request $request): JsonResponse
    {
        $d = $this->validar($request->query(), [
            'id_comercio' => 'required|integer|min:1',
            'id_producto' => 'required|integer|min:1',
        ]);

        return response()->json($this->inventario->consultarDisponibilidadProducto((int) $d['id_comercio'], (int) $d['id_producto']));
    }

    // operationId: consultarDisponibilidadProductoLote
    public function productoLote(Request $request): JsonResponse
    {
        $d = $this->validar($request->json()->all(), [
            'items' => 'required|array|min:1|max:100',
            'items.*.id_comercio' => 'required|integer|min:1',
            'items.*.id_producto' => 'required|integer|min:1',
        ]);

        return response()->json(['datos' => $this->inventario->consultarDisponibilidadProductoLote($d['items'])]);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, string>  $reglas
     * @return array<string, mixed>
     */
    private function validar(array $datos, array $reglas): array
    {
        $validador = Validator::make($datos, $reglas);

        if ($validador->fails()) {
            throw ErrorInventarioException::datosInvalidos(
                array_map(fn (array $mensajes): string => $mensajes[0], $validador->errors()->messages())
            );
        }

        return $validador->validated();
    }
}
