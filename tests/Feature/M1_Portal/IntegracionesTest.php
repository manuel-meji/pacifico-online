<?php

namespace Tests\Feature\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\CriterioInvalidoException;
use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Contracts\M1_Portal\Exceptions\RecursoNoEncontradoException;
use App\Services\M1_Portal\Dobles\CatalogoFake;
use App\Services\M1_Portal\Dobles\ComerciosStub;
use App\Services\M1_Portal\Dobles\DatosPrueba;
use App\Services\M1_Portal\DTO\CriterioBusquedaDTO;
use App\Services\M1_Portal\DTO\Disponibilidad;
use App\Services\M1_Portal\DTO\EstadoComercio;
use App\Services\M1_Portal\Integraciones\CachePortal;
use App\Services\M1_Portal\Integraciones\CarritoClient;
use App\Services\M1_Portal\Integraciones\CatalogoClient;
use App\Services\M1_Portal\Integraciones\ComercioClient;
use App\Services\M1_Portal\Integraciones\InventarioClient;
use Tests\Doubles\M1\CarritoNoDisponible;
use Tests\Doubles\M1\CatalogoNoDisponible;
use Tests\Doubles\M1\ComerciosNoDisponible;
use Tests\Doubles\M1\InventarioNoDisponible;
use Tests\TestCase;

/**
 * Adaptadores de M1 (Vista 2 y 3 del PC3) contra los dobles de M2, M3, M4 y M5:
 * traducen las respuestas a DTO, convierten los errores del proveedor en las
 * excepciones del contrato de M1 y respetan el tiempo máximo (RNF-M1-04) y la
 * caché (RNF-M1-06). Son las pruebas de contrato de M1 como consumidor.
 */
class IntegracionesTest extends TestCase
{
    // ---------------------------------------------------------------
    // CatalogoClient (M3)
    // ---------------------------------------------------------------

    public function test_catalogo_busca_y_traduce_cada_producto_a_producto_resumen(): void
    {
        $resultado = app(CatalogoClient::class)->buscarProductos(new CriterioBusquedaDTO(termino: 'camisa'));

        $this->assertSame(1, $resultado->totalResultados);
        $producto = $resultado->productos[0];
        $this->assertSame(DatosPrueba::PRODUCTO_CAMISA_LINO, $producto->idProducto);
        $this->assertSame(DatosPrueba::COMERCIO_LA_CEIBA, $producto->idComercio);
        $this->assertSame(18500.0, $producto->precio);
        $this->assertSame(21000.0, $producto->precioHasta);
        $this->assertSame('CRC', $producto->moneda);
    }

    public function test_catalogo_iguala_el_precio_hasta_al_precio_cuando_todas_las_variaciones_cuestan_igual(): void
    {
        $producto = app(CatalogoClient::class)->buscarProductos(new CriterioBusquedaDTO(termino: 'sombrero'))->productos[0];

        $this->assertSame($producto->precio, $producto->precioHasta);
    }

    public function test_catalogo_envia_a_m3_el_filtro_por_categoria_y_la_paginacion(): void
    {
        $resultado = app(CatalogoClient::class)->buscarProductos(new CriterioBusquedaDTO(
            idCategoria: DatosPrueba::CATEGORIA_ROPA,
            orden: 'precio_asc',
            pagina: 1,
            porPagina: 1,
        ));

        $this->assertSame(2, $resultado->totalResultados);
        $this->assertCount(1, $resultado->productos);
        $this->assertSame(DatosPrueba::PRODUCTO_SOMBRERO_AGOTADO, $resultado->productos[0]->idProducto);
    }

    public function test_catalogo_convierte_un_criterio_rechazado_por_m3_en_criterio_invalido(): void
    {
        $this->expectException(CriterioInvalidoException::class);

        app(CatalogoClient::class)->buscarProductos(new CriterioBusquedaDTO(porPagina: 500));
    }

