<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M7 - ERP Contable y Reportes Gerenciales
|--------------------------------------------------------------------------
| Prefijo: /m7
| Nombre de ruta: m7.*
| Subgrupo 7 (SG7)
*/

Route::get('/', function () {
    return response()->json(['module' => 'M7_Contabilidad', 'status' => 'online']);
})->name('index');
