<?php

namespace App\Contracts\M4_Inventario\Dependencias;

use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;

/**
 * Lo que M4 necesita de M2 (x-dependencias-consumidas del contrato de M4).
 * Se resuelve con el doble o con M2 real según config/integraciones.php, clave m4.m2.
 */
interface ComerciosDependencia
{
    /**
     * ObtenerComercioActivo de M2.
     *
     * @return array{id_comercio: int, estado: string, activo: bool, motivo_estado: string|null}
     *
     * @throws ErrorInventarioException ERR-006 si M2 no responde.
     */
    public function obtenerComercioActivo(int $idComercio): array;

    /**
     * ObtenerSedesComercio de M2.
     * id_sede de M2 es el mismo identificador que id_ubicacion de M4.
     *
     * @return array{id_comercio: int, sedes: list<array{id_sede: int, habilitada: bool}>}
     *
     * @throws ErrorInventarioException ERR-006 si M2 no responde.
     */
    public function obtenerSedesComercio(int $idComercio): array;
}
