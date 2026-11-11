<?php

namespace Tests\Feature;

use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use App\Events\M5_Pedidos\SubpedidoDespachado;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DespacharSubpedidoM2ConsumerTest extends TestCase
{
    /**
     * Verifica que M5 despacha el subpedido y emite el evento canónico SubpedidoDespachado,
     * el cual es consumido por M2 para registrar la comisión.
     */
    public function test_despachar_subpedido_notifica_a_m2_como_consumidor_y_registra_comision(): void
    {
        Event::fake([SubpedidoDespachado::class]);

        /** @var PedidosServiceInterface $pedidosService */
        $pedidosService = $this->app->make(PedidosServiceInterface::class);

        $resultado = $pedidosService->despacharSubpedido(55, 'GUIA-EXPRESS-123');

        $this->assertEquals(55, $resultado['id_subpedido']);
        $this->assertEquals('despachado', $resultado['estado_subpedido']);
        $this->assertEquals('SubpedidoDespachado', $resultado['evento']);
        $this->assertContains('M2', $resultado['consumidores_notificados']);

        Event::assertDispatched(SubpedidoDespachado::class, function (SubpedidoDespachado $evento) {
            return $evento->id_subpedido === 55
                && $evento->guia_transporte === 'GUIA-EXPRESS-123';
        });
    }
}
