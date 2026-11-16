<?php

use App\Http\Controllers\M5_Pedidos\ControladorDevoluciones;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M5 - Devoluciones de subpedidos (RF-019, RF-020, RF-021)
|--------------------------------------------------------------------------
| Lo monta M5DevolucionesServiceProvider con los mismos prefijos que m5_pedidos.php
| (api/m5/v1, api/v1 y m5), de modo que las rutas existentes no se tocan.
*/

// Cliente (RF-019)
Route::post('/subpedidos/{id_subpedido}/devoluciones', [ControladorDevoluciones::class, 'solicitar'])->name('devoluciones.solicitar');
Route::get('/subpedidos/{id_subpedido}/devoluciones', [ControladorDevoluciones::class, 'porSubpedido'])->name('devoluciones.por_subpedido');

// Consulta (cliente o comercio)
Route::get('/devoluciones', [ControladorDevoluciones::class, 'listar'])->name('devoluciones.listar');
Route::get('/devoluciones/{id_devolucion}', [ControladorDevoluciones::class, 'mostrar'])->name('devoluciones.mostrar');
Route::get('/devoluciones/{id_devolucion}/evidencias/{id_evidencia}', [ControladorDevoluciones::class, 'descargarEvidencia'])->name('devoluciones.evidencia');

// Comercio (RF-020) y procesamiento (RF-021)
Route::post('/devoluciones/{id_devolucion}/aprobar', [ControladorDevoluciones::class, 'aprobar'])->name('devoluciones.aprobar');
Route::post('/devoluciones/{id_devolucion}/rechazar', [ControladorDevoluciones::class, 'rechazar'])->name('devoluciones.rechazar');
Route::post('/devoluciones/{id_devolucion}/reintentar-reembolso', [ControladorDevoluciones::class, 'reintentarReembolso'])->name('devoluciones.reintentar_reembolso');
