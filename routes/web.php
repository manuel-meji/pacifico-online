<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Pacíficos Online
| IF6100 - Análisis y Diseño de Sistemas (II Ciclo 2026)
|--------------------------------------------------------------------------
|
| Las rutas de cada módulo se definen en su propio archivo dentro de
| routes/modules/ y se cargan con su prefijo canónico correspondiente.
|
*/

Route::get('/', function () {
    return view('welcome');
});

// M1: Portal Público y Búsqueda
Route::prefix('m1')
    ->name('m1.')
    ->group(base_path('routes/modules/m1_portal.php'));

// M2: Micrositios y Administración de Comercios
Route::prefix('m2')
    ->name('m2.')
    ->group(base_path('routes/modules/m2_comercios.php'));

// M3: Catálogo de Productos
Route::prefix('m3')
    ->name('m3.')
    ->group(base_path('routes/modules/m3_catalogo.php'));

// M4: Inventario Multiempresa
Route::prefix('m4')
    ->name('m4.')
    ->group(base_path('routes/modules/m4_inventario.php'));

// M5: Carrito y Gestión de Pedidos
Route::prefix('m5')
    ->name('m5.')
    ->group(base_path('routes/modules/m5_pedidos.php'));

// M6: Pagos y Facturación Electrónica
Route::prefix('m6')
    ->name('m6.')
    ->group(base_path('routes/modules/m6_pagos.php'));

// M7: ERP Contable y Reportes Gerenciales
Route::prefix('m7')
    ->name('m7.')
    ->group(base_path('routes/modules/m7_contabilidad.php'));

// Núcleo Compartido
Route::prefix('nucleo')
    ->name('nucleo.')
    ->group(base_path('routes/modules/nucleo.php'));
