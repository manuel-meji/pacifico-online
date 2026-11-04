<?php

namespace App\Services\M6_Pagos\Clients;

use App\Exceptions\M6_Pagos\ErrorContratoException;

/** Puerto de M6 hacia M5. Lo implementa AdaptadorM5 (real) o FakePedidoClient (pruebas). */
interface PedidoClient
{
    /** @throws ErrorContratoException PEDIDO_NO_ENCONTRADO */
    public function consultarEstadoPedido(int $idPedido): string;

    /** @throws ErrorContratoException PEDIDO_NO_ENCONTRADO */
    public function obtenerDatosPedido(int $idPedido): PedidoDTO;

    public function notificarResultadoPago(int $idPedido, int $idPago, string $estado, ?string $motivo): void;
}
