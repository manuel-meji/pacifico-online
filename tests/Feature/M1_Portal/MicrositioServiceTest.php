<?php

namespace Tests\Feature\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Contracts\M1_Portal\Exceptions\RecursoNoEncontradoException;
use App\Services\M1_Portal\Dobles\DatosPrueba;
use App\Services\M1_Portal\DTO\Disponibilidad;
use App\Services\M1_Portal\DTO\EstadoComercio;
use App\Services\M1_Portal\DTO\ProductoResumenDTO;
use App\Services\M1_Portal\MicrositioService;
use Tests\Doubles\M1\CatalogoNoDisponible;
use Tests\Doubles\M1\CatalogoSinProductos;
use Tests\Doubles\M1\ComercioConDatosMinimos;
use Tests\Doubles\M1\ComerciosNoDisponible;
use Tests\Doubles\M1\InventarioNoDisponible;
use Tests\Feature\M1_Portal\Concerns\ValidaEsquemaContrato;

/**
 * CU-009 Ver micrositio y página de comercios: MicrositioService con los
 * dobles de M2, M3 y M4, y las rutas GET /comercios y
 * GET /comercios/{id_comercio}/micrositio contra el contrato v1.3.0.
 */
class MicrositioServiceTest extends PruebaM1
{
    use ValidaEsquemaContrato;

    // ---------------------------------------------------------------
    // Flujo básico
    // ---------------------------------------------------------------

    public function test_el_micrositio_trae_el_comercio_y_solo_sus_productos_completos(): void
    {
        $micrositio = $this->servicio()->obtenerMicrositio(DatosPrueba::COMERCIO_LA_CEIBA, 1, 20);

        $this->assertSame(DatosPrueba::COMERCIO_LA_CEIBA, $micrositio->comercio->idComercio);
        $this->assertSame('Tienda La Ceiba', $micrositio->comercio->nombreComercial);
        $this->assertSame(EstadoComercio::Activo, $micrositio->comercio->estado);
        $this->assertSame(
            [DatosPrueba::PRODUCTO_CAMISA_LINO, DatosPrueba::PRODUCTO_SOMBRERO_AGOTADO],
            array_map(fn (ProductoResumenDTO $producto): int => $producto->idProducto, $micrositio->productos->productos),
        );
        $this->assertSame(2, $micrositio->productos->totalResultados);
        $this->assertTrue($micrositio->productosDisponibles);

        foreach ($micrositio->productos->productos as $producto) {
            $this->assertSame(DatosPrueba::COMERCIO_LA_CEIBA, $producto->idComercio);
            $this->assertSame('Tienda La Ceiba', $producto->nombreComercio);
        }

        $this->assertSame(Disponibilidad::Disponible, $micrositio->productos->productos[0]->disponibilidad);
        $this->assertSame(Disponibilidad::Agotado, $micrositio->productos->productos[1]->disponibilidad);
    }

    public function test_el_micrositio_conserva_banners_y_galeria_por_separado(): void
    {
        $comercio = $this->servicio()->obtenerMicrositio(DatosPrueba::COMERCIO_LA_CEIBA, 1, 20)->comercio;

        $this->assertSame([['imagen_url' => 'https://cdn.pacificos.example/comercios/34/banner-1.jpg', 'texto_alternativo' => 'Banner de Tienda La Ceiba']], $comercio->banners);
        $this->assertSame([['imagen_url' => 'https://cdn.pacificos.example/comercios/34/galeria-1.jpg', 'texto_alternativo' => 'Galería de Tienda La Ceiba']], $comercio->galeria);
    }

    public function test_los_productos_del_micrositio_se_paginan(): void
    {
        $micrositio = $this->servicio()->obtenerMicrositio(DatosPrueba::COMERCIO_LA_CEIBA, 2, 1);

        $this->assertSame(2, $micrositio->productos->pagina);
        $this->assertSame(1, $micrositio->productos->porPagina);
        $this->assertSame(2, $micrositio->productos->totalResultados);
        $this->assertSame(DatosPrueba::PRODUCTO_SOMBRERO_AGOTADO, $micrositio->productos->productos[0]->idProducto);
    }

