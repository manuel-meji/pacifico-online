<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios\Aceptacion;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Events\M2_Comercios\ComercioAprobado;
use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\SolicitudComercio;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;


class AceptacionAprobarComercioTest extends TestCase
{
    use PreparaM2;

    private const SOLICITUD = [
        'nombre_comercial' => 'Soda La Pacífica',
        'cedula_juridica' => '3-101-765432',
        'razon_social' => 'Soda La Pacífica Sociedad Anónima',
        'direccion_fiscal' => 'Liberia, Guanacaste, 200 m norte del parque',
        'inscrito_como_contribuyente' => true,
        'nombre_encargado' => 'María Pérez Solano',
        'correo_encargado' => 'maria.perez@sodalapacifica.cr',
        'telefono_encargado' => '8888-1234',
    ];

    public function test_un_interesado_solicita_y_la_plataforma_aprueba_el_comercio(): void
    {
        Event::fake([ComercioAprobado::class]);
        $cuentasCreadas = [];
        $this->mock(ServicioIdentidadInterface::class, function (MockInterface $mock) use (&$cuentasCreadas): void {
            $mock->shouldReceive('existeCorreo')->andReturn(false);
            $mock->shouldReceive('crearCuenta')->once()
                ->andReturnUsing(function (array $datos, RolUsuario $rol, int $idComercio) use (&$cuentasCreadas) {
                    $cuentasCreadas[] = compact('datos', 'rol', 'idComercio');

                    return $this->usuario('encargado_comercio', $idComercio);
                });
        });
        $this->mock(NucleoServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('registrarAuditoria')->once()
                ->with('aprobar_solicitud_comercio', 'solicitud_comercio', \Mockery::type('int'), \Mockery::type('array'));
        });

       
        $registro = $this->postJson('/api/m2/v1/solicitudes', self::SOLICITUD)
            ->assertOk()
            ->assertJsonPath('estado', SolicitudComercio::PENDIENTE)
            ->assertJsonPath('correo_encargado', self::SOLICITUD['correo_encargado']);
        $idSolicitud = $registro->json('id_solicitud');
        $idComercio = $registro->json('id_comercio');

      
        $this->assertNotContains($idComercio, $this->idsEnListadoPublico());
        $this->getJson("/api/m2/v1/comercios/{$idComercio}/publico")
            ->assertNotFound()->assertJsonPath('error.codigo', 'ERR-004');

       
        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum');
        $pendientes = $this->getJson('/api/m2/v1/solicitudes?estado=Pendiente')->assertOk();
        $fila = collect($pendientes->json('solicitudes'))->firstWhere('id_solicitud', $idSolicitud);
        $this->assertNotNull($fila, 'La solicitud registrada debe aparecer entre las pendientes.');
        $this->assertSame(self::SOLICITUD['nombre_comercial'], $fila['comercio']['nombre_comercial']);
        $this->assertSame(Comercio::PENDIENTE, $fila['comercio']['estado']);

        $this->getJson("/api/m2/v1/solicitudes/{$idSolicitud}")
            ->assertOk()
            ->assertJsonPath('comercio.cedula_juridica', self::SOLICITUD['cedula_juridica'])
            ->assertJsonPath('requiere_documentos', false);

      
        $this->postJson("/api/m2/v1/solicitudes/{$idSolicitud}/aprobar")
            ->assertOk()
            ->assertJsonPath('id_comercio', $idComercio)
            ->assertJsonPath('estado', Comercio::ACTIVO);

       
        $this->assertContains($idComercio, $this->idsEnListadoPublico());
        $this->getJson("/api/m2/v1/comercios/{$idComercio}/publico")->assertOk();
        $this->getJson("/api/m2/v1/comercios/{$idComercio}")
            ->assertOk()
            ->assertJsonPath('estado', Comercio::ACTIVO)
            ->assertJsonPath('motivo_estado', null);

        
        $this->assertNull(collect($this->getJson('/api/m2/v1/solicitudes?estado=Pendiente')->json('solicitudes'))
            ->firstWhere('id_solicitud', $idSolicitud));
        $this->assertNotNull(collect($this->getJson('/api/m2/v1/solicitudes?estado=Aprobada')->json('solicitudes'))
            ->firstWhere('id_solicitud', $idSolicitud));

        
        Event::assertDispatched(ComercioAprobado::class, fn (ComercioAprobado $evento): bool => $evento->id_comercio === $idComercio
            && $evento->estado === Comercio::ACTIVO
            && $evento->nombre_comercial === self::SOLICITUD['nombre_comercial']);
        Event::assertDispatchedTimes(ComercioAprobado::class, 1);

