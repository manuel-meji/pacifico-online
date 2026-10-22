<?php

use App\Http\Controllers\M5_Pedidos\ControladorCarrito;
use App\Http\Controllers\M5_Pedidos\ControladorPedido;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M5 - Carrito y Gestión de Pedidos
|--------------------------------------------------------------------------
| Prefijo: /m5
| Nombre de ruta: m5.*
| Subgrupo 5 (SG5)
*/

Route::get('/', function () {
    return response()->json(['module' => 'M5_Pedidos', 'status' => 'online']);
})->name('index');

// Carrito
Route::get('/carrito', [ControladorCarrito::class, 'obtenerCarrito'])->name('carrito.obtener');
Route::delete('/carrito', [ControladorCarrito::class, 'vaciarCarrito'])->name('carrito.vaciar');
Route::post('/carrito/items', [ControladorCarrito::class, 'agregarAlCarrito'])->name('carrito.agregar');
Route::put('/carrito/items/{id_item_carrito}', [ControladorCarrito::class, 'actualizarCantidad'])->name('carrito.actualizar');
Route::delete('/carrito/items/{id_item_carrito}', [ControladorCarrito::class, 'eliminarItem'])->name('carrito.eliminar');

// Pedidos
Route::get('/pedidos', [ControladorPedido::class, 'listarPedidos'])->name('pedidos.listar');
Route::post('/pedidos', [ControladorPedido::class, 'procesarCheckout'])->name('pedidos.checkout');
Route::get('/pedidos/{id_pedido}', [ControladorPedido::class, 'obtenerPedido'])->name('pedidos.detalle');
Route::post('/pedidos/{id_pedido}/cancelar', [ControladorPedido::class, 'cancelarPedidoTotal'])->name('pedidos.cancelar');
Route::post('/pedidos/{id_pedido}/pago-confirmado', [ControladorPedido::class, 'registrarPagoConfirmado'])->name('pedidos.pago_confirmado');

// Subpedidos
Route::get('/subpedidos/{id_subpedido}', [ControladorPedido::class, 'obtenerSubpedido'])->name('subpedidos.detalle');
Route::post('/subpedidos/{id_subpedido}/despachar', [ControladorPedido::class, 'despacharSubpedido'])->name('subpedidos.despachar');
Route::post('/subpedidos/{id_subpedido}/entregar', [ControladorPedido::class, 'confirmarEntregaSubpedido'])->name('subpedidos.entregar');
Route::post('/subpedidos/{id_subpedido}/cancelar', [ControladorPedido::class, 'cancelarSubpedido'])->name('subpedidos.cancelar');
