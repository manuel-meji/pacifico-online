<?php

namespace Tests\Unit\M5_Pedidos;

use App\Contracts\M5_Pedidos\CarritoServiceInterface;
use App\Contracts\M5_Pedidos\GestionPedidosServiceInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use App\Contracts\M5_Pedidos\SubpedidosServiceInterface;
use App\Services\M5_Pedidos\PedidosService;
use ReflectionClass;
use Tests\Doubles\M5_Pedidos\PedidosServiceFake;
use Tests\TestCase;

class ContratosM5Test extends TestCase
{
    public function test_interfaces_estan_definidas_con_la_estructura_esperada(): void
    {
        $this->assertTrue(interface_exists(CarritoServiceInterface::class));
        $this->assertTrue(interface_exists(SubpedidosServiceInterface::class));
        $this->assertTrue(interface_exists(GestionPedidosServiceInterface::class));
        $this->assertTrue(interface_exists(PedidosServiceInterface::class));
    }

    public function test_carrito_service_interface_tiene_los_metodos_del_contrato(): void
    {
        $ref = new ReflectionClass(CarritoServiceInterface::class);
        $metodosEsperados = [
            'obtenerCarrito',
            'vaciarCarrito',
            'agregarItemCarrito',
            'actualizarCantidadItemCarrito',
            'eliminarItemCarrito',
        ];

        foreach ($metodosEsperados as $metodo) {
            $this->assertTrue($ref->hasMethod($metodo), "Falta el método {$metodo} en CarritoServiceInterface");
        }
    }

    public function test_subpedidos_service_interface_tiene_los_metodos_del_contrato(): void
    {
        $ref = new ReflectionClass(SubpedidosServiceInterface::class);
        $metodosEsperados = [
            'obtenerSubpedido',
            'despacharSubpedido',
            'confirmarEntregaSubpedido',
            'cancelarSubpedidoParcial',
        ];

        foreach ($metodosEsperados as $metodo) {
            $this->assertTrue($ref->hasMethod($metodo), "Falta el método {$metodo} en SubpedidosServiceInterface");
        }
    }

    public function test_gestion_pedidos_service_interface_tiene_los_metodos_de_pedidos_maestros(): void
    {
        $ref = new ReflectionClass(GestionPedidosServiceInterface::class);
        $metodosEsperados = [
            'listarPedidos',
            'crearPedidoCheckout',
            'obtenerPedido',
            'cancelarPedidoTotal',
        ];

        foreach ($metodosEsperados as $metodo) {
            $this->assertTrue($ref->hasMethod($metodo), "Falta el método {$metodo} en GestionPedidosServiceInterface");
        }
    }

    public function test_pedidos_service_interface_tiene_los_metodos_de_interoperabilidad(): void
    {
        $ref = new ReflectionClass(PedidosServiceInterface::class);
        $metodosEsperados = [
            'crearPedido',
            'marcarComoPagado',
            'despacharSubpedido',
            'obtenerCarrito',
            'agregarItemCarrito',
        ];

        foreach ($metodosEsperados as $metodo) {
            $this->assertTrue($ref->hasMethod($metodo), "Falta el método {$metodo} en PedidosServiceInterface");
        }
    }

    public function test_implementacion_pedidos_service_cumple_los_contratos_de_m5(): void
    {
        $servicio = $this->app->make(PedidosServiceInterface::class);
        $this->assertInstanceOf(PedidosService::class, $servicio);
        $this->assertInstanceOf(CarritoServiceInterface::class, $servicio);
        $this->assertInstanceOf(SubpedidosServiceInterface::class, $servicio);
        $this->assertInstanceOf(GestionPedidosServiceInterface::class, $servicio);
    }

    public function test_doble_fake_cumple_los_contratos_de_m5(): void
    {
        $fake = new PedidosServiceFake;
        $this->assertInstanceOf(PedidosServiceInterface::class, $fake);
        $this->assertInstanceOf(CarritoServiceInterface::class, $fake);
        $this->assertInstanceOf(SubpedidosServiceInterface::class, $fake);
        $this->assertInstanceOf(GestionPedidosServiceInterface::class, $fake);

        // Prueba de funcionalidad básica en fake
        $carrito = $fake->obtenerCarrito(10);
        $this->assertSame(10, $carrito['id_cliente']);

        $agregado = $fake->agregarItemCarrito(10, ['id_variacion' => 50, 'cantidad' => 2]);
        $this->assertCount(1, $agregado['items']);

        $pedido = $fake->crearPedidoCheckout(['id_cliente' => 10]);
        $this->assertSame(10, $pedido['id_cliente']);
        $this->assertSame('confirmado', $pedido['estado_pedido']);

        $consultado = $fake->obtenerPedido($pedido['id_pedido']);
        $this->assertSame($pedido['id_pedido'], $consultado['id_pedido']);
    }
}
