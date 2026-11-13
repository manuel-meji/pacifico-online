<?php

namespace Tests\Feature;

use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DespacharSubpedidoM2ConsumerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', [
            '--path' => 'database/migrations/M5_Pedidos',
            '--realpath' => false,
        ]);
    }

    /**
     * Verifica que M5 despacha el subpedido y emite el evento canónico SubpedidoDespachado,
     * el cual es consumido por M2 para registrar la comisión.
     */
    public function test_despachar_subpedido_notifica_a_m2_como_consumidor_y_registra_comision(): void
    {
        $pedidoId = (new PedidoMaestro)->crearPedidoMaestro(15000.00, 1);

        $subpedido = new Subpedido;
        $subpedido->forceFill([
            'id_subpedido' => 55,
            'id_pedido' => $pedidoId,
            'id_comercio' => 2,
            'subtotal' => 15000.00,
            'costo_envio' => 0.00,
            'impuesto' => 1950.00,
            'descuento' => 0.00,
            'total' => 16950.00,
            'estado_subpedido' => 'pagado',
        ])->save();

        Event::fake([SubpedidoDespachado::class]);

        /** @var PedidosServiceInterface $pedidosService */
        $pedidosService = $this->app->make(PedidosServiceInterface::class);

        $resultado = $pedidosService->despacharSubpedido(55, 'GUIA-EXPRESS-123');

        $this->assertEquals(55, $resultado['id_subpedido']);
        $this->assertEquals('despachado', $resultado['estado_subpedido']);
        $this->assertEquals('SubpedidoDespachado', $resultado['evento']);
        $this->assertContains('M2', $resultado['consumidores_notificados']);

        Event::assertDispatched(SubpedidoDespachado::class, function (SubpedidoDespachado $evento) use ($pedidoId) {
            return $evento->id_subpedido === 55
                && $evento->id_pedido === $pedidoId
                && $evento->id_comercio === 2
                && $evento->guia_transporte === 'GUIA-EXPRESS-123';
        });
    }
}
