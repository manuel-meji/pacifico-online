<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M2 - Micrositios y Administración de Comercios
|--------------------------------------------------------------------------
| Prefijo: /m2
| Nombre de ruta: m2.*
| Subgrupo 2 (SG2)
*/

Route::get('/', function () {
    return response()->json(['module' => 'M2_Comercios', 'status' => 'online']);
})->name('index');
