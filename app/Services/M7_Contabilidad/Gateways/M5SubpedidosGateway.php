<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad\Gateways;

use App\Contracts\M5_Pedidos\SubpedidosServiceInterface;
use App\Contracts\M7_Contabilidad\SubpedidosM7GatewayInterface;
use LogicException;

final class M5SubpedidosGateway implements SubpedidosM7GatewayInterface
{
    public function __construct(
        private readonly SubpedidosServiceInterface $subpedidos,
    ) {}

    public function obtenerSubpedido(int $subpedidoId): array
    {
        $subpedido = $this->subpedidos->obtenerSubpedido($subpedidoId);

        $idSubpedido = (int) ($subpedido['id_subpedido'] ?? 0);

        if ($idSubpedido !== $subpedidoId) {
            throw new LogicException(
                'El subpedido devuelto por M5 no coincide con el solicitado.'
            );
        }

        $idPedido = (int) ($subpedido['id_pedido'] ?? 0);
        $idComercio = (int) ($subpedido['id_comercio'] ?? 0);

        if ($idPedido <= 0 || $idComercio <= 0) {
            throw new LogicException(
                'M5 devolvió identificadores inválidos para el subpedido.'
            );
        }

        foreach (['subtotal', 'impuesto', 'costo_envio'] as $campo) {
            if (! array_key_exists($campo, $subpedido)) {
                throw new LogicException(
                    "M5 no devolvió el campo {$campo} del subpedido."
                );
            }

            if ((float) $subpedido[$campo] < 0) {
                throw new LogicException(
                    "M5 devolvió un valor inválido para {$campo}."
                );
            }
        }

        return $subpedido;
    }
}