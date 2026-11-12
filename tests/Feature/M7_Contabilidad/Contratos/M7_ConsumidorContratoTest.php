<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad\Contratos;

use App\Events\M5_Pedidos\PedidoConfirmado;
use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use ReflectionClass;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * Contrato consumidor de M7 frente a los eventos de M5.
 * Solo lee las clases de M5 por reflexión; no las modifica.
 */
class M7_ConsumidorContratoTest extends TestCase
{
    public function test_subpedido_despachado_contiene_los_campos_que_m7_consume(): void
    {
        $reflexion = new ReflectionClass(SubpedidoDespachado::class);

        $esperados = [
            'id_evento' => 'string',
            'fecha_evento' => 'string',
            'id_pedido' => 'int',
            'id_subpedido' => 'int',
            'id_comercio' => 'int',
        ];

        foreach ($esperados as $campo => $tipo) {
            $this->assertTrue(
                $reflexion->hasProperty($campo),
                "SubpedidoDespachado debe contener {$campo}."
            );

            $tipoReal = $reflexion->getProperty($campo)->getType();
            $this->assertInstanceOf(ReflectionNamedType::class, $tipoReal);
            $this->assertSame($tipo, $tipoReal->getName(), "{$campo} debe ser {$tipo}.");
        }
    }

    public function test_pedido_confirmado_contiene_los_campos_que_m7_consume(): void
    {
        $reflexion = new ReflectionClass(PedidoConfirmado::class);

        $esperados = [
            'id_evento' => 'string',
            'fecha_evento' => 'string',
            'id_pedido' => 'int',
            'subpedidos' => 'array',
        ];

        foreach ($esperados as $campo => $tipo) {
            $this->assertTrue(
                $reflexion->hasProperty($campo),
                "PedidoConfirmado debe contener {$campo}."
            );

            $tipoReal = $reflexion->getProperty($campo)->getType();
            $this->assertInstanceOf(ReflectionNamedType::class, $tipoReal);
            $this->assertSame($tipo, $tipoReal->getName(), "{$campo} debe ser {$tipo}.");
        }
    }

        public function test_pedido_confirmado_real_trae_la_estructura_interna_de_subpedidos(): void
    {
        $evento = $this->construirPedidoConfirmadoReal();

        $this->assertNotEmpty($evento->subpedidos);

        $esperados = [
            'id_subpedido' => 'is_int',
            'id_comercio' => 'is_int',
            'subtotal' => 'is_float',
        ];

        foreach ($evento->subpedidos as $indice => $subpedido) {
            foreach ($esperados as $campo => $validador) {
                $this->assertArrayHasKey(
                    $campo,
                    $subpedido,
                    "subpedidos[{$indice}] debe contener {$campo}."
                );
                $this->assertTrue(
                    $validador($subpedido[$campo]),
                    "subpedidos[{$indice}].{$campo} debe cumplir {$validador}."
                );
            }
        }

        $this->assertSame(10, $evento->subpedidos[0]['id_subpedido']);
        $this->assertSame(3, $evento->subpedidos[0]['id_comercio']);
        $this->assertSame(100.25, $evento->subpedidos[0]['subtotal']);
    }

    public function test_subpedido_despachado_real_se_construye_con_los_campos_que_m7_lee(): void
    {
        $subpedido = (new Subpedido)->forceFill([
            'id_subpedido' => 10,
            'id_pedido' => 5,
            'id_comercio' => 3,
            'subtotal' => 100.25,
            'costo_envio' => 0,
        ]);
        $subpedido->setRelation('lineas', collect());

        $evento = new SubpedidoDespachado($subpedido);

        $this->assertIsString($evento->id_evento);
        $this->assertIsString($evento->fecha_evento);
        $this->assertSame(5, $evento->id_pedido);
        $this->assertSame(10, $evento->id_subpedido);
        $this->assertSame(3, $evento->id_comercio);
    }

    private function construirPedidoConfirmadoReal(): PedidoConfirmado
    {
        $subpedido = (new Subpedido)->forceFill([
            'id_subpedido' => 10,
            'id_pedido' => 5,
            'id_comercio' => 3,
            'subtotal' => 100.25,
            'costo_envio' => 0,
        ]);
        $subpedido->setRelation('lineas', collect());

        $pedido = (new PedidoMaestro)->forceFill([
            'id_pedido' => 5,
            'id_cliente' => 7,
            'monto_total' => 100.25,
        ]);
        $pedido->setRelation('subpedidos', collect([$subpedido]));

        return new PedidoConfirmado($pedido);
    }
}
