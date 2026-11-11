<?php

use App\Http\Controllers\M3_Catalogo\CatalogoConsultaController;
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
    Route::get('/categorias', [CatalogoConsultaController::class, 'categorias'])
        ->name('categorias.index');

    Route::get('/marcas', [CatalogoConsultaController::class, 'marcas'])
        ->name('marcas.index');

    Route::get('/productos', [CatalogoConsultaController::class, 'buscar'])
        ->name('productos.index');

    Route::get('/productos/{id_producto}', [ProductoController::class, 'show'])
        ->whereNumber('id_producto')
        ->name('productos.show');

    Route::get('/productos/{id_producto}/relacionados', [CatalogoConsultaController::class, 'relacionados'])
        ->whereNumber('id_producto')
        ->name('productos.relacionados');

    Route::get('/variaciones/{id_variacion}', [VariacionController::class, 'show'])
        ->whereNumber('id_variacion')
        ->name('variaciones.show');
});
