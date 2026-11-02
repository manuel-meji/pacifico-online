<?php

namespace Tests\Doubles\M5;

use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Services\M6_Pagos\Clients\{PedidoClient, PedidoDTO, SubpedidoDTO};

/** Fake de M5. Pedido 8942 = confirmado (2 comercios); 8943 = cancelado; cualquier otro = no existe. */
final class FakePedidoClient implements PedidoClient
{
    /** @var array<int,array{idPago:int,estado:string,motivo:?string}> notificaciones recibidas */
    public array $notificaciones = [];

    private array $estados = [8942 => 'confirmado', 8943 => 'cancelado'];

    public function consultarEstadoPedido(int $idPedido): string
    {
        return $this->estados[$idPedido] ?? throw new ErrorContratoException('PEDIDO_NO_ENCONTRADO');
    }

    public function obtenerDatosPedido(int $idPedido): PedidoDTO
    {
        if ($idPedido !== 8942) {
            throw new ErrorContratoException('PEDIDO_NO_ENCONTRADO');
        }
        return new PedidoDTO(8942, 77, 59840.00, [
            new SubpedidoDTO(1001, 11, 30000.00, 3900.00, 1500.00),
            new SubpedidoDTO(1002, 12, 21000.00, 2730.00, 710.00),
        ]);
    }

    public function notificarResultadoPago(int $idPedido, int $idPago, string $estado, ?string $motivo): void
    {
        $this->notificaciones[] = compact('idPago', 'estado', 'motivo');
    }
}
