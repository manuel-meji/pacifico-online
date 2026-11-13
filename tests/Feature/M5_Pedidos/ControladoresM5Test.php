<?php

namespace Tests\Feature\M5_Pedidos;

use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Models\M5_Pedidos\PedidoLinea;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use App\Models\Nucleo\Usuario;
use App\Services\M5_Pedidos\Dobles\CatalogoStub;
use App\Services\M5_Pedidos\Dobles\ComerciosStub;
use App\Services\M5_Pedidos\Dobles\InventarioStub;
use App\Services\M5_Pedidos\Dobles\PagosStub;
use App\Services\M5_Pedidos\Dobles\PortalStub;
use Database\Seeders\M5PedidosSeeder;
use Illuminate\Support\Facades\Event;
use Tests\Doubles\M1\PortalPublicoFake;
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

    public function test_carrito_y_checkout_con_sesion_resuelven_id_cliente_por_usuario_en_m1(): void
    {
        $this->configurarMocks();

        // El usuario autenticado tiene id_usuario = 501, que corresponde a id_cliente = 1 en M1 (PortalStub / PortalPublicoFake)
        $usuario = new Usuario;
        $usuario->forceFill(['id_usuario' => PortalPublicoFake::USUARIO_CON_CLIENTE]);

        // 1. Agregar al carrito con sesión activa
        $resAgregar = $this->actingAs($usuario)
            ->postJson('/m5/carrito/items', [
                'id_variacion' => 101,
                'cantidad' => 2,
            ])
            ->assertStatus(201);

        $this->assertSame(PortalPublicoFake::CLIENTE_CON_DIRECCIONES, $resAgregar->json('id_cliente'));
        $this->assertDatabaseHas('pedidos_carritos', [
            'id_cliente' => PortalPublicoFake::CLIENTE_CON_DIRECCIONES,
        ]);
        $this->assertDatabaseMissing('pedidos_carritos', [
            'id_cliente' => PortalPublicoFake::USUARIO_CON_CLIENTE,
        ]);

        $itemId = $resAgregar->json('items.0.id_item_carrito');

        // 2. Obtener carrito con sesión activa
        $this->actingAs($usuario)
            ->getJson('/m5/carrito')
            ->assertStatus(200)
            ->assertJsonPath('id_cliente', PortalPublicoFake::CLIENTE_CON_DIRECCIONES)
            ->assertJsonCount(1, 'items');

        // 3. Actualizar cantidad con sesión activa
        $this->actingAs($usuario)
            ->putJson('/m5/carrito/items/'.$itemId, ['cantidad' => 3])
            ->assertStatus(200)
            ->assertJsonPath('id_cliente', PortalPublicoFake::CLIENTE_CON_DIRECCIONES);

        // 4. Listar direcciones con sesión activa
        $this->actingAs($usuario)
            ->getJson('/m5/carrito/direcciones')
            ->assertStatus(200)
            ->assertJsonPath('id_cliente', PortalPublicoFake::CLIENTE_CON_DIRECCIONES);

        // 5. Procesar checkout con sesión activa
        $resCheckout = $this->actingAs($usuario)
            ->postJson('/m5/pedidos', ['metodoPago' => 'tarjeta'])
            ->assertStatus(201);

        $this->assertSame(PortalPublicoFake::CLIENTE_CON_DIRECCIONES, $resCheckout->json('id_cliente'));

        // 6. Vaciar carrito con sesión activa
        $this->actingAs($usuario)
            ->postJson('/m5/carrito/items', ['id_variacion' => 102, 'cantidad' => 1])
            ->assertStatus(201);

        $this->actingAs($usuario)
            ->deleteJson('/m5/carrito')
            ->assertStatus(200);

        $this->assertDatabaseMissing('items_carrito', ['id_variacion' => 102]);
    }

    public function test_promociones_m1_precio_vigente_en_carrito_vencimiento_y_confirmacion_pedido(): void
    {
        $this->configurarMocks();

        $portalStub = new PortalStub;
        $this->app->instance(PortalPublicoContract::class, $portalStub);

        // Parte A: Se agrega al carrito con promoción vigente (15.000 -> 12.000, promo #15), pero vence antes del checkout
        $portalStub->definirPromocionVariacion(101, 15000.00, 12000.00, 15, '2026-11-15T23:59:59-06:00');

        $resCarrito = $this->postJson('/m5/carrito/items', [
            'id_cliente' => 1,
            'id_variacion' => 101,
            'cantidad' => 2,
        ])->assertStatus(201);

        $this->assertEqualsWithDelta(12000.00, (float) $resCarrito->json('items.0.precio_unitario'), 0.001);
        $this->assertEqualsWithDelta(15000.00, (float) $resCarrito->json('items.0.precio_base'), 0.001);
        $this->assertEqualsWithDelta(12000.00, (float) $resCarrito->json('items.0.precio_final'), 0.001);
        $this->assertEqualsWithDelta(6000.00, (float) $resCarrito->json('items.0.descuento'), 0.001);
        $this->assertSame(15, $resCarrito->json('items.0.id_promocion'));
        $this->assertEqualsWithDelta(24000.00, (float) $resCarrito->json('resumen.subtotal'), 0.001);

        // La promoción termina mientras el producto está en el carrito: al confirmar se cobra el precio normal (15.000 x 2 = 30.000)
        $portalStub->limpiarPromociones();

        $resCheckoutNormal = $this->postJson('/m5/pedidos', [
            'id_cliente' => 1,
            'metodoPago' => 'tarjeta',
        ])->assertStatus(201);

        $idPedidoNormal = (int) $resCheckoutNormal->json('id_pedido');
        $this->assertEqualsWithDelta(30000.00, (float) $resCheckoutNormal->json('subtotal'), 0.001);

        $lineaNormal = PedidoLinea::whereHas('subpedido', fn ($q) => $q->where('id_pedido', $idPedidoNormal))->first();
        $this->assertNotNull($lineaNormal);
        $this->assertEqualsWithDelta(15000.00, (float) $lineaNormal->precio_unitario, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $lineaNormal->descuento, 0.001);
        $this->assertNull($lineaNormal->id_promocion);

        // Parte B: Se agrega a precio normal y la promoción empieza antes de confirmar el pedido (15.000 -> 10.000, promo #22)
        $this->postJson('/m5/carrito/items', [
            'id_cliente' => 1,
            'id_variacion' => 101,
            'cantidad' => 2,
        ])->assertStatus(201);

        $portalStub->definirPromocionVariacion(101, 15000.00, 10000.00, 22, '2026-11-20T23:59:59-06:00');

        $resCheckoutPromo = $this->postJson('/m5/pedidos', [
            'id_cliente' => 1,
            'metodoPago' => 'tarjeta',
            'cupon_descuento' => 'CUPON-CONVIVE',
        ])->assertStatus(201);

        $idPedidoPromo = (int) $resCheckoutPromo->json('id_pedido');
        // Subtotal ya rebajado: 2 x 10.000 = 20.000 (tanto en maestro como en el subpedido enviado a M2)
        $this->assertEqualsWithDelta(20000.00, (float) $resCheckoutPromo->json('subtotal'), 0.001);
        $this->assertEqualsWithDelta(20000.00, (float) $resCheckoutPromo->json('subpedidos.0.subtotal'), 0.001);
        $this->assertSame('CUPON-CONVIVE', $resCheckoutPromo->json('cupon_descuento'));

        $lineaPromo = PedidoLinea::whereHas('subpedido', fn ($q) => $q->where('id_pedido', $idPedidoPromo))->first();
        $this->assertNotNull($lineaPromo);
        $this->assertEqualsWithDelta(10000.00, (float) $lineaPromo->precio_unitario, 0.001);
        $this->assertEqualsWithDelta(10000.00, (float) $lineaPromo->descuento, 0.001);
        $this->assertSame(22, (int) $lineaPromo->id_promocion);
    }

    public function test_portal_comercio_m2_lista_subpedidos_y_transiciona_por_en_preparacion_despachado_entregado(): void
    {
        $this->configurarMocks();
        $this->app->instance(CatalogoServiceInterface::class, new CatalogoStub);

        // Cliente #2 crea un pedido con productos del comercio #1 (variación 2) y comercio #4 (variación 3)
        $this->postJson('/m5/carrito/items', ['id_cliente' => 2, 'id_variacion' => 2, 'cantidad' => 1])->assertStatus(201);
        $this->postJson('/m5/carrito/items', ['id_cliente' => 2, 'id_variacion' => 3, 'cantidad' => 1])->assertStatus(201);

        $resCheckout = $this->postJson('/m5/pedidos', [
            'id_cliente' => 2,
            'metodoPago' => 'tarjeta',
        ])->assertStatus(201);

        $idPedido = (int) $resCheckout->json('id_pedido');

        // Confirmar pago del pedido maestro -> ambos subpedidos pasan a 'pagado'
        $this->postJson("/m5/pedidos/{$idPedido}/pago-confirmado", [
            'referencia_pago' => 'REF-M2-REAL-01',
        ])->assertStatus(200);

        // El encargado del comercio #1 inicia sesión y consulta sus pedidos desde el portal integrado
        $encargadoComercio1 = new Usuario;
        $encargadoComercio1->forceFill([
            'id_usuario' => 701,
            'rol' => 'encargado_comercio',
            'id_comercio' => 1,
        ]);

        $resLista = $this->actingAs($encargadoComercio1)
            ->getJson('/m5/pedidos?modo_comercio=1&id_comercio=1')
            ->assertStatus(200);

        $subpedidosComercio1 = $resLista->json('pedidos.0.subpedidos');
        $this->assertCount(1, $subpedidosComercio1);
        $this->assertSame(1, (int) $subpedidosComercio1[0]['id_comercio']);
        $this->assertNotEmpty($subpedidosComercio1[0]['nombre_comercio']);

        $idSubpedido1 = (int) $subpedidosComercio1[0]['id_subpedido'];

        // 1. Pasar de 'pagado' a 'en_preparacion'
        $this->actingAs($encargadoComercio1)
            ->postJson("/m5/subpedidos/{$idSubpedido1}/preparar")
            ->assertStatus(200)
            ->assertJsonPath('estado_subpedido', 'en_preparacion');

        // Una vez en 'en_preparacion', RN-M5-05 impide cancelar el subpedido
        $this->actingAs($encargadoComercio1)
            ->postJson("/m5/subpedidos/{$idSubpedido1}/cancelar", ['motivo' => 'Intento tardío'])
            ->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M5-003');

        // 2. Pasar de 'en_preparacion' a 'despachado'
        $this->actingAs($encargadoComercio1)
            ->postJson("/m5/subpedidos/{$idSubpedido1}/despachar", ['guia_transporte' => 'GUIA-CR-999'])
            ->assertStatus(200)
            ->assertJsonPath('estado_subpedido', 'despachado')
            ->assertJsonPath('guia_seguimiento', 'GUIA-CR-999');

        // 3. Pasar de 'despachado' a 'entregado'
        $this->actingAs($encargadoComercio1)
            ->postJson("/m5/subpedidos/{$idSubpedido1}/entregar")
            ->assertStatus(200)
            ->assertJsonPath('estado_subpedido', 'entregado');

        // Como el subpedido del comercio #2 aún sigue en 'pagado', el pedido maestro NO debe haberse marcado entregado prematuramente
        $this->assertDatabaseHas('pedidos_maestros', [
            'id_pedido' => $idPedido,
            'estado_pedido' => 'pagado',
        ]);
    }

    public function test_obtener_carrito_recalcula_costo_envio_segun_zona_o_direccion(): void
    {
        $this->postJson('/m5/carrito/items', [
            'id_variacion' => 101,
            'cantidad' => 1,
        ]);

        $resGam = $this->getJson('/m5/carrito?id_cliente=1&zona=GAM');
        $resGam->assertOk();
        $this->assertEqualsWithDelta(1500.0, (float) $resGam->json('resumen.costo_envio'), 0.01);

        $resResto = $this->getJson('/m5/carrito?id_cliente=1&zona=Resto%20del%20pa%C3%ADs');
        $resResto->assertOk();
        $this->assertEqualsWithDelta(2500.0, (float) $resResto->json('resumen.costo_envio'), 0.01);
    }

    public function test_listar_direcciones_completa_texto_completo_cuando_m1_no_lo_incluye(): void
    {
        $portalSinTextoCompleto = new class extends PortalStub
        {
            public function listarDireccionesCliente(int $idCliente): array
            {
                return [
                    [
                        'id_direccion_cliente' => 8,
                        'id_cliente' => $idCliente,
                        'alias_direccion' => 'UCR',
                        'nombre_destinatario' => 'Manuel Mejicano',
                        'telefono_destinatario' => '70280576',
                        'provincia' => 'San José',
                        'canton' => 'Desamparados',
                        'distrito' => 'Desamparados',
                        'otras_senas' => 'UCR',
                        'es_principal' => true,
                    ],
                ];
            }
        };

        $this->app->instance(PortalPublicoContract::class, $portalSinTextoCompleto);

        $this->getJson('/m5/carrito/direcciones?id_cliente=1')
            ->assertOk()
            ->assertJsonPath('direcciones.0.id_direccion_cliente', 8)
            ->assertJsonPath('direcciones.0.texto_completo', 'UCR — San José, Desamparados, Desamparados, UCR');
    }

    public function test_checkout_multi_item_asigna_clave_idempotencia_y_reserva_por_variacion(): void
    {
        $this->configurarMocks();

        $this->postJson('/m5/carrito/items', ['id_cliente' => 1, 'id_variacion' => 101, 'cantidad' => 1])->assertCreated();
        $this->postJson('/m5/carrito/items', ['id_cliente' => 1, 'id_variacion' => 102, 'cantidad' => 1])->assertCreated();

        $claves = [];
        $reservasPorVariacion = [
            101 => '11111111-1111-4111-8111-111111111101',
            102 => '22222222-2222-4222-8222-222222222102',
        ];

        $mockM4 = $this->createMock(InventarioModuleInterface::class);
        $mockM4->method('consultarExistencias')->willReturn(10);
        $mockM4->expects($this->exactly(2))
            ->method('reservarExistencias')
            ->willReturnCallback(function (int $idComercio, int $idVariacion, int $cantidad, ?int $idUbicacion, ?string $clave) use (&$claves, $reservasPorVariacion): array {
                $claves[] = $clave;

                return ['id_reserva' => $reservasPorVariacion[$idVariacion]];
            });

        $this->app->instance(InventarioModuleInterface::class, $mockM4);
        $this->app->instance(InventarioServiceInterface::class, $mockM4);

        $res = $this->postJson('/m5/pedidos', [
            'id_cliente' => 1,
            'id_direccion_cliente' => 1,
        ]);

        $res->assertCreated();
        $this->assertCount(2, array_unique($claves));

        $idPedido = (int) $res->json('id_pedido');
        $lineas = PedidoLinea::whereHas('subpedido', fn ($q) => $q->where('id_pedido', $idPedido))->get()->keyBy('id_variacion');

        $this->assertSame($reservasPorVariacion[101], $lineas[101]->id_reserva);
        $this->assertSame($reservasPorVariacion[102], $lineas[102]->id_reserva);
    }

    public function test_checkout_libera_reservas_parciales_cuando_falla_una_variacion_posterior(): void
    {
        $this->configurarMocks();

        $this->postJson('/m5/carrito/items', ['id_cliente' => 1, 'id_variacion' => 101, 'cantidad' => 1])->assertCreated();
        $this->postJson('/m5/carrito/items', ['id_cliente' => 1, 'id_variacion' => 102, 'cantidad' => 1])->assertCreated();

        $reservaItem1 = '33333333-3333-4333-8333-333333333101';

        $mockM4 = $this->createMock(InventarioModuleInterface::class);
        $mockM4->method('consultarExistencias')->willReturn(10);
        $mockM4->expects($this->exactly(2))
            ->method('reservarExistencias')
            ->willReturnCallback(function (int $idComercio, int $idVariacion) use ($reservaItem1): array {
                if ($idVariacion === 102) {
                    throw new \RuntimeException('Existencia insuficiente en segundo comercio');
                }

                return ['id_reserva' => $reservaItem1];
            });
        $mockM4->expects($this->once())
            ->method('liberarReserva')
            ->with($reservaItem1, 'solicitud_m5')
            ->willReturn(['id_reserva' => $reservaItem1, 'estado_reserva' => 'liberada']);

        $this->app->instance(InventarioModuleInterface::class, $mockM4);
        $this->app->instance(InventarioServiceInterface::class, $mockM4);

        $this->postJson('/m5/pedidos', [
            'id_cliente' => 1,
            'id_direccion_cliente' => 1,
        ])
            ->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M5-002');
    }

    public function test_listar_pedidos_comercio_incluye_nombre_de_producto_y_variacion_en_lineas(): void
    {
        $this->configurarMocks();

        $mockM3 = $this->createMock(CatalogoServiceInterface::class);
        $mockM3->method('obtenerVariacion')->willReturn([
            'id_variacion' => 101,
            'id_producto' => 10,
            'id_comercio' => 1,
            'sku' => 'SDM-LOG-K835-GRIS',
            'precio' => 28000,
            'tarifa_impuesto' => 13,
            'disponible' => true,
            'atributos' => [
                ['tipo_atributo' => 'Color', 'valor' => 'Gris Oscuro'],
            ],
            'producto' => [
                'id_producto' => 10,
                'id_comercio' => 1,
                'nombre' => 'Teclado mecánico Logitech K835',
            ],
        ]);
        $this->app->instance(CatalogoServiceInterface::class, $mockM3);

        $this->postJson('/m5/carrito/items', ['id_cliente' => 1, 'id_variacion' => 101, 'cantidad' => 1])->assertCreated();
        $this->postJson('/m5/pedidos', ['id_cliente' => 1, 'id_direccion_cliente' => 1])->assertCreated();

        $this->getJson('/m5/pedidos?id_comercio=1')
            ->assertOk()
            ->assertJsonPath('pedidos.0.subpedidos.0.lineas.0.nombre', 'Teclado mecánico Logitech K835 (Color: Gris Oscuro)')
            ->assertJsonPath('pedidos.0.subpedidos.0.lineas.0.nombre_producto', 'Teclado mecánico Logitech K835')
            ->assertJsonPath('pedidos.0.subpedidos.0.lineas.0.sku', 'SDM-LOG-K835-GRIS');
    }

    public function test_despachar_subpedido_revierte_estado_y_no_emite_evento_si_m4_falla(): void
    {
        $this->configurarMocks();
        $inventarioStub = (new InventarioStub)->setFallarSalida(true);
        $this->app->instance(InventarioServiceInterface::class, $inventarioStub);

        $this->postJson('/m5/carrito/items', [
            'id_cliente' => 1,
            'id_variacion' => 101,
            'cantidad' => 2,
        ])->assertCreated();

        $resCheckout = $this->postJson('/m5/pedidos', [
            'id_cliente' => 1,
            'id_direccion_cliente' => 1,
            'metodoPago' => 'tarjeta',
        ])->assertCreated();

        $idPedido = (int) $resCheckout->json('id_pedido');
        $this->postJson("/m5/pedidos/{$idPedido}/pago-confirmado", [
            'referencia_pago' => 'REF-FALLO-M4',
        ])->assertOk();

        $subpedido = Subpedido::where('id_pedido', $idPedido)->firstOrFail();
        $this->postJson("/m5/subpedidos/{$subpedido->id_subpedido}/preparar")->assertOk();

        Event::fake([SubpedidoDespachado::class]);

        $response = $this->postJson("/m5/subpedidos/{$subpedido->id_subpedido}/despachar", [
            'guia_seguimiento' => 'GUIA-ROLLBACK-01',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M5-002');

        Event::assertNotDispatched(SubpedidoDespachado::class);

        $subActualizado = $subpedido->fresh();
        $this->assertSame('en_preparacion', $subActualizado->estado_subpedido);
        $this->assertNull($subActualizado->guia_seguimiento);
    }
}
