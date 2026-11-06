<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios;

use App\Models\M2_Comercios\SolicitudComercio;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

class RevisionSolicitudesTest extends TestCase
{
    use PreparaM2;

    public function test_listado_filtra_y_pagina_sin_exponer_ruta_cifrada(): void
    {
        $primera = $this->crearSolicitud();
        $segunda = $this->crearSolicitud();
        $this->crearSolicitud(SolicitudComercio::APROBADA);

        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->getJson('/api/m2/v1/solicitudes?estado=Pendiente&pagina=2&por_pagina=1')
            ->assertOk()->assertJsonPath('pagina', 2)->assertJsonPath('por_pagina', 1)
            ->assertJsonPath('total', 2)->assertJsonCount(1, 'solicitudes')
            ->assertJsonPath('solicitudes.0.id_solicitud', $primera->id_solicitud)
            ->assertJsonMissingPath('solicitudes.0.documentos.0.ruta_cifrada');

        $this->getJson('/api/m2/v1/solicitudes?estado=Pendiente&por_pagina=1')
            ->assertOk()->assertJsonPath('solicitudes.0.id_solicitud', $segunda->id_solicitud);
    }

    public function test_listado_sin_filtro_y_pagina_vacia(): void
    {
        $this->crearSolicitud();
        $this->crearSolicitud(SolicitudComercio::APROBADA);

        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->getJson('/api/m2/v1/solicitudes')
            ->assertOk()->assertJsonPath('pagina', 1)->assertJsonPath('por_pagina', 20)
            ->assertJsonPath('total', 2)->assertJsonCount(2, 'solicitudes');

        $this->getJson('/api/m2/v1/solicitudes?pagina=3&por_pagina=1')
            ->assertOk()->assertJsonPath('total', 2)->assertJsonCount(0, 'solicitudes');
    }

    public function test_detalle_incluye_solicitud_comercio_y_documentos_sin_ruta_cifrada(): void
    {
        $solicitud = $this->crearSolicitud();

        $respuesta = $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->getJson('/api/m2/v1/solicitudes/'.$solicitud->id_solicitud)
            ->assertOk()->assertJsonPath('id_solicitud', $solicitud->id_solicitud)
            ->assertJsonPath('nombre_encargado', 'Encargado de prueba')
            ->assertJsonPath('comercio.id_comercio', $solicitud->id_comercio)
            ->assertJsonPath('documentos.0.tipo', 'patente')
            ->assertJsonPath('documentos.0.resultado', 'pendiente')
            ->assertJsonMissingPath('documentos.0.ruta_cifrada');

        $this->assertStringNotContainsString('ruta_cifrada', $respuesta->getContent());
        $this->assertStringNotContainsString('documento-secreto', $respuesta->getContent());
    }

    public function test_solicitud_inexistente_responde_404_err_004(): void
    {
        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->getJson('/api/m2/v1/solicitudes/999999')
            ->assertNotFound()->assertJsonPath('error.codigo', 'ERR-004');
    }

    public function test_ambas_consultas_exigen_sesion(): void
    {
        foreach (['/api/m2/v1/solicitudes', '/api/m2/v1/solicitudes/1'] as $ruta) {
            $this->getJson($ruta)->assertUnauthorized()->assertJsonPath('error.codigo', 'ERR-001');
        }
    }

    #[TestWith(['cliente', true, false])]
    #[TestWith(['encargado_comercio', true, false])]
    #[TestWith(['encargado_plataforma', false, false])]
    #[TestWith(['encargado_plataforma', true, true])]
    public function test_politica_solo_permite_plataforma_activa(string $rol, bool $activo, bool $permitido): void
    {
        $this->assertSame($permitido, Gate::forUser($this->usuario($rol, null, $activo))
            ->allows('revisar', new SolicitudComercio));
    }

    public function test_cliente_recibe_403_en_ambas_consultas(): void
    {
        $this->actingAs($this->usuario('cliente'), 'sanctum');
        foreach (['/api/m2/v1/solicitudes', '/api/m2/v1/solicitudes/999999'] as $ruta) {
            $this->getJson($ruta)->assertForbidden()->assertJsonPath('error.codigo', 'ERR-002');
        }
    }

    #[TestWith(['estado=Activo'])]
    #[TestWith(['estado[]=Pendiente'])]
    #[TestWith(['pagina=0'])]
    #[TestWith(['pagina=texto'])]
    #[TestWith(['por_pagina=0'])]
    #[TestWith(['por_pagina=51'])]
    public function test_filtros_invalidos_responden_422_err_005(string $consulta): void
    {
        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->getJson('/api/m2/v1/solicitudes?'.$consulta)
            ->assertUnprocessable()->assertJsonPath('error.codigo', 'ERR-005');
    }

    private function crearSolicitud(string $estado = SolicitudComercio::PENDIENTE): SolicitudComercio
    {
        $comercio = $this->crearComercio();
        $solicitud = $comercio->solicitudes()->create([
            'nombre_encargado' => 'Encargado de prueba', 'correo_encargado' => 'encargado@example.test', 'estado' => $estado,
        ]);
        $comercio->documentos()->create([
            'tipo' => 'patente', 'ruta_cifrada' => 'documento-secreto', 'resultado' => 'pendiente',
        ]);

        return $solicitud;
    }
}
