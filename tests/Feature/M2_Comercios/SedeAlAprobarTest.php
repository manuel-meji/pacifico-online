<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Events\M2_Comercios\ComercioAprobado;
use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\SedeComercio;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

/**
 * Al aprobar un comercio se le crea una «Sede principal» habilitada con su dirección fiscal,
 * para que M4 tenga dónde registrar su inventario (id_sede = id_ubicacion de M4).
 */
class SedeAlAprobarTest extends TestCase
{
    use PreparaM2;

    private function aprobar(Comercio $comercio): void
    {
        $solicitud = $comercio->solicitudes()->create([
            'nombre_encargado' => 'Persona', 'correo_encargado' => 'persona@example.test', 'requiere_documentos' => false,
        ]);
        $this->mock(ServicioIdentidadInterface::class, function (MockInterface $mock) use ($comercio): void {
            $mock->shouldReceive('crearCuenta')->andReturn($this->usuario('encargado_comercio', $comercio->id_comercio));
        });
        $this->mock(NucleoServiceInterface::class)->shouldReceive('registrarAuditoria');
        Event::fake([ComercioAprobado::class]);

        app(ComerciosService::class)->aprobarSolicitud($solicitud->id_solicitud);
    }

    public function test_al_aprobar_se_crea_la_sede_principal_habilitada_y_m4_la_ve(): void
    {
        $comercio = $this->crearComercio(['estado' => Comercio::PENDIENTE, 'direccion_fiscal' => 'Liberia, Guanacaste']);

        $this->aprobar($comercio);

        $sedes = SedeComercio::query()->where('id_comercio', $comercio->id_comercio)->get();
        $this->assertCount(1, $sedes);
        $this->assertSame('Sede principal', $sedes[0]->nombre);
        $this->assertSame('Liberia, Guanacaste', $sedes[0]->direccion);
        $this->assertTrue($sedes[0]->habilitada);

        $consulta = app(ComerciosService::class)->obtenerSedesComercio($comercio->id_comercio);
        $this->assertSame([$sedes[0]->id_sede], array_column($consulta['sedes'], 'id_sede'));
    }

    public function test_si_el_comercio_ya_tiene_sedes_no_se_duplica(): void
    {
        $comercio = $this->crearComercio(['estado' => Comercio::PENDIENTE]);
        $comercio->sedes()->create(['nombre' => 'Bodega', 'direccion' => 'Cañas', 'habilitada' => true]);

        $this->aprobar($comercio);

        $this->assertSame(['Bodega'], SedeComercio::query()->where('id_comercio', $comercio->id_comercio)->pluck('nombre')->all());
    }
}
