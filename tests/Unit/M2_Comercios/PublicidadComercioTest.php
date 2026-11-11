<?php

declare(strict_types=1);

namespace Tests\Unit\M2_Comercios;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\PublicidadComercio;
use App\Services\M2_Comercios\ComerciosService;
use Closure;
use Illuminate\Support\Carbon;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

/** Destacar comercios: solicitud del encargado y revisión de la plataforma (gestionarPublicidad). */
class PublicidadComercioTest extends TestCase
{
    use PreparaM2;

    private ComerciosService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->mock(NucleoServiceInterface::class, fn ($m) => $m->shouldReceive('registrarAuditoria')->zeroOrMoreTimes());
        $this->servicio = app(ComerciosService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_la_solicitud_del_encargado_queda_pendiente_y_no_destaca_todavia(): void
    {
        $c = $this->crearComercio();

        $publicidad = $this->servicio->solicitarPublicidad($c->id_comercio, 15, false);

        $this->assertSame(PublicidadComercio::PENDIENTE, $publicidad->estado);
        $this->assertSame(0, $this->servicio->listarComerciosHabilitados(1, 20, true)['total']);
        $this->assertFalse($this->servicio->listarPublicidadComercio($c->id_comercio)['destacado']);
    }

    public function test_al_aprobar_el_comercio_queda_destacado_desde_hoy_por_los_dias_solicitados(): void
    {
        $c = $this->crearComercio();
        $pendiente = $this->servicio->solicitarPublicidad($c->id_comercio, 7, false);

        $aprobada = $this->servicio->gestionarPublicidad($pendiente->id_publicidad_comercio, 'aprobar');

        $this->assertSame(PublicidadComercio::APROBADA, $aprobada->estado);
        $this->assertSame('2026-11-10', $aprobada->inicio->toDateString());
        $this->assertSame('2026-11-16', $aprobada->fin->toDateString());
        $this->assertSame([$c->id_comercio], array_column($this->servicio->listarComerciosHabilitados(1, 20, true)['comercios'], 'id_comercio'));
    }

    public function test_al_rechazar_no_queda_destacado_y_no_se_puede_revisar_dos_veces(): void
    {
        $c = $this->crearComercio();
        $pendiente = $this->servicio->solicitarPublicidad($c->id_comercio, 30, false);

        $this->servicio->gestionarPublicidad($pendiente->id_publicidad_comercio, 'rechazar');

        $this->assertFalse($this->servicio->listarPublicidadComercio($c->id_comercio)['destacado']);
        $this->esperarError('ERR-005', fn () => $this->servicio->gestionarPublicidad($pendiente->id_publicidad_comercio, 'aprobar'));
    }

    public function test_la_plataforma_destaca_directamente_sin_solicitud(): void
    {
        $c = $this->crearComercio();

        $publicidad = $this->servicio->solicitarPublicidad($c->id_comercio, 30, true);

        $this->assertSame(PublicidadComercio::APROBADA, $publicidad->estado);
        $this->assertTrue($this->servicio->listarPublicidadComercio($c->id_comercio)['destacado']);
    }

    public function test_reglas_dias_validos_comercio_activo_y_una_sola_pendiente(): void
    {
        $activo = $this->crearComercio();
        $suspendido = $this->crearComercio(['estado' => Comercio::SUSPENDIDO]);

        $this->esperarError('ERR-005', fn () => $this->servicio->solicitarPublicidad($activo->id_comercio, 10, false));
        $this->esperarError('ERR-005', fn () => $this->servicio->solicitarPublicidad($suspendido->id_comercio, 7, false));
        $this->servicio->solicitarPublicidad($activo->id_comercio, 7, false);
        $this->esperarError('ERR-005', fn () => $this->servicio->solicitarPublicidad($activo->id_comercio, 15, false));
        $this->esperarError('ERR-005', fn () => $this->servicio->gestionarPublicidad(1, 'borrar'));
        $this->esperarError('ERR-004', fn () => $this->servicio->gestionarPublicidad(999, 'aprobar'));
    }

    public function test_la_lista_de_pendientes_trae_el_nombre_del_comercio(): void
    {
        $c = $this->crearComercio(['nombre_comercial' => 'Café Volcán']);
        $this->servicio->solicitarPublicidad($c->id_comercio, 15, false);
        $this->servicio->solicitarPublicidad($this->crearComercio()->id_comercio, 7, true);

        $pendientes = $this->servicio->listarPublicidadPendiente()['publicidades'];

        $this->assertCount(1, $pendientes);
        $this->assertSame('Café Volcán', $pendientes[0]['nombre_comercial']);
        $this->assertSame(15, $pendientes[0]['dias_solicitados']);
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
