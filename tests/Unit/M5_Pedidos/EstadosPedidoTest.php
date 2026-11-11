<?php

namespace Tests\Unit\M5_Pedidos;

use App\Events\M5_Pedidos\PedidoPagado;
use App\Models\M5_Pedidos\Subpedido;
use App\Services\M5_Pedidos\Dobles\InventarioStub;
use App\Services\M5_Pedidos\Dobles\PagosStub;
use App\Services\M5_Pedidos\PedidosService;
use DomainException;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Unit\M5_Pedidos\Concerns\PreparaBaseM5;

/**
 * CP-007 y CP-008: máquina de estados del subpedido (RN-M5-13, RF-011).
 */
class EstadosPedidoTest extends TestCase
{
    use PreparaBaseM5;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrarTablasM5();
    }

    public function test_cp007_la_confirmacion_de_pago_pasa_pedido_y_subpedido_de_pendiente_a_pagado(): void
    {
        // Solo se simula PedidoPagado: los eventos de Eloquent deben seguir activos.
        Event::fake([PedidoPagado::class]);
        [$pedido, $subpedido] = $this->crearPedidoConSubpedido(41);
        $servicio = new PedidosService(null, null, new InventarioStub, new PagosStub);

        $this->assertTrue($servicio->marcarComoPagado($pedido->id_pedido, 'REF-CP007'));

        $this->assertSame('pagado', $pedido->fresh()->estado_pedido);
        $this->assertSame('pagado', $subpedido->fresh()->estado_subpedido);
        $this->assertDatabaseHas('estados_pedido', [
            'id_subpedido' => $subpedido->id_subpedido,
            'estado_anterior' => 'pendiente',
            'estado' => 'pagado',
        ]);
        Event::assertDispatched(PedidoPagado::class);
    }

    public function test_cp008_no_se_puede_pasar_de_pendiente_a_entregado_sin_pasar_por_despacho(): void
    {
        [, $subpedido] = $this->crearPedidoConSubpedido(42);

        $this->assertFalse(Subpedido::esTransicionPermitida('pendiente', 'entregado'));

        try {
            $subpedido->cambiarEstado('entregado', 'Intento de saltar el despacho');
            $this->fail('Se esperaba DomainException por transición inválida.');
        } catch (DomainException $excepcion) {
            $this->assertSame(409, $excepcion->getCode());
        }

        $this->assertSame('pendiente', $subpedido->fresh()->estado_subpedido);
        $this->assertDatabaseMissing('estados_pedido', [
            'id_subpedido' => $subpedido->id_subpedido,
            'estado' => 'entregado',
        ]);
    }
}
