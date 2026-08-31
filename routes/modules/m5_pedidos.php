<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M5 - Carrito y Gestión de Pedidos
|--------------------------------------------------------------------------
| Prefijo: /m5
| Nombre de ruta: m5.*
| Subgrupo 5 (SG5)
*/

Route::get('/', function () {
    return response()->json(['module' => 'M5_Pedidos', 'status' => 'online']);
})->name('index');
