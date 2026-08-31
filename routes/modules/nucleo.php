<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Núcleo Compartido
|--------------------------------------------------------------------------
| Prefijo: /nucleo
| Nombre de ruta: nucleo.*
| Administrado por: Comité de Arquitectura e Integración
*/

Route::get('/', function () {
    return response()->json(['module' => 'Nucleo', 'status' => 'online']);
})->name('index');
