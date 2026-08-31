<?php

namespace App\Services\M5_Pedidos;

use App\Contracts\M5_Pedidos\PedidosServiceInterface;

class PedidosService implements PedidosServiceInterface
{
    public function crearPedido(array $datosCompra): array
    {
        // Paso 3: Confirmación de carrito y creación de pedido
        return [
            'pedido_id' => rand(100, 999),
            'estado' => 'PENDIENTE_PAGO',
            'subtotal' => 15000.00,
            'impuesto' => 1950.00,
            'total' => 16950.00,
        ];
    }

    public function marcarComoPagado(int $pedidoId, string $comprobantePago): bool
    {
        // Paso 6: Actualizar pedido a PAGADO y publicar evento
        return true;
    }
}
