<?php

namespace Tests\Feature\M1_Portal;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use App\Providers\M1IntegracionesServiceProvider;
use App\Services\M1_Portal\Dobles\CarritoFake;
use App\Services\M1_Portal\Dobles\CatalogoFake;
use App\Services\M1_Portal\Dobles\ComerciosStub;
use App\Services\M1_Portal\Dobles\DatosPrueba;
use App\Services\M1_Portal\Dobles\ErrorProveedorException;
use App\Services\M1_Portal\Dobles\InventarioStub;
use App\Services\M1_Portal\PortalService;
use App\Services\M2_Comercios\ComerciosService;
use Tests\Doubles\M1\CarritoNoDisponible;
use Tests\Doubles\M1\CatalogoNoDisponible;
use Tests\Doubles\M1\CatalogoRelacionadosNoDisponible;
use Tests\Doubles\M1\ComerciosNoDisponible;
use Tests\Doubles\M1\InventarioNoDisponible;
use Tests\TestCase;

/**
 * Verifica que los dobles de M1 sean coherentes entre sí, se comporten según
 * los contratos de sus proveedores y se seleccionen desde config/integraciones.php.
 */
class DoblesCoherenciaTest extends TestCase
{
    public function test_los_servicios_de_m1_reciben_los_dobles_por_defecto(): void
    {
        $this->assertInstanceOf(ComerciosStub::class, $this->proveedorDeM1(ComerciosServiceInterface::class));
        $this->assertInstanceOf(CatalogoFake::class, $this->proveedorDeM1(CatalogoServiceInterface::class));
        $this->assertInstanceOf(InventarioStub::class, $this->proveedorDeM1(InventarioServiceInterface::class));
        $this->assertInstanceOf(CarritoFake::class, $this->proveedorDeM1(PedidosServiceInterface::class));
    }

    public function test_una_prueba_puede_elegir_una_variante_de_error_por_configuracion(): void
    {
        config(['integraciones.m1.m2.doble' => ComerciosNoDisponible::class]);

        $this->assertInstanceOf(ComerciosNoDisponible::class, $this->proveedorDeM1(ComerciosServiceInterface::class));
    }

    public function test_el_modo_real_entrega_el_servicio_del_proveedor(): void
    {
        config(['integraciones.m1.m2.modo' => 'real']);

        $this->assertInstanceOf(ComerciosService::class, $this->proveedorDeM1(ComerciosServiceInterface::class));
    }

    public function test_los_dobles_no_afectan_a_los_demas_modulos(): void
    {
        $this->assertInstanceOf(ComerciosService::class, app(ComerciosServiceInterface::class));
    }

    public function test_el_fake_de_m5_conserva_su_estado_durante_la_peticion(): void
    {
        $primero = $this->proveedorDeM1(PedidosServiceInterface::class);
        $primero->agregarItemCarrito(DatosPrueba::CLIENTE_CARRITO_VACIO, ['id_variacion' => DatosPrueba::VARIACION_CAFE_MOLIDO]);

        $segundo = $this->proveedorDeM1(PedidosServiceInterface::class);

        $this->assertCount(1, $segundo->obtenerCarrito(DatosPrueba::CLIENTE_CARRITO_VACIO)['items']);
    }

    public function test_los_datos_de_m2_m3_y_m4_comparten_identificadores(): void
    {
        $comercios = DatosPrueba::comercios();
        $productos = DatosPrueba::productos();

        foreach ($productos as $producto) {
            $this->assertArrayHasKey($producto['id_comercio'], $comercios, "Producto {$producto['id_producto']} sin comercio en M2.");

            foreach ($producto['relacionados'] as $idRelacionado) {
                $this->assertArrayHasKey($idRelacionado, $productos);
            }
        }

        foreach (DatosPrueba::existencias() as $existencia) {
            $encontrada = DatosPrueba::buscarVariacion($existencia['id_variacion']);

            $this->assertNotNull($encontrada, "Existencia de M4 con variación {$existencia['id_variacion']} inexistente en M3.");
            $this->assertSame($existencia['id_producto'], $encontrada['producto']['id_producto']);
            $this->assertSame($existencia['id_comercio'], $encontrada['producto']['id_comercio']);
        }

        $idsVisibles = array_values(array_unique(array_column(
            (new CatalogoFake)->buscarProductos(['por_pagina' => 50])['productos'],
            'id_comercio',
        )));
        $resumen = (new ComerciosStub)->obtenerResumenComerciosLote($idsVisibles);

        $this->assertCount(count($idsVisibles), $resumen['comercios'], 'M3 devuelve productos de comercios que M2 no habilita.');
    }

