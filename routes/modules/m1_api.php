<?php

use App\Http\Controllers\M1_Portal\BusquedaController;
use App\Http\Controllers\M1_Portal\ClienteController;
use App\Http\Controllers\M1_Portal\DireccionClienteController;
use App\Http\Controllers\M1_Portal\FichaProductoController;
use App\Http\Middleware\EstablecerCliente;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M1 - Portal Público y Búsqueda: API del contrato OpenAPI v1.2.0
|--------------------------------------------------------------------------
| Prefijo: /api/m1/v1
| Nombre de ruta: api.m1.v1.*
| Subgrupo 1 (SG1).
|
| Aislamiento del núcleo (tarea 23 del PC4, RN-12, ADR-006): EstablecerCliente
| fija ContextoCliente con el cliente de la sesión, usando ResolvedorCliente de
| M1. En la búsqueda y la ficha la sesión es opcional (security: [] en el
| contrato); si hay sesión de cliente, la búsqueda se registra con su
| id_cliente.
*/

Route::middleware(EstablecerCliente::class)->group(function (): void {
    Route::get('/productos/busqueda', [BusquedaController::class, 'buscar'])->name('productos.busqueda');

    Route::get('/productos/{id_producto}', [FichaProductoController::class, 'mostrar'])
        ->where('id_producto', '[1-9][0-9]*')
        ->name('productos.ficha');
});

Route::get('/categorias', [BusquedaController::class, 'listarCategorias'])->name('categorias');

// CU-010 Registrar cliente. Pública (autorregistro); máximo 10 intentos por
// minuto por IP para frenar registros masivos y pruebas de correos.
Route::post('/clientes', [ClienteController::class, 'registrar'])
    ->middleware('throttle:10,1')
    ->name('clientes.registrar');

// Datos de la persona compradora. Requieren la sesión del núcleo, que fija el
// ámbito de cliente; cada persona solo ve y edita sus propios datos
// (RN-M1-16, ERR-003 en cualquier otro caso).
Route::middleware(['auth:sanctum', EstablecerCliente::class])->group(function (): void {
    Route::get('/clientes/sesion', [ClienteController::class, 'sesion'])->name('clientes.sesion');

    Route::get('/clientes/{id_cliente}', [ClienteController::class, 'mostrar'])
        ->where('id_cliente', '[1-9][0-9]*')
        ->name('clientes.mostrar');

    Route::put('/clientes/{id_cliente}', [ClienteController::class, 'actualizar'])
        ->where('id_cliente', '[1-9][0-9]*')
        ->name('clientes.actualizar');

    // Direcciones de envío del cliente (tarea 17).
    Route::get('/clientes/{id_cliente}/direcciones', [DireccionClienteController::class, 'listar'])
        ->where('id_cliente', '[1-9][0-9]*')
        ->name('clientes.direcciones.listar');

    Route::post('/clientes/{id_cliente}/direcciones', [DireccionClienteController::class, 'agregar'])
        ->where('id_cliente', '[1-9][0-9]*')
        ->name('clientes.direcciones.agregar');

    Route::put('/clientes/{id_cliente}/direcciones/{id_direccion_cliente}', [DireccionClienteController::class, 'actualizar'])
        ->where(['id_cliente' => '[1-9][0-9]*', 'id_direccion_cliente' => '[1-9][0-9]*'])
        ->name('clientes.direcciones.actualizar');
});
