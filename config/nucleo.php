<?php

// Núcleo compartido (Comité de Arquitectura e Integración)

return [

    /*
    |--------------------------------------------------------------------------
    | Cuentas del escenario de demostración
    |--------------------------------------------------------------------------
    |
    | Contraseña de las cuentas que crea Database\Seeders\Nucleo\CuentasDemoSeeder.
    | Cada persona la define en su .env (DEMO_CONTRASENA) y no se sube al repositorio.
    | No tiene valor por defecto: sin ella el seeder se detiene.
    |
    */

    'demo' => [
        'contrasena' => env('DEMO_CONTRASENA'),
    ],

];
