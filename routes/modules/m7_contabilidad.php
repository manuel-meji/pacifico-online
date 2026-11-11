<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\M7_Contabilidad\IntegracionContableController;

/*
|--------------------------------------------------------------------------
| M7 - ERP Contable y Reportes Gerenciales
|--------------------------------------------------------------------------
| Prefijo: /m7
| Nombre de ruta: m7.*
| Subgrupo 7 (SG7)
*/

Route::get(
    'pagos/{idPago}/registro-contable',
    [IntegracionContableController::class, 'consultarRegistroContablePago']
)->name('pagos.registro-contable');

Route::get('/', function () {
    return response()->json(['module' => 'M7_Contabilidad', 'status' => 'online']);
})->name('index');
