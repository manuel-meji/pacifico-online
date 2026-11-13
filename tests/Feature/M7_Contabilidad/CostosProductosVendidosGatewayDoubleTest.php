<?php

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\CostosProductosVendidosGatewayInterface;
use RuntimeException;
use Tests\Doubles\M7\CostosProductosVendidosStub;
use Tests\TestCase;

class CostosProductosVendidosGatewayDoubleTest extends TestCase
{
    public function test_m7_puede_simular_costos_disponibles_de_m4(): void
    {
        $stub = new CostosProductosVendidosStub;

        $stub->configurarCostosDisponibles([
            'id_subpedido' => 10,
            'id_comercio' => 2,
            'variaciones' => [
                [
                    'id_variacion' => 5,
                    'cantidad' => 3,
                    'costo_unitario' => 2000.00,
                    'costo_total' => 6000.00,
                ],
            ],
            'costo_total' => 6000.00,
        ]);

        $this->app->instance(
            CostosProductosVendidosGatewayInterface::class,
            $stub
        );

        $servicio = app(CostosProductosVendidosGatewayInterface::class);
        $respuesta = $servicio->obtenerCostosProductosVendidos(10);

        $this->assertSame($stub, $servicio);
        $this->assertSame(10, $respuesta['id_subpedido']);
        $this->assertSame(2, $respuesta['id_comercio']);
        $this->assertSame(6000.00, $respuesta['costo_total']);
        $this->assertSame(3, $respuesta['variaciones'][0]['cantidad']);
    }

    public function test_m7_puede_simular_costos_temporalmente_no_disponibles(): void
    {
        $stub = new CostosProductosVendidosStub;
        $stub->configurarCostosNoDisponibles();

        $this->app->instance(
            CostosProductosVendidosGatewayInterface::class,
            $stub
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(409);
        $this->expectExceptionMessage(
            'COSTOS_SUBPEDIDO_NO_DISPONIBLES'
        );

        app(CostosProductosVendidosGatewayInterface::class)
            ->obtenerCostosProductosVendidos(10);
    }

    public function test_m7_detecta_si_el_doble_no_fue_configurado(): void
    {
        $stub = new CostosProductosVendidosStub;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage(
            'No se configuró una respuesta para el doble de M4.'
        );

        $stub->obtenerCostosProductosVendidos(10);
    }

    public function test_m7_no_devuelve_costos_de_otro_subpedido(): void
    {
        $stub = new CostosProductosVendidosStub;

        $stub->configurarCostosDisponibles([
            'id_subpedido' => 10,
            'id_comercio' => 2,
            'variaciones' => [],
            'costo_total' => 0.00,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(404);

        $stub->obtenerCostosProductosVendidos(20);
    }

    public function test_m7_valida_la_estructura_del_contrato_de_costos_de_m4(): void
    {
        $stub = new CostosProductosVendidosStub;

        $respuesta = [
            'id_subpedido' => 10,
            'id_comercio' => 2,
            'variaciones' => [
                [
                    'id_variacion' => 5,
                    'cantidad' => 3,
                    'costo_unitario' => 2000.00,
                    'costo_total' => 6000.00,
                ],
                [
                    'id_variacion' => 6,
                    'cantidad' => 2,
                    'costo_unitario' => 1500.00,
                    'costo_total' => 3000.00,
                ],
            ],
            'costo_total' => 9000.00,
        ];

        $stub->configurarCostosDisponibles($respuesta);

        $resultado = $stub->obtenerCostosProductosVendidos(10);

        $this->assertSame(
            ['id_subpedido', 'id_comercio', 'variaciones', 'costo_total'],
            array_keys($resultado)
        );

        foreach ($resultado['variaciones'] as $variacion) {
            $this->assertEqualsCanonicalizing(
                ['id_variacion', 'cantidad', 'costo_unitario', 'costo_total'],
                array_keys($variacion)
            );

            $this->assertGreaterThanOrEqual(1, $variacion['cantidad']);
            $this->assertGreaterThanOrEqual(0, $variacion['costo_unitario']);
            $this->assertGreaterThanOrEqual(0, $variacion['costo_total']);
        }

        $this->assertNotEmpty($resultado['variaciones']);

        $this->assertEquals(
            9000.00,
            array_sum(array_column($resultado['variaciones'], 'costo_total'))
        );

        $this->assertEquals(9000.00, $resultado['costo_total']);
    }
}
