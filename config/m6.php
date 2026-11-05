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
*/

return [

    'usar_dobles' => (bool) env('M6_USAR_DOBLES', true),

    'confirmacion_inmediata' => (bool) env('M6_CONFIRMACION_INMEDIATA', true),

    'webhook' => [
        // Cabecera con la firma: hash_hmac('sha256', <cuerpo crudo>, secreto).
        'cabecera_firma' => 'X-Firma-Pasarela',
        'secreto' => env('M6_WEBHOOK_SECRETO', 'm6-secreto-solo-desarrollo'),
    ],

    // Segundos que se espera el candado por pedido antes de responder "pago activo".
    'espera_candado_segundos' => 5,
];