    public function test_catalogo_informa_err_006_si_m3_no_responde(): void
    {
        config(['integraciones.m1.m3.doble' => CatalogoNoDisponible::class]);

        $this->expectException(DependenciaNoDisponibleException::class);

        app(CatalogoClient::class)->buscarProductos(new CriterioBusquedaDTO(termino: 'café'));
    }

    public function test_catalogo_busca_por_ids_en_el_orden_pedido_y_omite_los_no_visibles(): void
    {
        $productos = app(CatalogoClient::class)->buscarPorIds([
            DatosPrueba::PRODUCTO_JARRON,
            DatosPrueba::PRODUCTO_DE_COMERCIO_SUSPENDIDO,
            DatosPrueba::PRODUCTO_CAFE_MOLIDO,
        ]);

        $this->assertSame(
            [DatosPrueba::PRODUCTO_JARRON, DatosPrueba::PRODUCTO_CAFE_MOLIDO],
            array_map(fn ($producto) => $producto->idProducto, $productos),
        );
    }

    public function test_catalogo_arma_la_ficha_con_el_nombre_de_la_marca_y_la_venta_de_cada_variacion(): void
    {
        $ficha = app(CatalogoClient::class)->obtenerProducto(DatosPrueba::PRODUCTO_JARRON);

        $this->assertSame('Jarrón de barro chorotega', $ficha->nombre);
        $this->assertNull($ficha->marca);
        $this->assertSame(DatosPrueba::COMERCIO_ARTESANIAS_GUAITIL, $ficha->comercio->idComercio);
        $this->assertFalse($ficha->variaciones[0]->ventaHabilitada);
        $this->assertTrue($ficha->variaciones[1]->ventaHabilitada);
        $this->assertFalse($ficha->compraHabilitada);

        $camisa = app(CatalogoClient::class)->obtenerProducto(DatosPrueba::PRODUCTO_CAMISA_LINO);
        $this->assertSame('Ceiba Wear', $camisa->marca);
        $this->assertSame([['tipo_atributo' => 'Talla', 'valor' => 'S'], ['tipo_atributo' => 'Color', 'valor' => 'Blanco']], $camisa->variaciones[0]->atributos);
    }

    public function test_catalogo_convierte_un_producto_inexistente_o_de_comercio_suspendido_en_recurso_no_encontrado(): void
    {
        $cliente = app(CatalogoClient::class);

        foreach ([DatosPrueba::PRODUCTO_INEXISTENTE, DatosPrueba::PRODUCTO_DE_COMERCIO_SUSPENDIDO] as $idProducto) {
            try {
                $cliente->obtenerProducto($idProducto);
                $this->fail("El producto {$idProducto} no debería encontrarse.");
            } catch (RecursoNoEncontradoException $excepcion) {
                $this->assertSame('ERR-004', $excepcion->codigo());
            }
        }
    }

    public function test_catalogo_lista_los_relacionados_visibles(): void
    {
        $relacionados = app(CatalogoClient::class)->listarRelacionados(DatosPrueba::PRODUCTO_JARRON);

        $this->assertSame([DatosPrueba::PRODUCTO_TAZA_SIN_EXISTENCIA], array_map(fn ($producto) => $producto->idProducto, $relacionados));
    }

    public function test_catalogo_guarda_marcas_y_categorias_en_la_cache_y_no_las_vuelve_a_pedir(): void
    {
        $contador = new class extends CatalogoFake
        {
            public int $llamadasMarcas = 0;

            public int $llamadasCategorias = 0;

            public function listarMarcas(): array
            {
                $this->llamadasMarcas++;

                return parent::listarMarcas();
            }

            public function listarCategorias(): array
            {
                $this->llamadasCategorias++;

                return parent::listarCategorias();
            }
        };
        $this->app->instance(CatalogoFake::class, $contador);
        $cliente = app(CatalogoClient::class);

        $cliente->obtenerProducto(DatosPrueba::PRODUCTO_CAMISA_LINO);
        $cliente->obtenerProducto(DatosPrueba::PRODUCTO_CAFE_MOLIDO);
        $cliente->listarCategorias();
        $categorias = $cliente->listarCategorias();

        $this->assertSame(1, $contador->llamadasMarcas);
        $this->assertSame(1, $contador->llamadasCategorias);
        $this->assertCount(9, $categorias);
        $this->assertSame(DatosPrueba::CATEGORIA_ROPA, $categorias[1]->idCategoriaPadre);
    }

