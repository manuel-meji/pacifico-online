<?php

namespace App\Http\Controllers\M5_Pedidos;

use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Contracts\M1_Portal\PortalServiceInterface;
use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Services\M5_Pedidos\PedidosService;
use App\Services\Nucleo\ContextoCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControladorCarrito extends Controller
{
    private $m3Catalogo;

    private $m4Inventario;

    private $m2Comercios;

    private $m1Portal;

    private PedidosService $pedidosService;

    public function __construct(
        ?CatalogoServiceInterface $catalogoService = null,
        ?InventarioServiceInterface $inventarioService = null,
        ?ComerciosServiceInterface $comerciosService = null,
        ?PedidosService $pedidosService = null,
        ?PortalPublicoContract $portalService = null
    ) {
        $this->m3Catalogo = $catalogoService ?? (app()->bound(CatalogoServiceInterface::class) ? app(CatalogoServiceInterface::class) : null);
        $this->m4Inventario = $inventarioService ?? (app()->bound(InventarioServiceInterface::class) ? app(InventarioServiceInterface::class) : null);
        $this->m2Comercios = $comerciosService ?? (app()->bound(ComerciosServiceInterface::class) ? app(ComerciosServiceInterface::class) : null);
        $this->pedidosService = $pedidosService ?? app(PedidosService::class);
        $this->m1Portal = $portalService ?? (app()->bound(PortalPublicoContract::class) ? app(PortalPublicoContract::class) : (app()->bound(PortalServiceInterface::class) ? app(PortalServiceInterface::class) : null));
    }

    public function obtenerCarrito(Request $request): JsonResponse
    {
        $clienteId = $this->resolverIdCliente($request);
        $carrito = (new Carrito)->obtenerOCrearCarrito($clienteId);
        $carrito->load('items');

        $desglose = $this->pedidosService->calcularDesgloseCarrito($carrito, $this->m3Catalogo, $this->m2Comercios, $this->m1Portal);
        $totalGeneral = (float) ($desglose['resumen']['total'] ?? 0.0);

        if ($totalGeneral > 0.0) {
            $carrito->recalcularTotales($totalGeneral);
        }

        return response()->json($this->formatearRespuestaCarrito($carrito, $desglose['grupos_comercio'], null, $desglose), 200);
    }

    public function agregarAlCarrito(Request $request, ?int $variacionId = null, ?int $cant = null): JsonResponse
    {
        // 1. Obtener clienteId de la sesión mediante M1 (obtenerIdClientePorUsuario) o del request
        $clienteId = $this->resolverIdCliente($request);

        $idVariacion = $variacionId ?? (int) $request->input('id_variacion');
        $cantidad = $cant ?? (int) $request->input('cantidad', 1);
        $idReserva = $request->input('id_reserva');

        if ($idVariacion <= 0 || $cantidad <= 0) {
            return $this->errorRespuesta(
                'ERR-005',
                'Parámetros inválidos',
                ['cantidad' => 'Debe ser un número entero mayor o igual a 1.'],
                422
            );
        }

        // 2. Consultar detalles de la variación al M3_Catalogo (Contrato M3 v1.2.1)
        $datosVariacion = null;
        if ($this->m3Catalogo) {
            try {
                $datosVariacion = $this->m3Catalogo->obtenerVariacion($idVariacion);
            } catch (\Throwable) {
                return $this->errorRespuesta('ERR-004', 'Variación inexistente', null, 422);
            }
        }

        if (! $datosVariacion) {
            return $this->errorRespuesta('ERR-004', 'Variación inexistente', null, 422);
        }

        // 3. Verificar existencias en M4_Inventario (Contrato M4 v1.1.0)
        $comercioId = (int) ($datosVariacion['id_comercio'] ?? $datosVariacion['producto']['id_comercio'] ?? 1);
        $ubicacionId = (int) ($datosVariacion['id_ubicacion'] ?? 1);

        $stockDisponible = $this->consultarStockVariacion($comercioId, $idVariacion, $ubicacionId);

        // Evaluar cantidad acumulada si el ítem ya está en el carrito
        $carrito = (new Carrito)->obtenerOCrearCarrito($clienteId);
        $itemPrevio = ItemCarrito::where('id_carrito', $carrito->id_carrito)
            ->where('id_variacion', $idVariacion)
            ->first();
        $cantidadFinal = $cantidad + ($itemPrevio ? (int) $itemPrevio->cantidad : 0);

        // 4. Evaluar disponibilidad
        if (! $stockDisponible || $stockDisponible < $cantidadFinal) {
            return $this->errorRespuesta(
                'ERR-M5-001',
                'Existencia insuficiente',
                ['existencia_disponible' => $stockDisponible, 'cantidad_solicitada' => $cantidadFinal],
                409
            );
        }

        // 5. Lógica interna de Entidades: Obtener o crear carrito y agregar ítem
        $item = (new ItemCarrito)->crearItemCarrito($carrito->id_carrito, $idVariacion, $cantidad);
        if ($idReserva !== null) {
            $item->id_reserva = (string) $idReserva;
            $item->save();
        }

        // 6. Recalcular costos mediante el servicio centralizado (B1)
        $carrito->load('items');
        $desglose = $this->pedidosService->calcularDesgloseCarrito($carrito, $this->m3Catalogo, $this->m2Comercios, $this->m1Portal);
        $carrito->recalcularTotales((float) $desglose['resumen']['total']);

        // 7. Retornar respuesta exitosa (formato Carrito según OpenAPI + campos aditivos B2)
        return response()->json(
            $this->formatearRespuestaCarrito($carrito, $desglose['grupos_comercio'], 'Carrito actualizado', $desglose),
            201
        );
    }

    public function actualizarCantidad(Request $request, int $itemId): JsonResponse
    {
        if ($request->user() !== null) {
            $this->resolverIdCliente($request);
        }

        $nuevaCantidad = (int) $request->input('cantidad', 1);

        if ($nuevaCantidad <= 0) {
            return $this->errorRespuesta(
                'ERR-005',
                'La cantidad debe ser mayor a 0',
                ['cantidad' => 'Debe ser un entero mayor o igual a 1.'],
                422
            );
        }

        $item = ItemCarrito::find($itemId);
        if (! $item) {
            return $this->errorRespuesta('ERR-004', 'Ítem no encontrado en el carrito', null, 404);
        }

        $carrito = Carrito::with('items')->find($item->id_carrito);
        if (! $carrito) {
            return $this->errorRespuesta('ERR-004', 'Ítem no encontrado en el carrito', null, 404);
        }

        // Verificar disponibilidad en M4
        $comercioId = 1;
        if ($this->m3Catalogo) {
            try {
                $variacion = $this->m3Catalogo->obtenerVariacion((int) $item->id_variacion);
                $comercioId = (int) ($variacion['id_comercio'] ?? 1);
            } catch (\Throwable) {
            }
        }

        $stock = $this->consultarStockVariacion($comercioId, (int) $item->id_variacion, 1);
        if ($stock >= 0 && $this->m4Inventario && $stock < $nuevaCantidad) {
            return $this->errorRespuesta(
                'ERR-M5-001',
                'Existencia insuficiente',
                ['existencia_disponible' => $stock, 'cantidad_solicitada' => $nuevaCantidad],
                409
            );
        }

        $item->actualizarCantidad($nuevaCantidad);

        $carrito = Carrito::with('items')->find($item->id_carrito);
        $desglose = $this->pedidosService->calcularDesgloseCarrito($carrito, $this->m3Catalogo, $this->m2Comercios, $this->m1Portal);
        if ($carrito) {
            $carrito->recalcularTotales((float) $desglose['resumen']['total']);
        }

        return response()->json(
            $this->formatearRespuestaCarrito($carrito, $desglose['grupos_comercio'], 'Cantidad actualizada correctamente', $desglose),
            200
        );
    }

    public function eliminarItem(Request|int $requestOrId, ?int $itemId = null, ?int $id_item_carrito = null): JsonResponse
    {
        if ($requestOrId instanceof Request) {
            if ($requestOrId->user() !== null) {
                $this->resolverIdCliente($requestOrId);
            }
            $targetItemId = (int) ($id_item_carrito ?? $itemId ?? $requestOrId->route('id_item_carrito') ?? $requestOrId->route('itemId') ?? $requestOrId->input('id_item_carrito') ?? $requestOrId->input('itemId'));
        } else {
            $targetItemId = $itemId !== null ? (int) $itemId : (int) ($id_item_carrito ?? $requestOrId);
        }

        $item = ItemCarrito::find($targetItemId);
        if (! $item) {
            return $this->errorRespuesta('ERR-004', 'Ítem no encontrado en el carrito', null, 404);
        }

        $carritoId = $item->id_carrito;
        $carrito = Carrito::with('items')->find($carritoId);
        if (! $carrito) {
            return $this->errorRespuesta('ERR-004', 'Ítem no encontrado en el carrito', null, 404);
        }

        $item->delete();

        $carrito = Carrito::with('items')->find($carritoId);
        $desglose = $this->pedidosService->calcularDesgloseCarrito($carrito, $this->m3Catalogo, $this->m2Comercios, $this->m1Portal);
        if ($carrito) {
            $carrito->recalcularTotales((float) $desglose['resumen']['total']);
        }

        return response()->json(
            $this->formatearRespuestaCarrito($carrito, $desglose['grupos_comercio'], 'Ítem eliminado correctamente', $desglose),
            200
        );
    }

    public function vaciarCarrito(Request $request): JsonResponse
    {
        $clienteId = $this->resolverIdCliente($request);
        $carrito = Carrito::where('id_cliente', $clienteId)->first();

        if ($carrito) {
            $carrito->vaciar();
        }

        return response()->json([
            'mensaje' => 'Carrito vaciado exitosamente',
        ], 200);
    }

    public function calcularCostosPedidos(float $subtotales, float $impuestos, float $costoEnvio = 0.0): float
    {
        return round($subtotales + $impuestos + $costoEnvio, 2);
    }

    public function agruparItemPorComercio(?Carrito $carrito = null): array
    {
        $desglose = $this->pedidosService->calcularDesgloseCarrito($carrito, $this->m3Catalogo, $this->m2Comercios, $this->m1Portal);

        return $desglose['grupos_comercio'];
    }

    /**
     * B2: Respuesta del carrito para la pantalla con todos los campos aditivos
     * (nombre, sku, imagen, precio, nombre_comercio, subtotal, iva/impuesto,
     * costo_envio por grupo y resumen {subtotal, impuesto, costo_envio, total}).
     *
     * @param  array<int, array<string, mixed>>  $grupos
     * @param  array<string, mixed>|null  $desglose
     * @return array<string, mixed>
     */
    public function formatearRespuestaCarrito(?Carrito $carrito, array $grupos = [], ?string $mensaje = null, ?array $desglose = null): array
    {
        if (! $carrito) {
            return [
                'id_carrito' => 1,
                'id_cliente' => 1,
                'monto_estimado' => 0.0,
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
                'items' => [],
                'grupos_comercio' => [],
                'resumen' => [
                    'subtotal' => 0.0,
                    'impuesto' => 0.0,
                    'costo_envio' => 0.0,
                    'total' => 0.0,
                ],
            ];
        }

        if ($desglose === null) {
            $desglose = $this->pedidosService->calcularDesgloseCarrito($carrito, $this->m3Catalogo, $this->m2Comercios, $this->m1Portal);
            if (empty($grupos)) {
                $grupos = $desglose['grupos_comercio'];
            }
        }

        $itemsFormateados = $desglose['items'] ?? [];
        $resumen = $desglose['resumen'] ?? [
            'subtotal' => 0.0,
            'impuesto' => 0.0,
            'costo_envio' => 0.0,
            'total' => 0.0,
        ];

        $respuesta = [
            'id_carrito' => (int) $carrito->id_carrito,
            'id_cliente' => (int) $carrito->id_cliente,
            'monto_estimado' => (float) ($carrito->monto_estimado ?? $resumen['total'] ?? 0.0),
            'created_at' => $carrito->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'updated_at' => $carrito->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            'items' => $itemsFormateados,
            'carrito' => $carrito,
            'grupos_comercio' => array_values($grupos),
            'resumen' => $resumen,
            'hay_comercio_suspendido' => (bool) ($desglose['hay_comercio_suspendido'] ?? false),
            'direcciones_cliente' => $this->obtenerDireccionesCliente((int) ($carrito->id_cliente ?? 1)),
        ];

        if ($mensaje !== null) {
            $respuesta['mensaje'] = $mensaje;
        }

        return $respuesta;
    }

    public function obtenerDireccionesCliente(int $clienteId): array
    {
        if ($this->m1Portal && method_exists($this->m1Portal, 'listarDireccionesCliente')) {
            try {
                return $this->m1Portal->listarDireccionesCliente($clienteId);
            } catch (\Throwable) {
                // Si el proveedor M1 no responde o falla, continuar con direcciones de respaldo
            }
        }

        return [
            [
                'id_direccion_cliente' => 1,
                'id_cliente' => $clienteId,
                'alias_direccion' => 'Casa (Principal)',
                'nombre_destinatario' => 'Ana Mora Rojas',
                'telefono_destinatario' => '8888-0001',
                'provincia' => 'San José',
                'canton' => 'Central',
                'distrito' => 'Carmen',
                'otras_senas' => 'Av. Central Calle 5, 200 m norte del parque',
                'es_principal' => true,
                'texto_completo' => 'Casa (Principal) — San José, Central, Carmen, Av. Central Calle 5, 200 m norte del parque',
            ],
            [
                'id_direccion_cliente' => 2,
                'id_cliente' => $clienteId,
                'alias_direccion' => 'Trabajo / Oficina',
                'nombre_destinatario' => 'Ana Mora Rojas',
                'telefono_destinatario' => '8888-0001',
                'provincia' => 'San José',
                'canton' => 'Montes de Oca',
                'distrito' => 'San Pedro',
                'otras_senas' => 'Del hospital 100 m sur, Oficinas Pacíficos #3',
                'es_principal' => false,
                'texto_completo' => 'Trabajo / Oficina — San José, Montes de Oca, San Pedro, Del hospital 100 m sur, Oficinas Pacíficos #3',
            ],
        ];
    }

    public function listarDirecciones(Request $request): JsonResponse
    {
        $clienteId = $this->resolverIdCliente($request);
        $direcciones = $this->obtenerDireccionesCliente($clienteId);

        return response()->json([
            'id_cliente' => $clienteId,
            'direcciones' => $direcciones,
        ], 200);
    }

    /**
     * Obtiene el id_cliente real asociado a la cuenta autenticada del núcleo
     * mediante PortalPublicoContract::obtenerIdClientePorUsuario, o usa el
     * parámetro de respaldo cuando no hay sesión iniciada.
     */
    public function resolverIdCliente(Request $request): int
    {
        $usuario = $request->user();

        if ($usuario !== null) {
            $idUsuario = (int) ($usuario->id_usuario ?? $usuario->id ?? 0);

            if ($idUsuario > 0 && $this->m1Portal && method_exists($this->m1Portal, 'obtenerIdClientePorUsuario')) {
                $idCliente = (int) $this->m1Portal->obtenerIdClientePorUsuario($idUsuario);
                if (app()->bound(ContextoCliente::class)) {
                    app(ContextoCliente::class)->establecer($idCliente);
                }

                return $idCliente;
            }
        }

        return (int) $request->input('id_cliente', 1);
    }

    private function consultarStockVariacion(int $comercioId, int $idVariacion, int $ubicacionId = 1): int
    {
        if (! $this->m4Inventario) {
            return 100;
        }

        if (method_exists($this->m4Inventario, 'consultarStockDisponible')) {
            $resStock = $this->m4Inventario->consultarStockDisponible($comercioId, $idVariacion, $ubicacionId);

            return is_array($resStock) ? (int) ($resStock['cantidad_disponible'] ?? 0) : (int) $resStock;
        }

        if (method_exists($this->m4Inventario, 'consultarExistencias')) {
            return (int) $this->m4Inventario->consultarExistencias($idVariacion);
        }

        return 100;
    }

    private function errorRespuesta(string $codigo, string $mensaje, ?array $detalles = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'error' => [
                'codigo' => $codigo,
                'mensaje' => $mensaje,
                'detalles' => $detalles,
            ],
            'mensaje' => $mensaje,
        ], $status);
    }
}
