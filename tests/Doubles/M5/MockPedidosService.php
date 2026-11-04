<?php

namespace Tests\Doubles\M5;

use App\Contracts\M5_Pedidos\GestionPedidosServiceInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;

class MockPedidosService implements GestionPedidosServiceInterface, PedidosServiceInterface
{
    public function obtenerPedido(int $idPedido): array
    {
        return [
            'id_pedido' => $idPedido,
            'id_cliente' => 101,
            'monto_total' => 25000.00,
            'estado' => 'CONFIRMADO',
            'estado_pedido' => 'confirmado',
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

    public function listarPedidos(?int $idCliente = null, array $filtros = []): array
    {
        return [
            'pagina' => 1,
            'por_pagina' => 15,
            'total_resultados' => 1,
            'pedidos' => [$this->obtenerPedido(1001)],
        ];
    }

    public function crearPedidoCheckout(array $datosCheckout): array
    {
        return $this->obtenerPedido(1001);
    }

    public function cancelarPedidoTotal(int $idPedido, array $datosCancelacion = []): array
    {
        $pedido = $this->obtenerPedido($idPedido);
        $pedido['estado'] = 'CANCELADO';
        $pedido['estado_pedido'] = 'cancelado';

        return $pedido;
    }

    public function crearPedido(array $datosCompra): array
    {
        return $this->obtenerPedido(1001);
    }

    public function marcarComoPagado(int $pedidoId, string $comprobantePago): bool
    {
        return true;
    }

    public function despacharSubpedido(int $subpedidoId, ?string $guiaTransporte = null): array
    {
        return [
            'id_subpedido' => $subpedidoId,
            'id_pedido' => 1001,
            'estado_subpedido' => 'despachado',
            'guia_transporte' => $guiaTransporte,
            'evento' => 'SubpedidoDespachado',
            'consumidores_notificados' => ['M1', 'M2', 'M4', 'M7'],
            'comision_registrada_m2' => true,
        ];
    }

    public function obtenerCarrito(int $idCliente): array
    {
        return [
            'id_carrito' => 100 + $idCliente,
            'id_cliente' => $idCliente,
            'monto_estimado' => 0.0,
            'items' => [],
        ];
    }

    public function agregarItemCarrito(int $idCliente, array $datos): array
    {
        return $this->obtenerCarrito($idCliente);
    }
}
