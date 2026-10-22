<?php

namespace Tests\Feature\M5_Pedidos;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use Tests\TestCase;

class ControladoresM5Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', [
            '--path' => 'database/migrations/M5_Pedidos',
            '--realpath' => false,
        ]);
    }

    private function configurarMocks(?int $stock = 10, bool $comercioActivo = true): void
    {
        $mockM3 = $this->createStub(CatalogoServiceInterface::class);
        $mockM3->method('obtenerVariacion')->willReturnCallback(function ($id) {
            return [
                'id_variacion' => $id,
                'nombre' => 'Producto Variación '.$id,
                'precio' => 10000.00,
                'tarifa_impuesto' => 13.00,
                'id_comercio' => 1,
            ];
        });
        $this->app->instance(CatalogoServiceInterface::class, $mockM3);

        $mockM4 = $this->createStub(InventarioServiceInterface::class);
        $mockM4->method('consultarExistencias')->willReturn($stock);
        $mockM4->method('solicitarReserva')->willReturn([
            'id_reserva' => 'reserva-uuid-test',
            'expiracion' => now()->addMinutes(15),
        ]);
        $this->app->instance(InventarioServiceInterface::class, $mockM4);

        $mockM2 = $this->createStub(ComerciosServiceInterface::class);
        $mockM2->method('obtenerMicrositio')->willReturn([
            'id_comercio' => 1,
            'nombre' => 'Comercio Uno',
            'estado' => $comercioActivo ? 'activo' : 'suspendido',
        ]);
        $this->app->instance(ComerciosServiceInterface::class, $mockM2);

        $mockM6 = $this->createStub(PagosServiceInterface::class);
        $mockM6->method('procesarPago')->willReturn([
            'urlPasarelaPago' => 'https://pago.pacificos.com/checkout/test',
            'transaccion_id' => 'TXN-999',
        ]);
        $this->app->instance(PagosServiceInterface::class, $mockM6);
    }

    public function test_flujo_completo_carrito_endpoints(): void
    {
        $this->configurarMocks();

        // 1. Agregar ítem al carrito
        $response = $this->postJson('/m5/carrito/items', [
            'id_cliente' => 1,
            'id_variacion' => 101,
            'cantidad' => 2,
        ]);
        $response->assertStatus(201);
        $this->assertDatabaseHas('items_carrito', [
            'id_variacion' => 101,
            'cantidad' => 2,
        ]);

        $itemId = ItemCarrito::where('id_variacion', 101)->first()->id_item_carrito;

        // 2. Obtener carrito
        $response = $this->getJson('/m5/carrito?id_cliente=1');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'carrito',
            'grupos_comercio',
            'monto_estimado',
        ]);

        // 3. Modificar cantidad
        $response = $this->putJson('/m5/carrito/items/'.$itemId, [
            'cantidad' => 5,
        ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('items_carrito', [
            'id_item_carrito' => $itemId,
            'cantidad' => 5,
        ]);

        // 4. Eliminar un ítem
        $response = $this->deleteJson('/m5/carrito/items/'.$itemId, [
            'id_cliente' => 1,
        ]);
        $response->assertStatus(200);
        $this->assertDatabaseMissing('items_carrito', [
            'id_item_carrito' => $itemId,
        ]);

        // 5. Vaciar carrito
        $this->postJson('/m5/carrito/items', [
            'id_cliente' => 1,
            'id_variacion' => 102,
            'cantidad' => 1,
        ]);
        $this->deleteJson('/m5/carrito', ['id_cliente' => 1])
            ->assertStatus(200);
        $this->assertDatabaseMissing('items_carrito', [
            'id_variacion' => 102,
        ]);
    }

    public function test_flujo_checkout_y_gestion_pedido_endpoints(): void
    {
        $this->configurarMocks();

        // Preparar carrito
        $this->postJson('/m5/carrito/items', [
            'id_cliente' => 1,
            'id_variacion' => 201,
            'cantidad' => 2,
        ])->assertStatus(201);

        // Checkout
        $checkoutResponse = $this->postJson('/m5/pedidos', [
            'id_cliente' => 1,
            'metodoPago' => 'tarjeta',
            'cupon_descuento' => 'DESC10',
        ]);
        $checkoutResponse->assertStatus(201);
        $checkoutResponse->assertJsonStructure([
            'pedido' => ['id_pedido', 'subpedidos'],
            'urlPasarelaPago',
        ]);

        $pedidoData = $checkoutResponse->json('pedido');
        $idPedido = $pedidoData['id_pedido'];
        $idSubpedido = $pedidoData['subpedidos'][0]['id_subpedido'];

        // Carrito debe estar vacío tras checkout
        $this->assertDatabaseMissing('items_carrito', ['id_variacion' => 201]);

        // Listar pedidos
        $this->getJson('/m5/pedidos?id_cliente=1')
            ->assertStatus(200)
            ->assertJsonFragment(['id_pedido' => $idPedido]);

        // Obtener detalle de pedido
        $this->getJson('/m5/pedidos/'.$idPedido)
            ->assertStatus(200)
            ->assertJsonFragment(['id_pedido' => $idPedido]);

        // Obtener detalle de subpedido
        $this->getJson('/m5/subpedidos/'.$idSubpedido)
            ->assertStatus(200)
            ->assertJsonFragment(['id_subpedido' => $idSubpedido]);

        // Confirmar pago (idempotente)
        $this->postJson('/m5/pedidos/'.$idPedido.'/pago-confirmado', ['refPago' => 'REF-ABC-123'])
            ->assertStatus(200);
        $this->assertSame('pagado', PedidoMaestro::find($idPedido)->estado_pedido);

        // Llamada repetida debe ser idempotente
        $this->postJson('/m5/pedidos/'.$idPedido.'/pago-confirmado', ['refPago' => 'REF-ABC-123'])
            ->assertStatus(200)
            ->assertJsonFragment(['mensaje' => 'Operación idempotente, pedido ya pagado']);

        // Despachar subpedido
        $this->postJson('/m5/subpedidos/'.$idSubpedido.'/despachar', ['guia_seguimiento' => 'GUIA-TRACK-99'])
            ->assertStatus(200);
        $this->assertSame('despachado', Subpedido::find($idSubpedido)->estado_subpedido);

        // Confirmar entrega subpedido
        $this->postJson('/m5/subpedidos/'.$idSubpedido.'/entregar')
            ->assertStatus(200);
        $this->assertSame('entregado', Subpedido::find($idSubpedido)->estado_subpedido);
    }

    public function test_cancelar_subpedido_y_cancelar_pedido(): void
    {
        $this->configurarMocks();

        // Carrito y pedido
        $this->postJson('/m5/carrito/items', [
            'id_cliente' => 2,
            'id_variacion' => 301,
            'cantidad' => 1,
        ]);

        $checkoutResponse = $this->postJson('/m5/pedidos', [
            'id_cliente' => 2,
        ]);
        $idPedido = $checkoutResponse->json('pedido.id_pedido');
        $idSubpedido = $checkoutResponse->json('pedido.subpedidos.0.id_subpedido');

        // Cancelar subpedido
        $this->postJson('/m5/subpedidos/'.$idSubpedido.'/cancelar', ['motivo' => 'Sin stock'])
            ->assertStatus(200);
        $this->assertSame('cancelado', Subpedido::find($idSubpedido)->estado_subpedido);
        // Como todos los subpedidos se cancelaron, el pedido maestro también se cancela
        $this->assertSame('cancelado', PedidoMaestro::find($idPedido)->estado_pedido);
    }
}
