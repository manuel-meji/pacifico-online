<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M6 - Pagos y Facturación Electrónica
|--------------------------------------------------------------------------
| Prefijo: /m6
| Nombre de ruta: m6.*
| Subgrupo 6 (SG6)
*/

Route::get('/', function () {
    return response()->json(['module' => 'M6_Pagos', 'status' => 'online']);
})->name('index');
