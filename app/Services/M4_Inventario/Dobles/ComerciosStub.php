<?php

namespace App\Services\M4_Inventario\Dobles;

use App\Contracts\M4_Inventario\Dependencias\ComerciosDependencia;

/**
 * Doble por defecto de M2 para M4. Todo comercio está Activo con las sedes 1 y
 * 2 habilitadas, salvo COMERCIO_SUSPENDIDO, que sirve para probar RN-M4-009.
 * Se sustituye por otra clase en config/integraciones.php, clave m4.m2.doble.
 */
class ComerciosStub implements ComerciosDependencia
{
    public const COMERCIO_SUSPENDIDO = 999;

    public function obtenerComercioActivo(int $idComercio): array
    {
        $activo = $idComercio !== self::COMERCIO_SUSPENDIDO;

        return [
            'id_comercio' => $idComercio,
            'estado' => $activo ? 'Activo' : 'Suspendido',
            'activo' => $activo,
            'motivo_estado' => $activo ? null : 'Suspendido por la plataforma.',
        ];
    }

    public function obtenerSedesComercio(int $idComercio): array
    {
        return [
            'id_comercio' => $idComercio,
            'sedes' => [
                ['id_sede' => 1, 'habilitada' => true],
                ['id_sede' => 2, 'habilitada' => true],
            ],
        ];
    }
}
