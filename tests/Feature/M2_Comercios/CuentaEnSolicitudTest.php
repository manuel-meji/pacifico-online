<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Events\M2_Comercios\ComercioAprobado;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\SolicitudComercio;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

/**
 * La persona encargada crea su cuenta al enviar la solicitud: ingresa para ver el estado de la
 * revisión, pero no administra el comercio hasta que la plataforma lo apruebe.
 */
class CuentaEnSolicitudTest extends TestCase
{
    use PreparaM2;

    private const DATOS = [
        'nombre_comercial' => 'Comercio con cuenta',
        'cedula_juridica' => '3-101-654321',
        'razon_social' => 'Comercio con cuenta S.A.',
        'direccion_fiscal' => 'Liberia, Guanacaste',
        'inscrito_como_contribuyente' => true,
        'nombre_encargado' => 'Persona Encargada',
        'correo_encargado' => 'clave@example.com',
    ];

    private function conContrasena(string $contrasena, string $confirmacion): array
    {
        return self::DATOS + ['contrasena_encargado' => $contrasena, 'contrasena_encargado_confirmation' => $confirmacion];
    }

    public function test_con_contrasena_crea_la_cuenta_con_la_solicitud_y_solo_pasa_el_cifrado(): void
    {
        $this->mock(NucleoServiceInterface::class);
        $recibido = [];
        $this->mock(ServicioIdentidadInterface::class, function (MockInterface $mock) use (&$recibido): void {
            $mock->shouldReceive('existeCorreo')->andReturn(false);
            $mock->shouldReceive('crearCuenta')->once()->andReturnUsing(function (array $datos, $rol, $idComercio) use (&$recibido) {
                $recibido = compact('datos', 'rol', 'idComercio');

                return $this->usuario('encargado_comercio', $idComercio);
            });
        });

        $respuesta = $this->postJson('/api/m2/v1/solicitudes', $this->conContrasena('MiClave2026', 'MiClave2026'));

        $respuesta->assertOk();
        $this->assertStringNotContainsString('MiClave2026', $respuesta->getContent());
        $solicitud = SolicitudComercio::query()->where('correo_encargado', 'clave@example.com')->firstOrFail();
        $this->assertTrue($solicitud->cuenta_creada);
        $this->assertSame(RolUsuario::EncargadoComercio, $recibido['rol']);
        $this->assertSame($solicitud->id_comercio, $recibido['idComercio']);
        $this->assertArrayNotHasKey('contrasena', $recibido['datos']);
        $this->assertTrue(Hash::check('MiClave2026', $recibido['datos']['contrasena_cifrada']));
        $this->assertSame(Comercio::PENDIENTE, $solicitud->comercio->estado);
    }

    /**
     * Hallazgo 23 (revisión del 22/11): sin contraseña la cuenta también se crea con la solicitud, sin
     * cifrado, para que el núcleo envíe la invitación. Así el correo queda reservado y la persona puede
     * corregir su solicitud si la rechazan.
     */
    public function test_sin_contrasena_crea_la_cuenta_con_la_solicitud_para_que_el_nucleo_invite(): void
    {
        $this->mock(NucleoServiceInterface::class);
        $recibido = [];
        $this->mock(ServicioIdentidadInterface::class, function (MockInterface $mock) use (&$recibido): void {
            $mock->shouldReceive('existeCorreo')->andReturn(false);
            $mock->shouldReceive('crearCuenta')->once()->andReturnUsing(function (array $datos, $rol, $idComercio) use (&$recibido) {
                $recibido = compact('datos', 'rol', 'idComercio');

                return $this->usuario('encargado_comercio', $idComercio);
            });
        });

        $this->postJson('/api/m2/v1/solicitudes', self::DATOS)->assertOk();

        $solicitud = SolicitudComercio::query()->where('correo_encargado', 'clave@example.com')->firstOrFail();
        $this->assertTrue($solicitud->cuenta_creada);
        $this->assertSame(RolUsuario::EncargadoComercio, $recibido['rol']);
        $this->assertSame($solicitud->id_comercio, $recibido['idComercio']);
        $this->assertArrayNotHasKey('contrasena_cifrada', $recibido['datos']);
    }

    public function test_contrasenas_que_no_coinciden_o_cortas_responden_err_005_en_espanol(): void
    {
        $this->mock(NucleoServiceInterface::class);
        $this->mock(ServicioIdentidadInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('existeCorreo')->andReturn(false);
            $mock->shouldNotReceive('crearCuenta');
        });

