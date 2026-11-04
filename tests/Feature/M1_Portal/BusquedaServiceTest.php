<?php

namespace Tests\Feature\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\CriterioInvalidoException;
use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Services\M1_Portal\BusquedaService;
use App\Services\M1_Portal\ConfiguracionMenuCategorias;
use App\Services\M1_Portal\Dobles\CatalogoFake;
use App\Services\M1_Portal\Dobles\DatosPrueba;
use App\Services\M1_Portal\DTO\CategoriaDTO;
use App\Services\M1_Portal\DTO\CriterioBusquedaDTO;
use App\Services\M1_Portal\DTO\Disponibilidad;
use App\Services\M1_Portal\DTO\ProductoResumenDTO;
use App\Services\M1_Portal\DTO\ResultadoBusquedaDTO;
use App\Services\M1_Portal\Integraciones\CatalogoClient;
use App\Services\M1_Portal\RegistroBusquedas;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Doubles\M1\CatalogoConComercioSuspendido;
use Tests\Doubles\M1\CatalogoNoDisponible;
use Tests\Doubles\M1\ComerciosNoDisponible;
use Tests\Doubles\M1\ConfiguracionMenuEnMemoria;
use Tests\Doubles\M1\InventarioNoDisponible;
use Tests\Doubles\M1\RegistroBusquedasEnMemoria;
use Tests\Doubles\M1\RegistroBusquedasQueFalla;
use Tests\TestCase;

/**
 * CU-002 Buscar productos y menú de categorías (BusquedaService) contra los
 * dobles de M2, M3 y M4, con el registro de búsquedas y categorias_portal en memoria.
 */