    // ---------------------------------------------------------------
    // ComercioClient (M2)
    // ---------------------------------------------------------------

    public function test_comercio_traduce_los_datos_publicos_a_comercio_dto(): void
    {
        $comercio = app(ComercioClient::class)->obtenerDatosComercioPublico(DatosPrueba::COMERCIO_LA_CEIBA);

        $this->assertSame('Tienda La Ceiba', $comercio->nombreComercial);
        $this->assertSame(EstadoComercio::Activo, $comercio->estado);
        $this->assertSame('https://cdn.pacificos.example/comercios/34/logo.png', $comercio->logotipo);
        $this->assertCount(2, $comercio->imagenes);
        $this->assertCount(7, $comercio->horarios);
        $this->assertSame('Liberia, Guanacaste', $comercio->contacto['direccion']);
    }

    public function test_comercio_suspendido_o_inexistente_lanza_recurso_no_encontrado_rn_m1_01(): void
    {
        $this->expectException(RecursoNoEncontradoException::class);

        app(ComercioClient::class)->obtenerDatosComercioPublico(DatosPrueba::COMERCIO_SUSPENDIDO);
    }

    public function test_comercio_resume_en_lote_en_el_orden_pedido_y_omite_los_no_habilitados(): void
    {
        $comercios = app(ComercioClient::class)->obtenerResumenComercios([
            DatosPrueba::COMERCIO_ARTESANIAS_GUAITIL,
            DatosPrueba::COMERCIO_SUSPENDIDO,
            DatosPrueba::COMERCIO_LA_CEIBA,
        ]);

        $this->assertSame(['Artesanías Guaitil', 'Tienda La Ceiba'], array_map(fn ($comercio) => $comercio->nombreComercial, $comercios));
    }

    public function test_comercio_lista_los_habilitados_con_su_paginacion(): void
    {
        $listado = app(ComercioClient::class)->listarComerciosHabilitados(1, 2);

        $this->assertSame(3, $listado->total);
        $this->assertCount(2, $listado->comercios);
        $this->assertSame(2, $listado->porPagina);
    }

    public function test_comercio_informa_err_006_si_m2_no_responde(): void
    {
        config(['integraciones.m1.m2.doble' => ComerciosNoDisponible::class]);

        $this->expectException(DependenciaNoDisponibleException::class);

        app(ComercioClient::class)->obtenerDatosComercioPublico(DatosPrueba::COMERCIO_LA_CEIBA);
    }

    public function test_un_proveedor_que_supera_el_tiempo_maximo_se_trata_como_no_disponible_rnf_m1_04(): void
    {
        config(['integraciones.m1.tiempo_maximo' => 0.05]);
        $this->app->instance(ComerciosStub::class, new class extends ComerciosStub
        {
            public function listarComerciosHabilitados(int $pagina = 1, int $porPagina = 20, bool $destacado = false): array
            {
                usleep(150_000);

                return parent::listarComerciosHabilitados($pagina, $porPagina, $destacado);
            }
        });

        $this->expectException(DependenciaNoDisponibleException::class);

        app(ComercioClient::class)->listarComerciosHabilitados(1, 20);
    }

    public function test_un_proveedor_real_sin_la_operacion_publicada_se_trata_como_no_disponible(): void
    {
        config(['integraciones.m1.m2.modo' => 'real']);

        $this->expectException(DependenciaNoDisponibleException::class);

        app(ComercioClient::class)->obtenerDatosComercioPublico(DatosPrueba::COMERCIO_LA_CEIBA);
    }

    // ---------------------------------------------------------------
    // InventarioClient (M4)
    // ---------------------------------------------------------------