    public function test_una_paginacion_fuera_de_rango_lanza_err_005_con_el_detalle_por_campo(): void
    {
        try {
            $this->servicio()->obtenerMicrositio(DatosPrueba::COMERCIO_LA_CEIBA, 0, 51);
            $this->fail('Debió lanzar DatosInvalidosException.');
        } catch (DatosInvalidosException $error) {
            $this->assertSame(['pagina' => 'Debe ser mayor o igual a 1.', 'por_pagina' => 'Debe estar entre 1 y 50.'], $error->detalles());
        }

        $this->expectException(DatosInvalidosException::class);
        $this->servicio()->listarComercios(1, 0);
    }

    public function test_los_limites_de_la_paginacion_son_validos(): void
    {
        $this->assertSame(MicrositioService::MAXIMO_POR_PAGINA, $this->servicio()->obtenerMicrositio(DatosPrueba::COMERCIO_LA_CEIBA, 1, 50)->productos->porPagina);
        $this->assertSame(1, $this->servicio()->listarComercios(1, 1)->porPagina);
    }

    public function test_si_m4_no_responde_los_productos_se_muestran_como_agotados(): void
    {
        config(['integraciones.m1.m4.doble' => InventarioNoDisponible::class]);

        $productos = $this->servicio()->obtenerMicrositio(DatosPrueba::COMERCIO_LA_CEIBA, 1, 20)->productos->productos;

        $this->assertCount(2, $productos);
        $this->assertSame([Disponibilidad::Agotado], array_values(array_unique(array_map(fn (ProductoResumenDTO $producto) => $producto->disponibilidad, $productos), SORT_REGULAR)));
    }

    // ---------------------------------------------------------------
    // A1: comercio inexistente o no activo (RN-M1-01)
    // ---------------------------------------------------------------

    public function test_a1_un_comercio_inexistente_lanza_err_004(): void
    {
        $this->expectException(RecursoNoEncontradoException::class);

        $this->servicio()->obtenerMicrositio(DatosPrueba::COMERCIO_INEXISTENTE, 1, 20);
    }

    public function test_a1_un_comercio_suspendido_lanza_err_004(): void
    {
        $this->expectException(RecursoNoEncontradoException::class);

        $this->servicio()->obtenerMicrositio(DatosPrueba::COMERCIO_SUSPENDIDO, 1, 20);
    }

    // ---------------------------------------------------------------
    // A2: comercio sin productos publicados
    // ---------------------------------------------------------------

    public function test_a2_un_comercio_sin_productos_trae_la_lista_vacia(): void
    {
        config(['integraciones.m1.m3.doble' => CatalogoSinProductos::class]);

        $micrositio = $this->servicio()->obtenerMicrositio(DatosPrueba::COMERCIO_CAFE_NICOYANO, 1, 20);

        $this->assertSame('Café Nicoyano', $micrositio->comercio->nombreComercial);
        $this->assertSame([], $micrositio->productos->productos);
        $this->assertSame(0, $micrositio->productos->totalResultados);
        // Sin productos no es lo mismo que M3 caído: no lleva aviso.
        $this->assertTrue($micrositio->productosDisponibles);
    }

    // ---------------------------------------------------------------
    // A3: campos opcionales vacíos
    // ---------------------------------------------------------------

    public function test_a3_los_campos_opcionales_vacios_no_dan_error(): void
    {
        config(['integraciones.m1.m2.doble' => ComercioConDatosMinimos::class]);

        $comercio = $this->servicio()->obtenerMicrositio(DatosPrueba::COMERCIO_LA_CEIBA, 1, 20)->comercio;

        $this->assertNull($comercio->descripcion);
        $this->assertNull($comercio->logotipo);
        $this->assertSame([], $comercio->contacto);
        $this->assertSame([], $comercio->banners);
        $this->assertSame([], $comercio->horarios);
    }

    // ---------------------------------------------------------------
    // A4: M2 no responde; M3 no responde
    // ---------------------------------------------------------------

