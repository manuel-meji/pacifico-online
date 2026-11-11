<?php

namespace Tests\Doubles\M1;

use App\Contracts\M1_Portal\ComercialPortalContract;
use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;

/**
 * Doble de ComercialPortalContract (contrato M1 1.4.0, SC-08) para quien
 * liquida al comercio (M2 o M7). Responde como el contrato describe, con
 * cargos fijos en memoria:
 * - Comercio 34: dos cargos (7 días el 2026-11-05 y 15 días el 2026-11-20).
 * - Comercio 35: un cargo (30 días el 2026-11-10).
 * - Cualquier otro comercio: sin cargos.
 */
class ComercialPortalFake implements ComercialPortalContract
{
    public const COMERCIO_CON_CARGOS = 34;

    public const COMERCIO_CON_UN_CARGO = 35;

    public function listarCargosDestacado(int $idComercio, string $desde, string $hasta): array
    {
        $errores = [];

        foreach (['desde' => $desde, 'hasta' => $hasta] as $campo => $valor) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) || ! checkdate((int) substr($valor, 5, 2), (int) substr($valor, 8, 2), (int) substr($valor, 0, 4))) {
                $errores[$campo] = 'Debe tener el formato AAAA-MM-DD.';
            }
        }

        if ($errores === [] && $hasta < $desde) {
            $errores['hasta'] = 'No puede ser anterior a desde.';
        }

        if ($errores !== []) {
            throw new DatosInvalidosException($errores);
        }

        return array_values(array_filter(
            self::cargos(),
            fn (array $cargo): bool => $cargo['id_comercio'] === $idComercio
                && substr($cargo['fecha_cargo'], 0, 10) >= $desde
                && substr($cargo['fecha_cargo'], 0, 10) <= $hasta,
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function cargos(): array
    {
        return [
            self::cargo(1, self::COMERCIO_CON_CARGOS, 501, 1520, 7, 15000.0, '2026-11-05T10:00:00+00:00', '2026-11-12T10:00:00+00:00'),
            self::cargo(2, self::COMERCIO_CON_UN_CARGO, 502, 1522, 30, 50000.0, '2026-11-10T09:30:00+00:00', '2026-12-10T09:30:00+00:00'),
            self::cargo(3, self::COMERCIO_CON_CARGOS, 503, 1521, 15, 25000.0, '2026-11-20T15:00:00+00:00', '2026-12-05T15:00:00+00:00'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function cargo(int $id, int $idComercio, int $idSolicitud, int $idProducto, int $dias, float $monto, string $inicio, string $fin): array
    {
        return [
            'id_cargo_destacado' => $id,
            'id_comercio' => $idComercio,
            'id_solicitud_destacado' => $idSolicitud,
            'id_producto' => $idProducto,
            'periodo_dias' => $dias,
            'monto' => $monto,
            'moneda' => 'CRC',
            'fecha_cargo' => $inicio,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
        ];
    }
}
