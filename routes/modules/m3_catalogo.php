
<?php

use App\Http\Controllers\M3_Catalogo\ProductoController;
use App\Http\Controllers\M3_Catalogo\VariacionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M3 - Catálogo de Productos
|--------------------------------------------------------------------------
| Prefijo: /api/m3/v1
| Nombre de ruta: api.m3.v1.*
| Subgrupo 3 (SG3)
*/

Route::get('/', function () {
    return response()->json([
        'module' => 'M3_Catalogo',
        'status' => 'online'
    ]);
})->name('index');

// Consulta individual de productos
Route::get('/productos/{id_producto}', [ProductoController::class, 'show'])
    ->whereNumber('id_producto')
    ->name('productos.show');

// Consulta individual de variaciones
Route::get('/variaciones/{id_variacion}', [VariacionController::class, 'show'])
    ->whereNumber('id_variacion')
    ->name('variaciones.show');
