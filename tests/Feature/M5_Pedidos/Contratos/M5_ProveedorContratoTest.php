<?php

namespace Tests\Feature\M5_Pedidos\Contratos;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Models\M5_Pedidos\PedidoLinea;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use App\Services\M5_Pedidos\Dobles\ComerciosStub;
use Tests\TestCase;

class M5_ProveedorContratoTest extends TestCase
{
    protected string $prefix = '/api/m5/v1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', [
            '--path' => 'database/migrations/M5_Pedidos',
            '--realpath' => false,
        ]);
        $this->configurarMocks();
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
        $mockM4->method('consultarExistencias')->willReturn($stock ?? 0);
        $mockM4->method('solicitarReserva')->willReturn([
            'id_reserva' => '550e8400-e29b-41d4-a716-446655440000',
            'expiracion' => now()->addMinutes(15)->toIso8601String(),
        ]);
        $this->app->instance(InventarioServiceInterface::class, $mockM4);

        $mockM2 = (new ComerciosStub)->setComercioSuspendido(! $comercioActivo);
        $this->app->instance(ComerciosServiceInterface::class, $mockM2);

        $mockM6 = $this->createStub(PagosServiceInterface::class);
        $mockM6->method('procesarPago')->willReturn([
            'urlPasarelaPago' => 'https://pago.pacificos.com/checkout/test',
            'transaccion_id' => 'TXN-999',
        ]);
        $this->app->instance(PagosServiceInterface::class, $mockM6);
    }

    // =========================================================================
    // Operaciones del Carrito
    // =========================================================================

    public function test_op01_agregar_item_al_carrito_exito_201_y_esquema_carrito(): void
    {
        $response = $this->postJson("{$this->prefix}/carrito/items", [
            'id_cliente' => 1,
            'id_variacion' => 101,
            'cantidad' => 2,
            'id_reserva' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id_carrito',
            'id_cliente',
            'monto_estimado',
            'created_at',
            'updated_at',
            'items' => [
                '*' => [
                    'id_item_carrito',
                    'id_carrito',
                    'id_variacion',
                    'cantidad',
                    'id_reserva',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    public function test_op02_obtener_carrito_activo_exito_200_y_esquema_carrito(): void
    {
        (new Carrito)->obtenerOCrearCarrito(1);

        $response = $this->getJson("{$this->prefix}/carrito?id_cliente=1");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id_carrito',
            'id_cliente',
            'monto_estimado',
            'created_at',
            'updated_at',
            'items',
            'direcciones_cliente',
        ]);
        $this->assertNotEmpty($response->json('direcciones_cliente'));
    }

    public function test_op02b_obtener_direcciones_cliente_desde_stub_m1(): void
    {
        $response = $this->getJson("{$this->prefix}/carrito/direcciones?id_cliente=1");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id_cliente',
            'direcciones' => [
                '*' => [
                    'id_direccion_cliente',
                    'alias_direccion',
                    'texto_completo',
                ],
            ],
        ]);
        $this->assertCount(2, $response->json('direcciones'));
        $this->assertSame('Casa (Principal)', $response->json('direcciones.0.alias_direccion'));
    }

    public function test_op03_actualizar_cantidad_item_carrito_exito_200(): void
    {
        $carrito = (new Carrito)->obtenerOCrearCarrito(1);
        $item = (new ItemCarrito)->crearItemCarrito($carrito->id_carrito, 101, 1);

        $response = $this->putJson("{$this->prefix}/carrito/items/{$item->id_item_carrito}", [
            'cantidad' => 4,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id_carrito',
            'id_cliente',
            'monto_estimado',
            'items',
        ]);
        $this->assertDatabaseHas('items_carrito', [
            'id_item_carrito' => $item->id_item_carrito,
            'cantidad' => 4,
        ]);
    }

    public function test_op04_eliminar_item_carrito_exito_200(): void
    {
        $carrito = (new Carrito)->obtenerOCrearCarrito(1);
        $item = (new ItemCarrito)->crearItemCarrito($carrito->id_carrito, 102, 1);

        $response = $this->deleteJson("{$this->prefix}/carrito/items/{$item->id_item_carrito}?id_cliente=1");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('items_carrito', [
            'id_item_carrito' => $item->id_item_carrito,
        ]);
    }

    public function test_op05_vaciar_carrito_exito_200_y_esquema_mensaje(): void
    {
        $carrito = (new Carrito)->obtenerOCrearCarrito(1);
        (new ItemCarrito)->crearItemCarrito($carrito->id_carrito, 103, 2);

        $response = $this->deleteJson("{$this->prefix}/carrito?id_cliente=1");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'mensaje',
        ]);
        $this->assertDatabaseMissing('items_carrito', [
            'id_carrito' => $carrito->id_carrito,
        ]);
    }

    // =========================================================================
    // Operaciones de Pedidos (Checkout, Consultas, Cancelación)
    // =========================================================================

    public function test_op06_checkout_crear_pedido_exito_201_y_esquema_pedidomaestro(): void
    {
        $carrito = (new Carrito)->obtenerOCrearCarrito(1);
        (new ItemCarrito)->crearItemCarrito($carrito->id_carrito, 101, 2);

        $response = $this->postJson("{$this->prefix}/pedidos", [
            'id_cliente' => 1,
            'id_direccion_cliente' => 10,
            'cupon_descuento' => 'DESC10',
            'tarjeta_regalo' => null,
            'nota' => 'Entregar en portería',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id_pedido',
            'id_cliente',
            'monto_total',
            'estado_pedido',
            'subpedidos' => [
                '*' => [
                    'id_subpedido',
                    'id_pedido',
                    'id_comercio',
                    'subtotal',
                    'costo_envio',
                    'impuesto',
                    'estado_subpedido',
                    'lineas' => [
                        '*' => [
                            'id_linea',
                            'id_subpedido',
                            'id_variacion',
                            'cantidad',
                            'precio_unitario',
                            'tarifa_impuesto',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_op07_obtener_detalle_pedido_maestro_exito_200(): void
    {
        $pedido = new PedidoMaestro;
        $pedidoId = $pedido->crearPedidoMaestro(22600.00, 1);
        $sub = (new Subpedido)->crearSubpedidos([
            ['id_comercio' => 1, 'subtotal' => 20000.0, 'costo_envio' => 0.0, 'impuesto' => 2600.0],
        ], $pedidoId)[0];
        (new PedidoLinea)->crearLinea($sub->id_subpedido, 101, 2, 10000.0, 13.0);

        $response = $this->getJson("{$this->prefix}/pedidos/{$pedidoId}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id_pedido',
            'id_cliente',
            'monto_total',
            'estado_pedido',
            'subpedidos' => [
                '*' => [
                    'id_subpedido',
                    'id_comercio',
                    'subtotal',
                    'lineas',
                ],
            ],
        ]);
    }

    public function test_op08_listar_pedidos_cliente_exito_200_y_esquema_resultadopedidos(): void
    {
        $pedido = new PedidoMaestro;
        $pedidoId = $pedido->crearPedidoMaestro(15000.00, 1);

        $response = $this->getJson("{$this->prefix}/pedidos?id_cliente=1");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'pagina',
            'por_pagina',
            'total_resultados',
            'pedidos' => [
                '*' => [
                    'id_pedido',
                    'id_cliente',
                    'monto_total',
                    'estado_pedido',
                ],
            ],
        ]);
    }

    public function test_op09_obtener_subpedido_por_id_exito_200_y_esquema_pedidosubpedido(): void
    {
        $pedido = new PedidoMaestro;
        $pedidoId = $pedido->crearPedidoMaestro(15000.00, 1);
        $sub = (new Subpedido)->crearSubpedidos([
            ['id_comercio' => 1, 'subtotal' => 15000.0, 'costo_envio' => 0.0, 'impuesto' => 1950.0],
        ], $pedidoId)[0];
        (new PedidoLinea)->crearLinea($sub->id_subpedido, 101, 1, 15000.0, 13.0);

        $response = $this->getJson("{$this->prefix}/subpedidos/{$sub->id_subpedido}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id_subpedido',
            'id_pedido',
            'id_comercio',
            'subtotal',
            'estado_subpedido',
            'lineas',
        ]);
    }

    public function test_op10_despachar_subpedido_exito_200(): void
    {
        $pedido = new PedidoMaestro;
        $pedidoId = $pedido->crearPedidoMaestro(15000.00, 1);
        $sub = (new Subpedido)->crearSubpedidos([
            ['id_comercio' => 1, 'subtotal' => 15000.0, 'costo_envio' => 0.0, 'impuesto' => 1950.0],
        ], $pedidoId)[0];

        $response = $this->postJson("{$this->prefix}/subpedidos/{$sub->id_subpedido}/despachar", [
            'guia_seguimiento' => 'GUIA-TRACK-999',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id_subpedido',
            'estado_subpedido',
            'guia_seguimiento',
        ]);
        $this->assertSame('despachado', $sub->fresh()->estado_subpedido);
    }

    public function test_op11_confirmar_entrega_subpedido_exito_200(): void
    {
        $pedido = new PedidoMaestro;
        $pedidoId = $pedido->crearPedidoMaestro(15000.00, 1);
        $sub = (new Subpedido)->crearSubpedidos([
            ['id_comercio' => 1, 'subtotal' => 15000.0, 'costo_envio' => 0.0, 'impuesto' => 1950.0],
        ], $pedidoId)[0];
        $sub->despachar('GUIA-001');

        $response = $this->postJson("{$this->prefix}/subpedidos/{$sub->id_subpedido}/entregar");

        $response->assertStatus(200);
        $this->assertSame('entregado', $sub->fresh()->estado_subpedido);
    }

    public function test_op12_cancelar_subpedido_parcial_sc02_exito_200(): void
    {
        $pedido = new PedidoMaestro;
        $pedidoId = $pedido->crearPedidoMaestro(30000.00, 1);
        $subpedidos = (new Subpedido)->crearSubpedidos([
            ['id_comercio' => 1, 'subtotal' => 15000.0, 'costo_envio' => 0.0, 'impuesto' => 1950.0],
            ['id_comercio' => 2, 'subtotal' => 15000.0, 'costo_envio' => 0.0, 'impuesto' => 1950.0],
        ], $pedidoId);

        $response = $this->postJson("{$this->prefix}/subpedidos/{$subpedidos[0]->id_subpedido}/cancelar", [
            'motivo' => 'Comprador solicitó anular producto de comercio 1',
        ]);

        $response->assertStatus(200);
        $this->assertSame('cancelado', $subpedidos[0]->fresh()->estado_subpedido);
        $this->assertNotSame('cancelado', $pedido->fresh()->estado_pedido);
    }

    public function test_op13_cancelar_pedido_maestro_completo_exito_200(): void
    {
        $pedido = new PedidoMaestro;
        $pedidoId = $pedido->crearPedidoMaestro(15000.00, 1);
        $sub = (new Subpedido)->crearSubpedidos([
            ['id_comercio' => 1, 'subtotal' => 15000.0, 'costo_envio' => 0.0, 'impuesto' => 1950.0],
        ], $pedidoId)[0];

        $response = $this->postJson("{$this->prefix}/pedidos/{$pedidoId}/cancelar", [
            'motivo' => 'Solicitud total de cliente',
        ]);

        $response->assertStatus(200);
        $this->assertSame('cancelado', $pedido->fresh()->estado_pedido);
        $this->assertSame('cancelado', $sub->fresh()->estado_subpedido);
    }

    public function test_op14_confirmar_pago_exito_y_operacion_idempotente(): void
    {
        $pedido = new PedidoMaestro;
        $pedidoId = $pedido->crearPedidoMaestro(15000.00, 1);
        (new Subpedido)->crearSubpedidos([
            ['id_comercio' => 1, 'subtotal' => 15000.0, 'costo_envio' => 0.0, 'impuesto' => 1950.0],
        ], $pedidoId);

        $response1 = $this->postJson("{$this->prefix}/pedidos/{$pedidoId}/pago-confirmado", [
            'referencia_pago' => 'TXN-ABC-001',
        ]);
        $response1->assertStatus(200);
        $this->assertSame('pagado', $pedido->fresh()->estado_pedido);

        // Reintento idempotente
        $response2 = $this->postJson("{$this->prefix}/pedidos/{$pedidoId}/pago-confirmado", [
            'referencia_pago' => 'TXN-ABC-001',
        ]);
        $response2->assertStatus(200);
        $response2->assertJsonFragment(['mensaje' => 'Operación idempotente, pedido ya pagado']);
    }

    // =========================================================================
    // Pruebas de Respuestas de Error Estándar (ErrorEstandar)
    // =========================================================================

    public function test_error_005_validacion_fallida_cantidad_invalida_retorna_422_con_errorestandar(): void
    {
        $response = $this->postJson("{$this->prefix}/carrito/items", [
            'id_cliente' => 1,
            'id_variacion' => 101,
            'cantidad' => 0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'error' => [
                'codigo',
                'mensaje',
                'detalles',
            ],
        ]);
        $this->assertSame('ERR-005', $response->json('error.codigo'));
    }

    public function test_error_004_recurso_no_encontrado_retorna_404_con_errorestandar(): void
    {
        $response = $this->getJson("{$this->prefix}/pedidos/999999");

        $response->assertStatus(404);
        $response->assertJsonStructure([
            'error' => [
                'codigo',
                'mensaje',
                'detalles',
            ],
        ]);
        $this->assertSame('ERR-004', $response->json('error.codigo'));
    }

    public function test_error_003_comercio_inactivo_retorna_409_con_errorestandar(): void
    {
        $this->configurarMocks(stock: 10, comercioActivo: false);
        $carrito = (new Carrito)->obtenerOCrearCarrito(1);
        (new ItemCarrito)->crearItemCarrito($carrito->id_carrito, 101, 2);

        $response = $this->postJson("{$this->prefix}/pedidos", [
            'id_cliente' => 1,
            'id_direccion_cliente' => 1,
        ]);

        $response->assertStatus(409);
        $response->assertJsonStructure([
            'error' => [
                'codigo',
                'mensaje',
                'detalles',
            ],
        ]);
        $this->assertSame('ERR-003', $response->json('error.codigo'));
    }

    public function test_error_m5_001_carrito_vacio_al_crear_pedido_retorna_error_con_errorestandar(): void
    {
        (new Carrito)->obtenerOCrearCarrito(1);

        $response = $this->postJson("{$this->prefix}/pedidos", [
            'id_cliente' => 1,
            'id_direccion_cliente' => 1,
        ]);

        $response->assertStatus(400);
        $this->assertSame('ERR-M5-001', $response->json('error.codigo'));
    }

    public function test_error_m5_001_existencia_insuficiente_retorna_409_con_errorestandar(): void
    {
        $this->configurarMocks(stock: 1);

        $response = $this->postJson("{$this->prefix}/carrito/items", [
            'id_cliente' => 1,
            'id_variacion' => 101,
            'cantidad' => 5,
        ]);

        $response->assertStatus(409);
        $this->assertSame('ERR-M5-001', $response->json('error.codigo'));
    }

    public function test_error_m5_002_estado_invalido_al_despachar_subpedido_ya_despachado(): void
    {
        $pedido = new PedidoMaestro;
        $pedidoId = $pedido->crearPedidoMaestro(15000.00, 1);
        $sub = (new Subpedido)->crearSubpedidos([
            ['id_comercio' => 1, 'subtotal' => 15000.0, 'costo_envio' => 0.0, 'impuesto' => 1950.0],
        ], $pedidoId)[0];
        $sub->despachar('GUIA-001');

        $response = $this->postJson("{$this->prefix}/subpedidos/{$sub->id_subpedido}/despachar", [
            'guia_seguimiento' => 'OTRA-GUIA',
        ]);

        $response->assertStatus(409);
        $this->assertSame('ERR-M5-002', $response->json('error.codigo'));
    }

    public function test_error_m5_003_no_se_puede_cancelar_pedido_con_subpedidos_despachados(): void
    {
        $pedido = new PedidoMaestro;
        $pedidoId = $pedido->crearPedidoMaestro(15000.00, 1);
        $sub = (new Subpedido)->crearSubpedidos([
            ['id_comercio' => 1, 'subtotal' => 15000.0, 'costo_envio' => 0.0, 'impuesto' => 1950.0],
        ], $pedidoId)[0];
        $sub->despachar('GUIA-001');

        $response = $this->postJson("{$this->prefix}/pedidos/{$pedidoId}/cancelar", [
            'motivo' => 'Quiero cancelar',
        ]);

        $response->assertStatus(409);
        $this->assertSame('ERR-M5-003', $response->json('error.codigo'));
    }
}