        $this->assertCount(1, $cuentasCreadas);
        $this->assertSame(
            ['nombre' => self::SOLICITUD['nombre_encargado'], 'correo' => self::SOLICITUD['correo_encargado']],
            $cuentasCreadas[0]['datos'],
        );
        $this->assertSame(RolUsuario::EncargadoComercio, $cuentasCreadas[0]['rol']);
        $this->assertSame($idComercio, $cuentasCreadas[0]['idComercio']);
    }

    public function test_un_cliente_no_puede_aprobar_una_solicitud(): void
    {
        Event::fake([ComercioAprobado::class]);
        $identidad = $this->mock(ServicioIdentidadInterface::class);
        $identidad->shouldReceive('existeCorreo')->andReturn(false);
        $identidad->shouldNotReceive('crearCuenta');
        $this->mock(NucleoServiceInterface::class)->shouldNotReceive('registrarAuditoria');

        $registro = $this->postJson('/api/m2/v1/solicitudes', self::SOLICITUD)->assertOk();
        $idSolicitud = $registro->json('id_solicitud');
        $idComercio = $registro->json('id_comercio');

        $this->actingAs($this->usuario('cliente'), 'sanctum')
            ->postJson("/api/m2/v1/solicitudes/{$idSolicitud}/aprobar")
            ->assertForbidden()
            ->assertJsonPath('error.codigo', 'ERR-002');

        $this->assertSame(SolicitudComercio::PENDIENTE, SolicitudComercio::query()->find($idSolicitud)->estado);
        $this->assertSame(Comercio::PENDIENTE, $this->comoSistema(fn () => Comercio::query()->find($idComercio)->estado));
        $this->assertNotContains($idComercio, $this->idsEnListadoPublico());
        Event::assertNotDispatched(ComercioAprobado::class);
    }

    public function test_una_solicitud_ya_aprobada_no_se_aprueba_dos_veces(): void
    {
        Event::fake([ComercioAprobado::class]);
        $identidad = $this->mock(ServicioIdentidadInterface::class);
        $identidad->shouldReceive('existeCorreo')->andReturn(false);
        $identidad->shouldReceive('crearCuenta')->once()
            ->andReturnUsing(fn (array $datos, RolUsuario $rol, int $idComercio) => $this->usuario('encargado_comercio', $idComercio));
        $this->mock(NucleoServiceInterface::class)->shouldReceive('registrarAuditoria')->once();

        $idSolicitud = $this->postJson('/api/m2/v1/solicitudes', self::SOLICITUD)->assertOk()->json('id_solicitud');
        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum');
        $this->postJson("/api/m2/v1/solicitudes/{$idSolicitud}/aprobar")->assertOk();

        $this->postJson("/api/m2/v1/solicitudes/{$idSolicitud}/aprobar")
            ->assertUnprocessable()
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonStructure(['error' => ['detalles' => ['estado']]]);
        Event::assertDispatchedTimes(ComercioAprobado::class, 1);
    }

    /** @return list<int> */
    private function idsEnListadoPublico(): array
    {
        return collect($this->listadoPublico()->assertOk()->json('comercios'))->pluck('id_comercio')->all();
    }

    private function listadoPublico(): TestResponse
    {
        return $this->getJson('/api/m2/v1/comercios/publico?por_pagina=50');
    }
}