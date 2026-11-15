<?php

namespace Tests\Doubles\M1;

use App\Contracts\M3_Catalogo\CatalogoServiceInterface;

/**
 * Doble de M3 que solo implementa CatalogoServiceInterface (obtenerDetalleProducto
 * y obtenerVariacion), sin la interfaz de consultas. Sirve para comprobar que
 * CatalogoClient, tipado con la intersección, rechaza un proveedor incompleto
 * (el caso que advirtió M3: un doble que cumple una sola de las dos interfaces).
 */
class CatalogoSoloDetalle implements CatalogoServiceInterface
{
    public function obtenerDetalleProducto(int $productoId): ?array
    {
        return null;
    }

    public function obtenerVariacion(int $variacionId): ?array
    {
        return null;
    }
}
