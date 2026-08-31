<?php

namespace App\Contracts\M5_Pedidos;

interface PedidosServiceInterface
{
    /**
     * Crear y confirmar compra desde el carrito multi-comercio.
     * Invocado en el paso 3 del recorrido transversal.
     */
    public function crearPedido(array $datosCompra): array;

    /**
     * Actualizar estado del pedido a Pagado y disparar eventos subsiguientes.
     * Invocado en el paso 6 del recorrido transversal.
     */
    public function marcarComoPagado(int $pedidoId, string $comprobantePago): bool;
}
