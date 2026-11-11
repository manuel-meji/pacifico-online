<?php

namespace Tests\Feature\M5_Pedidos;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Models\M5_Pedidos\PedidoLinea;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use App\Services\M5_Pedidos\Dobles\CatalogoStub;
use App\Services\M5_Pedidos\Dobles\ComerciosStub;
use Tests\Doubles\M5\InventarioEspia;
use Tests\Doubles\M5\PagosEspia;
use Tests\TestCase;

/**
 * Pruebas de aceptación de M5: recorren un caso de uso completo por las rutas HTTP.
 *
 * - CP-011: CU-004 Confirmar compra y generar pedido(s), con productos de dos comercios.
 * - CP-012: CU-007 Gestionar cancelación de pedido, sobre un pedido recién pagado.
 *
 * M5 no tiene inicio de sesión propio: el cliente se identifica con id_cliente en la petición.
 */
class AceptacionM5Test extends TestCase
{
    private InventarioEspia $inventario;

    private PagosEspia $pagos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', [
            '--path' => 'database/migrations/M5_Pedidos',
            '--realpath' => false,
        ]);

        $this->inventario = new InventarioEspia;
        $this->pagos = new PagosEspia;

        // Sin setIdComercio(): la variación 2 pertenece al comercio 1 y la 3 al comercio 4.
        $this->app->instance(CatalogoServiceInterface::class, new CatalogoStub);
        $this->app->instance(InventarioServiceInterface::class, $this->inventario);
        $this->app->instance(ComerciosServiceInterface::class, new ComerciosStub);
        $this->app->instance(PagosServiceInterface::class, $this->pagos);
    }

    public function test_cp011_compra_completa_con_productos_de_dos_comercios(): void
    {
        $idCliente = 51;

        // El cliente agrega un producto de la Tienda 1 y uno de la Tienda 4.
        $this->postJson('/m5/carrito/items', ['id_cliente' => $idCliente, 'id_variacion' => 2, 'cantidad' => 1])
            ->assertStatus(201);
        $this->postJson('/m5/carrito/items', ['id_cliente' => $idCliente, 'id_variacion' => 3, 'cantidad' => 1])
            ->assertStatus(201);

        // Consulta el carrito: dos grupos de comercio y desglose de costos.
        $carrito = $this->getJson('/m5/carrito?id_cliente='.$idCliente)
            ->assertStatus(200)
            ->assertJsonCount(2, 'grupos_comercio')
            ->assertJsonCount(2, 'items');
        // 10.000 + 8.500 de subtotal, IVA 13 % por línea y envío de 1.500 por comercio.
        $this->assertEqualsWithDelta(18500.0, $carrito->json('resumen.subtotal'), 0.001);
        $this->assertEqualsWithDelta(2405.0, $carrito->json('resumen.impuesto'), 0.001);
        $this->assertEqualsWithDelta(3000.0, $carrito->json('resumen.costo_envio'), 0.001);
        $this->assertEqualsWithDelta(23905.0, $carrito->json('resumen.total'), 0.001);

        // Confirma la compra.
        $checkout = $this->postJson('/m5/pedidos', ['id_cliente' => $idCliente, 'metodoPago' => 'tarjeta'])
            ->assertStatus(201);
        $this->assertEqualsWithDelta(23905.0, (float) $checkout->json('pedido.monto_total'), 0.001);

        // Un pedido maestro, dos subpedidos (uno por comercio) y el carrito vacío.
        $this->assertDatabaseCount('pedidos_maestros', 1);
        $this->assertDatabaseHas('pedidos_maestros', [
            'id_pedido' => $checkout->json('pedido.id_pedido'),
            'id_cliente' => $idCliente,
            'estado_pedido' => 'confirmado',
        ]);
        $this->assertDatabaseCount('pedidos_subpedidos', 2);
        $this->assertDatabaseHas('pedidos_subpedidos', ['id_comercio' => 1, 'estado_subpedido' => 'pendiente']);
        $this->assertDatabaseHas('pedidos_subpedidos', ['id_comercio' => 4, 'estado_subpedido' => 'pendiente']);
        $this->assertDatabaseCount('items_carrito', 0);

        $this->getJson('/m5/carrito?id_cliente='.$idCliente)
            ->assertStatus(200)
            ->assertJsonCount(0, 'items');
    }

    public function test_cp012_cancelacion_de_un_pedido_recien_pagado_registra_motivo_y_reembolso(): void
    {
        $idCliente = 52;
        $motivo = 'Cliente cambió de opinión';

        // Pedido recién pagado.
        $this->postJson('/m5/carrito/items', ['id_cliente' => $idCliente, 'id_variacion' => 2, 'cantidad' => 1])
            ->assertStatus(201);
        $checkout = $this->postJson('/m5/pedidos', ['id_cliente' => $idCliente])->assertStatus(201);
        $idPedido = $checkout->json('pedido.id_pedido');
        $idSubpedido = $checkout->json('pedido.subpedidos.0.id_subpedido');
        $montoTotal = (float) $checkout->json('pedido.monto_total');

        $this->postJson('/m5/pedidos/'.$idPedido.'/pago-confirmado', ['refPago' => 'REF-CP012'])
            ->assertStatus(200);
        $this->assertSame('pagado', PedidoMaestro::find($idPedido)->estado_pedido);

        // Contrato M4 v1.3.0: al pagar, M5 confirma la reserva enviando también el pedido y el subpedido.
        $idReserva = PedidoLinea::where('id_subpedido', $idSubpedido)->value('id_reserva');
        $this->assertNotEmpty($idReserva);
        $this->assertSame([
            ['id_reserva' => $idReserva, 'id_pedido' => $idPedido, 'id_subpedido' => $idSubpedido],
        ], $this->inventario->confirmadas);

        // El cliente cancela indicando el motivo.
        $this->postJson('/m5/pedidos/'.$idPedido.'/cancelar', ['motivo' => $motivo])
            ->assertStatus(200);

        // El subpedido y el pedido quedan cancelados y la cancelación queda registrada.
        $this->assertSame('cancelado', Subpedido::find($idSubpedido)->estado_subpedido);
        $this->assertSame('cancelado', PedidoMaestro::find($idPedido)->estado_pedido);
        $this->assertDatabaseHas('cancelaciones_pedido', [
            'id_pedido' => $idPedido,
            'motivo' => $motivo,
            'requiere_reembolso' => 1,
        ]);

        // RN-M5-10: se libera la reserva en M4 y se solicita el reembolso en M6.
        $this->assertContains($idReserva, $this->inventario->liberadas);
        $this->assertCount(1, $this->pagos->reembolsos);
        $this->assertSame($idPedido, $this->pagos->reembolsos[0]['id_pedido']);
        $this->assertEqualsWithDelta($montoTotal, $this->pagos->reembolsos[0]['monto'], 0.001);
    }
}
