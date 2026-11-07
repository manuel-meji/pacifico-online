<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Events\M2_Comercios\ComercioReactivado;
use App\Events\M2_Comercios\ComercioSuspendido;
use App\Models\M2_Comercios\Comercio;
use Illuminate\Support\Facades\Event;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

/** Rutas de administración de comercios (CU-0017 y CU-0018) que usa el panel de la plataforma. */
class ComerciosHttpTest extends TestCase
{
    use PreparaM2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(NucleoServiceInterface::class, fn ($m) => $m->shouldReceive('registrarAuditoria')->zeroOrMoreTimes());
        Event::fake([ComercioSuspendido::class, ComercioReactivado::class]);
    }

    public function test_la_plataforma_lista_comercios_en_cualquier_estado_y_filtra(): void
    {
        $this->crearComercio(['nombre_comercial' => 'A']);
        $this->crearComercio(['nombre_comercial' => 'B', 'estado' => Comercio::SUSPENDIDO]);
        $plataforma = $this->usuario('encargado_plataforma');

        $this->actingAs($plataforma, 'sanctum')->getJson('/api/m2/v1/comercios')
            ->assertOk()->assertJsonPath('total', 2)
            ->assertJsonStructure(['comercios' => [['id_comercio', 'nombre_comercial', 'cedula_juridica', 'razon_social', 'estado', 'motivo_estado']]]);
        $this->actingAs($plataforma, 'sanctum')->getJson('/api/m2/v1/comercios?estado=Suspendido')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('comercios.0.nombre_comercial', 'B');
    }

    public function test_suspender_y_reactivar_por_http(): void
    {
        $c = $this->crearComercio();
        $plataforma = $this->usuario('encargado_plataforma');

        $this->actingAs($plataforma, 'sanctum')->postJson("/api/m2/v1/comercios/{$c->id_comercio}/suspender", [])
            ->assertStatus(422)->assertJsonPath('error.codigo', 'ERR-005');
        $this->actingAs($plataforma, 'sanctum')->postJson("/api/m2/v1/comercios/{$c->id_comercio}/suspender", ['motivo' => 'Revisión'])
            ->assertOk()->assertJsonPath('estado', 'Suspendido')->assertJsonPath('motivo_estado', 'Revisión');
        $this->getJson("/api/m2/v1/comercios/{$c->id_comercio}/publico")->assertNotFound();
        $this->actingAs($plataforma, 'sanctum')->postJson("/api/m2/v1/comercios/{$c->id_comercio}/reactivar")
            ->assertOk()->assertJsonPath('estado', 'Activo');
        $this->getJson("/api/m2/v1/comercios/{$c->id_comercio}/publico")->assertOk();
    }

    public function test_solo_la_plataforma_administra_comercios(): void
    {
        $c = $this->crearComercio();
        $this->postJson("/api/m2/v1/comercios/{$c->id_comercio}/suspender", ['motivo' => 'X'])
            ->assertUnauthorized()->assertJsonPath('error.codigo', 'ERR-001');

        foreach ([$this->usuario('cliente'), $this->usuario('encargado_comercio', $c->id_comercio)] as $usuario) {
            $this->actingAs($usuario, 'sanctum')->postJson("/api/m2/v1/comercios/{$c->id_comercio}/suspender", ['motivo' => 'X'])
                ->assertForbidden()->assertJsonPath('error.codigo', 'ERR-002');
            $this->actingAs($usuario, 'sanctum')->getJson('/api/m2/v1/comercios')->assertForbidden();
        }
        $this->assertSame('Activo', $c->fresh()->estado);
    }
}
