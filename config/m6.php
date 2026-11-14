<?php

/*
|--------------------------------------------------------------------------
| M6 - Pagos y Facturación Electrónica
|--------------------------------------------------------------------------
| Variables opcionales en .env (todas tienen un valor por defecto seguro
| para desarrollo local):
|
|   M6_USAR_DOBLES=true                  Dobles de M1/M2/M5/M7 (tests/Doubles) o adaptadores reales.
|   M6_CONFIRMACION_INMEDIATA=true       El simulador "envía" el webhook en cuanto aprueba el cobro.
|   M6_WEBHOOK_SECRETO=...               Secreto HMAC compartido con la pasarela simulada.
|   M6_HACIENDA_RESPUESTA=aceptada       Respuesta del simulador de Hacienda (aceptada, rechazada,
|                                        enviada, no_disponible).
*/

return [

    'usar_dobles' => (bool) env('M6_USAR_DOBLES', true),
   // Sobrescribe M6_USAR_DOBLES por módulo. Si la variable no existe vale null y se usa la global.
       // M1, M2 y M7 usan el módulo real por defecto (integración del PC4). En las pruebas,
    // phpunit.xml fuerza M6_M1_DOBLE, M6_M2_DOBLE y M6_M7_DOBLE a (null) para que sigan usando los dobles.
       'doble_por_modulo' => [
           'm1' => env('M6_M1_DOBLE', false),
           'm2' => env('M6_M2_DOBLE', false),
           'm5' => env('M6_M5_DOBLE'),
        'm7' => env('M6_M7_DOBLE', false),
    ],

    'confirmacion_inmediata' => (bool) env('M6_CONFIRMACION_INMEDIATA', true),
    'url_frontend' => env('M6_URL_FRONTEND', 'http://localhost:5173'),

    'webhook' => [
        // Cabecera con la firma: hash_hmac('sha256', <cuerpo crudo>, secreto).
        'cabecera_firma' => 'X-Firma-Pasarela',
        'secreto' => env('M6_WEBHOOK_SECRETO', 'm6-secreto-solo-desarrollo'),
    ],

    // Segundos que se espera el candado por pedido antes de responder "pago activo".
    'espera_candado_segundos' => 5,

    // Simulador de Hacienda (CU-004). Ver App\Services\M6_Pagos\Simuladores\HaciendaSimulador.
    'hacienda' => [
        'respuesta' => env('M6_HACIENDA_RESPUESTA', 'aceptada'),
    ],
];
