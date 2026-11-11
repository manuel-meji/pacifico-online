<?php

namespace Tests\Feature\M1_Portal;

use App\Models\M1_Portal\CategoriaPortal;
use App\Models\M1_Portal\ComercioDestacado;
use App\Models\M1_Portal\ProductoDestacado;
use App\Services\M1_Portal\Dobles\DatosPrueba;
use App\Services\M1_Portal\PortadaService;
use App\Services\M1_Portal\PortalPublicoService;
use Database\Seeders\M1_Portal\PortalDemoSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\Doubles\M1\CatalogoNoDisponible;
use Tests\Doubles\M1\ComerciosNoDisponible;

/**
 * PortalDemoSeeder con los dobles de M2 y M3: llena el menú de categorías y los destacados con lo
 * que entregan los proveedores, sin duplicar filas y sin fallar si un proveedor no responde.
 *
 * Las migraciones de M1 tienen CHECK de PostgreSQL que no corren en el SQLite de las pruebas, así
 * que se crea una versión simple de las tres tablas con las mismas columnas.
 */
class PortalDemoSeederTest extends PruebaM1
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('categorias_portal', function (Blueprint $tabla): void {
            $tabla->id('id_categoria_portal');
            $tabla->unsignedBigInteger('id_categoria')->unique();
            $tabla->smallInteger('orden_visualizacion');
            $tabla->boolean('visible_en_menu')->default(true);
            $tabla->string('icono_url')->nullable();
            $tabla->timestamps();
        });

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

        Carbon::setTestNow('2026-11-08 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_el_menu_queda_con_las_categorias_principales_de_m3_en_su_orden(): void
    {
        $this->seed(PortalDemoSeeder::class);

        $this->assertSame(
            [DatosPrueba::CATEGORIA_ROPA => 1, DatosPrueba::CATEGORIA_ALIMENTOS => 2, DatosPrueba::CATEGORIA_ARTESANIA => 3],
            array_slice(CategoriaPortal::query()->orderBy('orden_visualizacion')->pluck('orden_visualizacion', 'id_categoria')->all(), 0, 3, true),
        );
        $this->assertFalse(CategoriaPortal::query()->where('id_categoria', DatosPrueba::CATEGORIA_CAMISAS)->exists(), 'Las subcategorías no necesitan fila.');
        $this->assertTrue(CategoriaPortal::query()->where('visible_en_menu', false)->doesntExist());
    }

    public function test_los_destacados_son_productos_y_comercios_activos_que_entregan_m3_y_m2(): void
    {
        $this->seed(PortalDemoSeeder::class);

        $productos = ProductoDestacado::query()->orderBy('posicion')->pluck('id_producto')->all();
        $comercios = ComercioDestacado::query()->orderBy('posicion')->pluck('id_comercio')->all();

        $this->assertNotEmpty($productos);
        $this->assertLessThanOrEqual(PortalDemoSeeder::MAXIMO_PRODUCTOS, count($productos));
        $this->assertNotContains(DatosPrueba::PRODUCTO_DE_COMERCIO_SUSPENDIDO, $productos);
        $this->assertSame([DatosPrueba::COMERCIO_LA_CEIBA, DatosPrueba::COMERCIO_CAFE_NICOYANO, DatosPrueba::COMERCIO_ARTESANIAS_GUAITIL], $comercios);
        $this->assertNotContains(DatosPrueba::COMERCIO_SUSPENDIDO, $comercios);
    }

    public function test_los_destacados_quedan_vigentes_desde_hoy_por_30_dias(): void
    {
        $this->seed(PortalDemoSeeder::class);

        $destacado = ComercioDestacado::query()->firstOrFail();

        $this->assertSame('2026-11-08 00:00:00', $destacado->fecha_inicio->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-08 23:59:59', $destacado->fecha_fin->format('Y-m-d H:i:s'));
        $this->assertTrue($destacado->estaVigente(Carbon::now()));
    }

    public function test_ejecutarlo_dos_veces_no_duplica_filas(): void
    {
        $this->seed(PortalDemoSeeder::class);
        $antes = [CategoriaPortal::query()->count(), ProductoDestacado::query()->count(), ComercioDestacado::query()->count()];

        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->seed(PortalDemoSeeder::class);

        $this->assertSame($antes, [CategoriaPortal::query()->count(), ProductoDestacado::query()->count(), ComercioDestacado::query()->count()]);
        $this->assertSame('2026-11-20', ComercioDestacado::query()->firstOrFail()->fecha_inicio->format('Y-m-d'));
    }

    public function test_si_m3_no_responde_siembra_igual_los_comercios(): void
    {
        config(['integraciones.m1.m3.doble' => CatalogoNoDisponible::class]);

        $this->seed(PortalDemoSeeder::class);

        $this->assertSame(0, CategoriaPortal::query()->count());
        $this->assertSame(0, ProductoDestacado::query()->count());
        $this->assertSame(3, ComercioDestacado::query()->count());
    }

    public function test_si_m2_no_responde_siembra_igual_categorias_y_productos(): void
    {
        config(['integraciones.m1.m2.doble' => ComerciosNoDisponible::class]);

        $this->seed(PortalDemoSeeder::class);

        $this->assertGreaterThan(0, CategoriaPortal::query()->count());
        $this->assertGreaterThan(0, ProductoDestacado::query()->count());
        $this->assertSame(0, ComercioDestacado::query()->count());
    }

    public function test_despues_de_sembrar_la_portada_muestra_los_destacados(): void
    {
        $this->seed(PortalDemoSeeder::class);

        $destacados = app(PortalPublicoService::class)->listarDestacados(PortadaService::LIMITE_POR_DEFECTO);

        // Los comercios destacados los decide M2 (en su doble, Guaitil y La Ceiba).
        $this->assertCount(2, $destacados['comercios']);
        $this->assertNotEmpty($destacados['productos']);
        $this->assertSame([], $destacados['secciones_no_disponibles']);
    }
}
