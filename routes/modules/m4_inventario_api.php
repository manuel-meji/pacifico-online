<?php

use App\Http\Controllers\M4_Inventario\AjusteController;
use App\Http\Controllers\M4_Inventario\AuditoriaController;
use App\Http\Controllers\M4_Inventario\EstadoController;
use App\Http\Controllers\M4_Inventario\ExistenciaController;
use App\Http\Controllers\M4_Inventario\KardexController;
use App\Http\Controllers\M4_Inventario\MovimientoController;
use App\Http\Controllers\M4_Inventario\ReservaController;
use App\Http\Middleware\EstablecerComercio;
use App\Http\Middleware\RequiereRol;
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

        // Panel del comercio. El Encargado de plataforma entra con acceso auditado (núcleo).
        Route::middleware(['auth:sanctum', EstablecerComercio::class, RequiereRol::class.':encargado_comercio,encargado_plataforma'])
            ->group(function (): void {
                Route::get('existencias', [ExistenciaController::class, 'listar'])->name('existencias');
                Route::put('existencias/{id_existencia}/stock-minimo', [ExistenciaController::class, 'fijarStockMinimo'])
                    ->whereNumber('id_existencia')->name('existencias.stock_minimo');
                Route::post('movimientos', [MovimientoController::class, 'registrar'])->name('movimientos');
                Route::post('ajustes', [AjusteController::class, 'registrar'])->name('ajustes');
                Route::get('kardex', [KardexController::class, 'consultar'])->name('kardex');
                Route::get('kardex/valorizado', [KardexController::class, 'valorizado'])->name('kardex.valorizado');
                Route::post('reservas', [ReservaController::class, 'reservar'])->name('reservas');
                Route::get('reservas/{id_reserva}', [ReservaController::class, 'mostrar'])->name('reservas.mostrar');
                Route::post('reservas/{id_reserva}/confirmar', [ReservaController::class, 'confirmar'])->name('reservas.confirmar');
                Route::post('reservas/{id_reserva}/liberar', [ReservaController::class, 'liberar'])->name('reservas.liberar');
                Route::post('reservas/{id_reserva}/salida', [ReservaController::class, 'registrarSalida'])->name('reservas.salida');
                Route::post('reservas/{id_reserva}/reingreso', [ReservaController::class, 'registrarReingreso'])->name('reservas.reingreso');
                Route::get('subpedidos/{id_subpedido}/costos-productos-vendidos', [KardexController::class, 'costosProductosVendidos'])
                    ->whereNumber('id_subpedido')->name('subpedidos.costos');
            });

        // Soporte/Auditoría (RN-M4-015): el núcleo concede el acceso auditado al Encargado de plataforma.
        Route::middleware(['auth:sanctum', EstablecerComercio::class, RequiereRol::class.':encargado_plataforma'])
            ->get('auditoria/inventario', [AuditoriaController::class, 'consultar'])
            ->name('auditoria');
    });
