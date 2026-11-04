<?php

namespace Tests\Feature\M1_Portal;

use App\Services\M1_Portal\ConfiguracionMenuCategorias;
use App\Services\M1_Portal\Dobles\DatosPrueba;
use App\Services\M1_Portal\RegistroBusquedas;
use Illuminate\Testing\TestResponse;
use Tests\Doubles\M1\CatalogoNoDisponible;
use Tests\Doubles\M1\ConfiguracionMenuEnMemoria;
use Tests\Doubles\M1\RegistroBusquedasEnMemoria;
use Tests\TestCase;

/**
 * Prueba de aceptación de los pasos 1 y 2 del recorrido transversal (tarea 25):
 * buscar un producto y abrir su ficha. Va de la ruta a la fachada real, los
 * servicios de M1 y los dobles de M2, M3 y M4 de config/integraciones.php.
 */
class AceptacionBuscarYVerFichaTest extends TestCase
{
    /** Esquema ProductoResumen del contrato. */
    private const PRODUCTO_RESUMEN = [
        'id_producto', 'nombre', 'imagen_principal', 'precio', 'precio_hasta', 'moneda', 'disponibilidad',
        'comercio' => ['id_comercio', 'nombre_comercial'],
    ];

    private RegistroBusquedasEnMemoria $registro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registro = new RegistroBusquedasEnMemoria;
        $this->app->instance(RegistroBusquedas::class, $this->registro);
        $this->app->instance(ConfiguracionMenuCategorias::class, new ConfiguracionMenuEnMemoria([
            DatosPrueba::CATEGORIA_ALIMENTOS => ['orden' => 1, 'visible' => true, 'icono_url' => null],
            DatosPrueba::CATEGORIA_ROPA => ['orden' => 2, 'visible' => true, 'icono_url' => null],
            DatosPrueba::CATEGORIA_ARTESANIA => ['orden' => 3, 'visible' => true, 'icono_url' => null],
            DatosPrueba::CATEGORIA_SOLO_SUSPENDIDO => ['orden' => 4, 'visible' => true, 'icono_url' => null],
            DatosPrueba::CATEGORIA_VACIA => ['orden' => 5, 'visible' => true, 'icono_url' => null],
        ]));
    }

    // ---------------------------------------------------------------
    // Recorrido: buscar y abrir la ficha
    // ---------------------------------------------------------------

    public function test_buscar_camisa_y_abrir_su_ficha(): void
    {
        $busqueda = $this->buscar(['q' => 'camisa'])
            ->assertOk()
            ->assertJsonStructure([
                'pagina', 'por_pagina', 'total_resultados',
                'productos' => ['*' => self::PRODUCTO_RESUMEN],
            ]);

        $ficha = $this->abrirFicha($busqueda->json('productos.0.id_producto'))
            ->assertOk()
            ->assertJsonStructure([
                'id_producto', 'nombre', 'descripcion', 'marca', 'video_url', 'moneda', 'imagenes',
                'variaciones' => ['*' => ['id_variacion', 'sku', 'precio', 'atributos', 'disponibilidad', 'comprable']],
                'comercio' => ['id_comercio', 'nombre_comercial'],
                'relacionados' => ['*' => self::PRODUCTO_RESUMEN],
            ])
            ->assertJsonPath('id_producto', DatosPrueba::PRODUCTO_CAMISA_LINO)
            ->assertJsonPath('comercio.nombre_comercial', 'Tienda La Ceiba');

        $comprables = array_column($ficha->json('variaciones'), 'comprable', 'id_variacion');
        $this->assertTrue($comprables[DatosPrueba::VARIACION_CAMISA_S]);
        $this->assertFalse($comprables[DatosPrueba::VARIACION_CAMISA_M_AGOTADA]);

        $this->assertNotEmpty($ficha->json('relacionados'));

        foreach ($ficha->json('relacionados') as $relacionado) {
            $this->assertNotSame('', $relacionado['comercio']['nombre_comercial']);
        }
    }

    public function test_la_busqueda_del_recorrido_queda_registrada(): void
    {
        $busqueda = $this->buscar(['q' => 'camisa'])->assertOk();
        $this->abrirFicha($busqueda->json('productos.0.id_producto'))->assertOk();

        $this->assertSame([[
            'termino' => 'camisa',
            'cantidad_resultados' => 1,
            'filtros' => [],
        ]], $this->registro->registradas);
    }

    public function test_un_producto_agotado_se_ve_pero_no_se_puede_comprar(): void
    {
        $busqueda = $this->buscar(['q' => 'sombrero'])
            ->assertOk()
            ->assertJsonPath('productos.0.disponibilidad', 'Agotado');

        $ficha = $this->abrirFicha($busqueda->json('productos.0.id_producto'))->assertOk();

        $this->assertNotEmpty($ficha->json('variaciones'));
        $this->assertNotContains(true, array_column($ficha->json('variaciones'), 'comprable'));
    }

    // ---------------------------------------------------------------
    // Menú de categorías
    // ---------------------------------------------------------------

    public function test_el_menu_trae_ropa_con_sus_subcategorias_y_omite_las_vacias(): void
    {
        $menu = $this->getJson(route('api.m1.v1.categorias'))
            ->assertOk()
            ->assertJsonStructure(['*' => ['id_categoria', 'nombre', 'icono_url', 'orden_visualizacion', 'subcategorias' => ['*' => ['id_categoria', 'nombre']]]])
            ->json();

        $ropa = $this->categoria($menu, 'Ropa');

        $this->assertSame(['Camisas', 'Sombreros'], array_column($ropa['subcategorias'], 'nombre'));
        $this->assertNotContains('Juguetes', array_column($menu, 'nombre'));
        $this->assertNotContains('Ferretería', array_column($menu, 'nombre'));
    }

    public function test_buscar_por_la_categoria_del_menu_trae_sus_productos(): void
    {
        $ropa = $this->categoria($this->getJson(route('api.m1.v1.categorias'))->assertOk()->json(), 'Ropa');

        $busqueda = $this->buscar(['id_categoria' => $ropa['id_categoria']])->assertOk();

        $this->assertSame(
            [DatosPrueba::PRODUCTO_CAMISA_LINO, DatosPrueba::PRODUCTO_SOMBRERO_AGOTADO],
            array_column($busqueda->json('productos'), 'id_producto'),
        );
    }

    // ---------------------------------------------------------------
    // Errores de punta a punta
    // ---------------------------------------------------------------

    public function test_un_termino_de_una_letra_sin_categoria_responde_422(): void
    {
        $this->buscar(['q' => 'c'])
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonStructure(['error' => ['detalles' => ['q']]]);

        $this->assertSame([], $this->registro->registradas);
    }

    public function test_un_producto_inexistente_responde_404(): void
    {
        $this->abrirFicha(DatosPrueba::PRODUCTO_INEXISTENTE)
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'ERR-004');
    }

    public function test_si_m3_no_responde_la_busqueda_responde_503(): void
    {
        config(['integraciones.m1.m3.doble' => CatalogoNoDisponible::class]);

        $this->buscar(['q' => 'camisa'])
            ->assertStatus(503)
            ->assertJsonPath('error.codigo', 'ERR-006');
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    private function buscar(array $parametros): TestResponse
    {
        return $this->getJson(route('api.m1.v1.productos.busqueda', $parametros));
    }

    private function abrirFicha(int $idProducto): TestResponse
    {
        return $this->getJson(route('api.m1.v1.productos.ficha', $idProducto));
    }

    /**
     * @param  list<array<string, mixed>>  $menu
     * @return array<string, mixed>
     */
    private function categoria(array $menu, string $nombre): array
    {
        foreach ($menu as $categoria) {
            if ($categoria['nombre'] === $nombre) {
                return $categoria;
            }
        }

        $this->fail("El menú no trae la categoría {$nombre}.");
    }
}
