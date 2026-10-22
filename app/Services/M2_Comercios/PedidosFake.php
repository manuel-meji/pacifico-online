<?php

namespace App\Services\M2_Comercios;

use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use LogicException;
use RuntimeException;

/**
 * Fake de M5 Pedidos para la operación obtenerSubpedido.
 *
 * Imita el contrato M5 v1.1.0 consumido por M2.
 */

class PedidosFake implements PedidosServiceInterface
{
    public const SUBPEDIDO_EXISTENTE = 1001;

    public const SUBPEDIDO_NO_ACCESIBLE = 9999;

    /**
     * Simula M5.obtenerSubpedido.
     *
     * @return array<string, mixed>
     */
    public function obtenerSubpedido(int $idSubpedido): array
    {
        if ($idSubpedido === self::SUBPEDIDO_NO_ACCESIBLE) {
            throw new RuntimeException(
                'ERR-003: El recurso solicitado no existe o no es accesible.'
            );
        }

        if ($idSubpedido !== self::SUBPEDIDO_EXISTENTE) {
            throw new RuntimeException(
                'ERR-004: No se encontró lo que buscas.'
            );
        }

        return [
            'id_subpedido' => 1001,
            'id_pedido' => 5001,
            'id_comercio' => 2,
            'subtotal' => 20000.00,
            'costo_envio' => 2500.00,
            'impuesto' => 2600.00,
            'estado_subpedido' => 'despachado',
            'lineas' => [
                [
                    'id_linea' => 1,
                    'id_subpedido' => 1001,
                    'id_variacion' => 101,
                    'id_reserva' => '550e8400-e29b-41d4-a716-446655440000',
                    'cantidad' => 1,
                    'precio_unitario' => 12000.00,
                    'tarifa_impuesto' => 13.0,
                ],
                [
                    'id_linea' => 2,
                    'id_subpedido' => 1001,
                    'id_variacion' => 102,
                    'id_reserva' => '550e8400-e29b-41d4-a716-446655440001',
                    'cantidad' => 2,
                    'precio_unitario' => 4000.00,
                    'tarifa_impuesto' => 13.0,
                ],
            ],
        ];
    }

    /*
     * Estos métodos pertenecen a la interfaz PHP actual de M5,
     * pero no forman parte de lo que M2 necesita de este doble.
     */

    public function crearPedido(array $datosCompra): array
    {
        throw new LogicException('Fuera del alcance del doble de M2.');
    }

    public function marcarComoPagado(
        int $pedidoId,
        string $comprobantePago
    ): bool {
        throw new LogicException('Fuera del alcance del doble de M2.');
    }

    public function despacharSubpedido(
        int $subpedidoId,
        ?string $guiaTransporte = null
    ): array {
        throw new LogicException('Fuera del alcance del doble de M2.');
    }
}