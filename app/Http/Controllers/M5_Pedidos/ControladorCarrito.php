<?php

namespace App\Http\Controllers\M5_Pedidos;

use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControladorCarrito extends Controller
{
    private $m3Catalogo;

    private $m4Inventario;

    public function __construct($catalogoService = null, $inventarioService = null)
    {
        $this->m3Catalogo = $catalogoService ?? (app()->bound(CatalogoServiceInterface::class) ? app(CatalogoServiceInterface::class) : null);
        $this->m4Inventario = $inventarioService ?? (app()->bound(InventarioServiceInterface::class) ? app(InventarioServiceInterface::class) : null);
    }

    public function obtenerCarrito(Request $request): JsonResponse
    {
        $clienteId = (int) ($request->user()?->id ?? $request->input('id_cliente', 1));
        $carrito = (new Carrito)->obtenerOCrearCarrito($clienteId);
        $carrito->load('items');

        $grupos = $this->agruparItemPorComercio($carrito);
        $totalGeneral = 0.0;
        foreach ($grupos as $g) {
            $totalGeneral += (float) ($g['total'] ?? 0.0);
        }

        if ($totalGeneral > 0.0) {
            $carrito->recalcularTotales($totalGeneral);
        }

        return response()->json([
            'carrito' => $carrito->load('items'),
            'grupos_comercio' => array_values($grupos),
            'monto_estimado' => (float) ($carrito->monto_estimado ?? $totalGeneral),
        ], 200);
    }

    public function agregarAlCarrito(Request $request, ?int $variacionId = null, ?int $cant = null): JsonResponse
    {
        // 1. Obtener clienteId del token autenticado o del request
        $clienteId = (int) ($request->user()?->id ?? $request->input('id_cliente', 1));

        $idVariacion = $variacionId ?? (int) $request->input('id_variacion');
        $cantidad = $cant ?? (int) $request->input('cantidad', 1);

        if ($idVariacion <= 0 || $cantidad <= 0) {
            return response()->json(['error' => 'Parámetros inválidos'], 422);
        }

        // 2. Consultar detalles de la variación al M3_Catalogo (Contrato M3 v1.2.1)
        $datosVariacion = null;
        if ($this->m3Catalogo) {
            try {
                $datosVariacion = $this->m3Catalogo->obtenerVariacion($idVariacion);
            } catch (\Throwable $e) {
                return response()->json(['error' => 'Variación inexistente'], 422);
            }
        }

        if (! $datosVariacion) {
            return response()->json(['error' => 'Variación inexistente'], 422);
        }

        // 3. Verificar existencias en M4_Inventario (Contrato M4 v1.1.0)
        $comercioId = (int) ($datosVariacion['id_comercio'] ?? $datosVariacion['producto']['id_comercio'] ?? 1);
        $ubicacionId = (int) ($datosVariacion['id_ubicacion'] ?? 1);

        $stockDisponible = 0;
        if ($this->m4Inventario) {
            if (method_exists($this->m4Inventario, 'consultarStockDisponible')) {
                $stockDisponible = (int) $this->m4Inventario->consultarStockDisponible($comercioId, $idVariacion, $ubicacionId);
            } elseif (method_exists($this->m4Inventario, 'consultarExistencias')) {
                $stockDisponible = (int) $this->m4Inventario->consultarExistencias($idVariacion);
            }
        }

        // 4. Evaluar disponibilidad
        if (! $stockDisponible || $stockDisponible < $cantidad) {
            return response()->json(['error' => 'Existencia insuficiente'], 422);
        }

        // 5. Lógica interna de Entidades: Obtener o crear carrito y agregar ítem
        $carrito = (new Carrito)->obtenerOCrearCarrito($clienteId);
        $item = (new ItemCarrito)->crearItemCarrito($carrito->id_carrito, $idVariacion, $cantidad);

        // 6. Recalcular costos
        $grupos = $this->agruparItemPorComercio($carrito);
        $subtotales = 0.0;
        $impuestos = 0.0;
        foreach ($grupos as $grupo) {
            $subtotales += (float) ($grupo['subtotal'] ?? 0.0);
            $impuestos += (float) ($grupo['impuesto'] ?? 0.0);
        }

        $totalEstimado = $this->calcularCostosPedidos($subtotales, $impuestos);
        $carrito->recalcularTotales($totalEstimado);

        // 7. Retornar respuesta exitosa
        return response()->json([
            'mensaje' => 'Carrito actualizado',
            'carrito' => $carrito->load('items'),
        ], 201);
    }

    public function actualizarCantidad(Request $request, int $itemId): JsonResponse
    {
        $nuevaCantidad = (int) $request->input('cantidad', 1);

        if ($nuevaCantidad <= 0) {
            return response()->json(['error' => 'La cantidad debe ser mayor a 0'], 422);
        }

        $item = ItemCarrito::find($itemId);
        if (! $item) {
            return response()->json(['error' => 'Ítem no encontrado en el carrito'], 404);
        }

        // Verificar disponibilidad en M4
        $stock = 0;
        if ($this->m4Inventario && method_exists($this->m4Inventario, 'consultarExistencias')) {
            $stock = (int) $this->m4Inventario->consultarExistencias($item->id_variacion);
        }
        if ($stock > 0 && $stock < $nuevaCantidad) {
            return response()->json(['error' => 'Existencia insuficiente'], 422);
        }

        $item->actualizarCantidad($nuevaCantidad);

        $carrito = Carrito::with('items')->find($item->id_carrito);
        if ($carrito) {
            $grupos = $this->agruparItemPorComercio($carrito);
            $subtotales = 0.0;
            $impuestos = 0.0;
            foreach ($grupos as $grupo) {
                $subtotales += (float) ($grupo['subtotal'] ?? 0.0);
                $impuestos += (float) ($grupo['impuesto'] ?? 0.0);
            }
            $carrito->recalcularTotales($this->calcularCostosPedidos($subtotales, $impuestos));
        }

        return response()->json([
            'mensaje' => 'Cantidad actualizada correctamente',
            'carrito' => $carrito?->load('items'),
        ], 200);
    }

    public function eliminarItem(Request|int $requestOrId, ?int $itemId = null, ?int $id_item_carrito = null): JsonResponse
    {
        if ($requestOrId instanceof Request) {
            $targetItemId = (int) ($id_item_carrito ?? $itemId ?? $requestOrId->route('id_item_carrito') ?? $requestOrId->route('itemId') ?? $requestOrId->input('id_item_carrito') ?? $requestOrId->input('itemId'));
            $clienteId = (int) ($requestOrId->user()?->id ?? $requestOrId->input('id_cliente', 1));
        } else {
            $targetItemId = $itemId !== null ? (int) $itemId : (int) ($id_item_carrito ?? $requestOrId);
            $clienteId = $itemId !== null ? (int) $requestOrId : 1;
        }

        $item = ItemCarrito::find($targetItemId);
        if (! $item) {
            return response()->json(['error' => 'Ítem no encontrado en el carrito'], 404);
        }

        $carritoId = $item->id_carrito;
        $item->delete();

        $carrito = Carrito::with('items')->find($carritoId);
        if ($carrito) {
            $grupos = $this->agruparItemPorComercio($carrito);
            $subtotales = 0.0;
            $impuestos = 0.0;
            foreach ($grupos as $grupo) {
                $subtotales += (float) ($grupo['subtotal'] ?? 0.0);
                $impuestos += (float) ($grupo['impuesto'] ?? 0.0);
            }
            $carrito->recalcularTotales($this->calcularCostosPedidos($subtotales, $impuestos));
        }

        return response()->json([
            'mensaje' => 'Ítem eliminado correctamente',
            'carrito' => $carrito?->load('items'),
        ], 200);
    }

    public function vaciarCarrito(Request $request): JsonResponse
    {
        $clienteId = (int) ($request->user()?->id ?? $request->input('id_cliente', 1));
        $carrito = Carrito::where('id_cliente', $clienteId)->first();

        if ($carrito) {
            $carrito->vaciar();
        }

        return response()->json([
            'mensaje' => 'Carrito vaciado exitosamente',
        ], 200);
    }

    public function calcularCostosPedidos(float $subtotales, float $impuestos): float
    {
        return $subtotales + $impuestos;
    }

    public function agruparItemPorComercio(?Carrito $carrito = null): array
    {
        if (! $carrito) {
            return [];
        }

        $carrito->loadMissing('items');
        $grupos = [];

        foreach ($carrito->items as $item) {
            $variacion = null;
            if ($this->m3Catalogo) {
                try {
                    $variacion = $this->m3Catalogo->obtenerVariacion($item->id_variacion);
                } catch (\Throwable $e) {
                    $variacion = null;
                }
            }

            $comercioId = (int) ($variacion['id_comercio'] ?? $variacion['producto']['id_comercio'] ?? 1);
            $precio = (float) ($variacion['precio'] ?? 10000.00);
            $tarifaIva = (float) ($variacion['tarifa_impuesto'] ?? 13.00);
            $subtotalLinea = $item->cantidad * $precio;
            $impuestoLinea = $subtotalLinea * ($tarifaIva / 100);

            if (! isset($grupos[$comercioId])) {
                $grupos[$comercioId] = [
                    'id_comercio' => $comercioId,
                    'items' => [],
                    'subtotal' => 0.0,
                    'impuesto' => 0.0,
                    'costo_envio' => 0.0,
                    'total' => 0.0,
                ];
            }

            $grupos[$comercioId]['items'][] = [
                'id_item_carrito' => $item->id_item_carrito,
                'id_variacion' => $item->id_variacion,
                'cantidad' => $item->cantidad,
                'precio_unitario' => $precio,
                'tarifa_impuesto' => $tarifaIva,
                'subtotal' => $subtotalLinea,
                'impuesto' => $impuestoLinea,
            ];

            $grupos[$comercioId]['subtotal'] += $subtotalLinea;
            $grupos[$comercioId]['impuesto'] += $impuestoLinea;
            $grupos[$comercioId]['total'] = $grupos[$comercioId]['subtotal'] + $grupos[$comercioId]['impuesto'] + $grupos[$comercioId]['costo_envio'];
        }

        return $grupos;
    }
}
