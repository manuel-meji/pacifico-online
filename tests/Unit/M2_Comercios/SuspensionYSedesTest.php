<?php

declare(strict_types=1);

namespace Tests\Unit\M2_Comercios;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Events\M2_Comercios\ComercioReactivado;
use App\Events\M2_Comercios\ComercioSuspendido;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M2_Comercios\BannerComercio;
use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\SedeComercio;
use App\Models\M2_Comercios\SuspensionComercio;
use App\Services\M2_Comercios\ComerciosService;
use Closure;
use Database\Seeders\M2_Comercios\ComerciosDemoSeeder;
use Illuminate\Support\Facades\Event;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

/** CU-0017 y CU-0018 (suspender y reactivar), sedes para M4 y aislamiento por comercio. */
class SuspensionYSedesTest extends TestCase
{
    use PreparaM2;

    private ComerciosService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(NucleoServiceInterface::class, fn ($m) => $m->shouldReceive('registrarAuditoria')->zeroOrMoreTimes());
        Event::fake([ComercioSuspendido::class, ComercioReactivado::class]);
        $this->actingAs($this->usuario('encargado_plataforma'));
        $this->servicio = app(ComerciosService::class);
    }

    public function test_suspender_un_comercio_activo_guarda_el_motivo_y_publica_el_evento(): void
    {
        $c = $this->crearComercio();

        $suspension = $this->servicio->suspenderComercio($c->id_comercio, '  Documentos vencidos  ');

        $this->assertSame('Documentos vencidos', $suspension->motivo);
        $this->assertTrue($suspension->estaAbierta());
        $this->assertSame(['estado' => 'Suspendido', 'activo' => false, 'motivo_estado' => 'Documentos vencidos'],
            array_intersect_key($this->servicio->obtenerComercioActivo($c->id_comercio), array_flip(['estado', 'activo', 'motivo_estado'])));
        Event::assertDispatched(ComercioSuspendido::class, fn (ComercioSuspendido $e): bool => $e->id_comercio === $c->id_comercio
            && $e->estado === 'Suspendido' && $e->motivo === 'Documentos vencidos' && $e->id_evento !== '');
        $this->esperarError('ERR-004', fn () => $this->servicio->obtenerDatosComercioPublico($c->id_comercio));
    }

    public function test_suspender_sin_motivo_o_un_comercio_no_activo_lanza_err_005(): void
    {
        $activo = $this->crearComercio();
        $pendiente = $this->crearComercio(['estado' => Comercio::PENDIENTE]);

        $this->esperarError('ERR-005', fn () => $this->servicio->suspenderComercio($activo->id_comercio, '   '));
        $this->esperarError('ERR-005', fn () => $this->servicio->suspenderComercio($pendiente->id_comercio, 'Motivo'));
        $this->assertSame(0, SuspensionComercio::query()->count());
        Event::assertNotDispatched(ComercioSuspendido::class);
    }

    public function test_reactivar_cierra_la_suspension_mantiene_la_comision_y_publica_el_evento(): void
    {
        $c = $this->crearComercio(['nombre_comercial' => 'Café Volcán']);
        $this->crearComision($c, 'porcentaje', 7);
        $this->servicio->suspenderComercio($c->id_comercio, 'Revisión');

        $this->servicio->reactivarComercio($c->id_comercio);

        $this->assertSame('Activo', $c->fresh()->estado);
        $this->assertNotNull(SuspensionComercio::query()->first()->fecha_reactivacion);
        $this->assertSame(7.0, $this->servicio->obtenerDatosFiscalesComercio($c->id_comercio)['comision']['valor']);
        Event::assertDispatched(ComercioReactivado::class, fn (ComercioReactivado $e): bool => $e->estado === 'Activo'
            && $e->nombre_comercial === 'Café Volcán');
    }

    public function test_reactivar_un_comercio_que_no_esta_suspendido_lanza_err_005(): void
    {
        $c = $this->crearComercio();

        $this->esperarError('ERR-005', fn () => $this->servicio->reactivarComercio($c->id_comercio));
        $this->esperarError('ERR-004', fn () => $this->servicio->reactivarComercio(999));
    }

    public function test_sedes_devuelve_todas_con_su_indicador_habilitada(): void
    {
        $c = $this->crearComercio();
        $c->sedes()->create(['nombre' => 'Tienda', 'direccion' => 'Liberia', 'habilitada' => true]);
        $c->sedes()->create(['nombre' => 'Bodega vieja', 'direccion' => 'Cañas', 'habilitada' => false]);

        $sedes = $this->servicio->obtenerSedesComercio($c->id_comercio)['sedes'];

        $this->assertSame(['Tienda', 'Bodega vieja'], array_column($sedes, 'nombre'));
        $this->assertSame([true, false], array_column($sedes, 'habilitada'));
        $this->assertSame(array_keys($sedes[0]), ['id_sede', 'nombre', 'direccion', 'habilitada']);
    }

    public function test_seeder_crea_las_sedes_del_escenario(): void
    {
        $this->seed(ComerciosDemoSeeder::class);
        $this->seed(ComerciosDemoSeeder::class);

        $this->assertSame(6, SedeComercio::query()->count());
    }

    public function test_el_encargado_de_un_comercio_solo_ve_lo_suyo_pero_el_micrositio_publico_sigue_completo(): void
    {
        $a = $this->crearComercio();
        $b = $this->crearComercio();
        $this->comoSistema(function () use ($a, $b): void {
            $a->banners()->create(['ruta' => 'https://cdn.test/a.png', 'texto_alternativo' => 'A', 'orden' => 1, 'activo' => true]);
            $b->banners()->create(['ruta' => 'https://cdn.test/b.png', 'texto_alternativo' => 'B', 'orden' => 1, 'activo' => true]);
        });

        $this->enAmbitoDe($a->id_comercio);

        $this->assertSame(['A'], BannerComercio::query()->pluck('texto_alternativo')->all());
        $this->assertCount(1, $this->servicio->obtenerDatosComercioPublico($b->id_comercio)['banners']);
        $this->esperarError('ERR-003', fn () => BannerComercio::query()->create([
            'id_comercio' => $b->id_comercio, 'ruta' => 'x', 'texto_alternativo' => 'X', 'orden' => 2, 'activo' => true,
        ]));
    }

    private function esperarError(string $codigo, Closure $operacion): void
    {
        try {
            $operacion();
            $this->fail("Se esperaba {$codigo}.");
        } catch (ErrorNucleoException $e) {
            $this->assertSame($codigo, $e->codigo);
        }
    }
}
