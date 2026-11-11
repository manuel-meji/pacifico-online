<?php

namespace Tests\Unit\M6_Pagos;

use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Services\M6_Pagos\Clients\ClienteClient;
use App\Services\M6_Pagos\Clients\ComercioClient;
use App\Services\M6_Pagos\Clients\ContabilidadClient;
use App\Services\M6_Pagos\Clients\PedidoClient;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\M1\FakeClienteClient;
use Tests\Doubles\M2\FakeComercioClient;
use Tests\Doubles\M5\FakePedidoClient;
use Tests\Doubles\M7\FakeContabilidadClient;

/** Garantiza que cada doble implementa el puerto real y cubre éxito + errores del contrato. */
class DoblesM6Test extends TestCase
{
    public function test_los_dobles_implementan_los_puertos_de_m6(): void
    {
        $this->assertInstanceOf(PedidoClient::class, new FakePedidoClient);
        $this->assertInstanceOf(ClienteClient::class, new FakeClienteClient);
        $this->assertInstanceOf(ComercioClient::class, new FakeComercioClient);
        $this->assertInstanceOf(ContabilidadClient::class, new FakeContabilidadClient);
    }

    public function test_m5_pedido_confirmado_con_desglose_que_suma_el_total(): void
    {
        $p = (new FakePedidoClient)->obtenerDatosPedido(8942);
        $suma = array_sum(array_map(fn ($s) => $s->subtotal + $s->impuesto + $s->envio, $p->subpedidos));
        $this->assertEqualsWithDelta($p->montoTotal, $suma, 0.01);
    }

    public function test_m5_estado_cancelado_y_pedido_inexistente(): void
    {
        $m5 = new FakePedidoClient;
        $this->assertSame('cancelado', $m5->consultarEstadoPedido(8943));
        $this->expectException(ErrorContratoException::class);
        $m5->consultarEstadoPedido(1);
    }

    public function test_m5_registra_la_notificacion_del_resultado_de_pago(): void
    {
        $m5 = new FakePedidoClient;
        $m5->notificarResultadoPago(8942, 5, 'confirmado', null);
        $this->assertSame('confirmado', $m5->notificaciones[0]['estado']);
    }

    public function test_m1_cliente_existente_y_no_encontrado(): void
    {
        $m1 = new FakeClienteClient;
        $this->assertSame('116540892', $m1->obtenerCliente(77)->identificacionTributaria);
        try {
            $m1->obtenerCliente(1);
            $this->fail('Debió lanzar CLIENTE_NO_ENCONTRADO');
        } catch (ErrorContratoException $e) {
            $this->assertSame('CLIENTE_NO_ENCONTRADO', $e->codigo);
        }
    }

    public function test_m2_datos_fiscales_y_errores(): void
    {
        $m2 = new FakeComercioClient;
        $this->assertSame('3101123456', $m2->obtenerDatosFiscales(11)->cedulaJuridica);
        $this->assertSame('no_inscrito', $m2->obtenerDatosFiscales(12)->condicionContribuyente);
        foreach ([13 => 'DATOS_FISCALES_INCOMPLETOS', 99 => 'COMERCIO_NO_ENCONTRADO'] as $id => $codigo) {
            try {
                $m2->obtenerDatosFiscales($id);
                $this->fail("Debió lanzar $codigo");
            } catch (ErrorContratoException $e) {
                $this->assertSame($codigo, $e->codigo);
            }
        }
    }

    public function test_m7_no_disponible_simula_conciliacion_parcial(): void
    {
        $this->expectException(\RuntimeException::class);
        (new FakeContabilidadClient(false))->consultarRegistroContablePago(1);
    }
}
