<?php

use App\Services\M1_Portal\Dobles\CarritoFake;
use App\Services\M1_Portal\Dobles\CatalogoFake;
use App\Services\M1_Portal\Dobles\ComerciosStub;
use App\Services\M1_Portal\Dobles\InventarioStub;
use App\Services\M3_Catalogo\Dobles\ComerciosStub as M3ComerciosStub;
use App\Services\M4_Inventario\Dobles\AutorizacionesStub as M4AutorizacionesStub;
use App\Services\M4_Inventario\Dobles\CatalogoStub as M4CatalogoStub;
use App\Services\M4_Inventario\Dobles\ComerciosStub as M4ComerciosStub;
use App\Services\M4_Inventario\Dobles\NucleoStub as M4NucleoStub;
use App\Services\M4_Inventario\Dobles\PedidosStub as M4PedidosStub;
use App\Services\M5_Pedidos\Dobles\CatalogoStub as M5CatalogoStub;
use App\Services\M5_Pedidos\Dobles\ComerciosStub as M5ComerciosStub;
use App\Services\M5_Pedidos\Dobles\InventarioStub as M5InventarioStub;
use App\Services\M5_Pedidos\Dobles\PagosStub as M5PagosStub;
use App\Services\M5_Pedidos\Dobles\PortalStub as M5PortalStub;

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
    | M2, M3, M4 y M5 están en 'real' por defecto: cualquier copia del proyecto y
    | producción quedan conectadas sin tocar el .env. Para trabajar sin un
    | proveedor, poner M1_M?_MODO=doble en el .env propio. Las pruebas usan
    | siempre los dobles (PruebaM1).
    |
    */

    'm1' => [
        'm2' => [
            'modo' => env('M1_M2_MODO', 'real'),
            'doble' => ComerciosStub::class,
        ],
        'm3' => [
            'modo' => env('M1_M3_MODO', 'real'),
            'doble' => CatalogoFake::class,
        ],

        'm4' => [
            'modo' => env('M1_M4_MODO', 'real'),
            'doble' => InventarioStub::class,
        ],
        'm5' => [
            'modo' => env('M1_M5_MODO', 'real'),
            'doble' => CarritoFake::class,
        ],

        // Segundos de espera máxima por proveedor (RNF-M1-04).
        'tiempo_maximo' => (float) env('M1_TIEMPO_MAXIMO', 3),

        // Segundos de vida de la caché de respuestas de M2, M3 y M4; máximo 5 (RNF-M1-06).
        'cache_segundos' => (int) env('M1_CACHE_SEGUNDOS', 5),

        // Clases de los eventos de M2 que escucha M1 (EventosComercioListener).
        // M2 todavía no los publica; si usa otros nombres, se cambian solo aquí.
        'eventos_m2' => [
            'comercio_aprobado' => 'App\\Events\\M2_Comercios\\ComercioAprobado',
            'comercio_suspendido' => 'App\\Events\\M2_Comercios\\ComercioSuspendido',
            'comercio_reactivado' => 'App\\Events\\M2_Comercios\\ComercioReactivado',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dependencias de M3 Catálogo
    |--------------------------------------------------------------------------
    */

    'm3' => [
        'm2' => [
            'modo' => env('M3_M2_MODO', 'doble'),
            'doble' => M3ComerciosStub::class,
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
            'modo' => env('M4_M2_MODO', 'real'),
            'doble' => M4ComerciosStub::class,
        ],
        'm3' => [
            'modo' => env('M4_M3_MODO', 'real'),
            'doble' => M4CatalogoStub::class,
        ],
        'nucleo' => [
            'modo' => env('M4_NUCLEO_MODO', 'doble'),
            'doble' => M4NucleoStub::class,
        ],
        'm5' => [
            'modo' => env('M4_M5_MODO', 'doble'),
            'doble' => M4PedidosStub::class,
        ],

        // Minutos de vigencia de una reserva si el solicitante no indica otro valor.
        'reserva_minutos' => (int) env('M4_RESERVA_MINUTOS', 15),

        // Clases de los eventos de M2 y M3 que escucha M4. M2 publica hoy solo
        // ComercioAprobado y M3 aún no publica ProductoPublicado: si usan otros
        // nombres, se cambian aquí (InventarioServiceProvider los registra).
        'eventos' => [
            'comercio_aprobado' => 'App\\Events\\M2_Comercios\\ComercioAprobado',
            'comercio_suspendido' => 'App\\Events\\M2_Comercios\\ComercioSuspendido',
            'comercio_reactivado' => 'App\\Events\\M2_Comercios\\ComercioReactivado',
            'producto_publicado' => 'App\\Events\\M3_Catalogo\\ProductoPublicado',
        ],

        // Verificador de autorizaciones de ajustes (RN-M4-011). El núcleo aún no
        // publica el flujo de autorizaciones: se usa el doble con las autorizaciones
        // registradas aquí (una sola vez cada una). Ver AutorizacionesStub.
        'autorizaciones' => [
            'doble' => M4AutorizacionesStub::class,
            'registradas' => [
                'autorizacion-ajuste-0001' => [
                    'operacion' => 'ajuste', 'id_comercio' => 104, 'id_variacion' => 501, 'ubicaciones' => [12], 'cantidad' => 2,
                    'id_autorizador' => 2, 'fecha_autorizacion' => '2026-11-09T09:00:00-06:00', 'vence' => '2026-12-31T23:59:59-06:00',
                ],
                // Comercio 1 del escenario (seeder de M4, sedes 1 y 2 de M2). Cada una sirve para un
                // conteo cuya diferencia con el sistema sea exactamente esa cantidad, una sola vez.
                'autorizacion-ajuste-c1-0001' => [
                    'operacion' => 'ajuste', 'id_comercio' => 1, 'id_variacion' => 501, 'ubicaciones' => [1], 'cantidad' => 1,
                    'id_autorizador' => 2, 'fecha_autorizacion' => '2026-11-10T09:00:00-06:00', 'vence' => '2026-12-31T23:59:59-06:00',
                ],
                'autorizacion-ajuste-c1-0002' => [
                    'operacion' => 'ajuste', 'id_comercio' => 1, 'id_variacion' => 501, 'ubicaciones' => [1], 'cantidad' => 2,
                    'id_autorizador' => 2, 'fecha_autorizacion' => '2026-11-10T09:00:00-06:00', 'vence' => '2026-12-31T23:59:59-06:00',
                ],
                'autorizacion-ajuste-c1-0003' => [
                    'operacion' => 'ajuste', 'id_comercio' => 1, 'id_variacion' => 501, 'ubicaciones' => [2], 'cantidad' => 1,
                    'id_autorizador' => 2, 'fecha_autorizacion' => '2026-11-10T09:00:00-06:00', 'vence' => '2026-12-31T23:59:59-06:00',
                ],
                'autorizacion-ajuste-c1-0004' => [
                    'operacion' => 'ajuste', 'id_comercio' => 1, 'id_variacion' => 501, 'ubicaciones' => [2], 'cantidad' => 2,
                    'id_autorizador' => 2, 'fecha_autorizacion' => '2026-11-10T09:00:00-06:00', 'vence' => '2026-12-31T23:59:59-06:00',
                ],
                'autorizacion-ajuste-c1-0005' => [
                    'operacion' => 'ajuste', 'id_comercio' => 1, 'id_variacion' => 502, 'ubicaciones' => [1], 'cantidad' => 1,
                    'id_autorizador' => 2, 'fecha_autorizacion' => '2026-11-10T09:00:00-06:00', 'vence' => '2026-12-31T23:59:59-06:00',
                ],
                'autorizacion-ajuste-c1-0006' => [
                    'operacion' => 'ajuste', 'id_comercio' => 1, 'id_variacion' => 502, 'ubicaciones' => [1], 'cantidad' => 2,
                    'id_autorizador' => 2, 'fecha_autorizacion' => '2026-11-10T09:00:00-06:00', 'vence' => '2026-12-31T23:59:59-06:00',
                ],
                // Transferencias del comercio 1 (RN-M4-012): ubicaciones [origen, destino] y cantidad exacta, una sola vez.
                'autorizacion-transferencia-c1-0001' => [
                    'operacion' => 'transferencia', 'id_comercio' => 1, 'id_variacion' => 501, 'ubicaciones' => [1, 2], 'cantidad' => 1,
                    'id_autorizador' => 2, 'fecha_autorizacion' => '2026-11-10T09:00:00-06:00', 'vence' => '2026-12-31T23:59:59-06:00',
                ],
                'autorizacion-transferencia-c1-0002' => [
                    'operacion' => 'transferencia', 'id_comercio' => 1, 'id_variacion' => 501, 'ubicaciones' => [1, 2], 'cantidad' => 2,
                    'id_autorizador' => 2, 'fecha_autorizacion' => '2026-11-10T09:00:00-06:00', 'vence' => '2026-12-31T23:59:59-06:00',
                ],
                'autorizacion-transferencia-c1-0003' => [
                    'operacion' => 'transferencia', 'id_comercio' => 1, 'id_variacion' => 501, 'ubicaciones' => [1, 2], 'cantidad' => 5,
                    'id_autorizador' => 2, 'fecha_autorizacion' => '2026-11-10T09:00:00-06:00', 'vence' => '2026-12-31T23:59:59-06:00',
                ],
                'autorizacion-transferencia-c1-0004' => [
                    'operacion' => 'transferencia', 'id_comercio' => 1, 'id_variacion' => 501, 'ubicaciones' => [2, 1], 'cantidad' => 1,
                    'id_autorizador' => 2, 'fecha_autorizacion' => '2026-11-10T09:00:00-06:00', 'vence' => '2026-12-31T23:59:59-06:00',
                ],
                'autorizacion-transferencia-c1-0005' => [
                    'operacion' => 'transferencia', 'id_comercio' => 1, 'id_variacion' => 501, 'ubicaciones' => [2, 1], 'cantidad' => 2,
                    'id_autorizador' => 2, 'fecha_autorizacion' => '2026-11-10T09:00:00-06:00', 'vence' => '2026-12-31T23:59:59-06:00',
                ],
                'autorizacion-transferencia-c1-0006' => [
                    'operacion' => 'transferencia', 'id_comercio' => 1, 'id_variacion' => 502, 'ubicaciones' => [1, 2], 'cantidad' => 1,
                    'id_autorizador' => 2, 'fecha_autorizacion' => '2026-11-10T09:00:00-06:00', 'vence' => '2026-12-31T23:59:59-06:00',
                ],
                'autorizacion-transferencia-c1-0007' => [
                    'operacion' => 'transferencia', 'id_comercio' => 1, 'id_variacion' => 502, 'ubicaciones' => [1, 2], 'cantidad' => 2,
                    'id_autorizador' => 2, 'fecha_autorizacion' => '2026-11-10T09:00:00-06:00', 'vence' => '2026-12-31T23:59:59-06:00',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dependencias y proveedores de M5 Carrito y Pedidos
    |--------------------------------------------------------------------------
    |
    | Para cada proveedor externo (M1, M2, M3, M4, M6), 'modo' elige entre el
    | doble de prueba ('doble') y el servicio real ('real').
    | Ver M5IntegracionesServiceProvider.
    |
    */

    'm5' => [
        'm1' => [
            'modo' => env('M5_M1_MODO', env('APP_ENV') === 'testing' ? 'doble' : 'real'),
            'doble' => M5PortalStub::class,
        ],
        'm2' => [
            'modo' => env('M5_M2_MODO', env('APP_ENV') === 'testing' ? 'doble' : 'real'),
            'doble' => M5ComerciosStub::class,
        ],
        'm3' => [
            'modo' => env('M5_M3_MODO', env('APP_ENV') === 'testing' ? 'doble' : 'real'),
            'doble' => M5CatalogoStub::class,
        ],
        'm4' => [
            'modo' => env('M5_M4_MODO', env('APP_ENV') === 'testing' ? 'doble' : 'real'),
            'doble' => M5InventarioStub::class,
        ],
        'm6' => [
            'modo' => env('M5_M6_MODO', 'doble'),
            'doble' => M5PagosStub::class,
        ],
    ],

];
