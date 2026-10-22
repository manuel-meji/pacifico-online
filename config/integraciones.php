<?php

use App\Services\M1_Portal\Dobles\CarritoFake;
use App\Services\M1_Portal\Dobles\CatalogoFake;
use App\Services\M1_Portal\Dobles\ComerciosStub;
use App\Services\M1_Portal\Dobles\InventarioStub;
use App\Services\M4_Inventario\Dobles\CatalogoStub as M4CatalogoStub;
use App\Services\M4_Inventario\Dobles\ComerciosStub as M4ComerciosStub;
use App\Services\M4_Inventario\Dobles\NucleoStub as M4NucleoStub;

return [

    /*
    |--------------------------------------------------------------------------
    | Proveedores de M1 Portal público y búsqueda
    |--------------------------------------------------------------------------
    |
    | Por cada proveedor, 'modo' elige entre el doble de prueba ('doble') y el
    | servicio real ('real'), y 'doble' es la clase que se usa en modo doble.
    | Una prueba puede cambiar 'doble' por una variante de tests/Doubles/M1.
    | Solo afecta a los servicios de M1 (ver M1IntegracionesServiceProvider).
    |
    */

    'm1' => [
        'm2' => [
            'modo' => env('M1_M2_MODO', 'doble'),
            'doble' => ComerciosStub::class,
        ],
        'm3' => [
            'modo' => env('M1_M3_MODO', 'doble'),
            'doble' => CatalogoFake::class,
        ],
        'm4' => [
            'modo' => env('M1_M4_MODO', 'doble'),
            'doble' => InventarioStub::class,
        ],
        'm5' => [
            'modo' => env('M1_M5_MODO', 'doble'),
            'doble' => CarritoFake::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dependencias de M4 Inventario multiempresa
    |--------------------------------------------------------------------------
    |
    | M4 necesita de M2 (estado y sedes del comercio), de M3 (variación y su
    | comercio) y del núcleo (comercio de la sesión y bitácora). Por cada
    | proveedor, 'modo' elige entre el doble ('doble') y el servicio real
    | ('real'), que se llama por su interfaz PHP dentro del mismo proceso.
    | 'doble' es la clase del doble; los dobles de prueba de Eddie se conectan
    | cambiando esa clase. Ver M4IntegracionesServiceProvider.
    |
    */

    'm4' => [
        'm2' => [
            'modo' => env('M4_M2_MODO', 'doble'),
            'doble' => M4ComerciosStub::class,
        ],
        'm3' => [
            'modo' => env('M4_M3_MODO', 'doble'),
            'doble' => M4CatalogoStub::class,
        ],
        'nucleo' => [
            'modo' => env('M4_NUCLEO_MODO', 'doble'),
            'doble' => M4NucleoStub::class,
        ],

        // Minutos de vigencia de una reserva si el solicitante no indica otro valor.
        'reserva_minutos' => (int) env('M4_RESERVA_MINUTOS', 15),
    ],

];
