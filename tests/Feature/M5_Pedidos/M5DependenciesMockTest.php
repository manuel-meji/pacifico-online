<?php

namespace Tests\Feature\M5_Pedidos;

use App\Services\M5_Pedidos\Dobles\CatalogoStub;
use App\Services\M5_Pedidos\Dobles\ComerciosStub;
use App\Services\M5_Pedidos\Dobles\ErrorProveedorException;
use App\Services\M5_Pedidos\Dobles\InventarioStub;
use App\Services\M5_Pedidos\Dobles\PagosStub;
use App\Services\M5_Pedidos\Dobles\PortalStub;
use App\Services\M5_Pedidos\PedidosService;
use Tests\TestCase;

/**
 * Pruebas de integración para los dobles de prueba formales (Stubs/Fakes) de M5.
 *
 * Valida la interoperabilidad según los contratos oficiales publicados en OpenAPI
 * y el mecanismo de inyección contextual mediante M5IntegracionesServiceProvider.
 */
class M5DependenciesMockTest extends TestCase
{
    public function test_m1_portal_doble_exito(): void
    {
        $stub = new PortalStub;
        $destacados = $stub->getDestacados();

        $this->assertArrayHasKey('productos_destacados', $destacados);
        $this->assertArrayHasKey('comercios_destacados', $destacados);

        $busqueda = $stub->buscar('café');
        $this->assertSame('café', $busqueda['query']);
        $this->assertNotEmpty($busqueda['resultados']);
    }

    public function test_m1_portal_doble_error_invalido(): void
    {
        $this->expectException(ErrorProveedorException::class);
        $this->expectExceptionCode(422);

        $stub = new PortalStub;
        $stub->buscar('');
    }

    public function test_m2_comercios_doble_exito(): void
    {
        $stub = new ComerciosStub;
        $micrositio = $stub->obtenerMicrositio(ComerciosStub::COMERCIO_ACTIVO);

        $this->assertSame('activo', $micrositio['estado']);
        $this->assertSame(2, $micrositio['id_comercio']);
        $this->assertTrue($stub->registrarComision(101, 15000.00));
    }

    public function test_m2_comercios_doble_error_suspendido(): void
    {
        $this->expectException(ErrorProveedorException::class);
        $this->expectExceptionCode(409);

        $stub = new ComerciosStub;
        $stub->obtenerMicrositio(ComerciosStub::COMERCIO_SUSPENDIDO);
    }

    public function test_m3_catalogo_doble_exito(): void
    {
        $stub = new CatalogoStub;
        $variacion = $stub->obtenerVariacion(1);

        $this->assertNotNull($variacion);
        $this->assertSame('Producto de prueba', $variacion['nombre']);
        $this->assertSame(2, $variacion['id_comercio']);
        $this->assertSame(15000.00, $variacion['precio']);

        $detalle = $stub->obtenerDetalleProducto(1);
        $this->assertNotNull($detalle);
        $this->assertSame('Producto de prueba', $detalle['nombre']);
    }

    public function test_m3_catalogo_doble_error_no_encontrado(): void
    {
        $this->expectException(ErrorProveedorException::class);
        $this->expectExceptionCode(422);

        $stub = new CatalogoStub;
        $stub->obtenerVariacion(CatalogoStub::VARIACION_INEXISTENTE);
    }

    public function test_m4_inventario_doble_exito(): void
    {
        $stub = new InventarioStub;

        $this->assertGreaterThanOrEqual(2, $stub->consultarExistencias(1));

        $reserva = $stub->solicitarReserva(1, 2, 101);
        $this->assertNotEmpty($reserva['id_reserva']);
        $this->assertSame('activa', $reserva['estado']);

        $disponibilidad = $stub->consultarStockDisponible(2, 1);
        $this->assertSame('disponible', $disponibilidad['estado_disponibilidad']);
        $this->assertTrue($stub->confirmarSalidaPorDespacho(1));
    }

    public function test_m4_inventario_doble_error_sin_stock(): void
    {
        $this->expectException(ErrorProveedorException::class);
        $this->expectExceptionCode(409);

        $stub = new InventarioStub;
        $stub->solicitarReserva(1, InventarioStub::CANTIDAD_SIN_STOCK, 101);
    }

    public function test_m6_pagos_doble_exito(): void
    {
        $stub = new PagosStub;
        $respuesta = $stub->procesarPago(101, 15000.00, 'tarjeta', []);

        $this->assertSame('TXN-12345', $respuesta['transaccion_id']);
        $this->assertSame('aprobado', $respuesta['estado']);

        $factura = $stub->generarFactura(101);
        $this->assertStringStartsWith('FAC-', $factura['consecutivo']);
    }

    public function test_m6_pagos_doble_error_pago_rechazado(): void
    {
        $this->expectException(ErrorProveedorException::class);
        $this->expectExceptionCode(402);

        $stub = new PagosStub;
        $stub->procesarPago(101, 15000.00, 'rechazado', []);
    }

    public function test_resolucion_por_contenedor_m5_integraciones(): void
    {
        $servicio = $this->app->make(PedidosService::class);

        $ref = new \ReflectionClass($servicio);
        $propComercios = $ref->getProperty('comerciosService');
        $propCatalogo = $ref->getProperty('catalogoService');
        $propInventario = $ref->getProperty('inventarioService');
        $propPagos = $ref->getProperty('pagosService');
        $propPortal = $ref->getProperty('portalService');

        $this->assertInstanceOf(ComerciosStub::class, $propComercios->getValue($servicio));
        $this->assertInstanceOf(CatalogoStub::class, $propCatalogo->getValue($servicio));
        $this->assertInstanceOf(InventarioStub::class, $propInventario->getValue($servicio));
        $this->assertInstanceOf(PagosStub::class, $propPagos->getValue($servicio));
        $this->assertInstanceOf(PortalStub::class, $propPortal->getValue($servicio));
    }
}
