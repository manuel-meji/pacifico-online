<?php

namespace Tests\Feature\M1_Portal;

use App\Models\M1_Portal\ComercioDestacado;
use App\Models\M1_Portal\ProductoDestacado;
use App\Services\M1_Portal\Dobles\DatosPrueba;
use App\Services\M1_Portal\DTO\ComercioDTO;
use App\Services\M1_Portal\DTO\DestacadosDTO;
use App\Services\M1_Portal\DTO\Disponibilidad;
use App\Services\M1_Portal\DTO\ProductoResumenDTO;
use App\Services\M1_Portal\Integraciones\CachePortal;
use App\Services\M1_Portal\PortadaService;
use App\Services\M1_Portal\ReglasDestacados;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\Doubles\M1\CatalogoNoDisponible;
use Tests\Doubles\M1\ComerciosNoDisponible;
use Tests\Doubles\M1\ComerciosSinDestacados;
use Tests\Doubles\M1\InventarioNoDisponible;
use Tests\Feature\M1_Portal\Concerns\ValidaEsquemaContrato;

/**
 * Página principal: PortadaService con las tablas de destacados de M1 y los
 * dobles de M2, M3 y M4, y la ruta GET /destacados contra el contrato.
 *
 * Las migraciones de destacados tienen CHECK de PostgreSQL que no corren en
 * el SQLite de las pruebas, así que se crea una versión simple de las dos
 * tablas con las mismas columnas.
 */
class PortadaServiceTest extends PruebaM1
{
    use ValidaEsquemaContrato;

