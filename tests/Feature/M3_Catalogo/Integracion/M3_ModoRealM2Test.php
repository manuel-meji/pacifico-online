<?php

declare(strict_types=1);

namespace Tests\Feature\M3_Catalogo\Integracion;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Events\M2_Comercios\ComercioAprobado;
use App\Events\M2_Comercios\ComercioReactivado;
use App\Events\M2_Comercios\ComercioSuspendido;
use App\Exceptions\M3_Catalogo\ErrorCatalogoException;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Services\M2_Comercios\Dobles\ComerciosDobleBase;
use App\Services\M3_Catalogo\CatalogoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\M3_Catalogo\EsquemaCatalogoPrueba;
use Tests\TestCase;

/**
 * M3 con M2 en modo real (integraciones.m3.m2.modo = 'real', el valor por defecto).
 *
 * En modo real ProductoService recibe lo que el contenedor tenga enlazado a
 * ComerciosServiceInterface. Aquí se enlaza un fake con la forma del contrato de M2
 * (id_comercio, estado, activo, motivo_estado) para no depender de las tablas de M2.
 */
class M3_ModoRealM2Test extends TestCase
{
    use EsquemaCatalogoPrueba;

    private const ACTIVO = 1;

    private const SUSPENDIDO = 2;

    private object $m2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaCatalogo();
        Schema::dropIfExists('estado_comercios_catalogo');
        Schema::create('estado_comercios_catalogo', function (Blueprint $tabla) {
            $tabla->unsignedBigInteger('id_comercio')->primary();
            $tabla->boolean('activo')->default(false);
            $tabla->timestampTz('fecha_ultimo_evento')->nullable();
            $tabla->uuid('id_ultimo_evento')->nullable();
            $tabla->timestampsTz();
        });

        $this->m2 = new class extends ComerciosDobleBase
        {
            /** @var list<int> */
            public array $consultados = [];

            public function obtenerComercioActivo(int $idComercio): array
            {
                $this->consultados[] = $idComercio;

                return match ($idComercio) {
                    1 => ['id_comercio' => 1, 'estado' => 'Activo', 'activo' => true, 'motivo_estado' => null],
                    2 => ['id_comercio' => 2, 'estado' => 'Suspendido', 'activo' => false, 'motivo_estado' => 'Membresía vencida'],
                    default => throw ErrorNucleoException::recursoNoEncontrado(),
                };
            }
        };

        config(['integraciones.m3.m2.modo' => 'real']);
        $this->app->instance(ComerciosServiceInterface::class, $this->m2);
        $this->comoComercio(self::ACTIVO);
    }

    /** @return array<string, mixed> */
    private function datosProducto(): array
    {
        $categoria = $this->servicioCatalogo()->crearCategoria(['nombre' => 'Categoría '.uniqid()]);

        return ['id_categoria' => $categoria['id_categoria'], 'nombre' => 'Producto de prueba'];
    }

    /** Producto ya publicado del comercio, insertado directo para probar solo la visibilidad. */
    private function productoPublicado(int $idComercio): int
    {
        $idCategoria = DB::table('categorias')->insertGetId(['nombre' => 'Visibilidad '.uniqid()], 'id_categoria');

        return (int) DB::table('productos')->insertGetId([
            'id_comercio' => $idComercio,
            'id_categoria' => $idCategoria,
            'nombre' => 'Producto publicado',
            'estado_producto' => 'publicado',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_producto');
    }

    private function totalEnBusqueda(): int
    {
        return app(CatalogoService::class)->buscarProductos()['total_resultados'];
    }

    private function hora(string $hora): Carbon
    {
        return Carbon::parse("2026-11-13 {$hora}");
    }

    public function test_en_modo_real_m3_consulta_el_servicio_enlazado_de_m2(): void
    {
        $this->servicioCatalogo()->crearProducto($this->datosProducto());

        $this->assertSame([self::ACTIVO], $this->m2->consultados);
    }

    public function test_comercio_activo_crea_producto(): void
    {
        $producto = $this->servicioCatalogo()->crearProducto($this->datosProducto());

        $this->assertSame('borrador', $producto['estado_producto']);
        $this->assertSame(self::ACTIVO, $producto['id_comercio']);
    }

    public function test_comercio_suspendido_falla_con_rn_m3_07(): void
    {
        $datos = $this->datosProducto();
        $this->comoComercio(self::SUSPENDIDO);

        try {
            $this->servicioCatalogo()->crearProducto($datos);
            $this->fail('Debió rechazar al comercio suspendido.');
        } catch (ErrorCatalogoException $error) {
            $this->assertSame('ERR-M3-004', $error->codigo);
            $this->assertSame(409, $error->http);
        }

        $this->assertSame(0, DB::table('productos')->count());
    }

    public function test_comercio_que_m2_no_conoce_no_crea_producto(): void
    {
        $datos = $this->datosProducto();
        $this->comoComercio(99);

        try {
            $this->servicioCatalogo()->crearProducto($datos);
            $this->fail('Debió propagar el ERR-004 de M2.');
        } catch (ErrorNucleoException $error) {
            $this->assertSame('ERR-004', $error->codigo);
        }

        $this->assertSame(0, DB::table('productos')->count());
    }

    public function test_la_suspension_saca_el_producto_de_la_busqueda_y_la_reactivacion_lo_devuelve(): void
    {
        $this->productoPublicado(self::ACTIVO);

        event(new ComercioAprobado((string) Str::uuid(), $this->hora('09:00:00'), self::ACTIVO, 'Activo', 'Comercio de prueba'));
        $this->assertSame(1, $this->totalEnBusqueda());

        event(new ComercioSuspendido((string) Str::uuid(), $this->hora('10:00:00'), self::ACTIVO, 'Suspendido', 'Membresía vencida'));
        $this->assertFalse((bool) DB::table('estado_comercios_catalogo')->where('id_comercio', self::ACTIVO)->value('activo'));
        $this->assertSame(0, $this->totalEnBusqueda());

        event(new ComercioReactivado((string) Str::uuid(), $this->hora('11:00:00'), self::ACTIVO, 'Activo', 'Comercio de prueba'));
        $this->assertTrue((bool) DB::table('estado_comercios_catalogo')->where('id_comercio', self::ACTIVO)->value('activo'));
        $this->assertSame(1, $this->totalEnBusqueda());
    }
}
