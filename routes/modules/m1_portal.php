<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M1 - Portal Público y Búsqueda
|--------------------------------------------------------------------------
| Prefijo: /m1
| Nombre de ruta: m1.*
| Subgrupo 1 (SG1)
*/

Route::get('/', function () {
    return response()->json(['module' => 'M1_Portal', 'status' => 'online']);
})->name('index');
