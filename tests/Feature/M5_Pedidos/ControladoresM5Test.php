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
use App\Services\M5_Pedidos\Dobles\CatalogoStub;
use App\Services\M5_Pedidos\Dobles\ComerciosStub;
use App\Services\M5_Pedidos\Dobles\InventarioStub;
use App\Services\M5_Pedidos\Dobles\PagosStub;
use Database\Seeders\M5PedidosSeeder;
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
        $mockM3 = (new CatalogoStub)->setIdComercio(1);
        $this->app->instance(CatalogoServiceInterface::class, $mockM3);

        $mockM4 = (new InventarioStub)->setSinStock($stock === 0);
        $this->app->instance(InventarioServiceInterface::class, $mockM4);

        $mockM2 = (new ComerciosStub)->setComercioSuspendido(! $comercioActivo);
        $this->app->instance(ComerciosServiceInterface::class, $mockM2);

        $mockM6 = new PagosStub;
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

        // 2. Obtener carrito (B2: incluye resumen, nombre, sku, imagen, nombre_comercio)
        $response = $this->getJson('/m5/carrito?id_cliente=1');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'carrito',
            'grupos_comercio',
            'monto_estimado',
            'resumen' => ['subtotal', 'impuesto', 'costo_envio', 'total'],
            'items' => [
                '*' => [
                    'id_item_carrito',
                    'id_variacion',
                    'nombre',
                    'sku',
                    'imagen',
                    'precio',
                    'nombre_comercio',
                    'subtotal',
                    'iva',
                ],
            ],
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
        $this->assertDatabaseHas('cancelaciones_pedido', [
            'id_pedido' => $idPedido,
            'id_subpedido' => $idSubpedido,
            'motivo' => 'Sin stock',
        ]);
    }

    public function test_rechaza_cancelar_subpedido_o_pedido_en_preparacion_rn_m5_05(): void
    {
        $this->configurarMocks();

        $this->postJson('/m5/carrito/items', [
            'id_cliente' => 3,
            'id_variacion' => 401,
            'cantidad' => 1,
        ]);

        $checkoutResponse = $this->postJson('/m5/pedidos', [
            'id_cliente' => 3,
        ])->assertStatus(201);

        $idPedido = $checkoutResponse->json('pedido.id_pedido');
        $idSubpedido = $checkoutResponse->json('pedido.subpedidos.0.id_subpedido');

        // Confirmar pago y pasar el subpedido a en_preparacion
        $this->postJson('/m5/pedidos/'.$idPedido.'/pago-confirmado', ['refPago' => 'REF-PREP-1'])
            ->assertStatus(200);

        $sub = Subpedido::find($idSubpedido);
        $sub->cambiarEstado('en_preparacion', 'Comercio preparando paquete');

        // Intentar cancelar subpedido en preparación -> 409 ERR-M5-003
        $this->postJson('/m5/subpedidos/'.$idSubpedido.'/cancelar', ['motivo' => 'Quiero cancelar'])
            ->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M5-003');

        // Intentar cancelar pedido maestro con subpedido en preparación -> 409 ERR-M5-003
        $this->postJson('/m5/pedidos/'.$idPedido.'/cancelar', ['motivo' => 'Quiero cancelar todo'])
            ->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M5-003');
    }

    public function test_checkout_falla_con_err_003_si_comercio_esta_suspendido_en_m2(): void
    {
        $this->configurarMocks(stock: 10, comercioActivo: false);

        $carrito = (new Carrito)->obtenerOCrearCarrito(4);
        (new ItemCarrito)->crearItemCarrito($carrito->id_carrito, 201, 1);

        $response = $this->postJson('/m5/pedidos', [
            'id_cliente' => 4,
            'metodoPago' => 'tarjeta',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-003');
    }

    public function test_b7_seeder_m5_pedidos_es_idempotente_y_crea_datos_demo(): void
    {
        $this->seed(M5PedidosSeeder::class);
        $this->seed(M5PedidosSeeder::class);

        $this->assertDatabaseHas('pedidos_maestros', [
            'cupon_descuento' => 'DEMO-MULTI-PC4',
        ]);
        $this->assertDatabaseHas('pedidos_maestros', [
            'cupon_descuento' => 'DEMO-CANCEL-PC4',
            'estado_pedido' => 'cancelado',
        ]);
        $this->assertDatabaseHas('cancelaciones_pedido', [
            'motivo' => 'Cliente solicitó cancelación antes de entrar en preparación',
            'requiere_reembolso' => 1,
        ]);
    }
}
