<?php

use App\Http\Controllers\M6_Pagos\ConciliacionController;
use App\Http\Controllers\M6_Pagos\ConfirmacionPagoController;
use App\Http\Controllers\M6_Pagos\FacturasController;
use App\Http\Controllers\M6_Pagos\HistorialController;
use App\Http\Controllers\M6_Pagos\NotasAjusteController;
use App\Http\Controllers\M6_Pagos\PagosController;
use App\Http\Controllers\M6_Pagos\ReembolsosController;
use App\Http\Middleware\M6_Pagos\VerificarFirmaPasarela;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M6 — API del contrato OpenAPI (contracts/m6-pagos/v1/openapi.yaml)
|--------------------------------------------------------------------------
| Prefijo /api/m6/v1, grupo "api" (sin CSRF), mismo patrón que M1, M2 y M5.
| Las carga App\Providers\M6_PagosServiceProvider. Las rutas web del módulo
| siguen en routes/modules/m6_pagos.php (/m6).
| Subgrupo 6 (SG6).
*/

Route::prefix('api/m6/v1')
    ->middleware('api')
    ->name('api.m6.v1.')
    ->group(function (): void {
        Route::get('health', fn () => response()->json(['status' => 'ok', 'module' => 'm6-pagos']))
            ->name('health');

        // CU-001 Procesar pago y consultar su estado.
        Route::post('pagos', [PagosController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('pagos.procesar');

        Route::get('pagos/{id_pago}', [PagosController::class, 'show'])
            ->whereNumber('id_pago')
            ->name('pagos.consultar');

        // CU-002 Webhook de la pasarela (firmado con HMAC, idempotente).
        Route::post('pagos/{id_pago}/confirmaciones', ConfirmacionPagoController::class)
            ->whereNumber('id_pago')
            ->middleware(VerificarFirmaPasarela::class)
            ->name('pagos.confirmaciones');

        // CU-004 Facturas electrónicas (una por subpedido). Se generan solas al confirmar el pago.
        Route::get('facturas', [FacturasController::class, 'index'])
            ->name('facturas.listar');

        Route::get('facturas/{id_factura}', [FacturasController::class, 'show'])
            ->whereNumber('id_factura')
            ->name('facturas.consultar');

        // CU-005 y CU-006 Notas de crédito y de débito sobre una factura.
        Route::post('facturas/{id_factura}/notas-credito', [NotasAjusteController::class, 'credito'])
            ->whereNumber('id_factura')
            ->middleware('throttle:60,1')
            ->name('facturas.notas-credito');

        Route::post('facturas/{id_factura}/notas-debito', [NotasAjusteController::class, 'debito'])
            ->whereNumber('id_factura')
            ->middleware('throttle:60,1')
            ->name('facturas.notas-debito');

        // CU-007 Reembolsos (un reembolso por subpedido, cada uno con su nota de crédito).
        Route::post('pedidos/{id_pedido}/reembolsos', [ReembolsosController::class, 'store'])
            ->whereNumber('id_pedido')
            ->middleware('throttle:60,1')
            ->name('pedidos.reembolsos');

        // CU-008 Historial de pagos y facturas (aislado por cliente y por comercio).
        Route::get('historial', HistorialController::class)
            ->name('historial');

        // CU-003 Conciliación de pagos del período.
        Route::post('conciliaciones', ConciliacionController::class)
            ->middleware('throttle:60,1')
            ->name('conciliaciones');
    });
