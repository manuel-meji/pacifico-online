<?php

namespace Tests\Feature\M5_Pedidos\Eventos;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Events\M5_Pedidos\PedidoConfirmado;
use App\Events\M5_Pedidos\PedidoCreado;
use App\Events\M5_Pedidos\PedidoPagado;
use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Models\M5_Pedidos\PedidoLinea;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class M5_EventosTest extends TestCase
{
    protected string $prefix = '/api/m5/v1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', [
            '--path' => 'database/migrations/M5_Pedidos',
            '--realpath' => false,
        ]);
        $this->configurarDependencias();
    }

    private function configurarDependencias(): void
    {
        $mockM3 = $this->createStub(CatalogoServiceInterface::class);
        $mockM3->method('obtenerVariacion')->willReturn([
            'id_variacion' => 101,
            'nombre' => 'Producto Evento Test',
            'precio' => 10000.00,
            'tarifa_impuesto' => 13.00,
            'id_comercio' => 1,
        ]);
        $this->app->instance(CatalogoServiceInterface::class, $mockM3);

        $mockM2 = $this->createStub(ComerciosServiceInterface::class);
        $mockM2->method('obtenerMicrositio')->willReturn([
            'id_comercio' => 1,
            'nombre' => 'Comercio Evento',
            'estado' => 'activo',
        ]);
        $this->app->instance(ComerciosServiceInterface::class, $mockM2);

        $mockM4 = $this->createStub(InventarioServiceInterface::class);
        $mockM4->method('consultarExistencias')->willReturn(50);
        $mockM4->method('solicitarReserva')->willReturn([
            'id_reserva' => '11111111-2222-3333-4444-555555555555',
            'expiracion' => now()->addMinutes(15)->toIso8601String(),
        ]);
        $this->app->instance(InventarioServiceInterface::class, $mockM4);

        $mockM6 = $this->createStub(PagosServiceInterface::class);
        $mockM6->method('procesarPago')->willReturn([
            'urlPasarelaPago' => 'https://pago.pacificos.com/checkout/evento-test',
            'transaccion_id' => 'TXN-EVT-001',
        ]);
        $this->app->instance(PagosServiceInterface::class, $mockM6);
    }

    public function test_al_crear_pedido_con_exito_se_despacha_pedido_confirmado_y_pedido_creado(): void
    {
        Event::fake([PedidoConfirmado::class, PedidoCreado::class]);

        $carrito = (new Carrito)->obtenerOCrearCarrito(5);
        (new ItemCarrito)->crearItemCarrito($carrito->id_carrito, 101, 2);

        $response = $this->postJson("{$this->prefix}/pedidos", [
            'id_cliente' => 5,
            'id_direccion_cliente' => 20,
            'nota' => 'Prueba de emisión de eventos',
        ]);

        $response->assertStatus(201);
        $pedidoId = $response->json('id_pedido');

        Event::assertDispatched(PedidoConfirmado::class, function (PedidoConfirmado $evento) use ($pedidoId) {
            return $evento->id_pedido === $pedidoId
                && $evento->id_cliente === 5
                && $evento->monto_total > 0
                && Str::isUuid($evento->id_evento)
                && ! empty($evento->fecha_evento)
                && count($evento->subpedidos) > 0
                && count($evento->reservas) > 0;
        });

        Event::assertDispatched(PedidoCreado::class, function (PedidoCreado $evento) use ($pedidoId) {
            return $evento->id_pedido === $pedidoId && $evento->id_cliente === 5;
        });
    }

    public function test_al_confirmarse_el_pago_se_despacha_pedido_pagado_con_payload_canonico_completo(): void
    {
        Event::fake([PedidoPagado::class]);

        $pedido = new PedidoMaestro;
        $pedidoId = $pedido->crearPedidoMaestro(22600.00, 5);
        $sub = (new Subpedido)->crearSubpedidos([
            [
                'id_comercio' => 1,
                'subtotal' => 20000.00,
                'costo_envio' => 0.00,
                'impuesto' => 2600.00,
            ],
        ], $pedidoId)[0];

        (new PedidoLinea)->crearLinea(
            $sub->id_subpedido,
            101,
            2,
            10000.00,
            13.00,
            '11111111-2222-3333-4444-555555555555'
        );

        $response = $this->postJson("{$this->prefix}/pedidos/{$pedidoId}/pago-confirmado", [
            'referencia_pago' => 'TXN-PAGADO-CANONICO',
            'monto_pagado' => 22600.00,
        ]);

        $response->assertStatus(200);

        Event::assertDispatched(PedidoPagado::class, function (PedidoPagado $evento) use ($pedidoId) {
            // 1. Metadatos obligatorios
            $esUuid = Str::isUuid($evento->id_evento);
            $esTimestampValido = ! empty($evento->timestamp) && strtotime($evento->timestamp) !== false;
            $tipoEventoCorrecto = $evento->tipo_evento === 'PedidoPagado';
            $versionContratoCorrecta = $evento->version_contrato === '1.1.0';

            // 2. Datos raíz obligatorios
            $datos = $evento->datos;
            $datosRaizCorrectos = $datos['pedido_id'] === $pedidoId
                && $datos['cliente_id'] === 5
                && $datos['monto_total'] == 22600.00
                && $datos['estado'] === 'PAGADO';

            // 3. Desglose canónico de subpedidos
            $subpedidosValidos = count($datos['subpedidos']) === 1
                && isset($datos['subpedidos'][0]['subpedido_id'])
                && isset($datos['subpedidos'][0]['comercio_id'])
                && isset($datos['subpedidos'][0]['subtotal'])
                && isset($datos['subpedidos'][0]['iva'])
                && isset($datos['subpedidos'][0]['envio'])
                && isset($datos['subpedidos'][0]['lineas'])
                && count($datos['subpedidos'][0]['lineas']) === 1
                && $datos['subpedidos'][0]['lineas'][0]['id_variacion'] === 101
                && $datos['subpedidos'][0]['lineas'][0]['cantidad'] === 2;

            return $esUuid
                && $esTimestampValido
                && $tipoEventoCorrecto
                && $versionContratoCorrecta
                && $datosRaizCorrectos
                && $subpedidosValidos;
        });
    }

    public function test_transicion_de_estado_en_pedidos_service_actualiza_a_pagado_y_despacha_evento(): void
    {
        Event::fake([PedidoPagado::class]);

        $pedido = new PedidoMaestro;
        $pedidoId = $pedido->crearPedidoMaestro(15000.00, 8);
        $sub = (new Subpedido)->crearSubpedidos([
            ['id_comercio' => 1, 'subtotal' => 15000.00, 'costo_envio' => 0.00, 'impuesto' => 1950.00],
        ], $pedidoId)[0];
        (new PedidoLinea)->crearLinea($sub->id_subpedido, 101, 1, 15000.00, 13.00);

        /** @var PedidosServiceInterface $servicio */
        $servicio = app(PedidosServiceInterface::class);
        $resultado = $servicio->marcarComoPagado($pedidoId, 'REF-SERVICE-999');

        $this->assertTrue($resultado);
        $this->assertSame('pagado', $pedido->fresh()->estado_pedido);
        $this->assertSame('pagado', $sub->fresh()->estado_subpedido);

        Event::assertDispatched(PedidoPagado::class, function (PedidoPagado $evento) use ($pedidoId) {
            return $evento->datos['pedido_id'] === $pedidoId
                && $evento->datos['estado'] === 'PAGADO'
                && $evento->tipo_evento === 'PedidoPagado'
                && $evento->version_contrato === '1.1.0'
                && Str::isUuid($evento->id_evento);
        });
    }
}
