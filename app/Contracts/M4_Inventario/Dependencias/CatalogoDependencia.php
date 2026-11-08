<?php

namespace App\Contracts\M4_Inventario\Dependencias;

use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;

/**
 * Lo que M4 necesita de M3 (x-dependencias-consumidas del contrato de M4).
 * Se resuelve con el doble o con M3 real según config/integraciones.php, clave m4.m3.
 */
interface CatalogoDependencia
{
    /**
     * obtenerVariacion de M3.
     * Devuelve null si la variación no existe.
     *
     * @return array{id_variacion: int, id_producto: int, id_comercio: int}|null
     *
     * @throws ErrorInventarioException ERR-006 si M3 no responde.
     */
    public function obtenerVariacion(int $idVariacion): ?array;
}
