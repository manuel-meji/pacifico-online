<?php

namespace Tests\Feature;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use Tests\TestCase;

class DespacharSubpedidoM2ConsumerTest extends TestCase
{
    /**
     * Verifica que M5 despacha el subpedido, notifica a M2 y registra la comision.
     */
    public function test_despachar_subpedido_notifica_a_m2_como_consumidor_y_registra_comision(): void
    {
        $mockComercios = $this->createMock(ComerciosServiceInterface::class);
        $mockComercios->expects($this->once())
            ->method('registrarComision')
            ->with($this->equalTo(101), $this->equalTo(15000.00))
            ->willReturn(true);

        $this->app->instance(ComerciosServiceInterface::class, $mockComercios);

        /** @var PedidosServiceInterface $pedidosService */
        $pedidosService = $this->app->make(PedidosServiceInterface::class);

        $resultado = $pedidosService->despacharSubpedido(55, 'GUIA-EXPRESS-123');

        $this->assertEquals(55, $resultado['id_subpedido']);
        $this->assertEquals('despachado', $resultado['estado_subpedido']);
        $this->assertEquals('SubpedidoDespachado', $resultado['evento']);
        $this->assertContains('M2', $resultado['consumidores_notificados']);
        $this->assertTrue($resultado['comision_registrada_m2']);
    }
}
