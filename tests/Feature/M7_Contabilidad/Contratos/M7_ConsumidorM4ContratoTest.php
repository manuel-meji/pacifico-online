<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad\Contratos;

use App\Contracts\M7_Contabilidad\CostosProductosVendidosGatewayInterface;
use ReflectionClass;
use ReflectionNamedType;
use Tests\Doubles\M7\CostosProductosVendidosStub;
use Tests\TestCase;

/**
 * Contrato consumidor de M7 frente a M4 (costos de productos vendidos).
 * Solo usa la interfaz de M7 y el doble existente; no toca código ni datos de M4.
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
}