    public function test_inventario_traduce_la_disponibilidad_por_producto_y_respeta_el_orden(): void
    {
        $disponibilidades = app(InventarioClient::class)->consultarDisponibilidadProductos([
            DatosPrueba::PRODUCTO_CAMISA_LINO => DatosPrueba::COMERCIO_LA_CEIBA,
            DatosPrueba::PRODUCTO_SOMBRERO_AGOTADO => DatosPrueba::COMERCIO_LA_CEIBA,
            DatosPrueba::PRODUCTO_TAZA_SIN_EXISTENCIA => DatosPrueba::COMERCIO_ARTESANIAS_GUAITIL,
        ]);

        $this->assertSame(
            [DatosPrueba::PRODUCTO_CAMISA_LINO, DatosPrueba::PRODUCTO_SOMBRERO_AGOTADO, DatosPrueba::PRODUCTO_TAZA_SIN_EXISTENCIA],
            array_map(fn ($disponibilidad) => $disponibilidad->idProducto, $disponibilidades),
        );
        $this->assertSame(
            [Disponibilidad::Disponible, Disponibilidad::Agotado, Disponibilidad::Agotado],
            array_map(fn ($disponibilidad) => $disponibilidad->estado, $disponibilidades),
        );
    }

    public function test_inventario_consulta_las_variaciones_de_un_producto(): void
    {
        $disponibilidades = app(InventarioClient::class)->consultarDisponibilidadVariaciones(
            DatosPrueba::PRODUCTO_CAMISA_LINO,
            DatosPrueba::COMERCIO_LA_CEIBA,
            [DatosPrueba::VARIACION_CAMISA_S, DatosPrueba::VARIACION_CAMISA_M_AGOTADA],
        );

        $this->assertSame(DatosPrueba::VARIACION_CAMISA_S, $disponibilidades[0]->idVariacion);
        $this->assertSame(Disponibilidad::Disponible, $disponibilidades[0]->estado);
        $this->assertSame(Disponibilidad::Agotado, $disponibilidades[1]->estado);
        $this->assertSame(DatosPrueba::PRODUCTO_CAMISA_LINO, $disponibilidades[1]->idProducto);
    }

    public function test_inventario_informa_err_006_si_m4_no_responde(): void
    {
        config(['integraciones.m1.m4.doble' => InventarioNoDisponible::class]);

        $this->expectException(DependenciaNoDisponibleException::class);

        app(InventarioClient::class)->consultarDisponibilidadProductos([DatosPrueba::PRODUCTO_CAMISA_LINO => DatosPrueba::COMERCIO_LA_CEIBA]);
    }

    // ---------------------------------------------------------------
    // CarritoClient (M5)
    // ---------------------------------------------------------------

    public function test_carrito_agrega_una_variacion_y_el_contador_suma_las_unidades(): void
    {
        $carrito = app(CarritoClient::class);

        $this->assertSame(2, $carrito->contarItems(DatosPrueba::CLIENTE_CON_CARRITO));

        $carrito->agregarItem(DatosPrueba::CLIENTE_CON_CARRITO, DatosPrueba::VARIACION_CAFE_MOLIDO, 3);

        $this->assertSame(5, app(CarritoClient::class)->contarItems(DatosPrueba::CLIENTE_CON_CARRITO));
    }

    public function test_carrito_convierte_la_falta_de_unidades_de_m5_en_datos_invalidos(): void
    {
        try {
            app(CarritoClient::class)->agregarItem(DatosPrueba::CLIENTE_CARRITO_VACIO, DatosPrueba::VARIACION_CAMISA_M_AGOTADA, 1);
            $this->fail('M5 debió rechazar una variación sin unidades.');
        } catch (DatosInvalidosException $excepcion) {
            $this->assertSame('ERR-005', $excepcion->codigo());
            $this->assertArrayHasKey('cantidad', $excepcion->detalles());
        }
    }

    public function test_carrito_convierte_una_variacion_no_vendible_en_datos_invalidos_con_su_detalle(): void
    {
        try {
            app(CarritoClient::class)->agregarItem(DatosPrueba::CLIENTE_CARRITO_VACIO, DatosPrueba::VARIACION_JARRON_NO_VENDIBLE, 1);
            $this->fail('M5 debió rechazar una variación que no está a la venta.');
        } catch (DatosInvalidosException $excepcion) {
            $this->assertArrayHasKey('id_variacion', $excepcion->detalles());
        }
    }

