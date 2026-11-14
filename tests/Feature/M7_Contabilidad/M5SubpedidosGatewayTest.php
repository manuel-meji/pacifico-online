<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M5_Pedidos\SubpedidosServiceInterface;
use App\Contracts\M7_Contabilidad\SubpedidosM7GatewayInterface;
use App\Services\M7_Contabilidad\Gateways\M5SubpedidosGateway;
use LogicException;
use Mockery;
use Tests\TestCase;

class M5SubpedidosGatewayTest extends TestCase
{
    public function test_el_binding_resuelve_el_gateway_real(): void
    {
        $gateway = app(SubpedidosM7GatewayInterface::class);

        $this->assertInstanceOf(M5SubpedidosGateway::class, $gateway);
    }

    public function test_obtiene_un_subpedido_valido(): void
    {
        $m5 = Mockery::mock(SubpedidosServiceInterface::class);

        $m5->shouldReceive('obtenerSubpedido')
            ->once()
            ->with(10)
            ->andReturn([
                'id_subpedido' => 10,
                'id_pedido' => 20,
                'id_comercio' => 30,
                'subtotal' => 20000.00,
                'impuesto' => 2600.00,
                'costo_envio' => 1500.00,
            ]);

        $gateway = new M5SubpedidosGateway($m5);

        $resultado = $gateway->obtenerSubpedido(10);

        $this->assertSame(10, $resultado['id_subpedido']);
        $this->assertSame(20, $resultado['id_pedido']);
        $this->assertSame(30, $resultado['id_comercio']);
    }

    public function test_rechaza_un_subpedido_distinto_al_solicitado(): void
    {
        $m5 = Mockery::mock(SubpedidosServiceInterface::class);

        $m5->shouldReceive('obtenerSubpedido')
            ->andReturn([
                'id_subpedido' => 99,
                'id_pedido' => 20,
                'id_comercio' => 30,
                'subtotal' => 20000.00,
                'impuesto' => 2600.00,
                'costo_envio' => 1500.00,
            ]);

        $gateway = new M5SubpedidosGateway($m5);

        $this->expectException(LogicException::class);

        $gateway->obtenerSubpedido(10);
    }

    public function test_rechaza_campos_financieros_faltantes(): void
    {
        $m5 = Mockery::mock(SubpedidosServiceInterface::class);

        $m5->shouldReceive('obtenerSubpedido')
            ->andReturn([
                'id_subpedido' => 10,
                'id_pedido' => 20,
                'id_comercio' => 30,
                'subtotal' => 20000.00,
                'impuesto' => 2600.00,
            ]);

        $gateway = new M5SubpedidosGateway($m5);

        $this->expectException(LogicException::class);

        $gateway->obtenerSubpedido(10);
    }

    public function test_rechaza_importes_negativos(): void
    {
        $m5 = Mockery::mock(SubpedidosServiceInterface::class);

        $m5->shouldReceive('obtenerSubpedido')
            ->andReturn([
                'id_subpedido' => 10,
                'id_pedido' => 20,
                'id_comercio' => 30,
                'subtotal' => -1,
                'impuesto' => 2600.00,
                'costo_envio' => 1500.00,
            ]);

        $gateway = new M5SubpedidosGateway($m5);

        $this->expectException(LogicException::class);

        $gateway->obtenerSubpedido(10);
    }
}