<?php

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\ComerciosM7GatewayInterface;
use RuntimeException;
use Tests\Doubles\M7\ComerciosM7Stub;
use Tests\TestCase;

class ComerciosM7GatewayDoubleTest extends TestCase
{
    public function test_m7_puede_simular_datos_fiscales_de_m2(): void
    {
        $stub = new ComerciosM7Stub();

        $stub->configurarDatosFiscales(2, [
            'id_comercio' => 2,
            'cedula_juridica' => '3101234567',
            'razon_social' => 'Comercio de prueba S.A.',
            'inscrito_como_contribuyente' => true,
            'direccion_fiscal' => 'Liberia, Guanacaste',
            'comision' => [
                'tipo' => 'PORCENTAJE',
                'valor' => 10.0,
                'base_calculo' => 'subtotal del subpedido',
                'moneda' => 'CRC',
            ],
        ]);

        $this->app->instance(ComerciosM7GatewayInterface::class, $stub);

        $respuesta = app(ComerciosM7GatewayInterface::class)
            ->obtenerDatosFiscalesComercio(2);

        $this->assertSame(2, $respuesta['id_comercio']);
        $this->assertSame('3101234567', $respuesta['cedula_juridica']);
        $this->assertSame('Comercio de prueba S.A.', $respuesta['razon_social']);
        $this->assertTrue($respuesta['inscrito_como_contribuyente']);
        $this->assertSame('CRC', $respuesta['comision']['moneda']);
    }
    public function test_m7_puede_simular_comision_calculada_de_m2(): void
    {
        $stub = new ComerciosM7Stub();
        $stub->configurarComision(10, 1500.00);

        $this->app->instance(ComerciosM7GatewayInterface::class, $stub);

        $comision = app(ComerciosM7GatewayInterface::class)
            ->obtenerComisionSubpedido(10);

        $this->assertSame(1500.00, $comision);
    }

    public function test_m7_puede_simular_datos_fiscales_no_disponibles(): void
    {
        $stub = new ComerciosM7Stub();
        $stub->configurarDatosFiscalesNoDisponibles();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(503);
        $this->expectExceptionMessage('DATOS_FISCALES_NO_DISPONIBLES');

        $stub->obtenerDatosFiscalesComercio(2);
    }

    public function test_m7_puede_simular_comision_no_disponible(): void
    {
        $stub = new ComerciosM7Stub();
        $stub->configurarComisionNoDisponible();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(503);
        $this->expectExceptionMessage('COMISION_NO_DISPONIBLE');

        $stub->obtenerComisionSubpedido(10);
    }

    public function test_m7_detecta_comercio_no_configurado(): void
    {
        $stub = new ComerciosM7Stub();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(404);

        $stub->obtenerDatosFiscalesComercio(999);
    }

    public function test_m7_detecta_subpedido_sin_comision_configurada(): void
    {
        $stub = new ComerciosM7Stub();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(404);

        $stub->obtenerComisionSubpedido(999);
    }
}
