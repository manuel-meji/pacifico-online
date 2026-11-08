<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios\Contratos;

use App\Contracts\M5_Pedidos\SubpedidosServiceInterface;
use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Services\M2_Comercios\PedidosFake;
use ReflectionClass;
use Tests\TestCase;

class M2_ContratoTest extends TestCase
{
    public function test_evento_subpedido_despachado_contiene_los_campos_que_m2_consume(): void
    {
        $reflexion = new ReflectionClass(SubpedidoDespachado::class);

        foreach (
            [
                'id_evento',
                'id_subpedido',
                'id_comercio',
                'subtotal',
            ] as $campo
        ) {
            $this->assertTrue(
                $reflexion->hasProperty($campo),
                "El evento SubpedidoDespachado debe contener {$campo}."
            );
        }
    }

    public function test_obtener_subpedido_cumple_los_campos_que_m2_consume(): void
    {
        $fake = new PedidosFake();

        $this->assertInstanceOf(
            SubpedidosServiceInterface::class,
            $fake
        );

        $subpedido = $fake->obtenerSubpedido(
            PedidosFake::SUBPEDIDO_EXISTENTE
        );

        foreach (
            [
                'id_subpedido',
                'id_comercio',
                'subtotal',
                'estado_subpedido',
            ] as $campo
        ) {
            $this->assertArrayHasKey($campo, $subpedido);
        }
    }
}