class BusquedaServiceTest extends TestCase
{
    private RegistroBusquedasEnMemoria $registro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registro = new RegistroBusquedasEnMemoria;
        $this->app->instance(RegistroBusquedas::class, $this->registro);
        $this->usarFilas($this->filasBase());
    }

    // ---------------------------------------------------------------
    // Flujo básico
    // ---------------------------------------------------------------

    public function test_buscar_camisa_devuelve_el_producto_con_su_comercio_y_disponible(): void
    {
        $resultado = $this->buscar(new CriterioBusquedaDTO(termino: 'camisa'));

        $this->assertSame(1, $resultado->totalResultados);
        $producto = $resultado->productos[0];
        $this->assertSame(DatosPrueba::PRODUCTO_CAMISA_LINO, $producto->idProducto);
        $this->assertSame('Tienda La Ceiba', $producto->nombreComercio);
        $this->assertSame(Disponibilidad::Disponible, $producto->disponibilidad);
    }

    public function test_filtrar_por_categoria_incluye_sus_subcategorias(): void
    {
        $resultado = $this->buscar(new CriterioBusquedaDTO(idCategoria: DatosPrueba::CATEGORIA_ROPA));

        $this->assertSame(
            [DatosPrueba::PRODUCTO_CAMISA_LINO, DatosPrueba::PRODUCTO_SOMBRERO_AGOTADO],
            $this->ids($resultado),
        );
    }

    public function test_un_producto_sin_unidades_se_muestra_agotado(): void
    {
        $resultado = $this->buscar(new CriterioBusquedaDTO(termino: 'sombrero'));

        $this->assertSame(DatosPrueba::PRODUCTO_SOMBRERO_AGOTADO, $resultado->productos[0]->idProducto);
        $this->assertSame(Disponibilidad::Agotado, $resultado->productos[0]->disponibilidad);
    }

    public function test_conserva_el_orden_que_entrega_m3_rn_m1_07(): void
    {
        $criterio = new CriterioBusquedaDTO(idCategoria: DatosPrueba::CATEGORIA_ARTESANIA, orden: 'precio_desc');

        $esperado = array_map(
            fn (ProductoResumenDTO $producto): int => $producto->idProducto,
            app(CatalogoClient::class)->buscarProductos($criterio)->productos,
        );

        $this->assertSame([DatosPrueba::PRODUCTO_JARRON, DatosPrueba::PRODUCTO_TAZA_SIN_EXISTENCIA], $esperado);
        $this->assertSame($esperado, $this->ids($this->buscar($criterio)));
    }

    // ---------------------------------------------------------------
    // Validación del criterio (RN-M1-08)
    // ---------------------------------------------------------------

    public function test_un_termino_de_un_caracter_sin_categoria_es_invalido_a1(): void
    {
        $this->expectException(CriterioInvalidoException::class);

        $this->buscar(new CriterioBusquedaDTO(termino: 'c'));
    }

    #[DataProvider('terminosInvalidos')]
    public function test_un_termino_vacio_o_corto_sin_categoria_lanza_err_005(?string $termino): void
    {
        try {
            $this->buscar(new CriterioBusquedaDTO(termino: $termino));
            $this->fail('Se esperaba CriterioInvalidoException.');
        } catch (CriterioInvalidoException $excepcion) {
            $this->assertSame('ERR-005', $excepcion->codigo());
        }
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function terminosInvalidos(): array
    {
        return [
            'nulo' => [null],
            'vacío' => [''],
            'solo espacios' => ['   '],
            'una letra con espacios' => [' a '],
        ];
    }

    public function test_un_termino_corto_con_categoria_si_busca(): void
    {
        $resultado = $this->buscar(new CriterioBusquedaDTO(termino: 'c', idCategoria: DatosPrueba::CATEGORIA_ALIMENTOS));

        $this->assertSame([DatosPrueba::PRODUCTO_CAFE_MOLIDO, DatosPrueba::PRODUCTO_CAFE_TOSTADO], $this->ids($resultado));
    }

    public function test_una_busqueda_rechazada_no_se_registra(): void
    {
        try {
            $this->buscar(new CriterioBusquedaDTO(termino: 'c'));
        } catch (CriterioInvalidoException) {
        }

        $this->assertSame([], $this->registro->registradas);
    }

    // ---------------------------------------------------------------
    // Sin resultados y fallas de los proveedores
    // ---------------------------------------------------------------

    public function test_sin_resultados_devuelve_lista_vacia_y_registra_cero_a2(): void
    {
        $resultado = $this->buscar(new CriterioBusquedaDTO(termino: 'bicicleta'));

        $this->assertSame([], $resultado->productos);
        $this->assertSame(0, $resultado->totalResultados);
        $this->assertCount(1, $this->registro->registradas);
        $this->assertSame(0, $this->registro->registradas[0]['cantidad_resultados']);
    }

    public function test_si_m3_no_responde_lanza_err_006_a3(): void
    {
        config(['integraciones.m1.m3.doble' => CatalogoNoDisponible::class]);

        $this->expectException(DependenciaNoDisponibleException::class);

        $this->buscar(new CriterioBusquedaDTO(termino: 'camisa'));
    }

    public function test_si_m2_no_responde_sigue_sin_nombre_de_comercio_y_con_disponibilidad(): void
    {
        config(['integraciones.m1.m2.doble' => ComerciosNoDisponible::class]);

        $producto = $this->buscar(new CriterioBusquedaDTO(termino: 'camisa'))->productos[0];

        $this->assertSame(DatosPrueba::PRODUCTO_CAMISA_LINO, $producto->idProducto);
        $this->assertSame('', $producto->nombreComercio);
        $this->assertSame(Disponibilidad::Disponible, $producto->disponibilidad);
    }

    public function test_si_m4_no_responde_sigue_con_el_comercio_y_el_producto_agotado(): void
    {
        config(['integraciones.m1.m4.doble' => InventarioNoDisponible::class]);

        $producto = $this->buscar(new CriterioBusquedaDTO(termino: 'camisa'))->productos[0];

        $this->assertSame('Tienda La Ceiba', $producto->nombreComercio);
        $this->assertSame(Disponibilidad::Agotado, $producto->disponibilidad);
    }

    public function test_descarta_productos_de_comercios_no_habilitados_rn_m1_01_y_rn_m1_02(): void
    {
        config(['integraciones.m1.m3.doble' => CatalogoConComercioSuspendido::class]);

        $resultado = $this->buscar(new CriterioBusquedaDTO(termino: 'camisa'));

        $this->assertSame([DatosPrueba::PRODUCTO_CAMISA_LINO], $this->ids($resultado));
        $this->assertSame(1, $resultado->totalResultados);
    }

    // ---------------------------------------------------------------
    // Registro de búsquedas
    // ---------------------------------------------------------------

    public function test_registra_el_termino_normalizado_los_resultados_y_los_filtros(): void
    {
        $this->buscar(new CriterioBusquedaDTO(
            termino: '  lino ',
            idCategoria: DatosPrueba::CATEGORIA_CAMISAS,
            idComercio: DatosPrueba::COMERCIO_LA_CEIBA,
            orden: 'precio_asc',
        ));

        $this->assertSame([[
            'termino' => 'lino',
            'cantidad_resultados' => 1,
            'filtros' => [
                'subcategoria' => (string) DatosPrueba::CATEGORIA_CAMISAS,
                'comercio' => (string) DatosPrueba::COMERCIO_LA_CEIBA,
                'orden' => 'precio_asc',
            ],
        ]], $this->registro->registradas);
    }

    public function test_una_categoria_sin_padre_se_registra_como_categoria(): void
    {
        $this->buscar(new CriterioBusquedaDTO(idCategoria: DatosPrueba::CATEGORIA_ROPA));

        $this->assertSame(['categoria' => (string) DatosPrueba::CATEGORIA_ROPA], $this->registro->registradas[0]['filtros']);
    }

    public function test_si_el_registro_falla_la_busqueda_responde_igual(): void
    {
        $this->app->instance(RegistroBusquedas::class, new RegistroBusquedasQueFalla);
        Log::spy();

        $resultado = $this->buscar(new CriterioBusquedaDTO(termino: 'camisa'));

        $this->assertSame([DatosPrueba::PRODUCTO_CAMISA_LINO], $this->ids($resultado));
        Log::shouldHaveReceived('warning')->once();
    }

    // ---------------------------------------------------------------
    // Menú de categorías (RN-M1-09)
    // ---------------------------------------------------------------

    public function test_el_menu_pone_cada_principal_seguida_de_sus_subcategorias_en_orden(): void
    {
        $this->assertSame([
            DatosPrueba::CATEGORIA_ALIMENTOS,
            DatosPrueba::CATEGORIA_CAFE,
            DatosPrueba::CATEGORIA_ROPA,
            DatosPrueba::CATEGORIA_CAMISAS,
            DatosPrueba::CATEGORIA_SOMBREROS,
            DatosPrueba::CATEGORIA_ARTESANIA,
            DatosPrueba::CATEGORIA_CERAMICA,
        ], $this->idsMenu());
    }

    public function test_el_menu_omite_las_categorias_sin_productos_visibles_rn_m1_09(): void
    {
        $ids = $this->idsMenu();

        $this->assertNotContains(DatosPrueba::CATEGORIA_VACIA, $ids);
        $this->assertNotContains(DatosPrueba::CATEGORIA_SOLO_SUSPENDIDO, $ids);
    }

    public function test_una_principal_sin_productos_propios_aparece_si_sus_subcategorias_tienen(): void
    {
        $this->assertContains(DatosPrueba::CATEGORIA_ROPA, $this->idsMenu());
    }

    public function test_una_principal_sin_fila_o_oculta_no_sale_ni_con_sus_subcategorias(): void
    {
        $filas = $this->filasBase();
        unset($filas[DatosPrueba::CATEGORIA_ALIMENTOS]);
        $filas[DatosPrueba::CATEGORIA_ARTESANIA]['visible'] = false;
        $this->usarFilas($filas);

        $this->assertSame([
            DatosPrueba::CATEGORIA_ROPA,
            DatosPrueba::CATEGORIA_CAMISAS,
            DatosPrueba::CATEGORIA_SOMBREROS,
        ], $this->idsMenu());
    }

    public function test_las_subcategorias_con_fila_van_antes_que_las_ordenadas_por_nombre(): void
    {
        $this->usarFilas($this->filasBase() + [DatosPrueba::CATEGORIA_SOMBREROS => $this->fila(1)]);

        $menu = $this->menuPorId();

        $this->assertSame(
            [DatosPrueba::CATEGORIA_SOMBREROS, DatosPrueba::CATEGORIA_CAMISAS],
            array_values(array_intersect($this->idsMenu(), [DatosPrueba::CATEGORIA_SOMBREROS, DatosPrueba::CATEGORIA_CAMISAS])),
        );
        $this->assertSame(1, $menu[DatosPrueba::CATEGORIA_SOMBREROS]->ordenVisualizacion);
        $this->assertNull($menu[DatosPrueba::CATEGORIA_CAMISAS]->ordenVisualizacion);
    }

    public function test_una_subcategoria_con_fila_oculta_no_sale(): void
    {
        $this->usarFilas($this->filasBase() + [DatosPrueba::CATEGORIA_CAMISAS => $this->fila(1, visible: false)]);

        $ids = $this->idsMenu();

        $this->assertNotContains(DatosPrueba::CATEGORIA_CAMISAS, $ids);
        $this->assertContains(DatosPrueba::CATEGORIA_SOMBREROS, $ids);
    }

    public function test_una_principal_lleva_su_orden_y_el_icono_de_su_fila(): void
    {
        $icono = 'https://cdn.pacificos.example/portal/ropa.svg';
        $filas = $this->filasBase();
        $filas[DatosPrueba::CATEGORIA_ROPA]['icono_url'] = $icono;
        $this->usarFilas($filas);

        $ropa = $this->menuPorId()[DatosPrueba::CATEGORIA_ROPA];

        $this->assertNull($ropa->idCategoriaPadre);
        $this->assertSame(2, $ropa->ordenVisualizacion);
        $this->assertSame($icono, $ropa->iconoUrl);
    }

    public function test_sin_icono_en_la_fila_se_usa_el_de_m3(): void
    {
        $alimentos = $this->menuPorId()[DatosPrueba::CATEGORIA_ALIMENTOS];

        $this->assertSame('https://cdn.pacificos.example/categorias/'.DatosPrueba::CATEGORIA_ALIMENTOS.'.svg', $alimentos->iconoUrl);
    }

    public function test_una_subcategoria_lleva_el_id_de_su_principal(): void
    {
        $this->assertSame(DatosPrueba::CATEGORIA_ROPA, $this->menuPorId()[DatosPrueba::CATEGORIA_CAMISAS]->idCategoriaPadre);
    }

    public function test_el_menu_se_guarda_en_la_cache_y_no_vuelve_a_consultar_a_m3_rnf_m1_06(): void
    {
        $contador = new class extends CatalogoFake
        {
            public int $llamadasBusqueda = 0;

            public function buscarProductos(array $filtros = []): array
            {
                $this->llamadasBusqueda++;

                return parent::buscarProductos($filtros);
            }
        };
        $this->app->instance(CatalogoFake::class, $contador);

        $primero = $this->idsMenu();
        $llamadas = $contador->llamadasBusqueda;
        $segundo = $this->idsMenu();

        $this->assertGreaterThan(0, $llamadas);
        $this->assertSame($llamadas, $contador->llamadasBusqueda);
        $this->assertSame($primero, $segundo);
    }

    public function test_el_menu_lanza_err_006_si_m3_no_responde(): void
    {
        config(['integraciones.m1.m3.doble' => CatalogoNoDisponible::class]);

        $this->expectException(DependenciaNoDisponibleException::class);

        app(BusquedaService::class)->listarCategoriasPortal();
    }

    /**
     * Filas base de categorias_portal: solo las principales, todas visibles.
     *
     * @return array<int, array{orden: int, visible: bool, icono_url: string|null}>
     */
    private function filasBase(): array
    {
        return [
            DatosPrueba::CATEGORIA_ALIMENTOS => $this->fila(1),
            DatosPrueba::CATEGORIA_ROPA => $this->fila(2),
            DatosPrueba::CATEGORIA_ARTESANIA => $this->fila(3),
            DatosPrueba::CATEGORIA_SOLO_SUSPENDIDO => $this->fila(4),
            DatosPrueba::CATEGORIA_VACIA => $this->fila(5),
        ];
    }

    /**
     * @return array{orden: int, visible: bool, icono_url: string|null}
     */
    private function fila(int $orden, bool $visible = true): array
    {
        return ['orden' => $orden, 'visible' => $visible, 'icono_url' => null];
    }

    /**
     * @param  array<int, array{orden: int, visible: bool, icono_url: string|null}>  $filas
     */
    private function usarFilas(array $filas): void
    {
        $this->app->instance(ConfiguracionMenuCategorias::class, new ConfiguracionMenuEnMemoria($filas));
    }

    /**
     * @return list<int>
     */
    private function idsMenu(): array
    {
        return array_map(fn (CategoriaDTO $categoria): int => $categoria->idCategoria, app(BusquedaService::class)->listarCategoriasPortal());
    }

    /**
     * @return array<int, CategoriaDTO>
     */
    private function menuPorId(): array
    {
        $menu = [];

        foreach (app(BusquedaService::class)->listarCategoriasPortal() as $categoria) {
            $menu[$categoria->idCategoria] = $categoria;
        }

        return $menu;
    }

    private function buscar(CriterioBusquedaDTO $criterio): ResultadoBusquedaDTO
    {
        return app(BusquedaService::class)->buscar($criterio);
    }

    /**
     * @return list<int>
     */
    private function ids(ResultadoBusquedaDTO $resultado): array
    {
        return array_map(fn (ProductoResumenDTO $producto): int => $producto->idProducto, $resultado->productos);
    }
}
