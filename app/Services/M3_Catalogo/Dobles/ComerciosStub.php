<?php

declare(strict_types=1);

namespace App\Services\M3_Catalogo\Dobles;

use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Services\M2_Comercios\Dobles\ComerciosDobleBase;

/**
 * Doble de M2 para M3. Solo simula obtenerComercioActivo con la forma del
 * contrato v1.2.1: id_comercio, estado, activo y motivo_estado (no solo un
 * booleano). Las demás operaciones responden ERR-006 (ver ComerciosDobleBase).
 *
 * Se activa con config('integraciones.m3.m2.modo') = 'doble'.
 */
class ComerciosStub extends ComerciosDobleBase
{
    /** @var array<int, array{estado: string, activo: bool, motivo_estado: ?string}> */
    protected array $comercios = [
        1 => ['estado' => 'Activo', 'activo' => true, 'motivo_estado' => null],
        34 => ['estado' => 'Activo', 'activo' => true, 'motivo_estado' => null],
        35 => ['estado' => 'Activo', 'activo' => true, 'motivo_estado' => null],
        36 => ['estado' => 'Activo', 'activo' => true, 'motivo_estado' => null],
        37 => ['estado' => 'Suspendido', 'activo' => false, 'motivo_estado' => 'Suspensión simulada para pruebas.'],
    ];

    public function obtenerComercioActivo(int $idComercio): array
    {
        if (! isset($this->comercios[$idComercio])) {
            // Igual que M2 real: ERR-004 si el comercio no existe.
            throw ErrorNucleoException::recursoNoEncontrado();
        }

        return ['id_comercio' => $idComercio] + $this->comercios[$idComercio];
    }
}