<?php

use App\Http\Controllers\M1_Portal\BusquedaController;
use App\Http\Controllers\M1_Portal\FichaProductoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M1 - Portal Público y Búsqueda: API del contrato OpenAPI v1.1.0
|--------------------------------------------------------------------------
| Prefijo: /api/m1/v1
| Nombre de ruta: api.m1.v1.*
| Subgrupo 1 (SG1). Rutas públicas (security: [] en el contrato).
*/

Route::get('/productos/busqueda', [BusquedaController::class, 'buscar'])->name('productos.busqueda');

Route::get('/productos/{id_producto}', [FichaProductoController::class, 'mostrar'])
    ->where('id_producto', '[1-9][0-9]*')
    ->name('productos.ficha');

Route::get('/categorias', [BusquedaController::class, 'listarCategorias'])->name('categorias');