    public function test_a4_si_m2_no_responde_el_micrositio_lanza_err_006(): void
    {
        config(['integraciones.m1.m2.doble' => ComerciosNoDisponible::class]);

        $this->expectException(DependenciaNoDisponibleException::class);

        $this->servicio()->obtenerMicrositio(DatosPrueba::COMERCIO_LA_CEIBA, 1, 20);
    }

    public function test_si_m3_no_responde_se_muestra_el_comercio_con_productos_vacios_y_el_aviso(): void
    {
        config(['integraciones.m1.m3.doble' => CatalogoNoDisponible::class]);

        $micrositio = $this->servicio()->obtenerMicrositio(DatosPrueba::COMERCIO_LA_CEIBA, 1, 20);

        $this->assertFalse($micrositio->productosDisponibles);
        $this->assertSame('Tienda La Ceiba', $micrositio->comercio->nombreComercial);
        $this->assertSame([], $micrositio->productos->productos);
        $this->assertSame([0, 1, 20], [$micrositio->productos->totalResultados, $micrositio->productos->pagina, $micrositio->productos->porPagina]);
    }

    // ---------------------------------------------------------------
    // Página de comercios
    // ---------------------------------------------------------------

    public function test_la_pagina_de_comercios_trae_solo_los_activos(): void
    {
        $listado = $this->servicio()->listarComercios(1, 20);
        $ids = array_map(fn ($comercio): int => $comercio->idComercio, $listado->comercios);

        $this->assertSame([DatosPrueba::COMERCIO_LA_CEIBA, DatosPrueba::COMERCIO_CAFE_NICOYANO, DatosPrueba::COMERCIO_ARTESANIAS_GUAITIL], $ids);
        $this->assertNotContains(DatosPrueba::COMERCIO_SUSPENDIDO, $ids);
        $this->assertSame(3, $listado->total);
    }

    public function test_si_m2_no_responde_la_pagina_de_comercios_lanza_err_006(): void
    {
        config(['integraciones.m1.m2.doble' => ComerciosNoDisponible::class]);

        $this->expectException(DependenciaNoDisponibleException::class);

        $this->servicio()->listarComercios(1, 20);
    }

    // ---------------------------------------------------------------
    // Rutas contra el contrato
    // ---------------------------------------------------------------

    public function test_get_comercios_responde_200_con_el_esquema_listado_comercios(): void
    {
        $respuesta = $this->getJson(route('api.m1.v1.comercios', ['pagina' => 1, 'por_pagina' => 2]))->assertOk();

        $this->assertCumpleEsquema('ListadoComercios', $respuesta->json());
        $respuesta->assertJson(['pagina' => 1, 'por_pagina' => 2, 'total' => 3]);
        $respuesta->assertJsonCount(2, 'comercios');
    }

