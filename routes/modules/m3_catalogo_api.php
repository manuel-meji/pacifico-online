<?php

use App\Http\Controllers\M3_Catalogo\AltaCatalogoController;
use App\Http\Controllers\M3_Catalogo\CatalogoConsultaController;
use App\Http\Controllers\M3_Catalogo\ProductoController;
use App\Http\Controllers\M3_Catalogo\VariacionController;
use App\Http\Middleware\EstablecerComercio;
use App\Http\Middleware\RequiereRol;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M3 — API del contrato openapi_M3_v1.3.0 (contracts/m3-catalogo/v1)
|--------------------------------------------------------------------------
| Prefijo /api/m3/v1 (el servidor que publica el contrato), nombre
| api.m3.v1.*, grupo "api" (sesión de Sanctum para el frontend React).
| La carga App\Providers\M3IntegracionesServiceProvider, igual que M2 y M4.
|
| Las rutas /m3/v1 de routes/modules/m3_catalogo.php se conservan: son las
| mismas lecturas con el prefijo anterior.
|
| Públicas: las seis lecturas (security: [] en el contrato). Con sesión del
| núcleo: las escrituras, con auth:sanctum + EstablecerComercio (deja el
| comercio en ContextoComercio, RN-12) + RequiereRol (Encargado del comercio).
*/

Route::prefix('api/m3/v1')
    ->middleware('api')
    ->name('api.m3.v1.')
    ->group(function (): void {
        Route::get('categorias', [CatalogoConsultaController::class, 'categorias'])->name('categorias.index');
        Route::get('marcas', [CatalogoConsultaController::class, 'marcas'])->name('marcas.index');
        Route::get('productos', [CatalogoConsultaController::class, 'buscar'])->name('productos.index');
        Route::get('productos/{id_producto}', [ProductoController::class, 'show'])
            ->whereNumber('id_producto')
            ->name('productos.show');
        Route::get('productos/{id_producto}/relacionados', [CatalogoConsultaController::class, 'relacionados'])
            ->whereNumber('id_producto')
            ->name('productos.relacionados');
        Route::get('variaciones/{id_variacion}', [VariacionController::class, 'show'])
            ->whereNumber('id_variacion')
            ->name('variaciones.show');

        // Alta del catálogo. El comercio sale de la sesión, nunca del cuerpo (RN-12).
        Route::middleware(['auth:sanctum', EstablecerComercio::class, RequiereRol::class.':encargado_comercio'])
            ->group(function (): void {
                Route::post('categorias', [AltaCatalogoController::class, 'crearCategoria'])->name('categorias.store');
                Route::post('marcas', [AltaCatalogoController::class, 'crearMarca'])->name('marcas.store');
                Route::post('productos', [AltaCatalogoController::class, 'crearProducto'])->name('productos.store');
                Route::patch('productos/{id_producto}', [AltaCatalogoController::class, 'actualizarProducto'])
                    ->whereNumber('id_producto')
                    ->name('productos.update');
                Route::post('productos/{id_producto}/variaciones', [AltaCatalogoController::class, 'crearVariacion'])
                    ->whereNumber('id_producto')
                    ->name('variaciones.store');
                Route::patch('variaciones/{id_variacion}', [VariacionController::class, 'updateDisponibilidad'])
                    ->whereNumber('id_variacion')
                    ->name('variaciones.update');
                Route::post('productos/{id_producto}/imagenes', [AltaCatalogoController::class, 'agregarImagen'])
                    ->whereNumber('id_producto')
                    ->name('imagenes.store');
                Route::post('productos/{id_producto}/relacionados', [AltaCatalogoController::class, 'asociarRelacionado'])
                    ->whereNumber('id_producto')
                    ->name('relacionados.store');
            });
    });
