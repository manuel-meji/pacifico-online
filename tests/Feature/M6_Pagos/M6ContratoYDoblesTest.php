<?php

namespace Tests\Feature\M6_Pagos;

use App\Contracts\M6_Pagos\PasarelaPagoInterface;
use App\Services\M6_Pagos\Clients\PedidoClient;
use App\Services\M6_Pagos\Simuladores\PasarelaPagoSimulador;
use Tests\TestCase;

class M6ContratoYDoblesTest extends TestCase
{
    public function test_permite_procesar_un_cobro_con_el_simulador_de_pasarela(): void
    {
        $pasarela = $this->app->make(PasarelaPagoInterface::class);

        $respuesta = $pasarela->procesarCobro('ORD-888', 25000.00, ['token_medio_pago' => 'tok_visa_ok']);

        $this->assertTrue($respuesta['exitoso']);
        $this->assertNotNull($respuesta['referencia_proveedor']);
        $this->assertSame('00', $respuesta['codigo_respuesta']);
    }

    public function test_el_simulador_rechaza_el_token_de_rechazo(): void
    {
        $respuesta = $this->app->make(PasarelaPagoInterface::class)
            ->procesarCobro('ORD-889', 25000.00, ['token_medio_pago' => PasarelaPagoSimulador::TOKEN_RECHAZO]);

        $this->assertFalse($respuesta['exitoso']);
        $this->assertSame('ERR_FONDOS_INSUFICIENTES', $respuesta['codigo_error']);
    }

    public function test_obtiene_el_pedido_confirmado_desde_el_doble_de_m5(): void
    {
        $pedidos = $this->app->make(PedidoClient::class);

        $pedido = $pedidos->obtenerDatosPedido(8942);

        $this->assertSame('confirmado', $pedidos->consultarEstadoPedido(8942));
        $this->assertSame(8942, $pedido->idPedido);
        $this->assertSame(59840.00, $pedido->montoTotal);
        $this->assertCount(2, $pedido->subpedidos);
    }
}
