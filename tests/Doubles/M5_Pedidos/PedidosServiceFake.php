<?php

namespace Tests\Doubles\M5_Pedidos;

use App\Contracts\M5_Pedidos\CarritoServiceInterface;
use App\Contracts\M5_Pedidos\GestionPedidosServiceInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use App\Contracts\M5_Pedidos\SubpedidosServiceInterface;

/**
 * Doble de prueba (Fake) canónico de M5 Carrito y pedidos según OpenAPI v1.1.0.
 * Documentado en x-interfaz-php para los módulos consumidores (M1, M2, M4, M6, M7).
 */
class PedidosServiceFake implements CarritoServiceInterface, GestionPedidosServiceInterface, PedidosServiceInterface, SubpedidosServiceInterface
{
    /** @var array<int, array<string, mixed>> */
    private array $carritos = [];

    /** @var array<int, array<string, mixed>> */
    private array $pedidos = [];

    /** @var array<int, array<string, mixed>> */
    private array $subpedidos = [];

    public function obtenerCarrito(int $idCliente): array
    {
        return $this->carritos[$idCliente] ?? [
            'id_carrito' => 100 + $idCliente,
            'id_cliente' => $idCliente,
            'monto_estimado' => 0.0,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'items' => [],
        ];
    }

    public function vaciarCarrito(int $idCliente): array
    {
        if (isset($this->carritos[$idCliente])) {
            $this->carritos[$idCliente]['items'] = [];
            $this->carritos[$idCliente]['monto_estimado'] = 0.0;
        }

        return ['mensaje' => 'Carrito vaciado exitosamente'];
    }

    public function agregarItemCarrito(int $idCliente, array $datos): array
    {
        $carrito = $this->obtenerCarrito($idCliente);
        $carrito['items'][] = [
            'id_item_carrito' => rand(1000, 9999),
            'id_carrito' => $carrito['id_carrito'],
            'id_variacion' => $datos['id_variacion'],
            'cantidad' => $datos['cantidad'] ?? 1,
            'id_reserva' => $datos['id_reserva'] ?? null,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];
        $this->carritos[$idCliente] = $carrito;

        return $carrito;
    }

    public function actualizarCantidadItemCarrito(int $idItemCarrito, int $cantidad, ?int $idCliente = null): array
    {
        $clienteId = $idCliente ?? 1;
        $carrito = $this->obtenerCarrito($clienteId);
        foreach ($carrito['items'] as &$item) {
            if ($item['id_item_carrito'] === $idItemCarrito) {
                $item['cantidad'] = $cantidad;
            }
        }
        $this->carritos[$clienteId] = $carrito;

        return $carrito;
    }

    public function eliminarItemCarrito(int $idItemCarrito, ?int $idCliente = null): array
    {
        $clienteId = $idCliente ?? 1;
        $carrito = $this->obtenerCarrito($clienteId);
        $carrito['items'] = array_values(array_filter($carrito['items'], fn ($i) => $i['id_item_carrito'] !== $idItemCarrito));
        $this->carritos[$clienteId] = $carrito;

        return $carrito;
    }

    public function listarPedidos(?int $idCliente = null, array $filtros = []): array
    {
        $lista = array_values($this->pedidos);
        if ($idCliente !== null) {
            $lista = array_values(array_filter($lista, fn ($p) => ($p['id_cliente'] ?? null) === $idCliente));
        }

        return [
            'pagina' => 1,
            'por_pagina' => 15,
            'total_resultados' => count($lista),
            'pedidos' => $lista,
        ];
    }

    public function crearPedidoCheckout(array $datosCheckout): array
    {
        $idPedido = rand(500, 999);
        $idCliente = (int) ($datosCheckout['id_cliente'] ?? 1);
        $pedido = [
            'id_pedido' => $idPedido,
            'id_cliente' => $idCliente,
            'id_direccion_cliente' => $datosCheckout['id_direccion_cliente'] ?? null,
            'fecha_creacion' => now()->toIso8601String(),
            'monto_total' => 25000.00,
            'estado_pedido' => 'confirmado',
            'subpedidos' => [
                [
                    'id_subpedido' => 800 + $idPedido,
                    'id_pedido' => $idPedido,
                    'id_comercio' => 2,
                    'subtotal' => 22000.00,
                    'impuesto' => 3000.00,
                    'costo_envio' => 0.00,
                    'estado_subpedido' => 'pendiente',
                    'lineas' => [],
                ],
            ],
        ];

        $this->pedidos[$idPedido] = $pedido;
        $this->subpedidos[800 + $idPedido] = $pedido['subpedidos'][0];

        return $pedido;
    }

    public function crearPedido(array $datosCompra): array
    {
        return $this->crearPedidoCheckout($datosCompra);
    }

    public function obtenerPedido(int $idPedido): array
    {
        return $this->pedidos[$idPedido] ?? [
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
                    'impuesto' => 3000.00,
                    'costo_envio' => 0.00,
                    'estado_subpedido' => 'pendiente',
                    'lineas' => [],
                ],
            ],
        ];
    }

    public function cancelarPedidoTotal(int $idPedido, array $datosCancelacion = []): array
    {
        $pedido = $this->obtenerPedido($idPedido);
        $pedido['estado_pedido'] = 'cancelado';
        $pedido['estado'] = 'CANCELADO';
        $this->pedidos[$idPedido] = $pedido;

        return $pedido;
    }

    public function marcarComoPagado(int $pedidoId, string $comprobantePago): bool
    {
        if (isset($this->pedidos[$pedidoId])) {
            $this->pedidos[$pedidoId]['estado_pedido'] = 'pagado';
            $this->pedidos[$pedidoId]['estado'] = 'PAGADO';
        }

        return true;
    }

    public function obtenerSubpedido(int $idSubpedido): array
    {
        return $this->subpedidos[$idSubpedido] ?? [
            'id_subpedido' => $idSubpedido,
            'id_pedido' => 101,
            'id_comercio' => 2,
            'subtotal' => 22000.00,
            'impuesto' => 3000.00,
            'costo_envio' => 0.00,
            'estado_subpedido' => 'pendiente',
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'lineas' => [],
        ];
    }

    public function despacharSubpedido(int $subpedidoId, ?string $guiaTransporte = null): array
    {
        if (isset($this->subpedidos[$subpedidoId])) {
            $this->subpedidos[$subpedidoId]['estado_subpedido'] = 'despachado';
        }

        return [
            'id_subpedido' => $subpedidoId,
            'id_pedido' => 101,
            'estado_subpedido' => 'despachado',
            'guia_transporte' => $guiaTransporte,
            'evento' => 'SubpedidoDespachado',
            'consumidores_notificados' => ['M1', 'M2', 'M4', 'M7'],
            'comision_registrada_m2' => true,
        ];
    }

    public function confirmarEntregaSubpedido(int $idSubpedido, array $datosEntrega = []): array
    {
        $sub = $this->obtenerSubpedido($idSubpedido);
        $sub['estado_subpedido'] = 'entregado';
        $this->subpedidos[$idSubpedido] = $sub;

        return $sub;
    }

    public function cancelarSubpedidoParcial(int $idSubpedido, array $datosCancelacion = []): array
    {
        $sub = $this->obtenerSubpedido($idSubpedido);
        $sub['estado_subpedido'] = 'cancelado';
        $this->subpedidos[$idSubpedido] = $sub;

        return $sub;
    }
}
