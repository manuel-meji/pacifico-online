<?php

namespace App\Services\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\CatalogoDependencia;
use App\Contracts\M4_Inventario\Dependencias\ComerciosDependencia;
use App\Models\M4_Inventario\EstadoComercioInventario;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;

/**
 * Valida contra M2 y M3, por sus contratos, que el comercio esté activo, que la
 * variación sea suya y que la ubicación sea una sede habilitada (figura 4.2b del PC3).
 * Nunca lee tablas de otros módulos.
 */
class ValidadorPertenencia
{
    public function __construct(
        private readonly ComerciosDependencia $comercios,
        private readonly CatalogoDependencia $catalogo,
    ) {}

    /**
     * RN-M4-009: solo un comercio Activo registra movimientos (ERR-M4-015). Si M2 ya
     * avisó por evento que el comercio está suspendido, no hace falta consultarlo.
     */
    public function validarComercioActivo(int $idComercio): void
    {
        if (EstadoComercioInventario::query()->whereKey($idComercio)->value('bloqueado')) {
            throw ErrorInventarioException::comercioNoHabilitado();
        }

        if (! ($this->comercios->obtenerComercioActivo($idComercio)['activo'] ?? false)) {
            throw ErrorInventarioException::comercioNoHabilitado();
        }
    }

    /**
     * La variación existe en M3 (ERR-M4-013) y es del comercio (ERR-003).
     *
     * @return array{id_variacion: int, id_producto: int, id_comercio: int, sku?: string|null}
     */
    public function validarVariacion(int $idComercio, int $idVariacion): array
    {
        $variacion = $this->catalogo->obtenerVariacion($idVariacion);

        if ($variacion === null) {
            throw ErrorInventarioException::variacionNoEncontrada();
        }

        if ($variacion['id_comercio'] !== $idComercio) {
            throw ErrorInventarioException::recursoNoAccesible();
        }

        return $variacion;
    }

    /** La ubicación es una sede habilitada del comercio en M2 (ERR-M4-014). */
    public function validarUbicacion(int $idComercio, int $idUbicacion): void
    {
        if (! in_array($idUbicacion, $this->ubicacionesHabilitadas($idComercio), true)) {
            throw ErrorInventarioException::ubicacionNoEncontrada();
        }
    }

    /**
     * Sedes habilitadas del comercio (id_sede de M2 = id_ubicacion de M4).
     *
     * @return list<int>
     */
    public function ubicacionesHabilitadas(int $idComercio): array
    {
        $sedes = $this->comercios->obtenerSedesComercio($idComercio)['sedes'] ?? [];

        return array_values(array_map(
            fn (array $sede): int => (int) $sede['id_sede'],
            array_filter($sedes, fn (array $sede): bool => (bool) ($sede['habilitada'] ?? false)),
        ));
    }
}
