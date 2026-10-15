<?php

namespace Tests\Feature\M5_Pedidos;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M6_Pagos\PagosServiceInterface;
use Exception;
use Tests\TestCase;

/**
 * Dobles de prueba (stubs) de las dependencias externas de M5.
 *
 * Cada doble implementa el contrato publicado por el módulo proveedor y se
 * registra en el contenedor para simular inyección de dependencias.
 */
class M5DependenciesMockTest extends TestCase
{
    public function test_m2_comercios_mock_exito(): void
    {
        $stub = $this->createStub(ComerciosServiceInterface::class);
        $stub->method('obtenerMicrositio')->willReturn([
            'id_comercio' => 2,
            'nombre' => 'Comercio de prueba',
            'estado' => 'activo',
        ]);
        $this->app->instance(ComerciosServiceInterface::class, $stub);

        $servicio = app(ComerciosServiceInterface::class);
        $micrositio = $servicio->obtenerMicrositio(2);

        $this->assertSame('activo', $micrositio['estado']);
    }

    public function test_m2_comercios_mock_error_suspendido(): void
    {
        $stub = $this->createStub(ComerciosServiceInterface::class);
        $stub->method('obtenerMicrositio')->willThrowException(new Exception('Comercio suspendido', 409));
        $this->app->instance(ComerciosServiceInterface::class, $stub);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(409);

        app(ComerciosServiceInterface::class)->obtenerMicrositio(3);
    }

    public function test_m3_catalogo_mock_exito(): void
    {
        $stub = $this->createStub(CatalogoServiceInterface::class);
        $stub->method('obtenerVariacion')->willReturn([
            'id_variacion' => 1,
            'nombre' => 'Producto de prueba',
            'precio' => 15000,
            'tarifa_impuesto' => 13.00,
            'id_comercio' => 2,
        ]);
        $this->app->instance(CatalogoServiceInterface::class, $stub);

        $variacion = app(CatalogoServiceInterface::class)->obtenerVariacion(1);

        $this->assertSame('Producto de prueba', $variacion['nombre']);
        $this->assertSame(2, $variacion['id_comercio']);
    }

    public function test_m4_inventario_mock_exito(): void
    {
        $stub = $this->createStub(InventarioServiceInterface::class);
        $stub->method('consultarExistencias')->willReturn(10);
        $stub->method('solicitarReserva')->willReturn([
            'id_reserva' => '3f2b8a52-6c1e-4d0b-89a7-bbc5d8d53f99',
            'expiracion' => now()->addMinutes(15),
        ]);
        $this->app->instance(InventarioServiceInterface::class, $stub);

        $servicio = app(InventarioServiceInterface::class);
        $this->assertGreaterThanOrEqual(2, $servicio->consultarExistencias(1));

        $reserva = $servicio->solicitarReserva(1, 2, 1);
        $this->assertSame('3f2b8a52-6c1e-4d0b-89a7-bbc5d8d53f99', $reserva['id_reserva']);
    }

    public function test_m4_inventario_mock_error_sin_stock(): void
    {
        $stub = $this->createStub(InventarioServiceInterface::class);
        $stub->method('solicitarReserva')->willThrowException(new Exception('Existencia insuficiente', 422));
        $this->app->instance(InventarioServiceInterface::class, $stub);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(422);

        app(InventarioServiceInterface::class)->solicitarReserva(1, 100, 1);
    }

    public function test_m6_pagos_mock_exito(): void
    {
        $stub = $this->createStub(PagosServiceInterface::class);
        $stub->method('procesarPago')->willReturn([
            'urlPasarelaPago' => 'https://pago.pacificos.com/checkout/123',
            'transaccion_id' => 'TXN-12345',
        ]);
        $this->app->instance(PagosServiceInterface::class, $stub);

        $respuesta = app(PagosServiceInterface::class)->procesarPago(1, 15000, 'tarjeta', []);

        $this->assertSame('TXN-12345', $respuesta['transaccion_id']);
    }
}
