<?php

use App\Http\Controllers\M7_Contabilidad\LibroDiarioController;
use App\Http\Controllers\M7_Contabilidad\LibroMayorController;
use App\Http\Controllers\M7_Contabilidad\ReporteGerencialController;
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
            RequiereRol::class . ':encargado_comercio,encargado_plataforma',
        ])->group(function (): void {

            Route::get('libro-diario', [LibroDiarioController::class, 'listar'])
                ->name('libro-diario');
            Route::get('libro-mayor/cuentas', [LibroMayorController::class, 'listarCuentas'])
                ->name('libro-mayor.cuentas');

            Route::get('libro-mayor', [LibroMayorController::class, 'consultar'])
                ->name('libro-mayor');

            Route::get('reporte-gerencial', [ ReporteGerencialController::class, 'consultar',])
        ->name('reporte-gerencial');            });
    });