<?php

namespace App\Services\M4_Inventario\Dobles;

use App\Contracts\M4_Inventario\Dependencias\CatalogoDependencia;

/**
 * Doble por defecto de M3 para M4. Toda variación existe y pertenece al
 * comercio 1, salvo las mayores a VARIACION_MAXIMA, que no existen (igual que el
 * servicio de M3 en el PC3). Se sustituye en config/integraciones.php, clave m4.m3.doble.
 */
class CatalogoStub implements CatalogoDependencia
{
    public const VARIACION_MAXIMA = 1000;

    public const COMERCIO = 1;

    public function obtenerVariacion(int $idVariacion): ?array
    {
        if ($idVariacion > self::VARIACION_MAXIMA) {
            return null;
        }

        return [
            'id_variacion' => $idVariacion,
            'id_producto' => 1,
            'id_comercio' => self::COMERCIO,
        ];
    }
}
