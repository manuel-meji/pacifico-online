<?php

namespace App\Services\M2_Comercios;

/**
 * Stub del evento AlertaStockMinimo publicado por M4.
 *
 * Imita M4 Inventario v1.2.0 / evento AlertaStockMinimo v1.0.0.
 */

class InventarioStub
{
    public const ID_EVENTO = '223e4567-e89b-12d3-a456-426614174000';

    /**
     * Evento válido de stock mínimo.
     *
     * @return array<string, mixed>
     */

    public static function valido(): array
    {
        return [
            'id_comercio' => 2,
            'id_variacion' => 101,
            'id_ubicacion' => 1,
            'cantidad_disponible' => 3,
            'stock_minimo' => 5,
            'fecha_evento' => '2026-10-22T10:30:00-06:00',
            'id_evento' => self::ID_EVENTO,
            'version_evento' => '1.0.0',
        ];
    }

    /**
     * Repite el mismo id_evento para comprobar idempotencia.
     *
     * @return array<string, mixed>
     */
    
    public static function duplicado(): array
    {
        return self::valido();
    }
}