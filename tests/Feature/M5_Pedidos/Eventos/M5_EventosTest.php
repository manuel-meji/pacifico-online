<?php

namespace Tests\Feature\M5_Pedidos\Eventos;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Events\M5_Pedidos\PedidoCancelado;
use App\Events\M5_Pedidos\PedidoConfirmado;
use App\Events\M5_Pedidos\PedidoCreado;
use App\Events\M5_Pedidos\PedidoPagado;
use App\Events\M5_Pedidos\SubpedidoCancelado;
use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Events\M5_Pedidos\SubpedidoEntregado;
use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Models\M5_Pedidos\PedidoLinea;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use App\Services\M5_Pedidos\Dobles\ComerciosStub;
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

        $mockM2 = (new ComerciosStub)->setComercioSuspendido(false);
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

    public function test_al_despachar_subpedido_se_emite_evento_subpedido_despachado_con_metadatos_canonicos(): void
    {
        Event::fake([SubpedidoDespachado::class]);

        $pedido = new PedidoMaestro;
        $pedidoId = $pedido->crearPedidoMaestro(20000.00, 1);
        $sub = (new Subpedido)->crearSubpedidos([
            ['id_comercio' => 2, 'subtotal' => 20000.00, 'costo_envio' => 1500.00, 'impuesto' => 2600.00],
        ], $pedidoId)[0];
        (new PedidoLinea)->crearLinea($sub->id_subpedido, 101, 2, 10000.00, 13.00, '99999999-aaaa-bbbb-cccc-dddddddddddd');

        $response = $this->postJson("{$this->prefix}/subpedidos/{$sub->id_subpedido}/despachar", [
            'guia_seguimiento' => 'GUIA-TRACK-123',
        ]);

        $response->assertStatus(200);

        Event::assertDispatched(SubpedidoDespachado::class, function (SubpedidoDespachado $evento) use ($sub, $pedidoId) {
            return Str::isUuid($evento->id_evento)
                && $evento->evento === 'SubpedidoDespachado'
                && $evento->id_subpedido === $sub->id_subpedido
                && $evento->id_pedido === $pedidoId
                && $evento->id_comercio === 2
                && $evento->estado_subpedido === 'despachado'
                && $evento->guia_transporte === 'GUIA-TRACK-123'
                && $evento->subtotal == 20000.00
                && ! empty($evento->fecha_evento)
                && ! empty($evento->fecha)
                && ! empty($evento->fecha_despacho)
                && count($evento->reservas) > 0;
        });
    }

    public function test_al_entregar_subpedido_se_emite_evento_subpedido_entregado_con_fechas(): void
    {
        Event::fake([SubpedidoEntregado::class]);

        $pedido = new PedidoMaestro;
        $pedidoId = $pedido->crearPedidoMaestro(15000.00, 1);
        $sub = (new Subpedido)->crearSubpedidos([
            ['id_comercio' => 2, 'subtotal' => 15000.00, 'costo_envio' => 0.00, 'impuesto' => 1950.00],
        ], $pedidoId)[0];
        $sub->despachar('GUIA-001');

        $response = $this->postJson("{$this->prefix}/subpedidos/{$sub->id_subpedido}/entregar");

        $response->assertStatus(200);

        Event::assertDispatched(SubpedidoEntregado::class, function (SubpedidoEntregado $evento) use ($sub, $pedidoId) {
            return Str::isUuid($evento->id_evento)
                && $evento->id_subpedido === $sub->id_subpedido
                && $evento->id_pedido === $pedidoId
                && $evento->id_comercio === 2
                && ! empty($evento->fecha)
                && ! empty($evento->fecha_entrega);
        });
    }

    public function test_al_cancelar_subpedido_parcial_sc02_se_emite_evento_subpedido_cancelado(): void
    {
        Event::fake([SubpedidoCancelado::class]);

        $pedido = new PedidoMaestro;
        $pedidoId = $pedido->crearPedidoMaestro(30000.00, 1);
        $subpedidos = (new Subpedido)->crearSubpedidos([
            ['id_comercio' => 1, 'subtotal' => 15000.00, 'costo_envio' => 0.00, 'impuesto' => 1950.00],
            ['id_comercio' => 2, 'subtotal' => 15000.00, 'costo_envio' => 0.00, 'impuesto' => 1950.00],
        ], $pedidoId);

        $response = $this->postJson("{$this->prefix}/subpedidos/{$subpedidos[0]->id_subpedido}/cancelar", [
            'motivo' => 'Anulación solicitada por cliente comercio 1',
        ]);

        $response->assertStatus(200);

        Event::assertDispatched(SubpedidoCancelado::class, function (SubpedidoCancelado $evento) use ($subpedidos, $pedidoId) {
            return Str::isUuid($evento->id_evento)
                && $evento->id_subpedido === $subpedidos[0]->id_subpedido
                && $evento->id_pedido === $pedidoId
                && $evento->id_comercio === 1
                && $evento->motivo === 'Anulación solicitada por cliente comercio 1'
                && $evento->requiere_reembolso === true
                && $evento->monto_subpedido > 0
                && count($evento->reservas) > 0;
        });
    }

    public function test_al_cancelar_pedido_maestro_total_se_emite_evento_pedido_cancelado(): void
    {
        Event::fake([PedidoCancelado::class]);

        $pedido = new PedidoMaestro;
        $pedidoId = $pedido->crearPedidoMaestro(15000.00, 1);
        $sub = (new Subpedido)->crearSubpedidos([
            ['id_comercio' => 1, 'subtotal' => 15000.00, 'costo_envio' => 0.00, 'impuesto' => 1950.00],
        ], $pedidoId)[0];

        $response = $this->postJson("{$this->prefix}/pedidos/{$pedidoId}/cancelar", [
            'motivo' => 'Cancelación de pedido total',
        ]);

        $response->assertStatus(200);

        Event::assertDispatched(PedidoCancelado::class, function (PedidoCancelado $evento) use ($pedidoId) {
            return Str::isUuid($evento->id_evento)
                && $evento->id_pedido === $pedidoId
                && $evento->motivo === 'Cancelación de pedido total'
                && $evento->requiere_reembolso === true
                && count($evento->reservas) > 0;
        });
    }

    public function test_pedido_confirmado_rechaza_registro_inexistente_y_no_fabrica_uuids_ficticios(): void
    {
        try {
            new PedidoConfirmado(999999);
            $this->fail('Se esperaba InvalidArgumentException para un pedido inexistente.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('999999', $e->getMessage());
        }

        $pedidoSinSubpedidos = (new PedidoMaestro)->forceFill([
            'id_pedido' => 880,
            'id_cliente' => 7,
            'monto_total' => 5000.00,
        ]);
        $pedidoSinSubpedidos->setRelation('subpedidos', collect());

        try {
            new PedidoConfirmado($pedidoSinSubpedidos);
            $this->fail('Se esperaba InvalidArgumentException para un pedido sin subpedidos.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('880', $e->getMessage());
        }

        $pedidoId = (new PedidoMaestro)->crearPedidoMaestro(12000.00, 9);
        $sub = (new Subpedido)->crearSubpedidos([
            ['id_comercio' => 3, 'subtotal' => 12000.00, 'costo_envio' => 0.00, 'impuesto' => 1560.00],
        ], $pedidoId)[0];
        (new PedidoLinea)->crearLinea($sub->id_subpedido, 101, 1, 12000.00, 13.00);

        $eventoSinReserva = new PedidoConfirmado($pedidoId);
        $this->assertSame([], $eventoSinReserva->reservas);
        $this->assertSame([], $eventoSinReserva->subpedidos[0]['reservas']);
    }

    public function test_subpedido_despachado_rechaza_registro_inexistente_y_no_fabrica_uuids_ficticios(): void
    {
        try {
            new SubpedidoDespachado(999999);
            $this->fail('Se esperaba InvalidArgumentException para un subpedido inexistente.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('999999', $e->getMessage());
        }

        $subInvalido = (new Subpedido)->forceFill([
            'id_subpedido' => 44,
            'id_pedido' => 12,
            'id_comercio' => 0,
            'subtotal' => 1000.00,
        ]);
        $subInvalido->setRelation('lineas', collect());

        try {
            new SubpedidoDespachado($subInvalido);
            $this->fail('Se esperaba InvalidArgumentException para id_comercio inválido.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('identificadores reales', $e->getMessage());
        }

        $pedidoId = (new PedidoMaestro)->crearPedidoMaestro(15000.00, 9);
        $sub = (new Subpedido)->crearSubpedidos([
            ['id_comercio' => 4, 'subtotal' => 15000.00, 'costo_envio' => 0.00, 'impuesto' => 1950.00],
        ], $pedidoId)[0];
        (new PedidoLinea)->crearLinea($sub->id_subpedido, 101, 1, 15000.00, 13.00);

        $eventoSinReserva = new SubpedidoDespachado($sub->id_subpedido, 'GUIA-REAL-01');
        $this->assertSame([], $eventoSinReserva->reservas);
        $this->assertSame($pedidoId, $eventoSinReserva->id_pedido);
        $this->assertSame(4, $eventoSinReserva->id_comercio);
    }
}
