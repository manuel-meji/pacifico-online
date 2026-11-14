<?php

namespace Tests\Feature\M5_Pedidos;

use App\Contracts\M1_Portal\ComercialPortalContract;
use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Contracts\M1_Portal\PortalServiceInterface;
use App\Events\M5_Pedidos\PedidoConfirmado;
use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Services\M5_Pedidos\Dobles\PortalStub;
use App\Services\M5_Pedidos\PedidosService;
use Tests\Doubles\M1\ComercialPortalFake;
use Tests\TestCase;

class CuponCheckoutTest extends TestCase
{
    private ComercialPortalFake $comercialFake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => 'database/migrations/M5_Pedidos',
            '--realpath' => false,
        ]);

        config()->set('integraciones.m5.m1.modo', 'real');
        $portalStub = new PortalStub;
        $this->app->instance(PortalServiceInterface::class, $portalStub);
        $this->app->instance(PortalPublicoContract::class, $portalStub);
        $this->comercialFake = new ComercialPortalFake;
        $this->app->instance(ComercialPortalContract::class, $this->comercialFake);
    }

    public function test_checkout_aplica_cupon_valido_y_registra_su_uso(): void
    {
        $carrito = Carrito::create(['id_cliente' => 1]);
        ItemCarrito::create([
            'id_carrito' => $carrito->id_carrito,
            'id_variacion' => 1,
            'cantidad' => 2,
        ]);

        $respuesta = $this->postJson('/api/m5/v1/pedidos?id_cliente=1', [
            'id_direccion_cliente' => 1,
            'metodoPago' => 'tarjeta',
            'cupon_descuento' => 'pacifico10',
        ]);

        $respuesta->assertStatus(201);

        $pedido = PedidoMaestro::firstOrFail();
        $this->assertSame('PACIFICO10', $pedido->cupon_descuento);
        $this->assertGreaterThan(0.0, (float) $pedido->descuento);
        $this->assertEqualsWithDelta(
            round((float) $pedido->subtotal + (float) $pedido->impuesto + (float) $pedido->costo_envio - (float) $pedido->descuento, 2),
            (float) $pedido->monto_total,
            0.01
        );

        ItemCarrito::create([
            'id_carrito' => $carrito->id_carrito,
            'id_variacion' => 1,
            'cantidad' => 1,
        ]);

        $segundoIntento = $this->postJson('/api/m5/v1/pedidos?id_cliente=1', [
            'id_direccion_cliente' => 1,
            'metodoPago' => 'tarjeta',
            'cupon_descuento' => 'PACIFICO10',
        ]);

        $segundoIntento->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-M5-CUPON');
    }

    public function test_checkout_rechaza_cupon_invalido_sin_crear_pedido(): void
    {
        $carrito = Carrito::create(['id_cliente' => 1]);
        ItemCarrito::create([
            'id_carrito' => $carrito->id_carrito,
            'id_variacion' => 1,
            'cantidad' => 1,
        ]);

        $respuesta = $this->postJson('/api/m5/v1/pedidos?id_cliente=1', [
            'id_direccion_cliente' => 1,
            'metodoPago' => 'tarjeta',
            'cupon_descuento' => 'VENCIDO',
        ]);

        $respuesta->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-M5-CUPON');

        $this->assertSame(0, PedidoMaestro::count());
        $this->assertSame(1, ItemCarrito::where('id_carrito', $carrito->id_carrito)->count());
    }

    public function test_checkout_distribuye_descuento_de_cupon_entre_subpedidos_y_publica_importes_coherentes(): void
    {
        $comercialMock = $this->createMock(ComercialPortalContract::class);
        $comercialMock->method('validarCupon')->willReturn([
            'valido' => true,
            'id_cupon' => 99,
            'monto_descuento' => 70.03,
            'mensaje' => null,
        ]);
        $comercialMock->method('registrarUsoCupon')->willReturn(true);
        $this->app->instance(ComercialPortalContract::class, $comercialMock);

        $grupos = [
            11 => [
                'id_comercio' => 11,
                'subtotal' => 300.00,
                'costo_envio' => 10.00,
                'impuesto' => 39.00,
                'items' => [],
            ],
            12 => [
                'id_comercio' => 12,
                'subtotal' => 400.00,
                'costo_envio' => 10.00,
                'impuesto' => 52.00,
                'items' => [],
            ],
        ];

        $servicio = app(PedidosService::class);
        $detalle = $servicio->crearPedidoCheckout([
            'id_cliente' => 1,
            'id_direccion_cliente' => 1,
            'cupon_descuento' => 'CUPON-MULTI',
            'grupos_comercio' => $grupos,
            'resumen' => [
                'subtotal' => 700.00,
                'costo_envio' => 20.00,
                'impuesto' => 91.00,
            ],
        ]);

        $this->assertEqualsWithDelta(70.03, (float) $detalle['descuento'], 0.001);
        $this->assertEqualsWithDelta(740.97, (float) $detalle['monto_total'], 0.001);

        $sumaSubtotales = array_sum(array_column($detalle['subpedidos'], 'subtotal'));
        $sumaTotales = array_sum(array_column($detalle['subpedidos'], 'total'));

        $this->assertEqualsWithDelta(629.97, $sumaSubtotales, 0.001);
        $this->assertEqualsWithDelta(740.97, $sumaTotales, 0.001);
        $this->assertEqualsWithDelta(269.99, (float) $detalle['subpedidos'][0]['subtotal'], 0.001);
        $this->assertEqualsWithDelta(359.98, (float) $detalle['subpedidos'][1]['subtotal'], 0.001);

        $evento = new PedidoConfirmado((int) $detalle['id_pedido']);
        $this->assertEqualsWithDelta(629.97, array_sum(array_column($evento->subpedidos, 'subtotal')), 0.001);
        $this->assertEqualsWithDelta($evento->monto_total, array_sum(array_column($evento->subpedidos, 'total')), 0.001);
    }
}
