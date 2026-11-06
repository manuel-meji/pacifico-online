<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\SolicitudComercio;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Support\Facades\Gate;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

class RechazoSolicitudesTest extends TestCase
{
    use PreparaM2;

    public function test_rechazo_actualiza_solicitud_comercio_y_auditoria(): void
    {
        $solicitud = $this->crearSolicitud();
        $this->mock(NucleoServiceInterface::class, function (MockInterface $mock) use ($solicitud): void {
            $mock->shouldReceive('registrarAuditoria')->once()->with(
                'rechazar_solicitud_comercio', 'solicitud_comercio', $solicitud->id_solicitud,
                ['modulo' => 'M2', 'id_comercio' => $solicitud->id_comercio, 'motivo' => 'Datos incompletos'],
            );
        });

        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->postJson($this->ruta($solicitud), ['motivo' => '  Datos incompletos  '])
            ->assertOk()->assertJsonPath('estado', 'Rechazada')
            ->assertJsonPath('motivo_rechazo', 'Datos incompletos');

        $this->assertSame(SolicitudComercio::RECHAZADA, $solicitud->fresh()->estado);
        $this->assertSame('Datos incompletos', $solicitud->fresh()->motivo_rechazo);
        $this->assertSame(Comercio::RECHAZADO, $solicitud->comercio->estado);
        $this->assertSame('Datos incompletos', app(ComerciosService::class)->obtenerComercioActivo($solicitud->id_comercio)['motivo_estado']);
    }

    #[TestWith([[]])]
    #[TestWith([['motivo' => '']])]
    #[TestWith([['motivo' => '   ']])]
    #[TestWith([['motivo' => ['texto']]])]
    public function test_motivo_incorrecto_responde_422_sin_cambios(array $datos): void
    {
        $solicitud = $this->crearSolicitud();
        $this->auditoriaNoEsperada();
        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->postJson($this->ruta($solicitud), $datos)
            ->assertUnprocessable()->assertJsonPath('error.codigo', 'ERR-005');
        $this->assertSame(SolicitudComercio::PENDIENTE, $solicitud->fresh()->estado);
        $this->assertSame(Comercio::PENDIENTE, $solicitud->comercio->estado);
    }

    public function test_servicio_exige_motivo_despues_de_trim(): void
    {
        $solicitud = $this->crearSolicitud();
        $this->auditoriaNoEsperada();
        try {
            app(ComerciosService::class)->rechazarSolicitud($solicitud->id_solicitud, " \t\n ");
            $this->fail('Se aceptó un motivo vacío.');
        } catch (ErrorNucleoException $error) {
            $this->assertSame('ERR-005', $error->getCodigo());
        }
        $this->assertSame(SolicitudComercio::PENDIENTE, $solicitud->fresh()->estado);
    }

    #[TestWith(['Aprobada'])]
    #[TestWith(['Rechazada'])]
    public function test_solicitud_procesada_responde_422_sin_sobrescribir_estado(string $estado): void
    {
        $solicitud = $this->crearSolicitud($estado);
        $this->auditoriaNoEsperada();
        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->postJson($this->ruta($solicitud), ['motivo' => 'Nuevo motivo'])
            ->assertUnprocessable()->assertJsonPath('error.codigo', 'ERR-005');
        $this->assertSame($estado, $solicitud->fresh()->estado);
        $this->assertSame('Motivo anterior', $solicitud->fresh()->motivo_rechazo);
        $this->assertSame(Comercio::PENDIENTE, $solicitud->comercio->estado);
    }

    public function test_solicitud_inexistente_responde_404(): void
    {
        $this->auditoriaNoEsperada();
        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->postJson('/api/m2/v1/solicitudes/999999/rechazar', ['motivo' => 'Datos incompletos'])
            ->assertNotFound()->assertJsonPath('error.codigo', 'ERR-004');
    }

    #[TestWith(['cliente', true, false])]
    #[TestWith(['encargado_comercio', true, false])]
    #[TestWith(['encargado_plataforma', false, false])]
    #[TestWith(['encargado_plataforma', true, true])]
    public function test_permiso_rechazar_exige_plataforma_activa(string $rol, bool $activo, bool $permitido): void
    {
        $this->assertSame($permitido, Gate::forUser($this->usuario($rol, null, $activo))->allows('rechazar', new SolicitudComercio));
    }

    public function test_cliente_no_puede_rechazar(): void
    {
        $solicitud = $this->crearSolicitud();
        $this->auditoriaNoEsperada();
        $this->actingAs($this->usuario('cliente'), 'sanctum')
            ->postJson($this->ruta($solicitud), ['motivo' => 'Datos incompletos'])
            ->assertForbidden()->assertJsonPath('error.codigo', 'ERR-002');
        $this->assertSame(SolicitudComercio::PENDIENTE, $solicitud->fresh()->estado);
    }

    public function test_sin_sesion_responde_401(): void
    {
        $this->postJson('/api/m2/v1/solicitudes/1/rechazar', ['motivo' => 'Datos incompletos'])
            ->assertUnauthorized()->assertJsonPath('error.codigo', 'ERR-001');
    }

    public function test_fallo_de_auditoria_revierte_solicitud_y_comercio(): void
    {
        $solicitud = $this->crearSolicitud();
        $this->mock(NucleoServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('registrarAuditoria')->once()->andThrow(new RuntimeException('Auditoría no disponible'));
        });

        try {
            app(ComerciosService::class)->rechazarSolicitud($solicitud->id_solicitud, 'Datos incompletos');
            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (RuntimeException $error) {
            $this->assertSame('Auditoría no disponible', $error->getMessage());
        }

        $this->assertSame(SolicitudComercio::PENDIENTE, $solicitud->fresh()->estado);
        $this->assertSame('Motivo anterior', $solicitud->fresh()->motivo_rechazo);
        $this->assertSame(Comercio::PENDIENTE, $solicitud->comercio->estado);
    }

    private function crearSolicitud(string $estado = SolicitudComercio::PENDIENTE): SolicitudComercio
    {
        return $this->crearComercio(['estado' => Comercio::PENDIENTE])->solicitudes()->create([
            'nombre_encargado' => 'Prueba', 'correo_encargado' => 'prueba@example.test',
            'estado' => $estado, 'motivo_rechazo' => 'Motivo anterior',
        ]);
    }

    private function auditoriaNoEsperada(): void
    {
        $this->mock(NucleoServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('registrarAuditoria');
        });
    }

    private function ruta(SolicitudComercio $solicitud): string
    {
        return '/api/m2/v1/solicitudes/'.$solicitud->id_solicitud.'/rechazar';
    }
}
