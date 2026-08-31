<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M4 - Inventario Multiempresa
|--------------------------------------------------------------------------
| Prefijo: /m4
| Nombre de ruta: m4.*
| Subgrupo 4 (SG4)
*/

Route::get('/', function () {
    return response()->json(['module' => 'M4_Inventario', 'status' => 'online']);
})->name('index');
