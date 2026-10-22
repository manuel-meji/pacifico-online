<?php

namespace Tests\Doubles\M5;

use App\Contracts\M5_Pedidos\PedidosServiceInterface;

class MockPedidosService implements PedidosServiceInterface
{
    public function obtenerPedido(int $idPedido): array
    {
        return [
            'id_pedido' => $idPedido,
            'id_cliente' => 101,
            'monto_total' => 25000.00,
            'estado' => 'CONFIRMADO',
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
}
