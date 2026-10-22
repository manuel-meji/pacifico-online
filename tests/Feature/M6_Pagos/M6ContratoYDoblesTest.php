<?php

namespace Tests\Feature\M6_Pagos;

use Tests\TestCase;
use App\Contracts\M6_Pagos\PasarelaPagoInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;

class M6ContratoYDoblesTest extends TestCase
{
    /** @test */
    public function permite_procesar_un_cobro_con_el_simulador_de_pasarela()
    {
        $pasarela = $this->app->make(PasarelaPagoInterface::class);

        $respuesta = $pasarela->procesarCobro('ORD-888', 25000.00, ['metodo' => 'TARJETA']);

        $this->assertTrue($respuesta['exitoso']);
        $this->assertNotNull($respuesta['referencia_proveedor']);
        $this->assertEquals('00', $respuesta['codigo_respuesta']);
    }

    /** @test */
    public function obtiene_el_pedido_desde_el_mock_de_m5()
    {
        $servicioPedidos = $this->app->make(PedidosServiceInterface::class);

        $pedido = $servicioPedidos->obtenerPedido(1001);

        $this->assertEquals(1001, $pedido['id_pedido']);
        $this->assertEquals('CONFIRMADO', $pedido['estado']);
        $this->assertEquals(25000.00, $pedido['monto_total']);
    }
}
