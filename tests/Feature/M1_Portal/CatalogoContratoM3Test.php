<?php

namespace Tests\Feature\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\RecursoNoEncontradoException;
use App\Services\M1_Portal\Dobles\CatalogoFake;
use App\Services\M1_Portal\Dobles\DatosPrueba;
use App\Services\M1_Portal\DTO\CriterioBusquedaDTO;
use App\Services\M1_Portal\Integraciones\CachePortal;
use App\Services\M1_Portal\Integraciones\CatalogoClient;
use Tests\Doubles\M1\CatalogoContratoM3v130;
use Tests\Feature\M1_Portal\Concerns\ValidaEsquemaContrato;

/**
 * El adaptador CatalogoClient funciona con el contrato M3 1.3.0 (rama
 * feature/M3-consultas-reales), además del 1.2.x que usa el doble CatalogoFake:
 * obtenerDetalleProducto con null, imagen_principal_url, imágenes como objetos y
 * sin moneda. Las respuestas de M1 siguen cumpliendo su propio contrato.
 */
class CatalogoContratoM3Test extends PruebaM1
{
    use ValidaEsquemaContrato;

    protected function setUp(): void
    {
        parent::setUp();

        config(['integraciones.m1.m3.doble' => CatalogoContratoM3v130::class]);
    }

    public function test_la_busqueda_toma_la_imagen_principal_url_y_pone_colones_si_no_viene_moneda(): void
    {
        $respuesta = $this->getJson(route('api.m1.v1.productos.busqueda', ['q' => 'camisa']))->assertOk();

        $camisa = collect($respuesta->json('productos'))->firstWhere('id_producto', DatosPrueba::PRODUCTO_CAMISA_LINO);
        $this->assertNotNull($camisa);
        $this->assertCumpleEsquema('ProductoResumen', $camisa);
        $this->assertSame('CRC', $camisa['moneda']);
        $this->assertSame('https://cdn.pacificos.example/p/1520.jpg', $camisa['imagen_principal']);
    }

    public function test_la_ficha_usa_obtener_detalle_producto_e_imagenes_como_objetos(): void
    {
        $respuesta = $this->getJson(route('api.m1.v1.productos.ficha', DatosPrueba::PRODUCTO_CAMISA_LINO))->assertOk();

        $this->assertCumpleEsquema('FichaProducto', $respuesta->json());
        $respuesta->assertJsonPath('moneda', 'CRC');
        $this->assertSame(
            ['https://cdn.pacificos.example/p/1520.jpg', 'https://cdn.pacificos.example/p/1520-2.jpg'],
            $respuesta->json('imagenes'),
        );
        $this->assertNotEmpty($respuesta->json('variaciones'));
    }

    public function test_las_imagenes_de_una_variacion_salen_de_las_del_producto_con_su_id_variacion(): void
    {
        $ficha = app(CatalogoClient::class)->obtenerProducto(DatosPrueba::PRODUCTO_CAMISA_LINO);

        $this->assertSame(['https://cdn.pacificos.example/p/1520-2.jpg'], $ficha->variaciones[0]->imagenes);
        $this->assertSame([], $ficha->variaciones[1]->imagenes);
    }

    public function test_un_producto_que_m3_devuelve_como_null_responde_404(): void
    {
        $this->getJson(route('api.m1.v1.productos.ficha', DatosPrueba::PRODUCTO_INEXISTENTE))
            ->assertStatus(404)
            ->assertJsonPath('error.codigo', 'ERR-004');
    }

    public function test_si_m3_no_tiene_relacionados_para_el_producto_la_ficha_sale_igual(): void
    {
        $this->assertSame([], app(CatalogoClient::class)->listarRelacionados(DatosPrueba::PRODUCTO_INEXISTENTE));
    }

    public function test_un_producto_sin_variaciones_disponibles_no_sale_a_precio_cero(): void
    {
        $contrato = new class extends CatalogoContratoM3v130
        {
            public function buscarProductos(array $filtros = []): array
            {
                return [
                    'pagina' => 1,
                    'por_pagina' => 20,
                    'total_resultados' => 2,
                    'productos' => [
                        ['id_producto' => 1, 'id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA, 'nombre' => 'Con precio', 'imagen_principal_url' => null, 'precio_desde' => 1500.0, 'precio_hasta' => 1500.0],
                        ['id_producto' => 2, 'id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA, 'nombre' => 'Sin variaciones disponibles', 'imagen_principal_url' => null, 'precio_desde' => null, 'precio_hasta' => null],
                    ],
                ];
            }
        };

        $resultado = (new CatalogoClient($contrato, app(CachePortal::class)))
            ->buscarProductos(new CriterioBusquedaDTO(termino: 'precio', idCategoria: null, idComercio: null, idMarca: null, precioMinimo: null, precioMaximo: null, orden: null, pagina: 1, porPagina: 20));

        $this->assertSame([1], array_map(fn ($producto): int => $producto->idProducto, $resultado->productos));
    }

    public function test_el_adaptador_sigue_funcionando_con_el_contrato_anterior(): void
    {
        config(['integraciones.m1.m3.doble' => CatalogoFake::class]);

        $this->getJson(route('api.m1.v1.productos.ficha', DatosPrueba::PRODUCTO_CAMISA_LINO))
            ->assertOk()
            ->assertJsonPath('moneda', 'CRC');
        $this->expectException(RecursoNoEncontradoException::class);

        app(CatalogoClient::class)->obtenerProducto(DatosPrueba::PRODUCTO_INEXISTENTE);
    }
}
