<?php

namespace App\Http\Controllers\M5_Pedidos;

use App\Contracts\M1_Portal\PortalServiceInterface;
use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Events\M5_Pedidos\PedidoConfirmado;
use App\Events\M5_Pedidos\PedidoCreado;
use App\Events\M5_Pedidos\PedidoPagado;
use App\Http\Controllers\Controller;
use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\PedidoLinea;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ControladorPedido extends Controller
{
    private $m2Comercios;

    private $m4Inventario;

    private $m6Pagos;

    public function __construct($comercioService = null, $inventarioService = null, $pagosService = null)
    {
        $this->m2Comercios = $comercioService ?? (app()->bound(ComerciosServiceInterface::class) ? app(ComerciosServiceInterface::class) : null);
        $this->m4Inventario = $inventarioService ?? (app()->bound(InventarioServiceInterface::class) ? app(InventarioServiceInterface::class) : null);
        $this->m6Pagos = $pagosService ?? (app()->bound(PagosServiceInterface::class) ? app(PagosServiceInterface::class) : null);
    }

    public function procesarCheckout(Request $request): JsonResponse
    {
        $datos = $request->all();
        $clienteId = (int) ($request->user()?->id ?? $request->input('id_cliente', 1));
        $direccionClienteId = (int) ($request->input('id_direccion_cliente', 1));

        // 1. Validar que el carrito no esté vacío
        $carrito = Carrito::with('items')->where('id_cliente', $clienteId)->first();
        $carritoTieneItems = $carrito && $carrito->items->isNotEmpty();

        if (! $carritoTieneItems) {
            return $this->errorRespuesta('ERR-M5-001', 'Carrito vacío o inexistente', null, 400);
        }

        // Validar cliente y dirección con M1_Portal (Contrato M1 v1.1.0)
        $portalService = app()->bound(PortalServiceInterface::class)
            ? app(PortalServiceInterface::class)
            : null;

        $datosComprador = null;
        $datosDireccionEnvio = null;

        if ($portalService) {
            try {
                if (method_exists($portalService, 'obtenerCliente')) {
                    $datosComprador = $portalService->obtenerCliente($clienteId);
                }
                if ($direccionClienteId && method_exists($portalService, 'obtenerDireccionCliente')) {
                    $datosDireccionEnvio = $portalService->obtenerDireccionCliente($clienteId, $direccionClienteId);
                }
            } catch (\Throwable $e) {
                // Manejo de error o continuar
            }
        }

        // Obtener detalles de variación para cada ítem y agrupar por comercio
        $catalogoService = app()->bound(CatalogoServiceInterface::class) ? app(CatalogoServiceInterface::class) : null;
        $comerciosInvolucrados = [];
        $itemsAgrupados = [];

        foreach ($carrito->items as $item) {
            $variacion = null;
            if ($catalogoService) {
                try {
                    $variacion = $catalogoService->obtenerVariacion($item->id_variacion);
                } catch (\Throwable $e) {
                    $variacion = null;
                }
            }

            $comercioId = (int) ($variacion['id_comercio'] ?? $variacion['producto']['id_comercio'] ?? 1);
            $precio = (float) ($variacion['precio'] ?? 10000.00);
            $tarifaIva = (float) ($variacion['tarifa_impuesto'] ?? 13.00);

            $comerciosInvolucrados[$comercioId] = $comercioId;

            if (! isset($itemsAgrupados[$comercioId])) {
                $itemsAgrupados[$comercioId] = [
                    'id_comercio' => $comercioId,
                    'items' => [],
                    'subtotal' => 0.0,
                    'impuesto' => 0.0,
                    'costo_envio' => 0.0,
                ];
            }

            $subtotalLinea = $item->cantidad * $precio;
            $impuestoLinea = $subtotalLinea * ($tarifaIva / 100);

            $itemsAgrupados[$comercioId]['items'][] = [
                'id_variacion' => $item->id_variacion,
                'cantidad' => $item->cantidad,
                'precio_unitario' => $precio,
                'tarifa_impuesto' => $tarifaIva,
                'subtotal' => $subtotalLinea,
                'impuesto' => $impuestoLinea,
                'id_reserva' => $item->id_reserva,
            ];

            $itemsAgrupados[$comercioId]['subtotal'] += $subtotalLinea;
            $itemsAgrupados[$comercioId]['impuesto'] += $impuestoLinea;
        }

        // 2. Validar estado de los comercios con M2_Comercios (Contrato M2 v1.2.1)
        $comerciosActivos = true;
        if ($this->m2Comercios) {
            if (method_exists($this->m2Comercios, 'ObtenerResumenComerciosLote')) {
                $comerciosActivos = $this->m2Comercios->ObtenerResumenComerciosLote(array_values($comerciosInvolucrados));
            } elseif (method_exists($this->m2Comercios, 'obtenerMicrositio')) {
                foreach ($comerciosInvolucrados as $comercioId) {
                    try {
                        $info = $this->m2Comercios->obtenerMicrositio($comercioId);
                        if (isset($info['estado']) && strtolower((string) $info['estado']) !== 'activo') {
                            $comerciosActivos = false;
                            break;
                        }
                    } catch (\Throwable $e) {
                        $comerciosActivos = false;
                        break;
                    }
                }
            }
        }

        if (! $comerciosActivos) {
            return $this->errorRespuesta('ERR-003', 'Comercio no disponible', null, 409);
        }

        // 3. Reservar existencias en M4_Inventario (Contrato M4 v1.1.0 / v1.2.1)
        $idempotencyKey = Str::uuid()->toString();
        $idReserva = null;

        if ($this->m4Inventario) {
            try {
                if ($this->m4Inventario instanceof InventarioModuleInterface) {
                    foreach ($carrito->items as $it) {
                        $resReserva = $this->m4Inventario->reservarExistencias(1, $it->id_variacion, $it->cantidad, null, $idempotencyKey);
                        $idReserva = is_array($resReserva) ? ($resReserva['id_reserva'] ?? $resReserva['reserva_id'] ?? null) : $resReserva;
                        if (! $idReserva) {
                            return $this->errorRespuesta('ERR-M5-002', 'Fallo de existencias / reserva rechazada', null, 409);
                        }
                    }
                } elseif (method_exists($this->m4Inventario, 'reservarExistencias')) {
                    $resReserva = $this->m4Inventario->reservarExistencias($idempotencyKey);
                    $idReserva = is_array($resReserva) ? ($resReserva['id_reserva'] ?? $resReserva['reserva_id'] ?? null) : $resReserva;
                    if (! $idReserva) {
                        return $this->errorRespuesta('ERR-M5-002', 'Fallo de existencias / reserva rechazada', null, 409);
                    }
                } elseif (method_exists($this->m4Inventario, 'solicitarReserva')) {
                    foreach ($carrito->items as $it) {
                        $this->m4Inventario->solicitarReserva($it->id_variacion, $it->cantidad, 0);
                    }
                    $idReserva = $idempotencyKey;
                }
            } catch (\Throwable $e) {
                return $this->errorRespuesta('ERR-M5-002', 'Fallo de existencias / reserva rechazada', null, 409);
            }
        } else {
            $idReserva = $idempotencyKey;
        }

        // 4. Calcular costos y crear jerarquía del pedido (Patrón Composite)
        $montoTotal = 0.0;
        foreach ($itemsAgrupados as $grupo) {
            $montoTotal += $this->calcularCostos(
                (float) $grupo['subtotal'],
                (float) $grupo['impuesto'],
                (float) $grupo['costo_envio']
            );
        }

        $pedidoMaestro = new PedidoMaestro;
        $idPedido = $pedidoMaestro->crearPedidoMaestro(
            $montoTotal,
            $clienteId,
            $request->input('cupon_descuento'),
            $request->input('tarjeta_regalo')
        );

        if ($direccionClienteId && Schema::hasColumn('pedidos_maestros', 'id_direccion_cliente')) {
            $pedidoMaestro->id_direccion_cliente = $direccionClienteId;
            $pedidoMaestro->save();
        }

        // 5. Bucle de creación de subpedidos y líneas
        $subpedidoObj = new Subpedido;
        foreach ($itemsAgrupados as $grupo) {
            $subpedidosCreados = $subpedidoObj->crearSubpedidos([
                [
                    'id_comercio' => $grupo['id_comercio'],
                    'subtotal' => $grupo['subtotal'],
                    'costo_envio' => $grupo['costo_envio'],
                    'impuesto' => $grupo['impuesto'],
                ],
            ], $idPedido);

            $subpedido = $subpedidosCreados[0];

            foreach ($grupo['items'] as $itemData) {
                $lineaReserva = (string) ($itemData['id_reserva'] ?? $idReserva ?? '');
                $lineaId = (new PedidoLinea)->crearLinea(
                    $subpedido->id_subpedido,
                    $itemData['id_variacion'],
                    $itemData['cantidad'],
                    $itemData['precio_unitario'],
                    $itemData['tarifa_impuesto'],
                    $lineaReserva ?: null
                );
            }
        }

        // Vaciar el carrito
        $carrito->vaciar();

        // 6. Publicar eventos canónicos
        $pedidoMaestro->load('subpedidos.lineas');
        event(new PedidoConfirmado($pedidoMaestro));
        event(new PedidoCreado($pedidoMaestro));

        // 7. Procesar pago con M6_Pagos (Contrato M6 v1.0.0 / v1.1.1)
        $urlPasarelaPago = 'https://pago.pacificos.com/checkout/'.$idPedido;
        if ($this->m6Pagos) {
            try {
                if (method_exists($this->m6Pagos, 'ProcesarPagoPedido')) {
                    $urlPasarelaPago = $this->m6Pagos->ProcesarPagoPedido(
                        $idPedido,
                        $datos['metodoPago'] ?? 'tarjeta',
                        $montoTotal,
                        $idempotencyKey
                    );
                } elseif (method_exists($this->m6Pagos, 'procesarPago')) {
                    $resPago = $this->m6Pagos->procesarPago(
                        $idPedido,
                        $montoTotal,
                        $datos['metodoPago'] ?? 'tarjeta',
                        ['idempotency_key' => $idempotencyKey]
                    );
                    if (isset($resPago['urlPasarelaPago'])) {
                        $urlPasarelaPago = $resPago['urlPasarelaPago'];
                    }
                }
            } catch (\Throwable $e) {
                // Conservar URL por defecto
            }
        }

        // 8. Retornar respuesta canónica alineada con esquema PedidoMaestro
        $pedidoArray = $pedidoMaestro->toArray();
        $respuesta = array_merge($pedidoArray, [
            'pedido' => $pedidoMaestro,
            'urlPasarelaPago' => $urlPasarelaPago,
        ]);

        return response()->json($respuesta, 201);
    }

    public function registrarPagoConfirmado(int|Request $pedidoIdOrRequest, ?string $refPago = null): JsonResponse
    {
        if ($pedidoIdOrRequest instanceof Request) {
            $pedidoId = (int) ($pedidoIdOrRequest->route('id_pedido') ?? $pedidoIdOrRequest->input('id_pedido'));
            $referencia = (string) ($pedidoIdOrRequest->input('refPago') ?? $pedidoIdOrRequest->input('referencia_pago') ?? $refPago ?? 'REF-'.uniqid());
        } else {
            $pedidoId = (int) $pedidoIdOrRequest;
            $referencia = (string) ($refPago ?? 'REF-'.uniqid());
        }

        // 1. Consultar estado actual del pedido
        $pedido = PedidoMaestro::with('subpedidos.lineas')->find($pedidoId);
        if (! $pedido) {
            return $this->errorRespuesta('ERR-004', 'Pedido no encontrado', null, 404);
        }

        // 2. Operación Idempotente: Si ya está pagado, ignorar sin cambios
        if (strtolower((string) $pedido->estado_pedido) === 'pagado') {
            return response()->json([
                'mensaje' => 'Operación idempotente, pedido ya pagado',
                'estado' => 'PAGADO',
                'pedido' => $pedido,
            ], 200);
        }

        // 3. Actualizar estado del Pedido Maestro a "pagado"
        $pedido->actualizarEstado('pagado');

        // 4. Actualizar estado de todos los subpedidos a "pagado"
        foreach ($pedido->subpedidos as $subpedido) {
            $subpedido->cambiarEstado('pagado');
        }

        // 5. Publicar evento canónico PedidoPagado
        $pedidoActualizado = $pedido->fresh(['subpedidos.lineas']);
        event(new PedidoPagado($pedidoActualizado));

        return response()->json([
            'mensaje' => 'Pago registrado correctamente',
            'referencia' => $referencia,
            'estado' => 'PAGADO',
            'pedido' => $pedidoActualizado,
        ], 200);
    }

    public function cancelarSubpedido(int|Request $subpedidoIdOrRequest, ?string $motivo = null): JsonResponse
    {
        if ($subpedidoIdOrRequest instanceof Request) {
            $subpedidoId = (int) ($subpedidoIdOrRequest->route('id_subpedido') ?? $subpedidoIdOrRequest->input('id_subpedido'));
            $motivoTexto = (string) ($subpedidoIdOrRequest->input('motivo') ?? $motivo ?? 'Cancelado');
        } else {
            $subpedidoId = (int) $subpedidoIdOrRequest;
            $motivoTexto = (string) ($motivo ?? 'Cancelado');
        }

        $subpedido = Subpedido::with(['pedidoMaestro', 'lineas'])->find($subpedidoId);
        if (! $subpedido) {
            return $this->errorRespuesta('ERR-004', 'Subpedido no encontrado', null, 404);
        }

        if (in_array(strtolower((string) $subpedido->estado_subpedido), ['despachado', 'entregado'])) {
            return $this->errorRespuesta('ERR-M5-003', 'No se puede cancelar un subpedido ya despachado o entregado', null, 409);
        }

        $subpedido->cancelar($motivoTexto);

        // Si todos los subpedidos están cancelados, cancelar el maestro
        $pedidoMaestro = $subpedido->pedidoMaestro;
        if ($pedidoMaestro) {
            $subpedidosActivos = $pedidoMaestro->subpedidos()->where('estado_subpedido', '!=', 'cancelado')->count();
            if ($subpedidosActivos === 0) {
                $pedidoMaestro->actualizarEstado('cancelado');
            }
        }

        $subpedidoActualizado = $subpedido->fresh('lineas');
        $respuesta = array_merge($subpedidoActualizado->toArray(), [
            'mensaje' => 'Subpedido cancelado correctamente',
            'subpedido' => $subpedidoActualizado,
        ]);

        return response()->json($respuesta, 200);
    }

    public function despacharSubpedido(int|Request $requestOrId, ?string $guia = null): JsonResponse
    {
        if ($requestOrId instanceof Request) {
            $subpedidoId = (int) ($requestOrId->route('id_subpedido') ?? $requestOrId->input('id_subpedido'));
            $guiaTransporte = (string) ($requestOrId->input('guia_seguimiento') ?? $requestOrId->input('guia_transporte') ?? $guia ?? '');
        } else {
            $subpedidoId = (int) $requestOrId;
            $guiaTransporte = (string) ($guia ?? '');
        }

        $subpedido = Subpedido::with(['pedidoMaestro', 'lineas'])->find($subpedidoId);
        if (! $subpedido) {
            return $this->errorRespuesta('ERR-004', 'Subpedido no encontrado', null, 404);
        }

        if (in_array(strtolower((string) $subpedido->estado_subpedido), ['despachado', 'entregado', 'cancelado'])) {
            return $this->errorRespuesta('ERR-M5-002', 'El subpedido ya fue despachado o cancelado previamente', null, 409);
        }

        $subpedido->despachar($guiaTransporte ?: null);

        // NOTA DE ARQUITECTURA (FASE 1 - DESACOPLAMIENTO M2):
        // Se elimina la llamada directa a registrarComision de M2. M5 no gestiona comisiones.
        // M2 calcula su comisión en su propio módulo a partir del evento SubpedidoDespachado.

        $subpedidoActualizado = $subpedido->fresh('lineas');
        $respuesta = array_merge($subpedidoActualizado->toArray(), [
            'mensaje' => 'Subpedido despachado correctamente',
            'subpedido' => $subpedidoActualizado,
        ]);

        return response()->json($respuesta, 200);
    }

    public function confirmarEntregaSubpedido(int|Request $requestOrId): JsonResponse
    {
        $subpedidoId = $requestOrId instanceof Request ? (int) ($requestOrId->route('id_subpedido') ?? $requestOrId->input('id_subpedido')) : (int) $requestOrId;
        $subpedido = Subpedido::with('lineas')->find($subpedidoId);
        if (! $subpedido) {
            return $this->errorRespuesta('ERR-004', 'Subpedido no encontrado', null, 404);
        }

        if (in_array(strtolower((string) $subpedido->estado_subpedido), ['entregado', 'cancelado'])) {
            return $this->errorRespuesta('ERR-M5-002', 'El subpedido ya fue entregado o cancelado', null, 409);
        }

        $subpedido->entregar();

        $subpedidoActualizado = $subpedido->fresh('lineas');
        $respuesta = array_merge($subpedidoActualizado->toArray(), [
            'mensaje' => 'Entrega confirmada',
            'subpedido' => $subpedidoActualizado,
        ]);

        return response()->json($respuesta, 200);
    }

    public function cancelarPedidoTotal(int|Request $requestOrId, ?string $motivo = null): JsonResponse
    {
        if ($requestOrId instanceof Request) {
            $pedidoId = (int) ($requestOrId->route('id_pedido') ?? $requestOrId->input('id_pedido'));
            $motivoTexto = (string) ($requestOrId->input('motivo') ?? $motivo ?? 'Cancelación total solicitada');
        } else {
            $pedidoId = (int) $requestOrId;
            $motivoTexto = (string) ($motivo ?? 'Cancelación total');
        }

        $pedido = PedidoMaestro::with('subpedidos.lineas')->find($pedidoId);
        if (! $pedido) {
            return $this->errorRespuesta('ERR-004', 'Pedido no encontrado', null, 404);
        }

        $hayDespachados = $pedido->subpedidos->contains(fn ($s) => in_array(strtolower((string) $s->estado_subpedido), ['despachado', 'entregado']));
        if ($hayDespachados) {
            return $this->errorRespuesta('ERR-M5-003', 'No se puede cancelar el pedido completo porque contiene subpedidos ya despachados', null, 409);
        }

        $pedido->cancelar($motivoTexto);

        $pedidoActualizado = $pedido->fresh('subpedidos.lineas');
        $respuesta = array_merge($pedidoActualizado->toArray(), [
            'mensaje' => 'Pedido maestro y subpedidos cancelados correctamente',
            'pedido' => $pedidoActualizado,
        ]);

        return response()->json($respuesta, 200);
    }

    public function obtenerPedido(int|Request $requestOrId): JsonResponse
    {
        $pedidoId = $requestOrId instanceof Request ? (int) ($requestOrId->route('id_pedido') ?? $requestOrId->input('id_pedido')) : (int) $requestOrId;
        $pedido = PedidoMaestro::with(['subpedidos.lineas'])->find($pedidoId);

        if (! $pedido) {
            return $this->errorRespuesta('ERR-004', 'Pedido no encontrado', null, 404);
        }

        return response()->json($pedido, 200);
    }

    public function obtenerSubpedido(int|Request $requestOrId): JsonResponse
    {
        $subpedidoId = $requestOrId instanceof Request ? (int) ($requestOrId->route('id_subpedido') ?? $requestOrId->input('id_subpedido')) : (int) $requestOrId;
        $subpedido = Subpedido::with('lineas')->find($subpedidoId);

        if (! $subpedido) {
            return $this->errorRespuesta('ERR-004', 'Subpedido no encontrado', null, 404);
        }

        return response()->json($subpedido, 200);
    }

    public function listarPedidos(Request $request): JsonResponse
    {
        $clienteId = (int) ($request->user()?->id ?? $request->input('id_cliente', 1));
        $query = PedidoMaestro::with('subpedidos.lineas')->where('id_cliente', $clienteId);

        if ($request->filled('estado_pedido')) {
            $query->where('estado_pedido', $request->input('estado_pedido'));
        }

        $pedidos = $query->orderByDesc('fecha_creacion')->get();

        return response()->json([
            'pagina' => (int) $request->input('pagina', 1),
            'por_pagina' => (int) $request->input('por_pagina', 15),
            'total_resultados' => $pedidos->count(),
            'pedidos' => $pedidos,
        ], 200);
    }

    public function calcularCostos(float $subtotal, float $impuestos, float $envio): float
    {
        return $subtotal + $impuestos + $envio;
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