    public function test_m2_solo_expone_comercios_activos(): void
    {
        $m2 = new ComerciosStub;

        $this->assertSame('Tienda La Ceiba', $m2->obtenerDatosComercioPublico(DatosPrueba::COMERCIO_LA_CEIBA)['nombre_comercial']);
        $this->assertErrorProveedor('ERR-004', fn () => $m2->obtenerDatosComercioPublico(DatosPrueba::COMERCIO_SUSPENDIDO));
        $this->assertErrorProveedor('ERR-004', fn () => $m2->obtenerDatosComercioPublico(DatosPrueba::COMERCIO_INEXISTENTE));

        $lote = $m2->obtenerResumenComerciosLote([
            DatosPrueba::COMERCIO_CAFE_NICOYANO, DatosPrueba::COMERCIO_SUSPENDIDO, DatosPrueba::COMERCIO_INEXISTENTE, DatosPrueba::COMERCIO_LA_CEIBA,
        ]);
        $this->assertSame([DatosPrueba::COMERCIO_CAFE_NICOYANO, DatosPrueba::COMERCIO_LA_CEIBA], array_column($lote['comercios'], 'id_comercio'));

        $this->assertSame(3, $m2->listarComerciosHabilitados()['total']);
        $this->assertSame(
            [DatosPrueba::COMERCIO_ARTESANIAS_GUAITIL, DatosPrueba::COMERCIO_LA_CEIBA],
            array_column($m2->listarComerciosHabilitados(destacado: true)['comercios'], 'id_comercio'),
        );

        $this->assertErrorProveedor('ERR-005', fn () => $m2->obtenerResumenComerciosLote([]));
        $this->assertErrorProveedor('ERR-005', fn () => $m2->obtenerResumenComerciosLote(range(1, 51)));
        $this->assertErrorProveedor('ERR-005', fn () => $m2->listarComerciosHabilitados(porPagina: 51));
    }

    public function test_m3_busca_solo_productos_visibles_y_aplica_filtros(): void
    {
        $m3 = new CatalogoFake;

        $ropa = $m3->buscarProductos(['id_categoria' => DatosPrueba::CATEGORIA_ROPA]);
        $this->assertSame([DatosPrueba::PRODUCTO_CAMISA_LINO, DatosPrueba::PRODUCTO_SOMBRERO_AGOTADO], array_column($ropa['productos'], 'id_producto'));

        $this->assertSame(0, $m3->buscarProductos(['id_categoria' => DatosPrueba::CATEGORIA_SOLO_SUSPENDIDO])['total_resultados']);
        $this->assertSame(0, $m3->buscarProductos(['id_categoria' => DatosPrueba::CATEGORIA_VACIA])['total_resultados']);
        // Sin tildes ni mayúsculas: coincide con los dos cafés y con la taza ("para café").
        $this->assertSame(3, $m3->buscarProductos(['termino' => 'CAFE'])['total_resultados']);
        $this->assertSame(
            [DatosPrueba::PRODUCTO_CAMISA_LINO],
            array_column($m3->buscarProductos(['precio_minimo' => 20000, 'precio_maximo' => 22000])['productos'], 'id_producto'),
        );

        $camisa = $m3->buscarProductos(['ids' => [DatosPrueba::PRODUCTO_CAMISA_LINO]])['productos'][0];
        $this->assertSame([18500.0, 21000.0], [$camisa['precio_desde'], $camisa['precio_hasta']]);

        $this->assertErrorProveedor('ERR-005', fn () => $m3->buscarProductos(['texto' => 'camisa']));
        $this->assertErrorProveedor('ERR-005', fn () => $m3->buscarProductos(['orden' => 'nombre']));
    }

