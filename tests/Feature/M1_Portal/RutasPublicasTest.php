<?php

namespace Tests\Feature\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Contracts\M1_Portal\Exceptions\RecursoNoEncontradoException;
use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Services\M1_Portal\Dobles\DatosPrueba;
use Mockery\MockInterface;

/**
 * Rutas públicas de M1 en /api/m1/v1 (búsqueda, ficha y categorías) con un
 * mock de PortalPublicoContract: validación de entrada y formato de error del
 * núcleo.
 */
class RutasPublicasTest extends PruebaM1
{
    private const RESULTADO_VACIO = ['pagina' => 1, 'por_pagina' => 20, 'total_resultados' => 0, 'productos' => []];

    // ---------------------------------------------------------------
    // GET productos/busqueda
    // ---------------------------------------------------------------

    public function test_una_busqueda_valida_pasa_los_parametros_al_contrato_y_responde_200(): void
    {
        $resultado = ['pagina' => 2, 'por_pagina' => 10, 'total_resultados' => 11, 'productos' => [['id_producto' => DatosPrueba::PRODUCTO_CAMISA_LINO]]];

        $this->mock(PortalPublicoContract::class, function (MockInterface $mock) use ($resultado): void {
            $mock->shouldReceive('buscarProductos')
                ->once()
                ->with('camisa', DatosPrueba::CATEGORIA_ROPA, DatosPrueba::COMERCIO_LA_CEIBA, 2, 10, DatosPrueba::MARCA_CEIBA_WEAR, 1000.0, 25000.5, 'precio_asc')
                ->andReturn($resultado);
        });

        $this->getJson(route('api.m1.v1.productos.busqueda', [
            'q' => 'camisa',
            'id_categoria' => DatosPrueba::CATEGORIA_ROPA,
            'id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA,
            'id_marca' => DatosPrueba::MARCA_CEIBA_WEAR,
            'precio_minimo' => 1000,
            'precio_maximo' => 25000.5,
            'orden' => 'precio_asc',
            'pagina' => 2,
            'por_pagina' => 10,
        ]))
            ->assertOk()
            ->assertExactJson($resultado);
    }

    public function test_sin_pagina_ni_por_pagina_usa_los_valores_por_defecto(): void
    {
        $this->mock(PortalPublicoContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('buscarProductos')
                ->once()
                ->with('café', null, null, 1, 20, null, null, null, null)
                ->andReturn(self::RESULTADO_VACIO);
        });

