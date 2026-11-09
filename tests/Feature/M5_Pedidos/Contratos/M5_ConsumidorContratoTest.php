<?php

namespace Tests\Feature\M5_Pedidos\Contratos;

use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Contracts\M1_Portal\PortalServiceInterface;
use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Events\M5_Pedidos\PedidoPagado;
use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Models\M5_Pedidos\PedidoLinea;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use App\Models\Nucleo\Usuario;
use App\Services\M5_Pedidos\Dobles\ComerciosStub;
use Illuminate\Support\Facades\Event;
use Tests\Doubles\M1\PortalPublicoFake;
use Tests\TestCase;

/**
 * Interfaz de doble interna para simular el contrato de M1 Portal (v1.1.0)
 * sin modificar los contratos PHP propietarios del subgrupo 1.
 */
interface PortalClienteConsumerInterface extends PortalServiceInterface
{
    public function obtenerCliente(int $idCliente): array;

    public function obtenerDireccionCliente(int $idCliente, int $idDireccionCliente): array;

    public function obtenerPreciosVigentes(array $idsVariacion): array;
}

class M5_ConsumidorContratoTest extends TestCase
{
    protected string $prefix = '/api/m5/v1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', [
            '--path' => 'database/migrations/M5_Pedidos',
            '--realpath' => false,
        ]);
        $this->configurarDependenciasBase();
    }

    private function configurarDependenciasBase(): void
    {
        $mockM3 = $this->createStub(CatalogoServiceInterface::class);
        $mockM3->method('obtenerVariacion')->willReturn([
            'id_variacion' => 101,
            'nombre' => 'Variación Test',
            'precio' => 10000.00,
            'tarifa_impuesto' => 13.00,
            'id_comercio' => 1,
        ]);
        $this->app->instance(CatalogoServiceInterface::class, $mockM3);

        $mockM2 = (new ComerciosStub)->setComercioSuspendido(false);
        $this->app->instance(ComerciosServiceInterface::class, $mockM2);

        $mockM4 = $this->createStub(InventarioServiceInterface::class);
        $mockM4->method('consultarExistencias')->willReturn(10);
        $mockM4->method('solicitarReserva')->willReturn([
            'id_reserva' => '550e8400-e29b-41d4-a716-446655440000',
            'expiracion' => now()->addMinutes(15)->toIso8601String(),
        ]);
        $this->app->instance(InventarioServiceInterface::class, $mockM4);

        $mockM6 = $this->createStub(PagosServiceInterface::class);
        $mockM6->method('procesarPago')->willReturn([
            'urlPasarelaPago' => 'https://pago.pacificos.com/checkout/consumer-test',
            'transaccion_id' => 'TXN-M6-001',
        ]);
        $this->app->instance(PagosServiceInterface::class, $mockM6);
    }

    // =========================================================================
    // 1. Consumidor de M1 (Portal / Clientes - Contrato v1.1.0)
    // =========================================================================

    public function test_consumidor_m1_procesa_direccion_y_datos_del_cliente_al_generar_pedido(): void
    {
        $mockM1 = $this->createMock(PortalClienteConsumerInterface::class);
        $mockM1->expects($this->once())
            ->method('obtenerCliente')
            ->with(42)
            ->willReturn([
                'id_cliente' => 42,
                'nombre' => 'Juan Pérez',
                'identificacion' => '1-0234-0567',
                'email' => 'juan.perez@test.com',
            ]);

        $mockM1->expects($this->once())
            ->method('obtenerDireccionCliente')
            ->with(42, 99)
            ->willReturn([
                'id_direccion' => 99,
                'id_cliente' => 42,
                'direccion_exacta' => 'Avenida Central, Calle 5',
                'provincia' => 'San José',
                'canton' => 'Central',
                'distrito' => 'Carmen',
            ]);

        $this->app->instance(PortalServiceInterface::class, $mockM1);

        $carrito = (new Carrito)->obtenerOCrearCarrito(42);
        (new ItemCarrito)->crearItemCarrito($carrito->id_carrito, 101, 1);

        $response = $this->postJson("{$this->prefix}/pedidos", [
            'id_cliente' => 42,
            'id_direccion_cliente' => 99,
            'nota' => 'Entregar en recepción',
        ]);

        $response->assertStatus(201);
        $pedidoId = $response->json('id_pedido');
        $this->assertNotNull($pedidoId);

        $pedido = PedidoMaestro::find($pedidoId);
        $this->assertSame(42, (int) $pedido->id_cliente);
        $this->assertSame(99, (int) $pedido->id_direccion_cliente);
    }

    public function test_consumidor_m1_obtiene_id_cliente_por_usuario_con_portal_publico_contract(): void
    {
        $this->app->instance(PortalPublicoContract::class, new PortalPublicoFake);

        $usuario = new Usuario;
        $usuario->forceFill(['id_usuario' => PortalPublicoFake::USUARIO_CON_CLIENTE]);

        $response = $this->actingAs($usuario)
            ->postJson("{$this->prefix}/carrito/items", [
                'id_variacion' => 101,
                'cantidad' => 1,
            ]);

        $response->assertStatus(201);
        $this->assertSame(PortalPublicoFake::CLIENTE_CON_DIRECCIONES, $response->json('id_cliente'));
    }

    public function test_consumidor_m1_aplica_precios_vigentes_de_promocion_en_checkout_y_subpedido_m2(): void
    {
        $mockM1 = $this->createMock(PortalClienteConsumerInterface::class);
        $mockM1->expects($this->once())
            ->method('obtenerPreciosVigentes')
            ->with([101])
            ->willReturn([
                [
                    'id_variacion' => 101,
                    'precio_base' => 10000.00,
                    'precio_final' => 8000.00,
                    'id_promocion' => 9,
                    'fin_promocion' => '2026-11-30T23:59:59-06:00',
                ],
            ]);

        $this->app->instance(PortalServiceInterface::class, $mockM1);

        $carrito = (new Carrito)->obtenerOCrearCarrito(42);
        (new ItemCarrito)->crearItemCarrito($carrito->id_carrito, 101, 2);

        $response = $this->postJson("{$this->prefix}/pedidos", [
            'id_cliente' => 42,
            'id_direccion_cliente' => 1,
        ]);

        $response->assertStatus(201);
        $pedidoId = (int) $response->json('id_pedido');

        $subpedido = Subpedido::where('id_pedido', $pedidoId)->first();
        $this->assertNotNull($subpedido);
        $this->assertEqualsWithDelta(16000.00, (float) $subpedido->subtotal, 0.001);

        $linea = PedidoLinea::where('id_subpedido', $subpedido->id_subpedido)->first();
        $this->assertNotNull($linea);
        $this->assertEqualsWithDelta(8000.00, (float) $linea->precio_unitario, 0.001);
        $this->assertEqualsWithDelta(4000.00, (float) $linea->descuento, 0.001);
        $this->assertSame(9, (int) $linea->id_promocion);
    }

    // =========================================================================
    // 2. Consumidor de M4 (Inventario - Contrato v1.2.1)
    // =========================================================================

    public function test_consumidor_m4_caso_exitoso_asigna_reserva_id_a_lineas_del_pedido(): void
    {
        $uuidReserva = '987e6543-e21b-12d3-a456-426614174000';

        $mockM4 = $this->createMock(InventarioModuleInterface::class);
        $mockM4->expects($this->once())
            ->method('reservarExistencias')
            ->willReturn([
                'id_reserva' => $uuidReserva,
                'reserva_id' => $uuidReserva,
                'estado' => 'reservado',
                'expiracion' => now()->addMinutes(15)->toIso8601String(),
            ]);

        $this->app->instance(InventarioModuleInterface::class, $mockM4);
        $this->app->instance(InventarioServiceInterface::class, $mockM4);

        $carrito = (new Carrito)->obtenerOCrearCarrito(10);
        (new ItemCarrito)->crearItemCarrito($carrito->id_carrito, 101, 2);

        $response = $this->postJson("{$this->prefix}/pedidos", [
            'id_cliente' => 10,
            'id_direccion_cliente' => 1,
        ]);

        $response->assertStatus(201);
        $pedidoId = $response->json('id_pedido');
        $linea = PedidoLinea::whereHas('subpedido', fn ($q) => $q->where('id_pedido', $pedidoId))->first();

        $this->assertNotNull($linea);
        $this->assertSame($uuidReserva, $linea->id_reserva);
    }

    public function test_consumidor_m4_caso_error_stock_insuficiente_aborta_y_retorna_err_m5_002(): void
    {
        $mockM4 = $this->createMock(InventarioModuleInterface::class);
        $mockM4->expects($this->once())
            ->method('reservarExistencias')
            ->willThrowException(new \RuntimeException('Existencia insuficiente en bodega'));

        $this->app->instance(InventarioModuleInterface::class, $mockM4);
        $this->app->instance(InventarioServiceInterface::class, $mockM4);

        $carrito = (new Carrito)->obtenerOCrearCarrito(10);
        (new ItemCarrito)->crearItemCarrito($carrito->id_carrito, 101, 100);

        $response = $this->postJson("{$this->prefix}/pedidos", [
            'id_cliente' => 10,
            'id_direccion_cliente' => 1,
        ]);

        $response->assertStatus(409);
        $this->assertSame('ERR-M5-002', $response->json('error.codigo'));
    }

    // =========================================================================
    // 3. Consumidor de M6 (Pagos - Contrato v1.1.1)
    // =========================================================================

    public function test_consumidor_m6_procesa_confirmacion_pago_actualiza_a_pagado_y_programa_evento(): void
    {
        Event::fake([PedidoPagado::class]);

        $pedido = new PedidoMaestro;
        $pedidoId = $pedido->crearPedidoMaestro(25000.00, 7);
        $sub = (new Subpedido)->crearSubpedidos([
            ['id_comercio' => 1, 'subtotal' => 25000.0, 'costo_envio' => 0.0, 'impuesto' => 3250.0],
        ], $pedidoId)[0];
        (new PedidoLinea)->crearLinea($sub->id_subpedido, 101, 2, 12500.0, 13.0);

        $response = $this->postJson("{$this->prefix}/pedidos/{$pedidoId}/pago-confirmado", [
            'referencia_pago' => 'TXN-PAGADO-777',
            'monto_pagado' => 28250.00,
        ]);

        $response->assertStatus(200);

        $this->assertSame('pagado', $pedido->fresh()->estado_pedido);
        $this->assertSame('pagado', $sub->fresh()->estado_subpedido);

        Event::assertDispatched(PedidoPagado::class, function (PedidoPagado $evento) use ($pedidoId) {
            return $evento->datos['pedido_id'] === $pedidoId
                && $evento->datos['estado'] === 'PAGADO'
                && $evento->tipo_evento === 'PedidoPagado'
                && $evento->version_contrato === '1.1.0'
                && ! empty($evento->id_evento)
                && ! empty($evento->timestamp)
                && count($evento->datos['subpedidos']) > 0;
        });
    }
}