    public function test_carrito_informa_err_006_si_m5_no_responde(): void
    {
        config(['integraciones.m1.m5.doble' => CarritoNoDisponible::class]);

        $this->expectException(DependenciaNoDisponibleException::class);

        app(CarritoClient::class)->contarItems(DatosPrueba::CLIENTE_CON_CARRITO);
    }

    // ---------------------------------------------------------------
    // CachePortal (RNF-M1-06)
    // ---------------------------------------------------------------

    public function test_la_cache_guarda_y_devuelve_valores(): void
    {
        $cache = app(CachePortal::class);

        $this->assertNull($cache->obtener('prueba'));

        $cache->guardar('prueba', ['valor' => 1]);

        $this->assertSame(['valor' => 1], $cache->obtener('prueba'));
    }

    public function test_la_cache_nunca_vive_mas_de_cinco_segundos_aunque_la_configuracion_diga_mas(): void
    {
        config(['integraciones.m1.cache_segundos' => 60]);
        $cache = app(CachePortal::class);
        $cache->guardar('prueba', 'valor');

        $this->travel(6)->seconds();

        $this->assertNull($cache->obtener('prueba'));
    }

    public function test_invalidar_un_comercio_olvida_sus_datos_publicos_y_su_resumen(): void
    {
        $cache = app(CachePortal::class);
        $cache->guardar(sprintf(CachePortal::CLAVE_COMERCIO_PUBLICO, 34), ['id_comercio' => 34]);
        $cache->guardar(sprintf(CachePortal::CLAVE_COMERCIO_RESUMEN, 34), ['id_comercio' => 34]);
        $cache->guardar(sprintf(CachePortal::CLAVE_COMERCIO_PUBLICO, 35), ['id_comercio' => 35]);

        $cache->invalidarComercio(34);

        $this->assertNull($cache->obtener(sprintf(CachePortal::CLAVE_COMERCIO_PUBLICO, 34)));
        $this->assertNull($cache->obtener(sprintf(CachePortal::CLAVE_COMERCIO_RESUMEN, 34)));
        $this->assertNotNull($cache->obtener(sprintf(CachePortal::CLAVE_COMERCIO_PUBLICO, 35)));
    }

    public function test_una_marca_de_agotado_se_impone_a_la_disponibilidad_guardada_y_se_quita_al_reponer(): void
    {
        $inventario = app(InventarioClient::class);
        $cache = app(CachePortal::class);
        $camisaS = fn () => $inventario->consultarDisponibilidadVariaciones(
            DatosPrueba::PRODUCTO_CAMISA_LINO,
            DatosPrueba::COMERCIO_LA_CEIBA,
            [DatosPrueba::VARIACION_CAMISA_S],
        )[0]->estado;

        $this->assertSame(Disponibilidad::Disponible, $camisaS());

        $cache->marcarAgotado(DatosPrueba::PRODUCTO_CAMISA_LINO, DatosPrueba::VARIACION_CAMISA_S);
        $this->assertSame(Disponibilidad::Agotado, $camisaS());

        $cache->quitarAgotado(DatosPrueba::PRODUCTO_CAMISA_LINO, DatosPrueba::VARIACION_CAMISA_S);
        $this->assertSame(Disponibilidad::Disponible, $camisaS());
    }

    public function test_un_producto_marcado_agotado_se_muestra_agotado_en_las_listas(): void
    {
        app(CachePortal::class)->marcarAgotado(DatosPrueba::PRODUCTO_CAFE_MOLIDO, null);

        $disponibilidad = app(InventarioClient::class)->consultarDisponibilidadProductos([
            DatosPrueba::PRODUCTO_CAFE_MOLIDO => DatosPrueba::COMERCIO_CAFE_NICOYANO,
        ]);

        $this->assertSame(Disponibilidad::Agotado, $disponibilidad[0]->estado);
    }
}
