<?php

use App\Http\Controllers\M3_Catalogo\ProductoController;
use App\Http\Controllers\M3_Catalogo\VariacionController;
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

// Contrato OpenAPI M3 v1.3.0
Route::prefix('v1')->group(function () {
    Route::get('/productos/{id_producto}', [ProductoController::class, 'show'])
        ->whereNumber('id_producto')
        ->name('productos.show');

    Route::get('/variaciones/{id_variacion}', [VariacionController::class, 'show'])
        ->whereNumber('id_variacion')
        ->name('variaciones.show');
});