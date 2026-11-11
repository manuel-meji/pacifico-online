<?php

declare(strict_types=1);

namespace Tests\Unit\M6_Pagos;

use App\Contracts\M7_Contabilidad\ContabilidadServiceInterface;
use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM1;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM2;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM5;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM7;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\M1\FakeClienteClient;
use Tests\Doubles\M1\PortalPublicoFake;
use Tests\Doubles\M2\ComerciosFiscalFake;
use Tests\Doubles\M2\FakeComercioClient;
use Tests\Doubles\M2\MockComerciosService;
use Tests\Doubles\M5\FakePedidoClient;
use Tests\Doubles\M5\PedidosContratoFake;

/**
 * Cada adaptador se prueba contra el doble del CONTRATO PUBLICADO del otro módulo
 * (sin base de datos ni contenedor) y se compara con el fake del PUERTO de M6:
 * si los dos dobles se desalinean, estas pruebas fallan.
 */
class AdaptadoresM6Test extends TestCase
{
    private function codigo(callable $accion): string
    {
        try {
            $accion();
        } catch (ErrorContratoException $e) {
            return $e->codigo;
        }

        $this->fail('Debió lanzar ErrorContratoException.');
    }

    public function test_m1_cliente_coincide_con_el_fake_del_puerto(): void
    {
        $real = (new AdaptadorM1(new PortalPublicoFake))->obtenerCliente(77);

        $this->assertSame(77, $real->idCliente);
        $this->assertSame('Fulano Castillo', $real->nombre);
        $this->assertSame('fulano@example.com', $real->correo);
        $this->assertSame((new FakeClienteClient)->obtenerCliente(77)->nombre, $real->nombre);
    }

    public function test_m1_la_identificacion_tributaria_aun_no_viaja_en_el_contrato(): void
    {
        $this->assertSame('', (new AdaptadorM1(new PortalPublicoFake))->obtenerCliente(77)->identificacionTributaria);
    }

    public function test_m1_cliente_inexistente(): void
    {
        $adaptador = new AdaptadorM1(new PortalPublicoFake);

        $this->assertSame('CLIENTE_NO_ENCONTRADO', $this->codigo(fn () => $adaptador->obtenerCliente(PortalPublicoFake::CLIENTE_INEXISTENTE)));
    }

    public function test_m2_datos_fiscales_coinciden_con_el_fake_del_puerto(): void
    {
        $adaptador = new AdaptadorM2(new ComerciosFiscalFake);
        $puerto = new FakeComercioClient;

        foreach ([11, 12] as $id) {
            $this->assertEquals($puerto->obtenerDatosFiscales($id), $adaptador->obtenerDatosFiscales($id));
        }
    }

    public function test_m2_errores_de_negocio(): void
    {
        $adaptador = new AdaptadorM2(new ComerciosFiscalFake);

        $this->assertSame('DATOS_FISCALES_INCOMPLETOS', $this->codigo(fn () => $adaptador->obtenerDatosFiscales(13)));
        $this->assertSame('COMERCIO_NO_ENCONTRADO', $this->codigo(fn () => $adaptador->obtenerDatosFiscales(99)));
    }

    public function test_m2_caido_se_propaga_como_err_006(): void
    {
        $this->expectException(ErrorNucleoException::class);

        (new AdaptadorM2(new ComerciosFiscalFake(disponible: false)))->obtenerDatosFiscales(11);
    }

    public function test_m5_pedido_coincide_con_el_fake_del_puerto(): void
    {
        $m5 = new PedidosContratoFake;
        $adaptador = new AdaptadorM5($m5, $m5);

        $this->assertSame('confirmado', $adaptador->consultarEstadoPedido(8942));
        $this->assertSame('cancelado', $adaptador->consultarEstadoPedido(8943));
        $this->assertEquals((new FakePedidoClient)->obtenerDatosPedido(8942), $adaptador->obtenerDatosPedido(8942));
    }

    public function test_m5_pedido_inexistente(): void
    {
        $m5 = new PedidosContratoFake;
        $adaptador = new AdaptadorM5($m5, $m5);

        $this->assertSame('PEDIDO_NO_ENCONTRADO', $this->codigo(fn () => $adaptador->obtenerDatosPedido(1)));
    }

    public function test_m5_pago_confirmado_marca_el_pedido_como_pagado_una_vez(): void
    {
        $m5 = new PedidosContratoFake;
        (new AdaptadorM5($m5, $m5))->notificarResultadoPago(8942, 5, 'confirmado', null);

        $this->assertSame([['pedido' => 8942, 'comprobante' => 'pago-5']], $m5->pagados);
    }

    public function test_m5_pago_rechazado_no_llama_a_m5_porque_viaja_por_evento(): void
    {
        $m5 = new PedidosContratoFake;
        (new AdaptadorM5($m5, $m5))->notificarResultadoPago(8942, 5, 'rechazado', 'Fondos insuficientes');

        $this->assertSame([], $m5->pagados);
    }

    public function test_m5_que_rechaza_marcar_como_pagado_lanza_error(): void
    {
        $m5 = new PedidosContratoFake(aceptaPago: false);

        $this->expectException(\RuntimeException::class);

        (new AdaptadorM5($m5, $m5))->notificarResultadoPago(8942, 5, 'confirmado', null);
    }

    public function test_m5_caido_se_propaga_como_err_006(): void
    {
        $m5 = new PedidosContratoFake(disponible: false);

        $this->expectException(ErrorNucleoException::class);

        (new AdaptadorM5($m5, $m5))->consultarEstadoPedido(8942);
    }

    public function test_m2_funciona_con_el_mock_oficial_de_m2(): void
    {
        $adaptador = new AdaptadorM2(new MockComerciosService);

        $datos = $adaptador->obtenerDatosFiscales(5);

        $this->assertSame('3-101-888999', $datos->cedulaJuridica);
        $this->assertSame('Tienda Demostración S.A.', $datos->razonSocial);
        $this->assertSame('inscrito', $datos->condicionContribuyente);
        $this->assertSame('COMERCIO_NO_ENCONTRADO', $this->codigo(fn () => $adaptador->obtenerDatosFiscales(MockComerciosService::COMERCIO_INEXISTENTE)));
    }

    public function test_m7_consulta_el_registro_contable_a_traves_del_contrato(): void
    {
        $m7 = new class implements ContabilidadServiceInterface
        {
            public function registrarAsientoDespacho(int $pedidoId, float $totalVenta, float $costoVentas): bool
            {
                return true;
            }

            public function consultarRegistroContablePago(int $idPago): array
            {
                return [
                    'id_pago' => $idPago,
                    'registrado' => $idPago === 5,
                    'asiento_id' => $idPago === 5 ? 90 : null,
                    'fecha_registro' => null,
                ];
            }
        };

        $adaptador = new AdaptadorM7($m7);

        $this->assertTrue($adaptador->consultarRegistroContablePago(5));
        $this->assertFalse($adaptador->consultarRegistroContablePago(6));
    }
}
