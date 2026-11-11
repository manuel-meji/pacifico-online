<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Events\M2_Comercios\ComercioAprobado;
use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\SolicitudComercio;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

class AprobacionSolicitudTest extends TestCase
{
    use PreparaM2;

    public function test_aprobacion_crea_cuenta_audita_y_publica_despues_del_commit(): void
    {
        $this->freezeTime();
        $solicitud = $this->crearSolicitud(true);
        $solicitud->documentos()->create([
            'tipo' => 'patente', 'ruta_cifrada' => 'secreto', 'resultado' => 'valido', 'fecha_vencimiento' => today(),
        ]);
        $this->simularCuenta($solicitud);
        $this->mock(NucleoServiceInterface::class, function (MockInterface $mock) use ($solicitud): void {
            $mock->shouldReceive('registrarAuditoria')->once()->with(
                'aprobar_solicitud_comercio', 'solicitud_comercio', $solicitud->id_solicitud,
                ['modulo' => 'M2', 'id_comercio' => $solicitud->id_comercio],
            );
        });
        $eventos = [];
        Event::forget(ComercioAprobado::class);
        Event::listen(ComercioAprobado::class, function (ComercioAprobado $evento) use (&$eventos): void {
            $eventos[] = $evento;
        });

        DB::beginTransaction();
        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->postJson($this->ruta($solicitud))->assertOk()->assertJsonPath('estado', 'Activo')
            ->assertJsonMissingPath('documentos');
        $this->assertCount(0, $eventos);
        DB::commit();

        $this->assertSame(SolicitudComercio::APROBADA, $solicitud->fresh()->estado);
        $this->assertSame(Comercio::ACTIVO, $solicitud->comercio->estado);
        $this->assertCount(1, $eventos);
        $evento = $eventos[0];
        $this->assertTrue(Str::isUuid($evento->id_evento));
        $this->assertTrue($evento->fecha_hora->equalTo(now()));
        $this->assertSame($solicitud->id_comercio, $evento->id_comercio);
        $this->assertSame('Activo', $evento->estado);
        $this->assertSame($solicitud->comercio->nombre_comercial, $evento->nombre_comercial);
    }

    public function test_sin_documentos_requeridos_permite_aprobar(): void
    {
        $solicitud = $this->crearSolicitud();
        $this->simularCuenta($solicitud);
        $this->mock(NucleoServiceInterface::class)->shouldReceive('registrarAuditoria')->once();
        Event::fake([ComercioAprobado::class]);
        $comercio = app(ComerciosService::class)->aprobarSolicitud($solicitud->id_solicitud);
        $this->assertTrue($comercio->estaActivo());
        Event::assertDispatchedTimes(ComercioAprobado::class, 1);
    }

    #[TestWith(['Aprobada'])]
    #[TestWith(['Rechazada'])]
    public function test_solicitud_procesada_no_crea_cuenta(string $estado): void
    {
        $solicitud = $this->crearSolicitud();
        $solicitud->update(['estado' => $estado, 'motivo_rechazo' => 'Revisada']);
        $this->exigirRechazoPorRegla($solicitud);
        $this->assertSame($estado, $solicitud->fresh()->estado);
    }

    #[TestWith(['inscrito_como_contribuyente', false])]
    #[TestWith(['cedula_juridica', ''])]
    #[TestWith(['razon_social', '   '])]
    #[TestWith(['direccion_fiscal', ''])]
    public function test_datos_fiscales_incorrectos_impiden_aprobar(string $campo, mixed $valor): void
    {
        $solicitud = $this->crearSolicitud();
        $solicitud->comercio->update([$campo => $valor]);
        $this->exigirRechazoPorRegla($solicitud);
        $this->assertSame(SolicitudComercio::PENDIENTE, $solicitud->fresh()->estado);
    }

    #[TestWith(['ausente', null])]
    #[TestWith(['pendiente', null])]
    #[TestWith(['invalido', null])]
    #[TestWith(['valido', '2000-01-01'])]
    public function test_documentos_incompletos_invalidos_o_vencidos_impiden_aprobar(string $resultado, ?string $vencimiento): void
    {
        $solicitud = $this->crearSolicitud(true);
        if ($resultado !== 'ausente') {
            $solicitud->documentos()->create([
                'tipo' => 'patente', 'ruta_cifrada' => 'secreto', 'resultado' => $resultado, 'fecha_vencimiento' => $vencimiento,
            ]);
        }
        $this->exigirRechazoPorRegla($solicitud);
    }

    public function test_solicitud_inexistente_responde_404(): void
    {
        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->postJson('/api/m2/v1/solicitudes/999999/aprobar')
            ->assertNotFound()->assertJsonPath('error.codigo', 'ERR-004');
    }

