<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad\Contratos;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Contracts\M7_Contabilidad\CostosProductosVendidosGatewayInterface;
use App\Services\M7_Contabilidad\Gateways\M4CostosProductosVendidosGateway;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;
use Tests\Doubles\M7\CostosProductosVendidosStub;
use Tests\TestCase;

/**
 * Contrato consumidor de M7 frente a M4 (costos de productos vendidos).
 * Solo usa la interfaz de M7 y el doble existente; no toca código ni datos de M4.
 *
 * NO es una prueba de integración con M4 real: el adaptador M4CostosProductosVendidosGateway
 * se prueba contra un mock de InventarioModuleInterface, así que verifica que M7 delega
 * y espera la estructura del contrato (id_subpedido, id_comercio, variaciones, costo_total),
 * no que M4 la entregue de esa forma en ejecución.
 */
class M7_ConsumidorM4ContratoTest extends TestCase
{
    public function test_la_interfaz_declara_el_metodo_que_el_listener_invoca(): void
    {
        $reflexion = new ReflectionClass(CostosProductosVendidosGatewayInterface::class);

        $this->assertTrue($reflexion->hasMethod('obtenerCostosProductosVendidos'));

        $metodo = $reflexion->getMethod('obtenerCostosProductosVendidos');
        $parametros = $metodo->getParameters();

        $this->assertCount(1, $parametros);
        $this->assertSame('int', $parametros[0]->getType()?->getName());

        $retorno = $metodo->getReturnType();
        $this->assertInstanceOf(ReflectionNamedType::class, $retorno);
        $this->assertSame('array', $retorno->getName());
    }

    public function test_el_doble_de_m4_cumple_la_interfaz(): void
    {
        $this->assertInstanceOf(
            CostosProductosVendidosGatewayInterface::class,
            new CostosProductosVendidosStub
        );
    }

    public function test_la_respuesta_trae_los_campos_que_el_listener_lee(): void
    {
        $stub = new CostosProductosVendidosStub;
        $stub->configurarCostosDisponibles([
            'id_subpedido' => 10,
            'id_comercio' => 2,
            'variaciones' => [[
                'id_variacion' => 5,
                'cantidad' => 3,
                'costo_unitario' => 2000.00,
                'costo_total' => 6000.00,
            ]],
            'costo_total' => 6000.00,
        ]);

        $respuesta = $stub->obtenerCostosProductosVendidos(10);

        foreach (['id_subpedido', 'id_comercio', 'costo_total'] as $campo) {
            $this->assertArrayHasKey($campo, $respuesta, "M4 debe entregar {$campo}.");
        }

        $this->assertIsInt($respuesta['id_subpedido']);
        $this->assertIsInt($respuesta['id_comercio']);
        $this->assertIsFloat($respuesta['costo_total']);
        $this->assertGreaterThan(0, $respuesta['costo_total'], 'El listener rechaza costos <= 0.');
        $this->assertSame(10, $respuesta['id_subpedido']);
    }

    public function test_el_adaptador_real_cumple_la_interfaz_de_m7(): void
    {
        $adaptador = new M4CostosProductosVendidosGateway(
            $this->createMock(InventarioModuleInterface::class)
        );

        $this->assertInstanceOf(
            CostosProductosVendidosGatewayInterface::class,
            $adaptador
        );
    }

    public function test_el_adaptador_real_delega_en_m4_y_devuelve_su_respuesta(): void
    {
        $respuestaM4 = $this->respuestaM4Valida();

        $inventario = $this->createMock(InventarioModuleInterface::class);
        $inventario->expects($this->once())
            ->method('obtenerCostoProductosVendidos')
            ->with(10)
            ->willReturn($respuestaM4);

        $adaptador = new M4CostosProductosVendidosGateway($inventario);

        $respuesta = $adaptador->obtenerCostosProductosVendidos(10);

        $this->assertSame($respuestaM4, $respuesta);

        foreach (['id_subpedido', 'id_comercio', 'costo_total'] as $campo) {
            $this->assertArrayHasKey($campo, $respuesta, "M4 debe entregar {$campo}.");
        }

        $this->assertSame(10, $respuesta['id_subpedido']);
        $this->assertSame(2, $respuesta['id_comercio']);
        $this->assertSame(6000.00, $respuesta['costo_total']);
    }

    public function test_el_adaptador_real_deja_pasar_los_errores_de_m4(): void
    {
        $inventario = $this->createMock(InventarioModuleInterface::class);
        $inventario->method('obtenerCostoProductosVendidos')
            ->willThrowException(new RuntimeException('COSTOS_SUBPEDIDO_NO_DISPONIBLES', 409));

        $adaptador = new M4CostosProductosVendidosGateway($inventario);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('COSTOS_SUBPEDIDO_NO_DISPONIBLES');

        $adaptador->obtenerCostosProductosVendidos(10);
    }

    public function test_la_estructura_de_variaciones_cumple_el_contrato(): void
    {
        $inventario = $this->createMock(InventarioModuleInterface::class);
        $inventario->method('obtenerCostoProductosVendidos')
            ->willReturn($this->respuestaM4Valida());

        $respuesta = (new M4CostosProductosVendidosGateway($inventario))
            ->obtenerCostosProductosVendidos(10);

        $this->assertArrayHasKey('variaciones', $respuesta);
        $this->assertIsArray($respuesta['variaciones']);
        $this->assertNotEmpty($respuesta['variaciones']);

        $esperados = [
            'id_variacion' => 'is_int',
            'cantidad' => 'is_int',
            'costo_unitario' => 'is_float',
            'costo_total' => 'is_float',
        ];

        $suma = 0.0;

        foreach ($respuesta['variaciones'] as $indice => $variacion) {
            foreach ($esperados as $campo => $validador) {
                $this->assertArrayHasKey(
                    $campo,
                    $variacion,
                    "variaciones[{$indice}] debe contener {$campo}."
                );
                $this->assertTrue(
                    $validador($variacion[$campo]),
                    "variaciones[{$indice}].{$campo} debe cumplir {$validador}."
                );
            }

            $suma += $variacion['costo_total'];
        }

        $this->assertEqualsWithDelta(
            $respuesta['costo_total'],
            $suma,
            0.01,
            'El costo_total debe ser la suma de las variaciones.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function respuestaM4Valida(): array
    {
        return [
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
        ];
    }
}