    private const AHORA = '2026-11-10 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['productos_destacados' => 'id_producto', 'comercios_destacados' => 'id_comercio'] as $nombre => $campo) {
            Schema::create($nombre, function (Blueprint $tabla) use ($campo): void {
                $tabla->id($campo.'_destacado');
                $tabla->unsignedBigInteger($campo);
                $tabla->smallInteger('posicion');
                $tabla->timestamp('fecha_inicio');
                $tabla->timestamp('fecha_fin');
                $tabla->boolean('activo')->default(true);
                $tabla->timestamps();
            });
        }

        Carbon::setTestNow(self::AHORA);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Vigencia y orden (RN-M1-11)
    // ---------------------------------------------------------------

    public function test_los_productos_salen_en_el_orden_de_posicion(): void
    {
        $this->destacarProducto(DatosPrueba::PRODUCTO_CAFE_MOLIDO, 3);
        $this->destacarProducto(DatosPrueba::PRODUCTO_JARRON, 1);
        $this->destacarProducto(DatosPrueba::PRODUCTO_CAMISA_LINO, 2);

        $this->assertSame(
            [DatosPrueba::PRODUCTO_JARRON, DatosPrueba::PRODUCTO_CAMISA_LINO, DatosPrueba::PRODUCTO_CAFE_MOLIDO],
            $this->idsProductos($this->destacados()),
        );
    }

    public function test_los_comercios_destacados_son_los_que_decide_m2_en_su_orden(): void
    {
        // En el doble de M2 están destacados Guaitil (publicidad desde el 10/10) y
        // La Ceiba (desde el 05/10); M2 los ordena de la publicidad más reciente a la
        // más antigua. La tabla comercios_destacados de M1 ya no se usa.
        $this->destacarComercio(DatosPrueba::COMERCIO_CAFE_NICOYANO, 1);

        $this->assertSame(
            [DatosPrueba::COMERCIO_ARTESANIAS_GUAITIL, DatosPrueba::COMERCIO_LA_CEIBA],
            $this->idsComercios($this->destacados()),
        );
    }

    public function test_sin_comercios_destacados_en_m2_la_seccion_viene_vacia(): void
    {
        config(['integraciones.m1.m2.doble' => ComerciosSinDestacados::class]);

        $this->assertSame([], $this->destacados()->comercios);
    }

    public function test_solo_salen_los_destacados_activos_y_dentro_de_su_periodo(): void
    {
        $this->destacarProducto(DatosPrueba::PRODUCTO_CAMISA_LINO, 1, activo: false);
        $this->destacarProducto(DatosPrueba::PRODUCTO_CAFE_MOLIDO, 2, inicio: '2026-10-01 00:00:00', fin: '2026-11-10 11:59:59');
        $this->destacarProducto(DatosPrueba::PRODUCTO_JARRON, 3, inicio: '2026-11-10 12:00:01', fin: '2026-12-01 00:00:00');

        $destacados = $this->destacados();

        $this->assertSame([], $destacados->productos);
        $this->assertSame([], $destacados->seccionesNoDisponibles);
    }

    public function test_las_fechas_de_inicio_y_fin_estan_incluidas(): void
    {
        $this->destacarProducto(DatosPrueba::PRODUCTO_CAMISA_LINO, 1, inicio: self::AHORA, fin: '2026-12-01 00:00:00');
        $this->destacarProducto(DatosPrueba::PRODUCTO_JARRON, 2, inicio: '2026-11-01 00:00:00', fin: self::AHORA);

        $this->assertSame([DatosPrueba::PRODUCTO_CAMISA_LINO, DatosPrueba::PRODUCTO_JARRON], $this->idsProductos($this->destacados()));
    }

    public function test_sin_destacados_vigentes_las_secciones_vienen_vacias_sin_error(): void
    {
        config(['integraciones.m1.m2.doble' => ComerciosSinDestacados::class]);

        $destacados = $this->destacados();

        $this->assertEquals(new DestacadosDTO([], [], []), $destacados);
    }

    // ---------------------------------------------------------------
    // Productos publicados de comercios activos (RN-M1-05, RN-M1-06)
    // ---------------------------------------------------------------

    public function test_los_productos_traen_el_comercio_y_la_disponibilidad(): void
    {
        $this->destacarProducto(DatosPrueba::PRODUCTO_CAMISA_LINO, 1);
        $this->destacarProducto(DatosPrueba::PRODUCTO_SOMBRERO_AGOTADO, 2);

        $productos = $this->destacados()->productos;

        // Desde 1.4.0 el agotado no se muestra (ver test_los_productos_agotados_no_se_muestran).
        $this->assertCount(1, $productos);
        $this->assertSame('Tienda La Ceiba', $productos[0]->nombreComercio);
        $this->assertSame(Disponibilidad::Disponible, $productos[0]->disponibilidad);
    }

    public function test_se_omiten_los_productos_y_comercios_que_m3_y_m2_no_devuelven(): void
    {
        $this->destacarProducto(DatosPrueba::PRODUCTO_DE_COMERCIO_SUSPENDIDO, 1);
        $this->destacarProducto(DatosPrueba::PRODUCTO_INEXISTENTE, 2);
        $this->destacarProducto(DatosPrueba::PRODUCTO_JARRON, 3);

        $destacados = $this->destacados();

        $this->assertSame([DatosPrueba::PRODUCTO_JARRON], $this->idsProductos($destacados));
        // M2 solo devuelve comercios Activo: el suspendido nunca sale como destacado.
        $this->assertNotContains(DatosPrueba::COMERCIO_SUSPENDIDO, $this->idsComercios($destacados));
    }

    // ---------------------------------------------------------------
    // Límite
    // ---------------------------------------------------------------

    public function test_el_limite_recorta_cada_seccion(): void
    {
        $this->destacarTodo();

        $destacados = app(PortadaService::class)->listarDestacados(2);

        $this->assertCount(2, $destacados->productos);
        $this->assertCount(2, $destacados->comercios);
    }

    public function test_los_omitidos_no_dejan_huecos_dentro_del_limite(): void
    {
        $this->destacarProducto(DatosPrueba::PRODUCTO_DE_COMERCIO_SUSPENDIDO, 1);
        $this->destacarProducto(DatosPrueba::PRODUCTO_CAMISA_LINO, 2);
        $this->destacarProducto(DatosPrueba::PRODUCTO_JARRON, 3);

        $destacados = app(PortadaService::class)->listarDestacados(2);

        $this->assertSame([DatosPrueba::PRODUCTO_CAMISA_LINO, DatosPrueba::PRODUCTO_JARRON], $this->idsProductos($destacados));
        $this->assertSame([DatosPrueba::COMERCIO_ARTESANIAS_GUAITIL, DatosPrueba::COMERCIO_LA_CEIBA], $this->idsComercios($destacados));
    }

    public function test_un_limite_fuera_de_rango_se_ajusta_de_1_a_12(): void
    {
        $this->destacarTodo();

        $this->assertSame(12, PortadaService::LIMITE_POR_DEFECTO);
        $this->assertSame(12, PortadaService::LIMITE_MAXIMO);
        $this->assertCount(1, app(PortadaService::class)->listarDestacados(0)->productos);
        $this->assertCount(1, app(PortadaService::class)->listarDestacados(-5)->comercios);
        $this->assertCount(3, app(PortadaService::class)->listarDestacados(500)->productos);

        $cache = app(CachePortal::class);
        $this->assertNotNull($cache->obtener($this->claveCache(1)));
        $this->assertNotNull($cache->obtener($this->claveCache(PortadaService::LIMITE_MAXIMO)));
    }

    // ---------------------------------------------------------------
    // Reglas de la portada desde 1.4.0 (SC-08)
    // ---------------------------------------------------------------

    public function test_los_productos_agotados_no_se_muestran(): void
    {
        $this->destacarProducto(DatosPrueba::PRODUCTO_SOMBRERO_AGOTADO, 1);
        $this->destacarProducto(DatosPrueba::PRODUCTO_CAMISA_LINO, 2);
        $this->destacarProducto(DatosPrueba::PRODUCTO_TAZA_SIN_EXISTENCIA, 3);
        $this->destacarProducto(DatosPrueba::PRODUCTO_JARRON, 4);

        $destacados = $this->destacados();

        $this->assertSame([DatosPrueba::PRODUCTO_CAMISA_LINO, DatosPrueba::PRODUCTO_JARRON], $this->idsProductos($destacados));
        foreach ($destacados->productos as $producto) {
            $this->assertSame(Disponibilidad::Disponible, $producto->disponibilidad);
        }
    }

    public function test_un_destacado_agotado_reaparece_solo_al_recuperar_existencias(): void
    {
        $this->destacarProducto(DatosPrueba::PRODUCTO_CAMISA_LINO, 1);
        $cache = app(CachePortal::class);

        // M4 avisa que se agotó (evento ExistenciaAgotada): deja de mostrarse.
        $cache->marcarAgotado(DatosPrueba::PRODUCTO_CAMISA_LINO, null);
        $this->assertSame([], $this->idsProductos($this->destacados()));

        // M4 avisa que se repuso (evento ExistenciaRepuesta): vuelve sin que nadie lo reactive.
        $cache->quitarAgotado(DatosPrueba::PRODUCTO_CAMISA_LINO, null);
        Carbon::setTestNow(Carbon::parse(self::AHORA)->addSeconds(6));

        $this->assertSame([DatosPrueba::PRODUCTO_CAMISA_LINO], $this->idsProductos($this->destacados()));
    }

    public function test_los_productos_de_un_comercio_suspendido_no_se_muestran_aunque_tengan_existencias(): void
    {
        $this->destacarProducto(DatosPrueba::PRODUCTO_DE_COMERCIO_SUSPENDIDO, 1);

        $this->assertSame([], $this->destacados()->productos);
    }

    public function test_los_productos_que_m3_ya_no_publica_no_se_muestran(): void
    {
        // Un producto desactivado por el comercio no lo devuelve M3.
        $this->destacarProducto(DatosPrueba::PRODUCTO_INEXISTENTE, 1);
        $this->destacarProducto(DatosPrueba::PRODUCTO_CAMISA_LINO, 2);

        $this->assertSame([DatosPrueba::PRODUCTO_CAMISA_LINO], $this->idsProductos($this->destacados()));
    }

    // ---------------------------------------------------------------
    // M2, M3 o M4 no responden (RNF-M1-05)
    // ---------------------------------------------------------------

    public function test_si_m3_no_responde_los_productos_vienen_vacios_y_se_informa(): void
    {
        $this->destacarTodo();
        config(['integraciones.m1.m3.doble' => CatalogoNoDisponible::class]);

        $destacados = $this->destacados();

        $this->assertSame([], $destacados->productos);
        $this->assertSame(['productos'], $destacados->seccionesNoDisponibles);
        // Los comercios destacados vienen de M2 y siguen saliendo.
        $this->assertCount(2, $destacados->comercios);
    }

    public function test_si_m2_no_responde_los_comercios_vienen_vacios_y_se_informa(): void
    {
        $this->destacarTodo();
        config(['integraciones.m1.m2.doble' => ComerciosNoDisponible::class]);

        $destacados = $this->destacados();

        $this->assertSame([], $destacados->comercios);
        $this->assertSame(['comercios'], $destacados->seccionesNoDisponibles);
        // Los productos siguen saliendo con lo que entregó M3.
        $this->assertCount(3, $destacados->productos);
    }

    public function test_si_m2_y_m3_no_responden_la_portada_no_falla(): void
    {
        $this->destacarTodo();
        config([
            'integraciones.m1.m2.doble' => ComerciosNoDisponible::class,
            'integraciones.m1.m3.doble' => CatalogoNoDisponible::class,
        ]);

        $this->assertEquals(new DestacadosDTO([], [], ['comercios', 'productos']), $this->destacados());
    }

    public function test_si_m4_no_responde_los_productos_salen_como_agotados_sin_ocultarse(): void
    {
        $this->destacarProducto(DatosPrueba::PRODUCTO_CAMISA_LINO, 1);
        config(['integraciones.m1.m4.doble' => InventarioNoDisponible::class]);

        $destacados = $this->destacados();

        $this->assertSame(Disponibilidad::Agotado, $destacados->productos[0]->disponibilidad);
        $this->assertSame([], $destacados->seccionesNoDisponibles);
    }

    // ---------------------------------------------------------------
    // Caché
    // ---------------------------------------------------------------

    public function test_el_resultado_completo_se_guarda_en_la_cache(): void
    {
        $this->destacarProducto(DatosPrueba::PRODUCTO_CAMISA_LINO, 1);
        $primero = $this->destacados();

        ProductoDestacado::query()->delete();

        $this->assertEquals($primero, $this->destacados());
    }

    public function test_un_resultado_con_una_seccion_no_disponible_no_se_guarda(): void
    {
        $this->destacarTodo();
        config(['integraciones.m1.m3.doble' => CatalogoNoDisponible::class]);

        $this->destacados();

        $this->assertNull(app(CachePortal::class)->obtener($this->claveCache(PortadaService::LIMITE_POR_DEFECTO)));
    }

    // ---------------------------------------------------------------
    // Ruta contra el contrato
    // ---------------------------------------------------------------

    public function test_get_destacados_responde_200_con_el_esquema_destacados(): void
    {
        $this->destacarTodo();

        $respuesta = $this->getJson(route('api.m1.v1.destacados'))->assertOk();

        $this->assertCumpleEsquema('Destacados', $respuesta->json());
        $respuesta->assertJsonPath('secciones_no_disponibles', []);
        $respuesta->assertJsonPath('comercios.1', [
            'id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA,
            'nombre_comercial' => 'Tienda La Ceiba',
            'logotipo' => 'https://cdn.pacificos.example/comercios/34/logo.png',
        ]);
        $respuesta->assertJsonPath('productos.0.comercio', ['id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA, 'nombre_comercial' => 'Tienda La Ceiba']);
    }

    public function test_get_destacados_aplica_el_limite_y_ajusta_los_valores_invalidos(): void
    {
        $this->destacarTodo();

        $this->getJson(route('api.m1.v1.destacados', ['limite' => 2]))->assertOk()->assertJsonCount(2, 'productos')->assertJsonCount(2, 'comercios');
        $this->getJson(route('api.m1.v1.destacados', ['limite' => 0]))->assertOk()->assertJsonCount(1, 'productos');
        $this->getJson(route('api.m1.v1.destacados', ['limite' => 500]))->assertOk()->assertJsonCount(3, 'productos');
        $this->getJson(route('api.m1.v1.destacados', ['limite' => 'abc']))->assertOk()->assertJsonCount(3, 'productos');
    }

    public function test_get_destacados_con_m2_y_m3_caidos_responde_200_con_las_secciones_no_disponibles(): void
    {
        $this->destacarTodo();
        config([
            'integraciones.m1.m2.doble' => ComerciosNoDisponible::class,
            'integraciones.m1.m3.doble' => CatalogoNoDisponible::class,
        ]);

        $respuesta = $this->getJson(route('api.m1.v1.destacados'))->assertOk();

        $this->assertCumpleEsquema('Destacados', $respuesta->json());
        $respuesta->assertExactJson(['comercios' => [], 'productos' => [], 'secciones_no_disponibles' => ['comercios', 'productos']]);
    }

    // ---------------------------------------------------------------
    // Ayudantes
    // ---------------------------------------------------------------

    private function claveCache(int $limite): string
    {
        return PortadaService::clave($limite, app(ReglasDestacados::class)->turno(Carbon::now()));
    }

    private function destacados(): DestacadosDTO
    {
        return app(PortadaService::class)->listarDestacados(PortadaService::LIMITE_POR_DEFECTO);
    }

    /**
     * Tres productos y tres comercios activos, todos vigentes.
     */
    private function destacarTodo(): void
    {
        $this->destacarProducto(DatosPrueba::PRODUCTO_CAMISA_LINO, 1);
        $this->destacarProducto(DatosPrueba::PRODUCTO_CAFE_MOLIDO, 2);
        $this->destacarProducto(DatosPrueba::PRODUCTO_JARRON, 3);
        $this->destacarComercio(DatosPrueba::COMERCIO_LA_CEIBA, 1);
        $this->destacarComercio(DatosPrueba::COMERCIO_CAFE_NICOYANO, 2);
        $this->destacarComercio(DatosPrueba::COMERCIO_ARTESANIAS_GUAITIL, 3);
    }

    private function destacarProducto(int $idProducto, int $posicion, bool $activo = true, string $inicio = '2026-11-01 00:00:00', string $fin = '2026-11-30 23:59:59'): void
    {
        ProductoDestacado::query()->create([
            'id_producto' => $idProducto,
            'posicion' => $posicion,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'activo' => $activo,
        ]);
    }

    private function destacarComercio(int $idComercio, int $posicion, bool $activo = true, string $inicio = '2026-11-01 00:00:00', string $fin = '2026-11-30 23:59:59'): void
    {
        ComercioDestacado::query()->create([
            'id_comercio' => $idComercio,
            'posicion' => $posicion,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'activo' => $activo,
        ]);
    }

    /**
     * @return list<int>
     */
    private function idsProductos(DestacadosDTO $destacados): array
    {
        return array_map(fn (ProductoResumenDTO $producto): int => $producto->idProducto, $destacados->productos);
    }

    /**
     * @return list<int>
     */
    private function idsComercios(DestacadosDTO $destacados): array
    {
        return array_map(fn (ComercioDTO $comercio): int => $comercio->idComercio, $destacados->comercios);
    }
}
