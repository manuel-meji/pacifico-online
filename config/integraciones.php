<?php

use App\Services\M1_Portal\Dobles\CarritoFake;
use App\Services\M1_Portal\Dobles\CatalogoFake;
use App\Services\M1_Portal\Dobles\ComerciosStub;
use App\Services\M1_Portal\Dobles\InventarioStub;

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

];
