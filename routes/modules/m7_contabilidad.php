<?php

use App\Http\Controllers\M7_Contabilidad\IntegracionContableController;
use App\Http\Middleware\EstablecerComercio;
use App\Http\Middleware\RequiereRol;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M7 - ERP Contable y Reportes Gerenciales
|--------------------------------------------------------------------------
| Prefijo: /m7
| Nombre de ruta: m7.*
| Subgrupo 7 (SG7)
*/

Route::middleware([
    'auth:sanctum',
    EstablecerComercio::class,
    RequiereRol::class.':encargado_comercio,encargado_plataforma',
])->group(function (): void {
    Route::get(
        'pagos/{idPago}/registro-contable',
        [IntegracionContableController::class, 'consultarRegistroContablePago']
    )->whereNumber('idPago')
        ->name('pagos.registro-contable');
});

Route::get('/', function () {
    return response()->json(['module' => 'M7_Contabilidad', 'status' => 'online']);
})->name('index');
