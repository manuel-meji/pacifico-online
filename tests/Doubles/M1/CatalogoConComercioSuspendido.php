<?php

namespace Tests\Doubles\M1;

use App\Services\M1_Portal\Dobles\CatalogoFake;
use App\Services\M1_Portal\Dobles\DatosPrueba;

/**
 * M3 entrega por error un producto de un comercio suspendido al inicio de
 * cada búsqueda. Sirve para probar la salvaguarda de RN-M1-01 y RN-M1-02.
 */
class CatalogoConComercioSuspendido extends CatalogoFake
{
    public function buscarProductos(array $filtros = []): array
    {
        $respuesta = parent::buscarProductos($filtros);

        array_unshift($respuesta['productos'], [
            'id_producto' => DatosPrueba::PRODUCTO_DE_COMERCIO_SUSPENDIDO,
            'id_comercio' => DatosPrueba::COMERCIO_SUSPENDIDO,
            'nombre' => 'Martillo de uña',
            'imagen_principal' => null,
            'precio_desde' => 9000.00,
            'precio_hasta' => null,
            'moneda' => DatosPrueba::MONEDA,
        ]);
        $respuesta['total_resultados']++;

        return $respuesta;
    }
}
