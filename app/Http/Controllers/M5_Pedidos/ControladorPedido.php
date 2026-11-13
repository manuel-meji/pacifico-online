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
use App\Services\M6_Pagos\PagosService;
use App\Services\Nucleo\ContextoCliente;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ControladorPedido extends Controller
{
    private $m1Portal;

    private $m2Comercios;

    private $m4Inventario;

    private $m6Pagos;

    private PedidosService $pedidosService;

    public function __construct(
        ?ComerciosServiceInterface $comercioService = null,
        ?InventarioServiceInterface $inventarioService = null,
        ?PagosServiceInterface $pagosService = null,
        ?PedidosService $pedidosService = null,
        ?PortalPublicoContract $portalService = null
    ) {
        $this->m2Comercios = $comercioService ?? (app()->bound(ComerciosServiceInterface::class) ? app(ComerciosServiceInterface::class) : null);
        $this->m4Inventario = $inventarioService ?? (app()->bound(InventarioServiceInterface::class) ? app(InventarioServiceInterface::class) : null);
        $this->m6Pagos = $pagosService ?? (app()->bound(PagosServiceInterface::class) ? app(PagosServiceInterface::class) : null);
        $this->pedidosService = $pedidosService ?? app(PedidosService::class);
        $this->m1Portal = $portalService ?? (app()->bound(PortalPublicoContract::class) ? app(PortalPublicoContract::class) : (app()->bound(PortalServiceInterface::class) ? app(PortalServiceInterface::class) : null));
    }

    public function procesarCheckout(Request $request): JsonResponse
    {
        $datos = $request->all();
        $clienteId = $this->resolverIdCliente($request);
        $direccionClienteId = (int) ($request->input('id_direccion_cliente', 1));

        // 1. Validar que el carrito no esté vacío
        $carrito = Carrito::with('items')->where('id_cliente', $clienteId)->first();
        $carritoTieneItems = $carrito && $carrito->items->isNotEmpty();

        if (! $carritoTieneItems) {
            return $this->errorRespuesta('ERR-M5-001', 'Carrito vacío o inexistente', null, 400);
        }

        // Validar cliente y dirección con M1_Portal (Contrato M1 v1.1.0)
        $portalService = (app()->resolved(PortalServiceInterface::class) && app()->bound(PortalServiceInterface::class))
            ? app(PortalServiceInterface::class)
            : ($this->m1Portal ?? (app()->bound(PortalServiceInterface::class) ? app(PortalServiceInterface::class) : null));

        $direccionCliente = null;
        if ($portalService) {
            try {
                if (method_exists($portalService, 'obtenerCliente')) {
                    $portalService->obtenerCliente($clienteId);
                }
                if ($direccionClienteId && method_exists($portalService, 'obtenerDireccionCliente')) {
                    $direccionCliente = $portalService->obtenerDireccionCliente($clienteId, $direccionClienteId);
                }
            } catch (\Throwable) {
                // Continuar flujo resiliente
            }
        }

        // B1: Calcular desglose unificado del carrito con M3, M2 y precios vigentes de M1
        $desglose = $this->pedidosService->calcularDesgloseCarrito(
            $carrito,
            null,
            $this->m2Comercios,
            $portalService,
            $request->input('zona') ?? $request->input('zona_entrega') ?? $direccionCliente,
            $direccionClienteId
        );
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

        $codigoCupon = trim((string) $request->input('cupon_descuento', ''));
        if ($codigoCupon !== '') {
            try {
                $this->pedidosService->validarCuponCheckout(
                    $codigoCupon,
                    $clienteId,
                    $itemsAgrupados,
                    (float) ($desglose['resumen']['subtotal'] ?? 0.0)
                );
            } catch (ValidationException $e) {
                $mensaje = $e->validator->errors()->first('cupon_descuento') ?: 'El cupón ingresado no es válido.';

                return $this->errorRespuesta('ERR-M5-CUPON', $mensaje, $e->errors(), 422);
            }
        }

        // 3. Reservar existencias en M4_Inventario (Contrato M4 v1.1.0 / v1.2.1 / RF-010)
        $idempotencyKey = Str::uuid()->toString();
        $idReserva = null;

        if ($this->m4Inventario) {
            try {
                if ($this->m4Inventario instanceof InventarioModuleInterface) {
                    foreach ($itemsAgrupados as $idComercio => $grupo) {
                        foreach ($grupo['items'] as $it) {
                            $idVar = (int) $it['id_variacion'];
                            $cant = (int) $it['cantidad'];
                            $resReserva = $this->m4Inventario->reservarExistencias((int) $idComercio, $idVar, $cant, null, $idempotencyKey);
                            $idReserva = is_array($resReserva) ? ($resReserva['id_reserva'] ?? $resReserva['reserva_id'] ?? null) : $resReserva;
                            if (! $idReserva) {
                                return $this->errorRespuesta('ERR-M5-002', 'Fallo de existencias / reserva rechazada', null, 409);
                            }
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
        try {
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
        } catch (ValidationException $e) {
            $mensaje = $e->validator->errors()->first('cupon_descuento') ?: 'El cupón ingresado no es válido.';

            return $this->errorRespuesta('ERR-M5-CUPON', $mensaje, $e->errors(), 422);
        }

        $idPedido = (int) $pedidoData['id_pedido'];
        $montoTotal = (float) $pedidoData['monto_total'];
        $pedidoMaestro = PedidoMaestro::with(['subpedidos.lineas', 'estados', 'cancelaciones'])->find($idPedido);

        // 6. Publicar eventos canónicos (D3: PedidoConfirmado se emite al confirmar el checkout)
        if ($pedidoMaestro) { // este trozo de código lo agregó JustinM6 con concentimiento de Marcos Ortega
            $pedidoMaestro->actualizarEstado('confirmado', 'Checkout confirmado'); // <-- línea nueva
            event(new PedidoConfirmado($pedidoMaestro));
            event(new PedidoCreado($pedidoMaestro));
        }

        // 7. Procesar pago con M6_Pagos (Contrato M6 v1.0.0 / v1.1.1)
        $urlPasarelaPago = 'https://pago.pacificos.com/checkout/'.$idPedido;
        if ($this->m6Pagos instanceof PagosService) {
            $urlPasarelaPago = "/m6.html?pedido={$idPedido}&monto=".number_format($montoTotal, 2, '.', ''); // lo cambió JustinM6, autorizó Manuel Mejicano
        } elseif ($this->m6Pagos) {
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
                Log::error('M5: error al invocar M6 durante el checkout.', [
                    'id_pedido' => $idPedido,
                    'monto_total' => $montoTotal,
                    'exception' => $e,
                ]);
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

    public function prepararSubpedido(int|Request $requestOrId): JsonResponse
    {
        $subpedidoId = $requestOrId instanceof Request
            ? (int) ($requestOrId->route('id_subpedido') ?? $requestOrId->input('id_subpedido'))
            : (int) $requestOrId;

        $subpedido = Subpedido::with(['pedidoMaestro', 'lineas'])->find($subpedidoId);
        if (! $subpedido) {
            return $this->errorRespuesta('ERR-004', 'Subpedido no encontrado', null, 404);
        }

        $usuarioId = auth()->check() ? (int) (auth()->user()?->id_usuario ?? auth()->id()) : null;

        try {
            $subpedido->cambiarEstado('en_preparacion', 'Subpedido en preparación por el comercio', $usuarioId ?: null);
        } catch (DomainException $e) {
            return $this->errorRespuesta('ERR-M5-002', $e->getMessage(), null, 409);
        }

        $subpedidoActualizado = $subpedido->fresh(['lineas', 'estados']);
        $respuesta = array_merge($subpedidoActualizado->toArray(), [
            'mensaje' => 'Subpedido marcado en preparación',
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

    /**
     * Solicitar la devolución formal de un subpedido entregado.
     * Registra el motivo en cancelaciones_pedido con requiere_reembolso = true
     * y notifica al servicio de reembolsos de M6 si corresponde.
     */
    public function solicitarDevolucionSubpedido(int|Request $requestOrId, ?string $motivo = null): JsonResponse
    {
        $req = request();
        if ($requestOrId instanceof Request) {
            $subpedidoId = (int) ($requestOrId->route('id_subpedido') ?? $requestOrId->input('id_subpedido'));
            $motivoTexto = (string) ($requestOrId->input('motivo') ?? $motivo ?? 'Devolución de producto solicitada');
        } else {
            $subpedidoId = (int) $requestOrId;
            $motivoTexto = (string) ($req->input('motivo') ?? $motivo ?? 'Devolución de producto solicitada');
        }

        $subpedido = Subpedido::with(['pedidoMaestro', 'lineas'])->find($subpedidoId);
        if (! $subpedido) {
            return $this->errorRespuesta('ERR-004', 'Subpedido no encontrado', null, 404);
        }

        $estadoActual = strtolower((string) ($subpedido->estado_subpedido ?: 'pendiente'));
        if ($estadoActual !== 'entregado') {
            return $this->errorRespuesta(
                'ERR-M5-002',
                "Solo se puede solicitar devolución de un subpedido que ya fue entregado (estado actual: '{$estadoActual}').",
                null,
                409
            );
        }

        $yaSolicitada = $subpedido->cancelaciones()
            ->where('motivo', 'like', '[DEVOLUCIÓN]%')
            ->exists();

        if ($yaSolicitada) {
            return $this->errorRespuesta(
                'ERR-M5-002',
                'Ya existe una solicitud de devolución registrada para este subpedido.',
                null,
                409
            );
        }

        // Registrar la cancelación / devolución
        $subpedido->cancelaciones()->create([
            'id_pedido' => (int) $subpedido->id_pedido,
            'motivo' => '[DEVOLUCIÓN] '.$motivoTexto,
            'requiere_reembolso' => true,
            'fecha_cancelacion' => now(),
        ]);

        // Registrar en historial de estados del pedido
        $subpedido->estados()->create([
            'id_pedido' => (int) $subpedido->id_pedido,
            'estado_anterior' => 'entregado',
            'estado' => 'entregado', // Mantiene estado pero registra el hito de devolución
            'motivo' => 'Devolución solicitada por cliente: '.$motivoTexto,
            'fecha_estado' => now(),
        ]);

        $montoDevolucion = (float) ($subpedido->total ?? ($subpedido->subtotal + $subpedido->impuesto + $subpedido->costo_envio));
        $this->pedidosService->solicitarReembolsoM6(
            (int) $subpedido->id_pedido,
            $montoDevolucion,
            '[DEVOLUCIÓN] '.$motivoTexto,
            (int) $subpedido->id_subpedido
        );

        return response()->json([
            'id_subpedido' => (int) $subpedido->id_subpedido,
            'mensaje' => 'Solicitud de devolución registrada correctamente',
            'requiere_reembolso' => true,
            'subpedido' => $subpedido->fresh(['lineas', 'estados', 'cancelaciones']),
        ], 200);
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
        $usuario = $request->user();
        $idComercioSesion = ($usuario !== null && isset($usuario->id_comercio) && (int) $usuario->id_comercio > 0)
            ? (int) $usuario->id_comercio
            : null;
        $idComercioFiltro = $idComercioSesion ?? ($request->filled('id_comercio') ? (int) $request->input('id_comercio') : null);
        $esModoComercio = $idComercioFiltro !== null || $request->boolean('modo_comercio');

        if ($esModoComercio) {
            $query = PedidoMaestro::withoutGlobalScope('cliente')
                ->with([
                    'subpedidos' => function ($q) use ($idComercioFiltro) {
                        $q->withoutGlobalScope('comercio')->with(['lineas', 'estados', 'cancelaciones']);
                        if ($idComercioFiltro !== null) {
                            $q->where('id_comercio', $idComercioFiltro);
                        }
                    },
                    'estados',
                    'cancelaciones',
                ])
                ->whereHas('subpedidos', function ($q) use ($idComercioFiltro) {
                    $q->withoutGlobalScope('comercio');
                    if ($idComercioFiltro !== null) {
                        $q->where('id_comercio', $idComercioFiltro);
                    }
                });
        } else {
            $clienteId = $this->resolverIdCliente($request);
            $query = PedidoMaestro::with(['subpedidos.lineas', 'estados', 'cancelaciones'])->where('id_cliente', $clienteId);
        }

        if ($request->filled('estado_pedido')) {
            $query->where('estado_pedido', $request->input('estado_pedido'));
        }

        $pedidos = $query->orderByDesc('fecha_creacion')->get();
        $nombresComercios = $this->resolverNombresComercios($pedidos);

        $pedidosTransformados = $pedidos->map(function (PedidoMaestro $p) use ($nombresComercios) {
            $arr = $p->toArray();
            $arr['direccion_envio_texto'] = $this->resolverTextoDireccion((int) $p->id_cliente, (int) $p->id_direccion_cliente);

            if (! empty($arr['subpedidos']) && is_array($arr['subpedidos'])) {
                foreach ($arr['subpedidos'] as &$sub) {
                    $idCom = (int) ($sub['id_comercio'] ?? 0);
                    if ($idCom > 0 && empty($sub['nombre_comercio'])) {
                        $sub['nombre_comercio'] = $nombresComercios[$idCom] ?? "Comercio #{$idCom}";
                    }
                }
                unset($sub);
            }

            return $arr;
        });

        return response()->json([
            'pagina' => (int) $request->input('pagina', 1),
            'por_pagina' => (int) $request->input('por_pagina', 15),
            'total_resultados' => $pedidos->count(),
            'pedidos' => $pedidosTransformados,
        ], 200);
    }

    /**
     * Resuelve los nombres comerciales reales en lote desde M2_Comercios para los subpedidos listados.
     *
     * @param  Collection<int, PedidoMaestro>  $pedidos
     * @return array<int, string>
     */
    private function resolverNombresComercios($pedidos): array
    {
        $ids = $pedidos
            ->flatMap(fn (PedidoMaestro $p) => $p->subpedidos->pluck('id_comercio'))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === [] || ! $this->m2Comercios) {
            return [];
        }

        $mapa = [];

        try {
            if (method_exists($this->m2Comercios, 'obtenerResumenComerciosLote')) {
                $res = $this->m2Comercios->obtenerResumenComerciosLote($ids);
                foreach ($res['comercios'] ?? [] as $comercio) {
                    $idCom = (int) ($comercio['id_comercio'] ?? 0);
                    if ($idCom > 0 && ! empty($comercio['nombre_comercial'])) {
                        $mapa[$idCom] = (string) $comercio['nombre_comercial'];
                    }
                }
            } elseif (method_exists($this->m2Comercios, 'obtenerMicrositio')) {
                foreach ($ids as $idCom) {
                    $info = $this->m2Comercios->obtenerMicrositio($idCom);
                    if (! empty($info['nombre_comercial']) || ! empty($info['nombre'])) {
                        $mapa[$idCom] = (string) ($info['nombre_comercial'] ?? $info['nombre']);
                    }
                }
            }
        } catch (\Throwable) {
            // Si algún comercio está suspendido o falla la consulta por lote, respaldar individualmente
        }

        foreach ($ids as $idCom) {
            if (! isset($mapa[$idCom]) && method_exists($this->m2Comercios, 'consultar')) {
                try {
                    $info = $this->m2Comercios->consultar($idCom);
                    if (! empty($info['nombre_comercial'])) {
                        $mapa[$idCom] = (string) $info['nombre_comercial'];
                    }
                } catch (\Throwable) {
                }
            }
        }

        return $mapa;
    }

    private function resolverTextoDireccion(int $idCliente, ?int $idDireccion): ?string
    {
        if (! $idDireccion) {
            return null;
        }

        // Misma fuente que el checkout (ControladorCarrito): respeta el modo doble/real de M5_M1_MODO.
        try {
            $direcciones = app(ControladorCarrito::class)->obtenerDireccionesCliente($idCliente);

            foreach ($direcciones as $dir) {
                if ((int) ($dir['id_direccion_cliente'] ?? 0) === $idDireccion) {
                    return $dir['texto_completo']
                        ?? "{$dir['alias_direccion']} — {$dir['provincia']}, {$dir['canton']}, {$dir['distrito']}, {$dir['otras_senas']}";
                }
            }
        } catch (\Throwable) {
            // Sin datos de M1: se devuelve el identificador.
        }

        return "Dirección #{$idDireccion}";
    }

    public function calcularCostos(float $subtotal, float $impuestos, float $envio): float
    {
        return round($subtotal + $impuestos + $envio, 2);
    }

    private function resolverIdCliente(Request $request): int
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
