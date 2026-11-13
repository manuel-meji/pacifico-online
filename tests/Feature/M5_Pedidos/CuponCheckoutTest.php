<?php

namespace Tests\Feature\M5_Pedidos;

use App\Contracts\M1_Portal\ComercialPortalContract;
use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Contracts\M1_Portal\PortalServiceInterface;
use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Services\M5_Pedidos\Dobles\PortalStub;
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
}
