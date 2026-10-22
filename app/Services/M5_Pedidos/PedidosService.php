<?php

namespace App\Services\M5_Pedidos;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M5_Pedidos\CarritoServiceInterface;
use App\Contracts\M5_Pedidos\GestionPedidosServiceInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use App\Contracts\M5_Pedidos\SubpedidosServiceInterface;
use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use Illuminate\Support\Facades\Schema;

class PedidosService implements CarritoServiceInterface, GestionPedidosServiceInterface, PedidosServiceInterface, SubpedidosServiceInterface
{
    public function __construct(
        protected ?ComerciosServiceInterface $comerciosService = null
    ) {}

    // =========================================================================
    // CarritoServiceInterface
    // =========================================================================

    public function obtenerCarrito(int $idCliente): array
    {
        if ($this->hasTable('pedidos_carritos')) {
            $carrito = (new Carrito)->obtenerOCrearCarrito($idCliente);
            $carrito->loadMissing('items');

            return [
                'id_carrito' => $carrito->id_carrito,
                'id_cliente' => $carrito->id_cliente,
                'monto_estimado' => (float) ($carrito->monto_estimado ?? 0.0),
                'created_at' => $carrito->created_at?->toIso8601String(),
                'updated_at' => $carrito->updated_at?->toIso8601String(),
                'items' => $carrito->items->map(fn ($item) => [
                    'id_item_carrito' => $item->id_item_carrito,
                    'id_carrito' => $item->id_carrito,
                    'id_variacion' => $item->id_variacion,
                    'cantidad' => $item->cantidad,
                    'id_reserva' => $item->id_reserva,
                    'created_at' => $item->created_at?->toIso8601String(),
                    'updated_at' => $item->updated_at?->toIso8601String(),
                ])->all(),
            ];
        }

        return [
            'id_carrito' => 1,
            'id_cliente' => $idCliente,
            'monto_estimado' => 0.0,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'items' => [],
        ];
    }

    public function vaciarCarrito(int $idCliente): array
    {
        if ($this->hasTable('pedidos_carritos')) {
            $carrito = Carrito::where('id_cliente', $idCliente)->first();
            if ($carrito) {
                $carrito->vaciar();
            }
        }

        return [
            'mensaje' => 'Carrito vaciado correctamente.',
        ];
    }

