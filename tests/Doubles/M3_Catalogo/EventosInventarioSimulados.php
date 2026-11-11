<?php

namespace Tests\Doubles\M3_Catalogo;

use Illuminate\Support\Str;

/**
 * Doble de M4 para M3: los eventos que M3 consume, con el payload real de M4
 * (ExistenciaAgotada y ExistenciaRepuesta v1.0.0).
 */
final class EventosInventarioSimulados
{
    /** @return array{nombre: string, version: string, carga_util: array<string, mixed>} */
    public static function existenciaAgotada(int $idComercio, int $idProducto, int $idVariacion): array
    {
        return self::evento('ExistenciaAgotada', $idComercio, $idProducto, $idVariacion, 0);
    }

    /** @return array{nombre: string, version: string, carga_util: array<string, mixed>} */
    public static function existenciaRepuesta(
        int $idComercio,
        int $idProducto,
        int $idVariacion,
        int $cantidadDisponible
    ): array {
        return self::evento('ExistenciaRepuesta', $idComercio, $idProducto, $idVariacion, $cantidadDisponible);
    }

    /** Mismo evento entregado dos veces (mismo id_evento), para probar que M3 es idempotente. */
    public static function duplicar(array $evento): array
    {
        return [$evento, $evento];
    }

    /** @return array{nombre: string, version: string, carga_util: array<string, mixed>} */
    private static function evento(
        string $nombre,
        int $idComercio,
        int $idProducto,
        int $idVariacion,
        int $cantidadDisponible
    ): array {
        return [
            'nombre' => $nombre,
            'version' => 'v1.0.0',
            'carga_util' => [
                'id_evento' => (string) Str::uuid(),
                'version_evento' => '1.0.0',
                'fecha_evento' => date(DATE_ATOM),
                'id_comercio' => $idComercio,
                'id_producto' => $idProducto,
                'id_variacion' => $idVariacion,
                'id_ubicacion' => null,
                'cantidad_disponible' => $cantidadDisponible,
            ],
        ];
    }
}