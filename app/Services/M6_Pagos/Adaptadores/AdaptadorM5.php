<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos\Adaptadores;

use App\Contracts\M5_Pedidos\GestionPedidosServiceInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use App\Enums\M6_Pagos\CodigoErrorM6;
use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M6_Pagos\EstadoPago;
use App\Services\M6_Pagos\Clients\PedidoClient;
use App\Services\M6_Pagos\Clients\PedidoDTO;
use App\Services\M6_Pagos\Clients\SubpedidoDTO;
use RuntimeException;

final class AdaptadorM5 implements PedidoClient
{
    public function __construct(
        private readonly GestionPedidosServiceInterface $gestion,
        private readonly PedidosServiceInterface $pedidos,
    ) {}

    public function consultarEstadoPedido(int $idPedido): string
    {
        return strtolower((string) ($this->pedido($idPedido)['estado_pedido'] ?? ''));
    }

    public function obtenerDatosPedido(int $idPedido): PedidoDTO
    {
        $pedido = $this->pedido($idPedido);

        return new PedidoDTO(
            idPedido: (int) $pedido['id_pedido'],
            idCliente: (int) $pedido['id_cliente'],
            montoTotal: (float) $pedido['monto_total'],
            subpedidos: array_map(
                static fn (array $s): SubpedidoDTO => new SubpedidoDTO(
                    idSubpedido: (int) $s['id_subpedido'],
                    idComercio: (int) $s['id_comercio'],
                    subtotal: (float) $s['subtotal'],
                    // M5 publica "impuesto" y, por compatibilidad, "impuestos".
                    impuesto: (float) ($s['impuesto'] ?? $s['impuestos'] ?? 0),
                    envio: (float) ($s['costo_envio'] ?? 0),
                ),
                array_values($pedido['subpedidos'] ?? []),
            ),
        );
    }

    public function notificarResultadoPago(int $idPedido, int $idPago, string $estado, ?string $motivo): void
    {
        if ($estado !== EstadoPago::CONFIRMADO) {
            return;
        }

        if (! $this->pedidos->marcarComoPagado($idPedido, "pago-{$idPago}")) {
            throw new RuntimeException("M5 no pudo marcar el pedido {$idPedido} como pagado.");
        }
    }

    /** @return array<string, mixed> */
    private function pedido(int $idPedido): array
    {
        try {
            return $this->gestion->obtenerPedido($idPedido);
        } catch (ErrorNucleoException $e) {
            if ($e->codigo === 'ERR-004') {
                throw ErrorContratoException::de(CodigoErrorM6::PedidoNoEncontrado);
            }

            throw $e;
        }
    }
}
