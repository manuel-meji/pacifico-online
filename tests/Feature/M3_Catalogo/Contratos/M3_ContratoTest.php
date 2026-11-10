<?php

namespace Tests\Feature\M3_Catalogo\Contratos;

use App\Events\M3_Catalogo\ProductoPublicado;
use App\Events\M4_Inventario\ExistenciaAgotada;
use App\Events\M4_Inventario\ExistenciaRepuesta;
use App\Exceptions\M3_Catalogo\ErrorCatalogoException;
use App\Exceptions\Nucleo\ErrorNucleoException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Doubles\M3_Catalogo\ComerciosNoDisponible;
use Tests\Doubles\M3_Catalogo\ComerciosStub;
use Tests\Doubles\M3_Catalogo\EventosInventarioSimulados;
use Tests\Feature\M3_Catalogo\EsquemaCatalogoPrueba;
use Tests\TestCase;

/**
 * Prueba de consumidor de M3: verifica que la lógica de M3 procesa bien lo que
 * responde el doble de M2 (comercio activo y suspendido) y que el doble de M4
 * tiene el mismo payload que los eventos reales.
 */
class M3_ContratoTest extends TestCase
{
    use EsquemaCatalogoPrueba;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCatalogo(1);
        Event::fake([ProductoPublicado::class]);
    }

    /** @return array<string, mixed> */
    private function datosProducto(): array
    {
        $categoria = $this->servicioCatalogo()->crearCategoria(['nombre' => 'Categoría '.uniqid()]);

        return ['id_categoria' => $categoria['id_categoria'], 'nombre' => 'Producto de prueba'];
    }

    public function test_un_comercio_activo_puede_crear_productos(): void
    {
        $producto = $this->servicioCatalogo()->crearProducto($this->datosProducto());

        $this->assertSame('borrador', $producto['estado_producto']);
        $this->assertSame(1, $producto['id_comercio']);
    }

    public function test_un_comercio_suspendido_no_puede_crear_productos(): void
    {
        $this->comoComercio(37);

        try {
            $this->servicioCatalogo()->crearProducto($this->datosProducto());
            $this->fail('Debió rechazar al comercio suspendido.');
        } catch (ErrorCatalogoException $error) {
            $this->assertSame('ERR-M3-004', $error->codigo);
            $this->assertSame(409, $error->http);
        }

        $this->assertSame(0, DB::table('productos')->count());
    }

    public function test_un_comercio_suspendido_no_puede_publicar(): void
    {
        $idProducto = DB::table('productos')->insertGetId([
            'id_comercio' => 37, 'id_categoria' => 1, 'nombre' => 'Producto',
            'estado_producto' => 'borrador', 'destacado' => false,
            'created_at' => now(), 'updated_at' => now(),
        ], 'id_producto');
        DB::table('producto_variaciones')->insert([
            'id_producto' => $idProducto, 'id_comercio' => 37, 'sku' => 'SUS-1',
            'precio' => 100, 'tarifa_impuesto' => 13, 'disponible' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('imagenes_producto')->insert([
            'id_producto' => $idProducto, 'imagen_url' => 'https://ejemplo.test/a.jpg',
            'formato' => 'jpg', 'orden' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->comoComercio(37);

        try {
            $this->servicioCatalogo()->cambiarEstado($idProducto, ['estado_producto' => 'publicado']);
            $this->fail('Debió rechazar la publicación.');
        } catch (ErrorCatalogoException $error) {
            $this->assertSame('ERR-M3-004', $error->codigo);
        }

        $this->assertSame('borrador', DB::table('productos')->where('id_producto', $idProducto)->value('estado_producto'));
        Event::assertNotDispatched(ProductoPublicado::class);
    }

    public function test_un_comercio_inexistente_propaga_err_004_de_m2(): void
    {
        $this->comoComercio(999);

        try {
            $this->servicioCatalogo()->crearProducto($this->datosProducto());
            $this->fail('Debió fallar: el comercio no existe.');
        } catch (ErrorNucleoException $error) {
            $this->assertSame('ERR-004', $error->codigo);
            $this->assertSame(404, $error->http);
        }
    }

    public function test_si_m2_no_responde_el_error_es_err_006(): void
    {
        config(['integraciones.m3.m2.doble' => ComerciosNoDisponible::class]);

        try {
            $this->servicioCatalogo()->crearProducto($this->datosProducto());
            $this->fail('Debió fallar: M2 no responde.');
        } catch (ErrorNucleoException $error) {
            $this->assertSame('ERR-006', $error->codigo);
            $this->assertSame(503, $error->http);
        }
    }

    public function test_el_doble_de_m2_devuelve_el_estado_con_la_forma_del_contrato(): void
    {
        $doble = new ComerciosStub;

        $this->assertSame(
            ['id_comercio' => 1, 'estado' => 'Activo', 'activo' => true, 'motivo_estado' => null],
            $doble->obtenerComercioActivo(1)
        );

        $suspendido = $doble->obtenerComercioActivo(37);
        $this->assertSame('Suspendido', $suspendido['estado']);
        $this->assertFalse($suspendido['activo']);
        $this->assertNotNull($suspendido['motivo_estado']);
    }

    public function test_los_dobles_de_eventos_de_m4_tienen_el_payload_real(): void
    {
        $agotada = EventosInventarioSimulados::existenciaAgotada(1, 2, 3);
        $repuesta = EventosInventarioSimulados::existenciaRepuesta(1, 2, 3, 5);

        $this->assertSame(
            array_keys((new ExistenciaAgotada(1, 2, 3))->payload()),
            array_keys($agotada['carga_util'])
        );
        $this->assertSame(
            array_keys((new ExistenciaRepuesta(1, 2, 3, 5))->payload()),
            array_keys($repuesta['carga_util'])
        );
        $this->assertNull($agotada['carga_util']['id_ubicacion']);
        $this->assertSame(0, $agotada['carga_util']['cantidad_disponible']);
        $this->assertSame(5, $repuesta['carga_util']['cantidad_disponible']);
    }
}