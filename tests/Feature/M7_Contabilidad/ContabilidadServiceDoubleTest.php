<?php

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Contracts\M7_Contabilidad\ContabilidadServiceInterface;
use App\Contracts\Nucleo\NucleoServiceInterface;
use Exception;
use Tests\TestCase;

class ContabilidadServiceDoubleTest extends TestCase
{
    public function test_m7_puede_sustituir_el_servicio_por_un_stub(): void
    {
        $stub = $this->createStub(ContabilidadServiceInterface::class);

        $stub->method('registrarAsientoDespacho')
            ->willReturn(true);

        $this->app->instance(
            ContabilidadServiceInterface::class,
            $stub
        );

        $servicio = app(ContabilidadServiceInterface::class);

        $this->assertTrue(
            $servicio->registrarAsientoDespacho(100, 15000.00, 9000.00)
        );

        $this->assertSame(
            $stub,
            app(ContabilidadServiceInterface::class)
        );
    }

    public function test_m7_puede_simular_datos_fiscales_de_m2(): void
    {
        $stub = $this->createStub(ComerciosServiceInterface::class);

        $stub->method('obtenerMicrositio')->willReturn([
            'id_comercio' => 2,
            'nombre' => 'Comercio de prueba',
            'estado' => 'activo',
        ]);

        $this->app->instance(ComerciosServiceInterface::class, $stub);

        $respuesta = app(ComerciosServiceInterface::class)
            ->obtenerMicrositio(2);

        $this->assertSame(2, $respuesta['id_comercio']);
        $this->assertSame('activo', $respuesta['estado']);
    }

    public function test_m7_puede_simular_error_de_m2(): void
    {
        $stub = $this->createStub(ComerciosServiceInterface::class);

        $stub->method('obtenerMicrositio')
            ->willThrowException(new Exception('Comercio no disponible', 409));

        $this->app->instance(ComerciosServiceInterface::class, $stub);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(409);

        app(ComerciosServiceInterface::class)->obtenerMicrositio(2);
    }

    public function test_m7_puede_simular_existencias_de_m4(): void
    {
        $stub = $this->createStub(InventarioServiceInterface::class);
        $stub->method('consultarExistencias')->willReturn(10);

        $this->app->instance(InventarioServiceInterface::class, $stub);

        $this->assertSame(
            10,
            app(InventarioServiceInterface::class)->consultarExistencias(1)
        );
    }

    public function test_m7_puede_simular_error_de_m4(): void
    {
        $stub = $this->createStub(InventarioServiceInterface::class);

        $stub->method('solicitarReserva')
            ->willThrowException(new Exception('Existencias insuficientes', 409));

        $this->app->instance(InventarioServiceInterface::class, $stub);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(409);

        app(InventarioServiceInterface::class)->solicitarReserva(1, 100, 1);
    }

    public function test_m7_puede_simular_un_subpedido_de_m5(): void
    {
        $stub = $this->createStub(PedidosServiceInterface::class);

        $stub->method('despacharSubpedido')->willReturn([
            'id_subpedido' => 10,
            'evento' => 'SubpedidoDespachado',
        ]);

        $this->app->instance(PedidosServiceInterface::class, $stub);

        $respuesta = app(PedidosServiceInterface::class)
            ->despacharSubpedido(10);

        $this->assertSame(10, $respuesta['id_subpedido']);
        $this->assertSame('SubpedidoDespachado', $respuesta['evento']);
    }

    public function test_m7_puede_simular_pago_confirmado_de_m6(): void
    {
        $stub = $this->createStub(PagosServiceInterface::class);

        $stub->method('generarFactura')->willReturn([
            'id_factura' => 25,
            'estado' => 'emitida',
        ]);

        $this->app->instance(PagosServiceInterface::class, $stub);

        $respuesta = app(PagosServiceInterface::class)->generarFactura(100);

        $this->assertSame(25, $respuesta['id_factura']);
        $this->assertSame('emitida', $respuesta['estado']);
    }

    public function test_m7_puede_simular_comercio_autenticado_del_nucleo(): void
    {
        $stub = $this->createStub(NucleoServiceInterface::class);

        $stub->method('getComercioActualId')->willReturn(2);

        $this->app->instance(NucleoServiceInterface::class, $stub);

        $this->assertSame(
            2,
            app(NucleoServiceInterface::class)->getComercioActualId()
        );
    }

    public function test_m7_puede_simular_error_de_pago_de_m6(): void
    {
        $stub = $this->createStub(PagosServiceInterface::class);

        $stub->method('procesarPago')
            ->willThrowException(new Exception('Pago rechazado', 402));

        $this->app->instance(PagosServiceInterface::class, $stub);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(402);

        app(PagosServiceInterface::class)->procesarPago(100, 15000, 'tarjeta', []);
    }
}
