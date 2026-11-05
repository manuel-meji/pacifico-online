<?php

namespace App\Http\Controllers\M5_Pedidos;

use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Contracts\M1_Portal\PortalServiceInterface;
use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Events\M5_Pedidos\PedidoCancelado;
use App\Events\M5_Pedidos\PedidoConfirmado;
use App\Events\M5_Pedidos\PedidoCreado;
use App\Events\M5_Pedidos\SubpedidoCancelado;
use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Events\M5_Pedidos\SubpedidoEntregado;
use App\Http\Controllers\Controller;
use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use App\Services\M5_Pedidos\PedidosService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ControladorPedido extends Controller
{
    private $m2Comercios;

    private $m4Inventario;

    private $m6Pagos;

    private PedidosService $pedidosService;

    public function __construct(?ComerciosServiceInterface $comercioService = null, ?InventarioServiceInterface $inventarioService = null, ?PagosServiceInterface $pagosService = null, ?PedidosService $pedidosService = null)
    {
        $this->m2Comercios = $comercioService ?? (app()->bound(ComerciosServiceInterface::class) ? app(ComerciosServiceInterface::class) : null);
        $this->m4Inventario = $inventarioService ?? (app()->bound(InventarioServiceInterface::class) ? app(InventarioServiceInterface::class) : null);
        $this->m6Pagos = $pagosService ?? (app()->bound(PagosServiceInterface::class) ? app(PagosServiceInterface::class) : null);
        $this->pedidosService = $pedidosService ?? app(PedidosService::class);
    }

    public function procesarCheckout(Request $request): JsonResponse
    {
        $datos = $request->all();
        $clienteId = (int) ($request->user()?->id_usuario ?? $request->user()?->id ?? $request->input('id_cliente', 1));
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

        if ($portalService) {
            try {
                if (method_exists($portalService, 'obtenerCliente')) {
                    $portalService->obtenerCliente($clienteId);
                }
                if ($direccionClienteId && method_exists($portalService, 'obtenerDireccionCliente')) {
                    $portalService->obtenerDireccionCliente($clienteId, $direccionClienteId);
                }
            } catch (\Throwable) {
                // Continuar flujo resiliente
            }
        }

        // B1: Calcular desglose unificado del carrito con M3 y M2
        $desglose = $this->pedidosService->calcularDesgloseCarrito($carrito, null, $this->m2Comercios);
        $itemsAgrupados = $desglose['grupos_comercio'];
        $comerciosInvolucrados = array_keys($itemsAgrupados);

        // 2. Validar estado de los comercios con M2_Comercios (Contrato M2 v1.2.1 / RF-008)
        $comerciosActivos = ! $desglose['hay_comercio_suspendido'];
        if ($comerciosActivos && $this->m2Comercios) {
            $idsSolicitados = array_values(array_map('intval', $comerciosInvolucrados));

            if (method_exists($this->m2Comercios, 'obtenerResumenComerciosLote') || method_exists($this->m2Comercios, 'ObtenerResumenComerciosLote')) {
                try {
                    $resumen = method_exists($this->m2Comercios, 'obtenerResumenComerciosLote')
                        ? $this->m2Comercios->obtenerResumenComerciosLote($idsSolicitados)
                        : $this->m2Comercios->ObtenerResumenComerciosLote($idsSolicitados);
                    $comerciosDevueltos = $resumen['comercios'] ?? [];
                    $idsDevueltos = array_map('intval', array_column($comerciosDevueltos, 'id_comercio'));

                    // M2 omite comercios no habilitados o inexistentes:
                    // todos los comercios solicitados deben estar presentes en la lista devuelta
                    $comerciosActivos = count($idsDevueltos) === count($idsSolicitados)
                        && empty(array_diff($idsSolicitados, $idsDevueltos));
                } catch (\Throwable) {
                    $comerciosActivos = false;
                }
            } elseif (method_exists($this->m2Comercios, 'obtenerComercioActivo')) {
                foreach ($idsSolicitados as $idComercio) {
                    try {
                        $info = $this->m2Comercios->obtenerComercioActivo($idComercio);
                        if (isset($info['activo']) && ! $info['activo']) {
                            $comerciosActivos = false;
                            break;
                        }
                    } catch (\Throwable) {
                        $comerciosActivos = false;
                        break;
                    }
                }
            } elseif (method_exists($this->m2Comercios, 'obtenerMicrositio')) {
                foreach ($idsSolicitados as $idComercio) {
                    try {
                        $info = $this->m2Comercios->obtenerMicrositio($idComercio);
                        if (isset($info['estado']) && strtolower((string) $info['estado']) !== 'activo') {
                            $comerciosActivos = false;
                            break;
                        }
                    } catch (\Throwable) {
                        $comerciosActivos = false;
                        break;
                    }
                }
            }
        }

        if (! $comerciosActivos) {
            return $this->errorRespuesta('ERR-003', 'Comercio no disponible o suspendido', null, 409);
        }

        // 3. Reservar existencias en M4_Inventario (Contrato M4 v1.1.0 / v1.2.1 / RF-010)
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
                        $res = $this->m4Inventario->solicitarReserva($it->id_variacion, $it->cantidad, 0);
                        if (is_array($res) && isset($res['id_reserva'])) {
                            $idReserva = $res['id_reserva'];
                        }
                    }
                    $idReserva = $idReserva ?? $idempotencyKey;
                }
            } catch (\Throwable) {
                return $this->errorRespuesta('ERR-M5-002', 'Fallo de existencias / reserva rechazada', null, 409);
            }
        } else {
            $idReserva = $idempotencyKey;
        }

        // 4 y 5. B3 + D4: Delegar creación de maestro, subpedidos y líneas en PedidosService (DB::transaction)
        $pedidoData = $this->pedidosService->crearPedidoCheckout([
            'id_cliente' => $clienteId,
            'id_direccion_cliente' => $direccionClienteId,
            'cupon_descuento' => $request->input('cupon_descuento'),
            'tarjeta_regalo' => $request->input('tarjeta_regalo'),
            'id_reserva' => $idReserva,
            'carrito' => $carrito,
            'grupos_comercio' => $itemsAgrupados,
            'resumen' => $desglose['resumen'],
        ]);

        $idPedido = (int) $pedidoData['id_pedido'];
        $montoTotal = (float) $pedidoData['monto_total'];
        $pedidoMaestro = PedidoMaestro::with(['subpedidos.lineas', 'estados', 'cancelaciones'])->find($idPedido);

        // 6. Publicar eventos canónicos (D3: PedidoConfirmado se emite al confirmar el checkout)
        if ($pedidoMaestro) {
            event(new PedidoConfirmado($pedidoMaestro));
            event(new PedidoCreado($pedidoMaestro));
        }

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
            } catch (\Throwable) {
                // Conservar URL por defecto
            }
        }

        // 8. Retornar respuesta canónica alineada con esquema PedidoMaestro
        $pedidoArray = $pedidoMaestro ? $pedidoMaestro->toArray() : $pedidoData;
        $respuesta = array_merge($pedidoArray, [
            'pedido' => $pedidoMaestro ?? $pedidoData,
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

        // 2. B6: Operación Idempotente: Si ya está pagado, responder 200 sin crear nada ni reemitir eventos
        if (strtolower((string) $pedido->estado_pedido) === 'pagado') {
            return response()->json([
                'mensaje' => 'Operación idempotente, pedido ya pagado',
                'estado' => 'PAGADO',
                'pedido' => $pedido,
            ], 200);
        }

        // 3. Delegar en PedidosService::marcarComoPagado (actualiza maestro/subpedidos, confirma reserva en M4 y emite PedidoPagado)
        $this->pedidosService->marcarComoPagado($pedidoId, $referencia);

        $pedidoActualizado = $pedido->fresh(['subpedidos.lineas', 'estados']);

        return response()->json([
            'mensaje' => 'Pago registrado correctamente',
            'referencia' => $referencia,
            'estado' => 'PAGADO',
            'pedido' => $pedidoActualizado,
        ], 200);
    }

    public function cancelarSubpedido(int|Request $subpedidoIdOrRequest, ?string $motivo = null): JsonResponse
    {
        $req = request();
        if ($subpedidoIdOrRequest instanceof Request) {
            $subpedidoId = (int) ($subpedidoIdOrRequest->route('id_subpedido') ?? $subpedidoIdOrRequest->input('id_subpedido'));
            $motivoTexto = (string) ($subpedidoIdOrRequest->input('motivo') ?? $motivo ?? 'Cancelado');
        } else {
            $subpedidoId = (int) $subpedidoIdOrRequest;
            $motivoTexto = (string) ($req->input('motivo') ?? $motivo ?? 'Cancelado');
        }

        $subpedido = Subpedido::with(['pedidoMaestro', 'lineas'])->find($subpedidoId);
        if (! $subpedido) {
            return $this->errorRespuesta('ERR-004', 'Subpedido no encontrado', null, 404);
        }

        // B5 / RN-M5-05: Rechazar también si ya está en_preparacion, despachado o entregado
        $estadoActual = strtolower((string) $subpedido->estado_subpedido);
        if (in_array($estadoActual, ['en_preparacion', 'despachado', 'entregado'], true)) {
            return $this->errorRespuesta(
                'ERR-M5-003',
                "No se puede cancelar el subpedido porque ya se encuentra en estado '{$estadoActual}'",
                ['estado_actual' => $estadoActual],
                409
            );
        }

        $pedidoMaestro = $subpedido->pedidoMaestro;
        $estabaMaestroCancelado = $pedidoMaestro && strtolower((string) $pedidoMaestro->estado_pedido) === 'cancelado';

        $this->pedidosService->cancelarSubpedidoParcial($subpedidoId, ['motivo' => $motivoTexto]);

        if ($pedidoMaestro) {
            $pedidoMaestro->refresh();
            if (! $estabaMaestroCancelado && strtolower((string) $pedidoMaestro->estado_pedido) === 'cancelado') {
                event(new PedidoCancelado($pedidoMaestro->fresh('subpedidos.lineas'), $motivoTexto));
            }
        }

        $subpedidoActualizado = $subpedido->fresh(['lineas', 'estados', 'cancelaciones']);
        event(new SubpedidoCancelado($subpedidoActualizado, $motivoTexto));
        $respuesta = array_merge($subpedidoActualizado->toArray(), [
            'mensaje' => 'Subpedido cancelado correctamente',
            'subpedido' => $subpedidoActualizado,
        ]);

        return response()->json($respuesta, 200);
    }

    public function despacharSubpedido(int|Request $requestOrId, ?string $guia = null): JsonResponse
    {
        $req = request();
        if ($requestOrId instanceof Request) {
            $subpedidoId = (int) ($requestOrId->route('id_subpedido') ?? $requestOrId->input('id_subpedido'));
            $guiaTransporte = (string) ($requestOrId->input('guia_seguimiento') ?? $requestOrId->input('guia_transporte') ?? $guia ?? '');
        } else {
            $subpedidoId = (int) $requestOrId;
            $guiaTransporte = (string) ($req->input('guia_seguimiento') ?? $req->input('guia_transporte') ?? $guia ?? '');
        }

        $subpedido = Subpedido::with(['pedidoMaestro', 'lineas'])->find($subpedidoId);
        if (! $subpedido) {
            return $this->errorRespuesta('ERR-004', 'Subpedido no encontrado', null, 404);
        }

        try {
            $subpedido->despachar($guiaTransporte ?: null);
        } catch (DomainException $e) {
            return $this->errorRespuesta('ERR-M5-002', $e->getMessage(), null, 409);
        }

        $subpedidoActualizado = $subpedido->fresh(['lineas', 'estados']);
        event(new SubpedidoDespachado($subpedidoActualizado, $guiaTransporte ?: null));
        $respuesta = array_merge($subpedidoActualizado->toArray(), [
            'mensaje' => 'Subpedido despachado correctamente',
            'subpedido' => $subpedidoActualizado,
        ]);

        return response()->json($respuesta, 200);
    }

    public function confirmarEntregaSubpedido(int|Request $requestOrId): JsonResponse
    {
        $subpedidoId = $requestOrId instanceof Request ? (int) ($requestOrId->route('id_subpedido') ?? $requestOrId->input('id_subpedido')) : (int) $requestOrId;
        $subpedido = Subpedido::with(['pedidoMaestro', 'lineas'])->find($subpedidoId);
        if (! $subpedido) {
            return $this->errorRespuesta('ERR-004', 'Subpedido no encontrado', null, 404);
        }

        try {
            $subpedido->entregar();
        } catch (DomainException $e) {
            return $this->errorRespuesta('ERR-M5-002', $e->getMessage(), null, 409);
        }

        $subpedidoActualizado = $subpedido->fresh(['lineas', 'estados']);
        event(new SubpedidoEntregado($subpedidoActualizado));
        $respuesta = array_merge($subpedidoActualizado->toArray(), [
            'mensaje' => 'Entrega confirmada',
            'subpedido' => $subpedidoActualizado,
        ]);

        return response()->json($respuesta, 200);
    }

    public function cancelarPedidoTotal(int|Request $requestOrId, ?string $motivo = null): JsonResponse
    {
        $req = request();
        if ($requestOrId instanceof Request) {
            $pedidoId = (int) ($requestOrId->route('id_pedido') ?? $requestOrId->input('id_pedido'));
            $motivoTexto = (string) ($requestOrId->input('motivo') ?? $motivo ?? 'Cancelación total solicitada');
        } else {
            $pedidoId = (int) $requestOrId;
            $motivoTexto = (string) ($req->input('motivo') ?? $motivo ?? 'Cancelación total');
        }

        $pedido = PedidoMaestro::with('subpedidos.lineas')->find($pedidoId);
        if (! $pedido) {
            return $this->errorRespuesta('ERR-004', 'Pedido no encontrado', null, 404);
        }

        // B5 / RN-M5-05: Rechazar si algún subpedido está en en_preparacion, despachado o entregado
        $subpedidoNoCancelable = $pedido->subpedidos->first(
            fn ($s) => in_array(strtolower((string) $s->estado_subpedido), ['en_preparacion', 'despachado', 'entregado'], true)
        );
        if ($subpedidoNoCancelable) {
            return $this->errorRespuesta(
                'ERR-M5-003',
                "No se puede cancelar el pedido completo porque el subpedido #{$subpedidoNoCancelable->id_subpedido} ya está en estado '{$subpedidoNoCancelable->estado_subpedido}'",
                ['estado_subpedido' => $subpedidoNoCancelable->estado_subpedido],
                409
            );
        }

        $this->pedidosService->cancelarPedidoTotal($pedidoId, ['motivo' => $motivoTexto]);

        $pedidoActualizado = $pedido->fresh(['subpedidos.lineas', 'estados', 'cancelaciones']);
        event(new PedidoCancelado($pedidoActualizado, $motivoTexto));
        $respuesta = array_merge($pedidoActualizado->toArray(), [
            'mensaje' => 'Pedido maestro y subpedidos cancelados correctamente',
            'pedido' => $pedidoActualizado,
        ]);

        return response()->json($respuesta, 200);
    }

    public function obtenerPedido(int|Request $requestOrId): JsonResponse
    {
        $pedidoId = $requestOrId instanceof Request ? (int) ($requestOrId->route('id_pedido') ?? $requestOrId->input('id_pedido')) : (int) $requestOrId;
        $pedido = PedidoMaestro::with(['subpedidos.lineas', 'estados', 'cancelaciones'])->find($pedidoId);

        if (! $pedido) {
            return $this->errorRespuesta('ERR-004', 'Pedido no encontrado', null, 404);
        }

        $datos = $pedido->toArray();
        $datos['direccion_envio_texto'] = $this->resolverTextoDireccion((int) $pedido->id_cliente, (int) $pedido->id_direccion_cliente);

        return response()->json($datos, 200);
    }

    public function obtenerSubpedido(int|Request $requestOrId): JsonResponse
    {
        $subpedidoId = $requestOrId instanceof Request ? (int) ($requestOrId->route('id_subpedido') ?? $requestOrId->input('id_subpedido')) : (int) $requestOrId;
        $subpedido = Subpedido::with(['lineas', 'estados', 'cancelaciones'])->find($subpedidoId);

        if (! $subpedido) {
            return $this->errorRespuesta('ERR-004', 'Subpedido no encontrado', null, 404);
        }

        return response()->json($subpedido, 200);
    }

    public function listarPedidos(Request $request): JsonResponse
    {
        $clienteId = (int) ($request->user()?->id_usuario ?? $request->user()?->id ?? $request->input('id_cliente', 1));
        $query = PedidoMaestro::with(['subpedidos.lineas', 'estados', 'cancelaciones'])->where('id_cliente', $clienteId);

        if ($request->filled('estado_pedido')) {
            $query->where('estado_pedido', $request->input('estado_pedido'));
        }

        $pedidos = $query->orderByDesc('fecha_creacion')->get();

        $pedidosTransformados = $pedidos->map(function (PedidoMaestro $p) {
            $arr = $p->toArray();
            $arr['direccion_envio_texto'] = $this->resolverTextoDireccion((int) $p->id_cliente, (int) $p->id_direccion_cliente);

            return $arr;
        });

        return response()->json([
            'pagina' => (int) $request->input('pagina', 1),
            'por_pagina' => (int) $request->input('por_pagina', 15),
            'total_resultados' => $pedidos->count(),
            'pedidos' => $pedidosTransformados,
        ], 200);
    }

    private function resolverTextoDireccion(int $idCliente, ?int $idDireccion): ?string
    {
        if (! $idDireccion) {
            return null;
        }

        $portal = app()->bound(PortalPublicoContract::class)
            ? app(PortalPublicoContract::class)
            : (app()->bound(PortalServiceInterface::class) ? app(PortalServiceInterface::class) : null);

        if ($portal && method_exists($portal, 'obtenerDireccionCliente')) {
            try {
                $dir = $portal->obtenerDireccionCliente($idCliente, $idDireccion);

                return $dir['texto_completo']
                    ?? "{$dir['alias_direccion']} — {$dir['provincia']}, {$dir['canton']}, {$dir['distrito']}, {$dir['otras_senas']}";
            } catch (\Throwable) {
                return "Dirección #{$idDireccion}";
            }
        }

        return "Dirección #{$idDireccion}";
    }

    public function calcularCostos(float $subtotal, float $impuestos, float $envio): float
    {
        return round($subtotal + $impuestos + $envio, 2);
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