        $this->postJson('/api/m2/v1/solicitudes', $this->conContrasena('MiClave2026', 'OtraClave2026'))
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonPath('error.detalles.contrasena_encargado.0', 'Las contraseñas no coinciden.');
        $this->postJson('/api/m2/v1/solicitudes', $this->conContrasena('corta', 'corta'))
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.contrasena_encargado.0', 'La contraseña debe tener al menos 8 caracteres.');

        $this->assertDatabaseMissing('solicitudes_comercio', ['correo_encargado' => 'clave@example.com']);
    }

    public function test_si_el_nucleo_rechaza_el_correo_no_queda_nada_creado(): void
    {
        $this->mock(NucleoServiceInterface::class);
        $this->mock(ServicioIdentidadInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('existeCorreo')->andReturn(false);
            $mock->shouldReceive('crearCuenta')->once()
                ->andThrow(ErrorNucleoException::validacionFallida(['correo' => ['El correo ya está registrado.']]));
        });

        $this->postJson('/api/m2/v1/solicitudes', $this->conContrasena('MiClave2026', 'MiClave2026'))
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.correo_encargado.0', 'Ya existe una cuenta con este correo. Usá otro correo para la persona encargada.');

        $this->assertDatabaseMissing('comercios', ['cedula_juridica' => '3-101-654321']);
        $this->assertDatabaseMissing('solicitudes_comercio', ['correo_encargado' => 'clave@example.com']);
    }

    public function test_aprobar_una_solicitud_con_cuenta_no_crea_otra_y_activa_el_comercio(): void
    {
        $comercio = $this->crearComercio(['estado' => Comercio::PENDIENTE]);
        $solicitud = $comercio->solicitudes()->create([
            'nombre_encargado' => 'Persona', 'correo_encargado' => 'persona@example.test', 'requiere_documentos' => false, 'cuenta_creada' => true,
        ]);
        $this->mock(ServicioIdentidadInterface::class)->shouldNotReceive('crearCuenta');
        $this->mock(NucleoServiceInterface::class)->shouldReceive('registrarAuditoria')->once();
        Event::fake([ComercioAprobado::class]);

        $aprobado = app(ComerciosService::class)->aprobarSolicitud($solicitud->id_solicitud);

        $this->assertTrue($aprobado->estaActivo());
        Event::assertDispatchedTimes(ComercioAprobado::class, 1);
    }

    public function test_el_encargado_ve_el_estado_de_su_solicitud_y_otros_roles_no(): void
    {
        $comercio = $this->crearComercio(['estado' => Comercio::RECHAZADO]);
        $solicitud = $comercio->solicitudes()->create([
            'nombre_encargado' => 'Persona', 'correo_encargado' => 'persona@example.test', 'requiere_documentos' => false,
            'cuenta_creada' => true, 'estado' => SolicitudComercio::RECHAZADA, 'motivo_rechazo' => 'Falta la patente.',
        ]);

        $this->actingAs($this->usuario('encargado_comercio', $comercio->id_comercio), 'sanctum')
            ->getJson('/api/m2/v1/mi-solicitud')
            ->assertOk()
            ->assertJsonPath('estado_comercio', Comercio::RECHAZADO)
            ->assertJsonPath('solicitud.id_solicitud', $solicitud->id_solicitud)
            ->assertJsonPath('solicitud.estado', SolicitudComercio::RECHAZADA)
            ->assertJsonPath('solicitud.motivo_rechazo', 'Falta la patente.');

        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->getJson('/api/m2/v1/mi-solicitud')
            ->assertStatus(403)
            ->assertJsonPath('error.codigo', 'ERR-002');
    }

    public function test_un_comercio_sin_aprobar_no_puede_editar_su_micrositio(): void
    {
        $pendiente = $this->crearComercio(['estado' => Comercio::PENDIENTE]);

        $this->actingAs($this->usuario('encargado_comercio', $pendiente->id_comercio), 'sanctum')
            ->putJson("/api/m2/v1/comercios/{$pendiente->id_comercio}/micrositio", ['descripcion' => 'Antes de aprobar'])
            ->assertStatus(403)
            ->assertJsonPath('error.codigo', 'ERR-002');

        $this->assertDatabaseMissing('micrositios', ['id_comercio' => $pendiente->id_comercio, 'descripcion' => 'Antes de aprobar']);
    }
}