        $this->getJson(route('api.m1.v1.productos.busqueda', ['q' => 'café']))->assertOk();
    }

    public function test_un_termino_de_una_letra_sin_categoria_ya_llama_al_contrato(): void
    {
        $this->mock(PortalPublicoContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('buscarProductos')
                ->once()
                ->with('c', null, null, 1, 20, null, null, null, null)
                ->andReturn(self::RESULTADO_VACIO);
        });

        $this->getJson(route('api.m1.v1.productos.busqueda', ['q' => 'c']))->assertOk();
    }

    public function test_una_busqueda_vacia_sin_categoria_llama_al_contrato_y_responde_200(): void
    {
        $this->mock(PortalPublicoContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('buscarProductos')
                ->once()
                ->with(null, null, null, 1, 20, null, null, null, null)
                ->andReturn(self::RESULTADO_VACIO);
        });

        $this->getJson(route('api.m1.v1.productos.busqueda'))->assertOk();
    }

    public function test_un_termino_de_una_letra_con_categoria_llama_al_contrato(): void
    {
        $this->mock(PortalPublicoContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('buscarProductos')
                ->once()
                ->with('c', DatosPrueba::CATEGORIA_ALIMENTOS, null, 1, 20, null, null, null, null)
                ->andReturn(self::RESULTADO_VACIO);
        });

        $this->getJson(route('api.m1.v1.productos.busqueda', ['q' => 'c', 'id_categoria' => DatosPrueba::CATEGORIA_ALIMENTOS]))
            ->assertOk();
    }

    public function test_por_pagina_fuera_de_rango_y_orden_invalido_responden_422_con_su_campo(): void
    {
        $this->mock(PortalPublicoContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('buscarProductos');
        });

        $this->getJson(route('api.m1.v1.productos.busqueda', ['q' => 'camisa', 'por_pagina' => 51, 'orden' => 'precio']))
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonPath('error.detalles.por_pagina', 'Debe estar entre 1 y 50.')
            ->assertJsonPath('error.detalles.orden', 'Debe ser precio_asc, precio_desc o novedad.');
    }

    public function test_si_un_proveedor_no_responde_la_busqueda_responde_503(): void
    {
        $this->mock(PortalPublicoContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('buscarProductos')->once()->andThrow(new DependenciaNoDisponibleException);
        });

        $this->getJson(route('api.m1.v1.productos.busqueda', ['q' => 'camisa']))
            ->assertStatus(503)
            ->assertJsonPath('error.codigo', 'ERR-006');
    }

    public function test_sin_encabezado_accept_un_error_de_validacion_responde_json_del_nucleo(): void
    {
        $this->mock(PortalPublicoContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('buscarProductos');
        });

        $this->get(route('api.m1.v1.productos.busqueda', ['por_pagina' => 51]))
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['error' => ['codigo', 'mensaje', 'detalles' => ['por_pagina']]])
            ->assertJsonPath('error.codigo', 'ERR-005');
    }

    // ---------------------------------------------------------------
    // GET productos/{id_producto}
    // ---------------------------------------------------------------

    public function test_la_ficha_responde_200_con_lo_que_entrega_el_contrato(): void
    {
        $ficha = ['id_producto' => DatosPrueba::PRODUCTO_CAMISA_LINO, 'nombre' => 'Camisa de lino'];

        $this->mock(PortalPublicoContract::class, function (MockInterface $mock) use ($ficha): void {
            $mock->shouldReceive('obtenerFichaProducto')->once()->with(DatosPrueba::PRODUCTO_CAMISA_LINO)->andReturn($ficha);
        });

        $this->getJson(route('api.m1.v1.productos.ficha', DatosPrueba::PRODUCTO_CAMISA_LINO))
            ->assertOk()
            ->assertExactJson($ficha);
    }

    public function test_la_ficha_de_un_producto_inexistente_responde_404(): void
    {
        $this->mock(PortalPublicoContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('obtenerFichaProducto')->once()->andThrow(new RecursoNoEncontradoException);
        });

        $this->getJson(route('api.m1.v1.productos.ficha', DatosPrueba::PRODUCTO_INEXISTENTE))
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'ERR-004');
    }

    public function test_un_id_de_producto_cero_responde_404_sin_llamar_al_contrato(): void
    {
        $this->mock(PortalPublicoContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('obtenerFichaProducto');
        });

        $this->getJson('/api/m1/v1/productos/0')
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'ERR-004');
    }

    // ---------------------------------------------------------------
    // GET categorias
    // ---------------------------------------------------------------

    public function test_categorias_responde_200_con_el_menu_del_contrato(): void
    {
        $menu = [[
            'id_categoria' => DatosPrueba::CATEGORIA_ROPA,
            'nombre' => 'Ropa',
            'icono_url' => null,
            'orden_visualizacion' => 1,
            'subcategorias' => [['id_categoria' => DatosPrueba::CATEGORIA_CAMISAS, 'nombre' => 'Camisas']],
        ]];

        $this->mock(PortalPublicoContract::class, function (MockInterface $mock) use ($menu): void {
            $mock->shouldReceive('listarCategoriasPortal')->once()->andReturn($menu);
        });

        $this->getJson(route('api.m1.v1.categorias'))
            ->assertOk()
            ->assertExactJson($menu);
    }
}