    #[TestWith(['cliente', true, false])]
    #[TestWith(['encargado_comercio', true, false])]
    #[TestWith(['encargado_plataforma', false, false])]
    #[TestWith(['encargado_plataforma', true, true])]
    public function test_permiso_aprobar_exige_plataforma_activa(string $rol, bool $activo, bool $permitido): void
    {
        $this->assertSame($permitido, Gate::forUser($this->usuario($rol, null, $activo))->allows('aprobar', new SolicitudComercio));
    }

    public function test_cliente_no_puede_aprobar(): void
    {
        $solicitud = $this->crearSolicitud();
        $this->mock(ServicioIdentidadInterface::class)->shouldNotReceive('crearCuenta');
        $this->actingAs($this->usuario('cliente'), 'sanctum')->postJson($this->ruta($solicitud))
            ->assertForbidden()->assertJsonPath('error.codigo', 'ERR-002');
    }

    public function test_sin_sesion_responde_401(): void
    {
        $this->postJson('/api/m2/v1/solicitudes/1/aprobar')->assertUnauthorized()->assertJsonPath('error.codigo', 'ERR-001');
    }

    #[TestWith(['identidad'])]
    #[TestWith(['auditoria'])]
    public function test_fallo_de_integracion_revierte_estados_y_no_emite_evento(string $proveedor): void
    {
        $solicitud = $this->crearSolicitud();
        Event::fake([ComercioAprobado::class]);
        if ($proveedor === 'identidad') {
            $this->mock(ServicioIdentidadInterface::class)->shouldReceive('crearCuenta')->once()->andThrow(new RuntimeException('Proveedor falló'));
            $this->mock(NucleoServiceInterface::class)->shouldNotReceive('registrarAuditoria');
        } else {
            $this->simularCuenta($solicitud);
            $this->mock(NucleoServiceInterface::class)->shouldReceive('registrarAuditoria')->once()->andThrow(new RuntimeException('Proveedor falló'));
        }
        try {
            app(ComerciosService::class)->aprobarSolicitud($solicitud->id_solicitud);
            $this->fail('Se esperaba el fallo del proveedor.');
        } catch (RuntimeException $error) {
            $this->assertSame('Proveedor falló', $error->getMessage());
        }
        $this->assertSame(SolicitudComercio::PENDIENTE, $solicitud->fresh()->estado);
        $this->assertSame(Comercio::PENDIENTE, $solicitud->comercio->estado);
        Event::assertNotDispatched(ComercioAprobado::class);
    }

    public function test_rollback_exterior_descarta_evento_y_aprobacion(): void
    {
        $solicitud = $this->crearSolicitud();
        $this->simularCuenta($solicitud);
        $this->mock(NucleoServiceInterface::class)->shouldReceive('registrarAuditoria')->once();
        Event::fake([ComercioAprobado::class]);
        DB::beginTransaction();
        app(ComerciosService::class)->aprobarSolicitud($solicitud->id_solicitud);
        Event::assertNotDispatched(ComercioAprobado::class);
        DB::rollBack();
        Event::assertNotDispatched(ComercioAprobado::class);
        $this->assertSame(SolicitudComercio::PENDIENTE, $solicitud->fresh()->estado);
        $this->assertSame(Comercio::PENDIENTE, $solicitud->comercio->estado);
    }

    private function exigirRechazoPorRegla(SolicitudComercio $solicitud): void
    {
        Event::fake([ComercioAprobado::class]);
        $this->mock(ServicioIdentidadInterface::class)->shouldNotReceive('crearCuenta');
        $this->mock(NucleoServiceInterface::class)->shouldNotReceive('registrarAuditoria');
        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')->postJson($this->ruta($solicitud))
            ->assertUnprocessable()->assertJsonPath('error.codigo', 'ERR-005');
        $this->assertSame(Comercio::PENDIENTE, $solicitud->comercio->fresh()->estado);
        Event::assertNotDispatched(ComercioAprobado::class);
    }

    private function simularCuenta(SolicitudComercio $solicitud): void
    {
        $this->mock(ServicioIdentidadInterface::class, function (MockInterface $mock) use ($solicitud): void {
            $mock->shouldReceive('crearCuenta')->once()->with(
                ['nombre' => $solicitud->nombre_encargado, 'correo' => $solicitud->correo_encargado],
                RolUsuario::EncargadoComercio, $solicitud->id_comercio,
            )->andReturn($this->usuario('encargado_comercio', $solicitud->id_comercio));
        });
    }

    private function crearSolicitud(bool $documentos = false): SolicitudComercio
    {
        return $this->crearComercio(['estado' => Comercio::PENDIENTE])->solicitudes()->create([
            'nombre_encargado' => 'Jorge Prueba', 'correo_encargado' => 'jorge@example.test', 'requiere_documentos' => $documentos,
        ]);
    }

    private function ruta(SolicitudComercio $solicitud): string
    {
        return '/api/m2/v1/solicitudes/'.$solicitud->id_solicitud.'/aprobar';
    }
}
