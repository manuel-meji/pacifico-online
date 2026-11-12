<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M7_Contabilidad\ComerciosM7GatewayInterface;
use App\Services\M7_Contabilidad\Gateways\M2ComerciosGateway;
use LogicException;
use Mockery;
use Tests\TestCase;

final class M2ComerciosGatewayTest extends TestCase
{
    public function test_binding_de_m7_resuelve_el_gateway_real_de_m2(): void
    {
        $gateway = app(ComerciosM7GatewayInterface::class);

        $this->assertInstanceOf(
            M2ComerciosGateway::class,
            $gateway
        );
    }

    public function test_m7_obtiene_datos_fiscales_desde_m2(): void
    {
        $m2 = Mockery::mock(ComerciosServiceInterface::class);

        $m2->shouldReceive('obtenerDatosFiscalesComercio')
            ->once()
            ->with(2)
            ->andReturn([
                'id_comercio' => 2,
                'cedula_juridica' => '3-101-100002',
                'razon_social' => 'Temulandia',
                'direccion_fiscal' => 'Costa Rica',
                'inscrito_como_contribuyente' => true,
                'comision' => [
                    'tipo' => 'porcentaje',
                    'valor' => 8.0,
                    'base_calculo' => 'subtotal del subpedido',
                    'moneda' => null,
                ],
            ]);

        $gateway = new M2ComerciosGateway($m2);

        $resultado = $gateway
            ->obtenerDatosFiscalesComercio(2);

        $this->assertSame(
            2,
            $resultado['id_comercio']
        );

        $this->assertSame(
            '3-101-100002',
            $resultado['cedula_juridica']
        );

        $this->assertSame(
            8.0,
            $resultado['comision']['valor']
        );
    }

    public function test_m7_acepta_comision_nula_en_datos_fiscales(): void
    {
        $m2 = Mockery::mock(ComerciosServiceInterface::class);

        $m2->shouldReceive('obtenerDatosFiscalesComercio')
            ->once()
            ->with(2)
            ->andReturn([
                'id_comercio' => 2,
                'cedula_juridica' => '3-101-100002',
                'razon_social' => 'Temulandia',
                'direccion_fiscal' => 'Costa Rica',
                'inscrito_como_contribuyente' => true,
                'comision' => null,
            ]);

        $gateway = new M2ComerciosGateway($m2);

        $resultado = $gateway
            ->obtenerDatosFiscalesComercio(2);

        $this->assertNull($resultado['comision']);
    }

    public function test_m7_extrae_monto_de_comision_del_arreglo_de_m2(): void
    {
        $m2 = Mockery::mock(ComerciosServiceInterface::class);

        $m2->shouldReceive('obtenerComisionSubpedido')
            ->once()
            ->with(11)
            ->andReturn([
                'id_subpedido' => 11,
                'id_comercio' => 2,
                'monto_comision' => 1500.50,
                'base_utilizada' => 15005.00,
                'moneda' => 'CRC',
            ]);

        $gateway = new M2ComerciosGateway($m2);

        $resultado = $gateway
            ->obtenerComisionSubpedido(11);

        $this->assertSame(
            1500.50,
            $resultado
        );
    }

    public function test_m7_rechaza_subpedido_distinto_devuelto_por_m2(): void
    {
        $m2 = Mockery::mock(ComerciosServiceInterface::class);

        $m2->shouldReceive('obtenerComisionSubpedido')
            ->once()
            ->with(11)
            ->andReturn([
                'id_subpedido' => 99,
                'id_comercio' => 2,
                'monto_comision' => 1500.50,
                'base_utilizada' => 15005.00,
                'moneda' => 'CRC',
            ]);

        $gateway = new M2ComerciosGateway($m2);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'El subpedido devuelto por M2 no coincide con el subpedido solicitado.'
        );

        $gateway->obtenerComisionSubpedido(11);
    }

    public function test_m7_rechaza_respuesta_sin_monto_de_comision(): void
    {
        $m2 = Mockery::mock(ComerciosServiceInterface::class);

        $m2->shouldReceive('obtenerComisionSubpedido')
            ->once()
            ->with(11)
            ->andReturn([
                'id_subpedido' => 11,
                'id_comercio' => 2,
                'base_utilizada' => 15005.00,
                'moneda' => 'CRC',
            ]);

        $gateway = new M2ComerciosGateway($m2);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'M2 no devolvió el monto de comisión esperado.'
        );

        $gateway->obtenerComisionSubpedido(11);
    }

    public function test_m7_rechaza_comercio_distinto_devuelto_por_m2(): void
    {
        $m2 = Mockery::mock(ComerciosServiceInterface::class);

        $m2->shouldReceive('obtenerDatosFiscalesComercio')
            ->once()
            ->with(2)
            ->andReturn([
                'id_comercio' => 5,
                'cedula_juridica' => '3-101-100005',
                'razon_social' => 'Café Volcán',
                'direccion_fiscal' => 'Costa Rica',
                'inscrito_como_contribuyente' => true,
                'comision' => null,
            ]);

        $gateway = new M2ComerciosGateway($m2);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'El comercio devuelto por M2 no coincide con el comercio solicitado.'
        );

        $gateway->obtenerDatosFiscalesComercio(2);
    }
}
