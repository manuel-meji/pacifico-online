<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M3 - Catálogo de Productos
|--------------------------------------------------------------------------
| Prefijo: /m3
| Nombre de ruta: m3.*
| Subgrupo 3 (SG3)
*/

Route::get('/', function () {
    return response()->json(['module' => 'M3_Catalogo', 'status' => 'online']);
})->name('index');