    public function test_m3_oculta_productos_de_comercios_no_activos_en_ficha_y_relacionados(): void
    {
        $m3 = new CatalogoFake;

        $this->assertCount(2, $m3->obtenerProducto(DatosPrueba::PRODUCTO_JARRON)['variaciones']);
        $this->assertErrorProveedor('ERR-004', fn () => $m3->obtenerProducto(DatosPrueba::PRODUCTO_DE_COMERCIO_SUSPENDIDO));
        $this->assertErrorProveedor('ERR-004', fn () => $m3->obtenerProducto(DatosPrueba::PRODUCTO_INEXISTENTE));

        $this->assertSame(
            [DatosPrueba::PRODUCTO_TAZA_SIN_EXISTENCIA],
            array_column($m3->listarRelacionados(DatosPrueba::PRODUCTO_JARRON), 'id_producto'),
        );
    }

    public function test_m4_calcula_la_disponibilidad_por_variacion_y_por_producto(): void
    {
        $m4 = new InventarioStub;

        [$camisaS, $camisaSTienda, $camisaM, $camisaL, $taza, $otroComercio] = $m4->consultarDisponibilidadLote([
            ['id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA, 'id_variacion' => DatosPrueba::VARIACION_CAMISA_S],
            ['id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA, 'id_variacion' => DatosPrueba::VARIACION_CAMISA_S, 'id_ubicacion' => DatosPrueba::UBICACION_LA_CEIBA_TIENDA],
            ['id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA, 'id_variacion' => DatosPrueba::VARIACION_CAMISA_M_AGOTADA],
            ['id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA, 'id_variacion' => DatosPrueba::VARIACION_CAMISA_L_BAJO_STOCK],
            ['id_comercio' => DatosPrueba::COMERCIO_ARTESANIAS_GUAITIL, 'id_variacion' => DatosPrueba::VARIACION_TAZA_SIN_EXISTENCIA],
            ['id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA, 'id_variacion' => DatosPrueba::VARIACION_CAFE_MOLIDO],
        ]);

        $this->assertSame([5, null, 'disponible'], [$camisaS['cantidad_disponible'], $camisaS['id_ubicacion'], $camisaS['estado_disponibilidad']]);
        $this->assertSame(3, $camisaSTienda['cantidad_disponible']);
        $this->assertSame('agotado', $camisaM['estado_disponibilidad']);
        $this->assertTrue($camisaL['bajo_stock']);
        $this->assertSame(['agotado', false], [$taza['estado_disponibilidad'], $taza['bajo_stock']]);
        $this->assertSame('agotado', $otroComercio['estado_disponibilidad']);

        $productos = $m4->consultarDisponibilidadProductoLote([
            ['id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA, 'id_producto' => DatosPrueba::PRODUCTO_CAMISA_LINO],
            ['id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA, 'id_producto' => DatosPrueba::PRODUCTO_SOMBRERO_AGOTADO],
            ['id_comercio' => DatosPrueba::COMERCIO_ARTESANIAS_GUAITIL, 'id_producto' => DatosPrueba::PRODUCTO_TAZA_SIN_EXISTENCIA],
        ]);
        $this->assertSame(['disponible', 'agotado', 'agotado'], array_column($productos, 'estado_disponibilidad'));

        $this->assertErrorProveedor('ERR-005', fn () => $m4->consultarDisponibilidadLote([]));
        $this->assertErrorProveedor('ERR-005', fn () => $m4->consultarDisponibilidadProductoLote([['id_comercio' => 34]]));
    }

    public function test_m5_agrega_al_carrito_y_valida_con_catalogo_e_inventario(): void
    {
        $m5 = new CarritoFake;

        $inicial = $m5->obtenerCarrito(DatosPrueba::CLIENTE_CON_CARRITO);
        $this->assertSame([DatosPrueba::CARRITO_CLIENTE_CON_CARRITO, 13600.0], [$inicial['id_carrito'], $inicial['monto_estimado']]);

        $actualizado = $m5->agregarItemCarrito(DatosPrueba::CLIENTE_CON_CARRITO, ['id_variacion' => DatosPrueba::VARIACION_CAMISA_S, 'cantidad' => 2]);
        $this->assertCount(2, $actualizado['items']);
        $this->assertSame(50600.0, $actualizado['monto_estimado']);
        $this->assertSame($actualizado, $m5->obtenerCarrito(DatosPrueba::CLIENTE_CON_CARRITO));

        $this->assertErrorProveedor('ERR-M5-001', fn () => $m5->agregarItemCarrito(1, ['id_variacion' => DatosPrueba::VARIACION_CAMISA_M_AGOTADA]));
        $this->assertErrorProveedor('ERR-M5-001', fn () => $m5->agregarItemCarrito(1, ['id_variacion' => DatosPrueba::VARIACION_CAMISA_S, 'cantidad' => 6]));
        $this->assertErrorProveedor('ERR-005', fn () => $m5->agregarItemCarrito(1, ['id_variacion' => DatosPrueba::VARIACION_JARRON_NO_VENDIBLE]));
        $this->assertErrorProveedor('ERR-005', fn () => $m5->agregarItemCarrito(1, ['id_variacion' => DatosPrueba::VARIACION_DE_COMERCIO_SUSPENDIDO]));
        $this->assertErrorProveedor('ERR-005', fn () => $m5->agregarItemCarrito(1, ['id_variacion' => DatosPrueba::VARIACION_INEXISTENTE]));
        $this->assertErrorProveedor('ERR-005', fn () => $m5->agregarItemCarrito(1, ['id_variacion' => DatosPrueba::VARIACION_CAFE_MOLIDO, 'cantidad' => 0]));
        $this->assertErrorProveedor('ERR-005', fn () => $m5->agregarItemCarrito(1, ['id_variacion' => DatosPrueba::VARIACION_CAFE_MOLIDO, 'id_reserva' => 'no-uuid']));
    }

    public function test_las_variantes_no_disponibles_lanzan_err_006(): void
    {
        $this->assertErrorProveedor('ERR-006', fn () => (new ComerciosNoDisponible)->listarComerciosHabilitados());
        $this->assertErrorProveedor('ERR-006', fn () => (new CatalogoNoDisponible)->buscarProductos());
        $this->assertErrorProveedor('ERR-006', fn () => (new InventarioNoDisponible)->consultarDisponibilidadProductoLote([
            ['id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA, 'id_producto' => DatosPrueba::PRODUCTO_CAMISA_LINO],
        ]));
        $this->assertErrorProveedor('ERR-006', fn () => (new CarritoNoDisponible)->obtenerCarrito(DatosPrueba::CLIENTE_CON_CARRITO));

        $soloRelacionados = new CatalogoRelacionadosNoDisponible;
        $this->assertSame(DatosPrueba::PRODUCTO_CAMISA_LINO, $soloRelacionados->obtenerProducto(DatosPrueba::PRODUCTO_CAMISA_LINO)['id_producto']);
        $this->assertErrorProveedor('ERR-006', fn () => $soloRelacionados->listarRelacionados(DatosPrueba::PRODUCTO_CAMISA_LINO));
    }

    /**
     * Resuelve la dependencia tal como la recibe un servicio de M1.
     */
    private function proveedorDeM1(string $interfaz): object
    {
        $consumidor = M1IntegracionesServiceProvider::CONSUMIDORES_M1[0];
        $this->assertSame(PortalService::class, $consumidor);

        return ($this->app->contextual[$consumidor][$interfaz])($this->app);
    }

    private function assertErrorProveedor(string $codigo, callable $accion): void
    {
        try {
            $accion();
        } catch (ErrorProveedorException $error) {
            $this->assertSame($codigo, $error->codigo, $error->getMessage());
            $this->assertSame($codigo, $error->toArray()['error']['codigo']);

            return;
        }

        $this->fail("Se esperaba un error {$codigo}.");
    }
}
