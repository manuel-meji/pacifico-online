<?php

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\CostosProductosVendidosGatewayInterface;
use RuntimeException;
use Tests\TestCase;

class CostosProductosVendidosGatewayDoubleTest extends TestCase
{
    public function test_m7_puede_simular_costos_disponibles_de_m4(): void
    {
        $stub = $this->createStub(
            CostosProductosVendidosGatewayInterface::class
        );

        $stub->method('obtenerCostosProductosVendidos')
            ->willReturn([
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

        $respuesta = app(
            CostosProductosVendidosGatewayInterface::class
        )->obtenerCostosProductosVendidos(10);

        $this->assertSame(10, $respuesta['id_subpedido']);
        $this->assertSame(2, $respuesta['id_comercio']);
        $this->assertSame(6000.00, $respuesta['costo_total']);
        $this->assertSame(3, $respuesta['variaciones'][0]['cantidad']);
    }

    public function test_m7_puede_simular_costos_temporalmente_no_disponibles(): void
    {
        $stub = $this->createStub(
            CostosProductosVendidosGatewayInterface::class
        );

        $stub->method('obtenerCostosProductosVendidos')
            ->willThrowException(
                new RuntimeException(
                    'COSTOS_SUBPEDIDO_NO_DISPONIBLES',
                    409
                )
            );

        $this->app->instance(
            CostosProductosVendidosGatewayInterface::class,
            $stub
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(409);
        $this->expectExceptionMessage(
            'COSTOS_SUBPEDIDO_NO_DISPONIBLES'
        );

        app(
            CostosProductosVendidosGatewayInterface::class
        )->obtenerCostosProductosVendidos(10);
    }
}