    public function agregarItemCarrito(int $idCliente, array $datos): array
    {
        $idVariacion = (int) ($datos['id_variacion'] ?? 1);
        $cantidad = (int) ($datos['cantidad'] ?? 1);
        $idReserva = $datos['id_reserva'] ?? null;

        if ($this->hasTable('pedidos_carritos') && $this->hasTable('items_carrito')) {
            $carrito = (new Carrito)->obtenerOCrearCarrito($idCliente);
            $item = (new ItemCarrito)->crearItemCarrito($carrito->id_carrito, $idVariacion, $cantidad);
            if ($idReserva !== null) {
                $item->id_reserva = $idReserva;
                $item->save();
            }

            return $this->obtenerCarrito($idCliente);
        }

        return [
            'id_carrito' => 1,
            'id_cliente' => $idCliente,
            'monto_estimado' => 15000.0,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'items' => [
                [
                    'id_item_carrito' => 1,
                    'id_carrito' => 1,
                    'id_variacion' => $idVariacion,
                    'cantidad' => $cantidad,
                    'id_reserva' => $idReserva,
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
        ];
    }

    public function actualizarCantidadItemCarrito(int $idItemCarrito, int $cantidad, ?int $idCliente = null): array
    {
        if ($this->hasTable('items_carrito')) {
            $item = ItemCarrito::find($idItemCarrito);
            if ($item) {
                $item->actualizarCantidad($cantidad);
                $carrito = Carrito::find($item->id_carrito);
                if ($carrito) {
                    return $this->obtenerCarrito((int) $carrito->id_cliente);
                }
            }
        }

        return $idCliente !== null ? $this->obtenerCarrito($idCliente) : [
            'id_carrito' => 1,
            'id_cliente' => $idCliente ?? 1,
            'monto_estimado' => 0.0,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'items' => [],
        ];
    }

    public function eliminarItemCarrito(int $idItemCarrito, ?int $idCliente = null): array
    {
        if ($this->hasTable('items_carrito')) {
            $item = ItemCarrito::find($idItemCarrito);
            if ($item) {
                $carritoId = $item->id_carrito;
                $item->delete();
                $carrito = Carrito::find($carritoId);
                if ($carrito) {
                    return $this->obtenerCarrito((int) $carrito->id_cliente);
                }
            }
        }

        return $idCliente !== null ? $this->obtenerCarrito($idCliente) : [
            'id_carrito' => 1,
            'id_cliente' => $idCliente ?? 1,
            'monto_estimado' => 0.0,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'items' => [],
        ];
    }

    // =========================================================================
    // SubpedidosServiceInterface
    // =========================================================================

    public function obtenerSubpedido(int $idSubpedido): array
    {
        if ($this->hasTable('pedidos_subpedidos')) {
            $subpedido = Subpedido::with('lineas')->find($idSubpedido);
            if ($subpedido) {
                return [
                    'id_subpedido' => $subpedido->id_subpedido,
                    'id_pedido' => $subpedido->id_pedido,
                    'id_comercio' => $subpedido->id_comercio,
                    'subtotal' => (float) $subpedido->subtotal,
                    'costo_envio' => (float) $subpedido->costo_envio,
                    'impuesto' => (float) $subpedido->impuesto,
                    'estado_subpedido' => (string) $subpedido->estado_subpedido,
                    'created_at' => $subpedido->created_at?->toIso8601String(),
                    'updated_at' => $subpedido->updated_at?->toIso8601String(),
                    'lineas' => $subpedido->lineas->map(fn ($l) => [
                        'id_linea' => $l->id_linea,
                        'id_subpedido' => $l->id_subpedido,
                        'id_variacion' => $l->id_variacion,
                        'id_reserva' => $l->id_reserva,
                        'cantidad' => $l->cantidad,
                        'precio_unitario' => (float) $l->precio_unitario,
                        'tarifa_impuesto' => (float) $l->tarifa_impuesto,
                    ])->all(),
                ];
            }
        }

        return [
            'id_subpedido' => $idSubpedido,
            'id_pedido' => 101,
            'id_comercio' => 2,
            'subtotal' => 15000.00,
            'costo_envio' => 500.00,
            'impuesto' => 1950.00,
            'estado_subpedido' => 'pendiente',
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'lineas' => [],
        ];
    }

    public function despacharSubpedido(int $subpedidoId, ?string $guiaTransporte = null): array
    {
        $pedidoId = 101;
        $subtotal = 15000.00;

        if ($this->hasTable('pedidos_subpedidos')) {
            $subpedido = Subpedido::find($subpedidoId);
            if ($subpedido) {
                $subpedido->despachar($guiaTransporte);
                $pedidoId = (int) $subpedido->id_pedido;
                $subtotal = (float) $subpedido->subtotal;
            }
        }

        // Paso 9 del recorrido transversal: M2 consume SubpedidoDespachado para registrar comisión
        $comisionRegistrada = false;
        if ($this->comerciosService) {
            $comisionRegistrada = $this->comerciosService->registrarComision($pedidoId, $subtotal);
        }

        return [
            'id_subpedido' => $subpedidoId,
            'id_pedido' => $pedidoId,
            'estado_subpedido' => 'despachado',
            'guia_transporte' => $guiaTransporte,
            'evento' => 'SubpedidoDespachado',
            'consumidores_notificados' => ['M1', 'M2', 'M4', 'M7'],
            'comision_registrada_m2' => $comisionRegistrada,
        ];
    }

    public function confirmarEntregaSubpedido(int $idSubpedido, array $datosEntrega = []): array
    {
        if ($this->hasTable('pedidos_subpedidos')) {
            $subpedido = Subpedido::find($idSubpedido);
            if ($subpedido) {
                $subpedido->entregar();

                return $this->obtenerSubpedido($idSubpedido);
            }
        }

        return [
            'id_subpedido' => $idSubpedido,
            'id_pedido' => 101,
            'id_comercio' => 2,
            'estado_subpedido' => 'entregado',
            'evento' => 'SubpedidoEntregado',
            'fecha_entrega' => now()->toIso8601String(),
        ];
    }

    public function cancelarSubpedidoParcial(int $idSubpedido, array $datosCancelacion = []): array
    {
        $motivo = $datosCancelacion['motivo'] ?? 'Cancelación parcial solicitada';

        if ($this->hasTable('pedidos_subpedidos')) {
            $subpedido = Subpedido::find($idSubpedido);
            if ($subpedido) {
                $subpedido->cancelar($motivo);

                return $this->obtenerSubpedido($idSubpedido);
            }
        }

        return [
            'id_subpedido' => $idSubpedido,
            'estado_subpedido' => 'cancelado',
            'motivo' => $motivo,
            'requiere_reembolso' => $datosCancelacion['requiere_reembolso'] ?? true,
            'evento' => 'SubpedidoCancelado',
        ];
    }

    // =========================================================================
    // PedidosServiceInterface (Maestro y transversales)
    // =========================================================================

    public function listarPedidos(?int $idCliente = null, array $filtros = []): array
    {
        $page = (int) ($filtros['page'] ?? 1);
        $perPage = (int) ($filtros['per_page'] ?? 15);

        if ($this->hasTable('pedidos_maestros')) {
            $query = PedidoMaestro::with('subpedidos.lineas');
            if ($idCliente !== null) {
                $query->where('id_cliente', $idCliente);
            }
            if (! empty($filtros['estado'])) {
                $query->where('estado_pedido', $filtros['estado']);
            }

            $total = $query->count();
            $pedidos = $query->forPage($page, $perPage)->get();

            return [
                'pagina' => $page,
                'por_pagina' => $perPage,
                'total_resultados' => $total,
                'pedidos' => $pedidos->map(fn ($p) => $this->obtenerPedido((int) $p->id_pedido))->all(),
            ];
        }

        return [
            'pagina' => $page,
            'por_pagina' => $perPage,
            'total_resultados' => 0,
            'pedidos' => [],
        ];
    }

    public function crearPedidoCheckout(array $datosCheckout): array
    {
        $idCliente = (int) ($datosCheckout['id_cliente'] ?? 1);
        $idDireccion = isset($datosCheckout['id_direccion_cliente']) ? (int) $datosCheckout['id_direccion_cliente'] : null;

        if ($this->hasTable('pedidos_maestros')) {
            $pedido = new PedidoMaestro;
            $idPedido = $pedido->crearPedidoMaestro(
                0.0,
                $idCliente,
                $datosCheckout['cupon_descuento'] ?? null,
                $datosCheckout['tarjeta_regalo'] ?? null,
                $idDireccion
            );

            return $this->obtenerPedido($idPedido);
        }

        return [
            'id_pedido' => rand(100, 999),
            'id_cliente' => $idCliente,
            'id_direccion_cliente' => $idDireccion,
            'fecha_creacion' => now()->toIso8601String(),
            'monto_total' => 16950.00,
            'estado_pedido' => 'confirmado',
            'subpedidos' => [],
        ];
    }

    public function crearPedido(array $datosCompra): array
    {
        // Paso 3: Confirmación de carrito y creación de pedido (recorrido transversal)
        return [
            'pedido_id' => rand(100, 999),
            'estado' => 'PENDIENTE_PAGO',
            'subtotal' => 15000.00,
            'impuesto' => 1950.00,
            'total' => 16950.00,
        ];
    }

    public function obtenerPedido(int $idPedido): array
    {
        if ($this->hasTable('pedidos_maestros')) {
            $pedido = PedidoMaestro::with(['subpedidos.lineas', 'notas', 'recibos', 'cancelaciones'])->find($idPedido);
            if ($pedido) {
                return [
                    'id_pedido' => $pedido->id_pedido,
                    'id_cliente' => $pedido->id_cliente,
                    'id_direccion_cliente' => $pedido->id_direccion_cliente,
                    'fecha_creacion' => $pedido->fecha_creacion ? (string) $pedido->fecha_creacion : now()->toIso8601String(),
                    'monto_total' => (float) $pedido->monto_total,
                    'estado_pedido' => (string) $pedido->estado_pedido,
                    'estado' => strtoupper((string) $pedido->estado_pedido), // Compatibilidad con M6 mock
                    'cupon_descuento' => $pedido->cupon_descuento,
                    'tarjeta_regalo' => $pedido->tarjeta_regalo,
                    'subpedidos' => $pedido->subpedidos->map(fn ($sub) => [
                        'id_subpedido' => $sub->id_subpedido,
                        'id_pedido' => $sub->id_pedido,
                        'id_comercio' => $sub->id_comercio,
                        'subtotal' => (float) $sub->subtotal,
                        'costo_envio' => (float) $sub->costo_envio,
                        'impuesto' => (float) $sub->impuesto,
                        'impuestos' => (float) $sub->impuesto, // Compatibilidad con alias M6
                        'estado_subpedido' => (string) $sub->estado_subpedido,
                        'lineas' => $sub->lineas->map(fn ($l) => [
                            'id_linea' => $l->id_linea,
                            'id_subpedido' => $l->id_subpedido,
                            'id_variacion' => $l->id_variacion,
                            'id_reserva' => $l->id_reserva,
                            'cantidad' => $l->cantidad,
                            'precio_unitario' => (float) $l->precio_unitario,
                            'tarifa_impuesto' => (float) $l->tarifa_impuesto,
                        ])->all(),
                    ])->all(),
                    'notas' => $pedido->notas?->toArray() ?? [],
                    'recibos' => $pedido->recibos?->toArray() ?? [],
                ];
            }
        }

        return [
            'id_pedido' => $idPedido,
            'id_cliente' => 101,
            'monto_total' => 25000.00,
            'estado_pedido' => 'confirmado',
            'estado' => 'CONFIRMADO',
            'fecha_creacion' => now()->toIso8601String(),
            'subpedidos' => [
                [
                    'id_subpedido' => 801,
                    'id_comercio' => 2,
                    'subtotal' => 22000.00,
                    'impuestos' => 3000.00,
                    'costo_envio' => 0.00,
                ],
            ],
        ];
    }

    public function cancelarPedidoTotal(int $idPedido, array $datosCancelacion = []): array
    {
        $motivo = $datosCancelacion['motivo'] ?? 'Cancelación solicitada por el cliente';

        if ($this->hasTable('pedidos_maestros')) {
            $pedido = PedidoMaestro::find($idPedido);
            if ($pedido) {
                $pedido->cancelar($motivo);

                return $this->obtenerPedido($idPedido);
            }
        }

        return [
            'id_pedido' => $idPedido,
            'estado_pedido' => 'cancelado',
            'motivo' => $motivo,
            'requiere_reembolso' => $datosCancelacion['requiere_reembolso'] ?? true,
            'evento' => 'PedidoCancelado',
        ];
    }

    public function marcarComoPagado(int $pedidoId, string $comprobantePago): bool
    {
        // Paso 6: Actualizar pedido a PAGADO y publicar evento
        if ($this->hasTable('pedidos_maestros')) {
            $pedido = PedidoMaestro::find($pedidoId);
            if ($pedido) {
                $pedido->estado_pedido = 'pagado';
                $pedido->save();
            }
        }

        return true;
    }

    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}
