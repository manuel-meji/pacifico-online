<?php

use App\Http\Controllers\M4_Inventario\EstadoController;
use App\Http\Controllers\M4_Inventario\ExistenciaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M4 — API del contrato openapi_M4_v1.3.0 (contracts/m4-inventario/v1)
|--------------------------------------------------------------------------
| Prefijo /api/m4/v1, nombre api.m4.v1.*, grupo "api" (sesión de Sanctum para
| el frontend React). La carga App\Providers\M4IntegracionesServiceProvider.
| Los controladores solo llaman a InventarioModuleInterface.
|
| Públicas: estado y disponibilidad (M1, portal). Con sesión del núcleo:
| auth:sanctum + EstablecerComercio (deja el comercio en ContextoComercio para
| el Global Scope, RN-12) + RequiereRol.
*/

Route::prefix('api/m4/v1')
    ->middleware('api')
    ->name('api.m4.v1.')
    ->group(function (): void {
        Route::get('health', EstadoController::class)->name('health');

        Route::get('existencias/disponibilidad', [ExistenciaController::class, 'disponibilidad'])->name('disponibilidad');
        Route::post('existencias/disponibilidad/lote', [ExistenciaController::class, 'disponibilidadLote'])->name('disponibilidad.lote');
        Route::get('existencias/disponibilidad/producto', [ExistenciaController::class, 'disponibilidadProducto'])->name('disponibilidad.producto');
        Route::post('existencias/disponibilidad/producto/lote', [ExistenciaController::class, 'disponibilidadProductoLote'])->name('disponibilidad.producto.lote');
    });
