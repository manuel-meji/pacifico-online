<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios;

use App\Contracts\Nucleo\NucleoServiceInterface;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

/** Rutas de destacar comercios: permisos por rol y flujo completo por HTTP. */
class PublicidadHttpTest extends TestCase
{
    use PreparaM2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(NucleoServiceInterface::class, fn ($m) => $m->shouldReceive('registrarAuditoria')->zeroOrMoreTimes());
    }

    public function test_el_encargado_solicita_y_la_plataforma_aprueba(): void
    {
        $c = $this->crearComercio();
        $encargado = $this->usuario('encargado_comercio', $c->id_comercio);
        $plataforma = $this->usuario('encargado_plataforma');

        $this->actingAs($encargado, 'sanctum')->postJson("/api/m2/v1/comercios/{$c->id_comercio}/publicidad", ['dias' => 15])
            ->assertCreated()->assertJsonPath('destacado', false)->assertJsonPath('publicidades.0.estado', 'Pendiente');

        $id = $this->actingAs($plataforma, 'sanctum')->getJson('/api/m2/v1/publicidad')
            ->assertOk()->json('publicidades.0.id_publicidad_comercio');

        $this->actingAs($plataforma, 'sanctum')->postJson("/api/m2/v1/publicidad/{$id}", ['accion' => 'aprobar'])
            ->assertOk()->assertJsonPath('destacado', true);
        $this->getJson('/api/m2/v1/comercios/publico?destacado=true')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_la_plataforma_destaca_directamente(): void
    {
        $c = $this->crearComercio();

        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->postJson("/api/m2/v1/comercios/{$c->id_comercio}/publicidad", ['dias' => 7])
            ->assertCreated()->assertJsonPath('destacado', true)->assertJsonPath('publicidades.0.estado', 'Aprobada');
    }

    public function test_permisos_y_validaciones(): void
    {
        $c = $this->crearComercio();
        $otro = $this->crearComercio();
        $ruta = "/api/m2/v1/comercios/{$c->id_comercio}/publicidad";

        $this->postJson($ruta, ['dias' => 7])->assertUnauthorized();
        $this->actingAs($this->usuario('encargado_comercio', $otro->id_comercio), 'sanctum')
            ->postJson($ruta, ['dias' => 7])->assertForbidden()->assertJsonPath('error.codigo', 'ERR-002');
        $this->actingAs($this->usuario('cliente'), 'sanctum')->getJson('/api/m2/v1/publicidad')->assertForbidden();
        $this->actingAs($this->usuario('encargado_comercio', $c->id_comercio), 'sanctum')
            ->postJson($ruta, ['dias' => 10])->assertStatus(422)->assertJsonPath('error.codigo', 'ERR-005');
        $this->actingAs($this->usuario('encargado_comercio', $c->id_comercio), 'sanctum')
            ->postJson('/api/m2/v1/publicidad/1', ['accion' => 'aprobar'])->assertForbidden();
    }
}
