<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios\Aceptacion;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Events\M2_Comercios\ComercioReactivado;
use App\Events\M2_Comercios\ComercioSuspendido;
use App\Models\M2_Comercios\Comercio;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;


class AceptacionSuspenderComercioTest extends TestCase
{
    use PreparaM2;

    private const MOTIVO = 'Incumplimiento de las políticas de devolución de la plataforma';

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([ComercioSuspendido::class, ComercioReactivado::class]);
        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum');
    }

    public function test_la_plataforma_suspende_un_comercio_y_luego_lo_reactiva(): void
    {
        $this->mock(NucleoServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('registrarAuditoria')->once()->ordered()
                ->with('suspender_comercio', 'comercio', \Mockery::type('int'), ['modulo' => 'M2', 'motivo' => self::MOTIVO]);
            $mock->shouldReceive('registrarAuditoria')->once()->ordered()
                ->with('reactivar_comercio', 'comercio', \Mockery::type('int'), ['modulo' => 'M2']);
        });
        $comercio = $this->crearComercio(['nombre_comercial' => 'Café Volcán']);
        $id = $comercio->id_comercio;

        $this->assertContains($id, $this->idsEnListadoPublico());
        $this->getJson("/api/m2/v1/comercios/{$id}/publico")->assertOk();

        $this->postJson("/api/m2/v1/comercios/{$id}/suspender", ['motivo' => self::MOTIVO])
            ->assertOk()
            ->assertJsonPath('id_comercio', $id)
            ->assertJsonPath('estado', Comercio::SUSPENDIDO)
            ->assertJsonPath('motivo_estado', self::MOTIVO);

        $this->getJson("/api/m2/v1/comercios/{$id}/publico")
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'ERR-004');
        $this->assertNotContains($id, $this->idsEnListadoPublico());

        $this->getJson("/api/m2/v1/comercios/{$id}")
            ->assertOk()
            ->assertJsonPath('estado', Comercio::SUSPENDIDO)
            ->assertJsonPath('motivo_estado', self::MOTIVO);
        $suspendidos = collect($this->getJson('/api/m2/v1/comercios?estado=Suspendido')->assertOk()->json('comercios'));
        $this->assertSame([$id], $suspendidos->pluck('id_comercio')->all());

        Event::assertDispatched(ComercioSuspendido::class, fn (ComercioSuspendido $evento): bool => $evento->id_comercio === $id
            && $evento->estado === Comercio::SUSPENDIDO
            && $evento->motivo === self::MOTIVO);
        Event::assertNotDispatched(ComercioReactivado::class);

        $this->postJson("/api/m2/v1/comercios/{$id}/reactivar")
            ->assertOk()
            ->assertJsonPath('estado', Comercio::ACTIVO)
            ->assertJsonPath('motivo_estado', null);

        $this->assertContains($id, $this->idsEnListadoPublico());
        $this->getJson("/api/m2/v1/comercios/{$id}/publico")->assertOk();

        Event::assertDispatched(ComercioReactivado::class, fn (ComercioReactivado $evento): bool => $evento->id_comercio === $id
            && $evento->estado === Comercio::ACTIVO
            && $evento->nombre_comercial === 'Café Volcán');
        $this->assertSame(0, $comercio->suspensiones()->whereNull('fecha_reactivacion')->count());
        $this->assertSame(1, $comercio->suspensiones()->whereNotNull('fecha_reactivacion')->count());
    }

    #[TestWith([[]])]
    #[TestWith([['motivo' => '']])]
    #[TestWith([['motivo' => '    ']])]
    public function test_suspender_sin_motivo_responde_422(array $cuerpo): void
    {
        $this->mock(NucleoServiceInterface::class)->shouldNotReceive('registrarAuditoria');
        $comercio = $this->crearComercio();

        $this->postJson("/api/m2/v1/comercios/{$comercio->id_comercio}/suspender", $cuerpo)
            ->assertUnprocessable()
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonStructure(['error' => ['detalles' => ['motivo']]]);

        $this->assertSame(Comercio::ACTIVO, $comercio->fresh()->estado);
        $this->assertContains($comercio->id_comercio, $this->idsEnListadoPublico());
        Event::assertNotDispatched(ComercioSuspendido::class);
    }

    #[TestWith([Comercio::PENDIENTE])]
    #[TestWith([Comercio::RECHAZADO])]
    #[TestWith([Comercio::SUSPENDIDO])]
    public function test_solo_se_suspende_un_comercio_activo(string $estado): void
    {
        $this->mock(NucleoServiceInterface::class)->shouldNotReceive('registrarAuditoria');
        $comercio = $this->crearComercio(['estado' => $estado]);

        $this->postJson("/api/m2/v1/comercios/{$comercio->id_comercio}/suspender", ['motivo' => self::MOTIVO])
            ->assertUnprocessable()
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonStructure(['error' => ['detalles' => ['estado']]]);

        $this->assertSame($estado, $comercio->fresh()->estado);
        $this->assertSame(0, $comercio->suspensiones()->count());
        Event::assertNotDispatched(ComercioSuspendido::class);
    }

    private function idsEnListadoPublico(): array
    {
        return collect($this->getJson('/api/m2/v1/comercios/publico?por_pagina=50')->assertOk()->json('comercios'))
            ->pluck('id_comercio')->all();
    }
}