<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad\Contratos;

use App\Events\M5_Pedidos\PedidoConfirmado;
use App\Events\M5_Pedidos\SubpedidoDespachado;
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
}
