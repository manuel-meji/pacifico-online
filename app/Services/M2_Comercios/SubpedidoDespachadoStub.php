<?php

namespace App\Services\M2_Comercios;

/**
 * Stub del evento SubpedidoDespachado publicado por M5.
 *
 * Imita el contrato M5 v1.1.0 / evento v1.0.0 consumido por M2.
 */

class SubpedidoDespachadoStub
{
    public const ID_EVENTO = '123e4567-e89b-12d3-a456-426614174000';

    /**
     * Payload válido del evento SubpedidoDespachado.
     *
     * @return array<string, mixed>
     */
    public static function valido(): array
    {
        return [
            'id_evento' => self::ID_EVENTO,
            'fecha_evento' => '2026-10-22T10:00:00-06:00',
            'fecha' => '2026-10-22T10:00:00-06:00',
            'fecha_despacho' => '2026-10-22T10:00:00-06:00',
            'evento' => 'SubpedidoDespachado',
            'id_subpedido' => 1001,
            'id_pedido' => 5001,
            'id_comercio' => 2,
            'subtotal' => 20000.00,
            'costo_envio' => 2500.00,
            'estado_subpedido' => 'despachado',
            'guia_transporte' => 'GUIA-001',
            'reservas' => [
                '550e8400-e29b-41d4-a716-446655440000',
            ],
        ];
    }

    /**
     * Repite exactamente el mismo id_evento para probar idempotencia.
     *
     * @return array<string, mixed>
     */
    public static function duplicado(): array
    {
        return self::valido();
    }

    /**
     * Payload incompleto para comprobar rechazo de eventos inválidos.
     *
     * @return array<string, mixed>
     */
    public static function invalido(): array
    {
        $evento = self::valido();

        unset($evento['id_subpedido']);

        return $evento;
    }
}