    public function test_get_micrositio_responde_200_con_el_esquema_micrositio(): void
    {
        $respuesta = $this->getJson(route('api.m1.v1.comercios.micrositio', ['id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA]))->assertOk();

        $this->assertCumpleEsquema('Micrositio', $respuesta->json());
        $respuesta->assertJsonPath('comercio.nombre_comercial', 'Tienda La Ceiba');
        $respuesta->assertJsonPath('comercio.banners.0.texto_alternativo', 'Banner de Tienda La Ceiba');
        $respuesta->assertJsonPath('comercio.contacto.correo', 'contacto34@pacificos.example');
        $respuesta->assertJsonPath('productos.total_resultados', 2);
        $respuesta->assertJsonPath('productos_disponibles', true);
        $respuesta->assertJsonPath('productos.productos.0.comercio', ['id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA, 'nombre_comercial' => 'Tienda La Ceiba']);
        $this->assertArrayNotHasKey('id_horario', $respuesta->json('comercio.horarios.0'));
    }

    public function test_get_micrositio_de_un_comercio_inexistente_o_suspendido_responde_404_err_004(): void
    {
        foreach ([DatosPrueba::COMERCIO_INEXISTENTE, DatosPrueba::COMERCIO_SUSPENDIDO] as $idComercio) {
            $this->getJson(route('api.m1.v1.comercios.micrositio', ['id_comercio' => $idComercio]))
                ->assertNotFound()
                ->assertJsonPath('error.codigo', 'ERR-004');
        }
    }

    public function test_get_micrositio_con_m2_caido_responde_503_err_006(): void
    {
        config(['integraciones.m1.m2.doble' => ComerciosNoDisponible::class]);

        $this->getJson(route('api.m1.v1.comercios.micrositio', ['id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA]))
            ->assertStatus(503)
            ->assertJsonPath('error.codigo', 'ERR-006');
    }

    public function test_get_comercios_con_m2_caido_responde_503_err_006(): void
    {
        config(['integraciones.m1.m2.doble' => ComerciosNoDisponible::class]);

        $this->getJson(route('api.m1.v1.comercios'))
            ->assertStatus(503)
            ->assertJsonPath('error.codigo', 'ERR-006');
    }

    public function test_get_micrositio_con_m3_caido_responde_200_con_productos_vacios_y_el_aviso(): void
    {
        config(['integraciones.m1.m3.doble' => CatalogoNoDisponible::class]);

        $respuesta = $this->getJson(route('api.m1.v1.comercios.micrositio', ['id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA]))->assertOk();

        $this->assertCumpleEsquema('Micrositio', $respuesta->json());
        $respuesta->assertJsonPath('comercio.nombre_comercial', 'Tienda La Ceiba');
        $respuesta->assertJsonPath('productos', ['pagina' => 1, 'por_pagina' => 20, 'total_resultados' => 0, 'productos' => []]);
        $respuesta->assertJsonPath('productos_disponibles', false);
    }

    public function test_get_micrositio_con_campos_opcionales_vacios_cumple_el_contrato(): void
    {
        config(['integraciones.m1.m2.doble' => ComercioConDatosMinimos::class]);

        $respuesta = $this->getJson(route('api.m1.v1.comercios.micrositio', ['id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA]))->assertOk();

        $this->assertCumpleEsquema('Micrositio', $respuesta->json());
        $respuesta->assertJsonPath('comercio.descripcion', null);
        $respuesta->assertJsonPath('comercio.logo_url', null);
        $respuesta->assertJsonPath('comercio.banners', []);
        // contacto es un objeto en el contrato: con sus campos en null, no una lista vacía.
        $this->assertStringContainsString('"contacto":{"telefono":null,"correo":null,"direccion":null}', $respuesta->getContent());
    }

    public function test_get_comercios_con_paginacion_invalida_responde_422_err_005(): void
    {
        $this->getJson(route('api.m1.v1.comercios', ['pagina' => 'abc', 'por_pagina' => 500]))
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonPath('error.detalles', ['pagina' => 'Debe ser un número entero.', 'por_pagina' => 'Debe estar entre 1 y 50.']);
    }

    public function test_get_micrositio_con_paginacion_invalida_responde_422_err_005(): void
    {
        $this->getJson(route('api.m1.v1.comercios.micrositio', ['id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA, 'pagina' => 0, 'por_pagina' => 'x']))
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonPath('error.detalles', ['pagina' => 'Debe ser mayor o igual a 1.', 'por_pagina' => 'Debe ser un número entero.']);
    }

    public function test_sin_paginacion_las_rutas_usan_pagina_1_y_20_por_pagina(): void
    {
        $this->getJson(route('api.m1.v1.comercios'))->assertOk()->assertJson(['pagina' => 1, 'por_pagina' => 20]);

        $this->getJson(route('api.m1.v1.comercios.micrositio', ['id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA]))
            ->assertOk()
            ->assertJsonPath('productos.pagina', 1)
            ->assertJsonPath('productos.por_pagina', 20);
    }

    public function test_un_id_de_comercio_que_no_es_entero_positivo_no_encuentra_la_ruta(): void
    {
        $this->getJson('/api/m1/v1/comercios/0/micrositio')->assertNotFound();
        $this->getJson('/api/m1/v1/comercios/abc/micrositio')->assertNotFound();
    }

    private function servicio(): MicrositioService
    {
        return app(MicrositioService::class);
    }
}
