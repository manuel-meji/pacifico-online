<?php

use App\Http\Controllers\M7_Contabilidad\ConsultasOperativasController;
use App\Http\Controllers\M7_Contabilidad\LibroDiarioController;
use App\Http\Controllers\M7_Contabilidad\LibroMayorController;
use App\Http\Controllers\M7_Contabilidad\PlanCuentasController;
use App\Http\Controllers\M7_Contabilidad\ReporteGerencialController;
use App\Http\Controllers\M7_Contabilidad\BalanceGeneralController;
use App\Http\Middleware\EstablecerComercio;
use App\Http\Middleware\RequiereRol;
use Illuminate\Support\Facades\Route;


Route::prefix('api/m7/v1')
    ->middleware('api')
    ->name('api.m7.v1.')
    ->group(function (): void {

        Route::middleware([
            'auth:sanctum',
            EstablecerComercio::class,
            RequiereRol::class.':encargado_comercio,encargado_plataforma',
        ])->group(function (): void {
            Route::get('comercios', [ConsultasOperativasController::class, 'comercios'])
                ->middleware(RequiereRol::class.':encargado_plataforma')
                ->name('comercios');
            Route::get('saldos-cobrar-pagar', [ConsultasOperativasController::class, 'saldos'])
                ->name('saldos-cobrar-pagar');
            Route::get('centros-costo', [ConsultasOperativasController::class, 'centros'])
                ->name('centros-costo');
            Route::get('centros-costo/sin-asignar/movimientos', [ConsultasOperativasController::class, 'movimientosSinCentro'])
                ->name('centros-costo.sin-asignar.movimientos');
            Route::get('centros-costo/{idCentroCosto}/movimientos', [ConsultasOperativasController::class, 'movimientosCentro'])
                ->whereNumber('idCentroCosto')
                ->name('centros-costo.movimientos');
            Route::get('asientos/{idAsiento}', [ConsultasOperativasController::class, 'asiento'])
                ->whereNumber('idAsiento')
                ->name('asientos.show');
            Route::get('cuentas', [PlanCuentasController::class, 'listar'])
                ->name('cuentas.index');

            Route::get('cuentas/{idCuentaContable}', [PlanCuentasController::class, 'consultar'])
                ->whereNumber('idCuentaContable')
                ->name('cuentas.show');

            Route::get('libro-diario', [LibroDiarioController::class, 'listar'])
                ->name('libro-diario');
            Route::get('libro-mayor/cuentas', [LibroMayorController::class, 'listarCuentas'])
                ->name('libro-mayor.cuentas');

            Route::get('libro-mayor', [LibroMayorController::class, 'consultar'])
                ->name('libro-mayor');

             Route::get('reporte-gerencial', [ReporteGerencialController::class, 'consultar'])
                ->name('reporte-gerencial');

            Route::get('balance-general', [BalanceGeneralController::class, 'consultar'])
                ->name('balance-general');
        });
    });

