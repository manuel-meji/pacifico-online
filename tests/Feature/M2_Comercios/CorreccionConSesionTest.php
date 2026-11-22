<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios;

use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\DocumentoComercio;
use App\Models\M2_Comercios\SolicitudComercio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

/**
 * DEF-34: la persona encargada corrige su solicitud rechazada desde su sesión. El número y la cédula
 * no se pueden cambiar y el error dice cuál no coincide; el correo sí se puede corregir (también en
 * su cuenta) y los documentos rechazados o vencidos se reemplazan.
 */
class CorreccionConSesionTest extends TestCase
{
    use PreparaM2;

    private const RUTA = '/api/m2/v1/mi-solicitud/correccion';

    private Comercio $comercio;

    private SolicitudComercio $solicitud;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->comercio = $this->crearComercio(['estado' => Comercio::RECHAZADO, 'cedula_juridica' => '3-101-777777']);
        $this->solicitud = $this->comercio->solicitudes()->create([
            'nombre_encargado' => 'Persona', 'correo_encargado' => 'persona@example.test', 'requiere_documentos' => false,
            'cuenta_creada' => true, 'estado' => SolicitudComercio::RECHAZADA, 'motivo_rechazo' => 'Falta la razón social.',
        ]);
    }

    private function comoEncargado(): self
    {
        return $this->actingAs($this->usuario('encargado_comercio', $this->comercio->id_comercio), 'sanctum');
    }

    private function documento(array $datos): DocumentoComercio
    {
        Storage::disk('local')->put('m2/documentos/viejo.enc', 'cifrado');

        return DocumentoComercio::create($datos + [
            'id_comercio' => $this->comercio->id_comercio, 'tipo' => 'patente', 'ruta_cifrada' => 'm2/documentos/viejo.enc',
            'fecha_vencimiento' => now()->addYear()->toDateString(), 'resultado' => 'valido', 'observacion' => null,
        ]);
    }

    public function test_corrige_su_solicitud_y_vuelve_a_quedar_pendiente(): void
    {
        $this->mock(ServicioIdentidadInterface::class)->shouldNotReceive('actualizarCorreo');

        $this->comoEncargado()->postJson(self::RUTA, [
            'id_solicitud' => $this->solicitud->id_solicitud,
            'cedula_juridica' => '3-101-777777',
            'correo_encargado' => 'PERSONA@example.test',
            'razon_social' => 'Razón corregida S.A.',
        ])->assertOk()->assertJsonPath('estado', SolicitudComercio::PENDIENTE);

        $this->assertSame('Razón corregida S.A.', $this->comercio->fresh()->razon_social);
        $this->assertSame(Comercio::PENDIENTE, $this->comercio->fresh()->estado);
        $this->assertNull($this->solicitud->fresh()->motivo_rechazo);
    }

    public function test_si_el_numero_de_solicitud_no_coincide_el_error_lo_dice(): void
    {
        $this->comoEncargado()->postJson(self::RUTA, ['id_solicitud' => $this->solicitud->id_solicitud + 50])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.id_solicitud.0', "El número de solicitud no corresponde a tu solicitud (#{$this->solicitud->id_solicitud}).");

        $this->assertSame(SolicitudComercio::RECHAZADA, $this->solicitud->fresh()->estado);
    }

    public function test_si_la_cedula_no_coincide_el_error_lo_dice_y_no_se_cambia(): void
    {
        $this->comoEncargado()->postJson(self::RUTA, ['cedula_juridica' => '3-101-123456'])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.cedula_juridica.0', 'La cédula jurídica no coincide con la registrada en tu solicitud; no se puede cambiar al corregirla.');

        $this->assertSame('3-101-777777', $this->comercio->fresh()->cedula_juridica);
    }

    public function test_el_correo_se_puede_corregir_y_se_actualiza_en_la_cuenta(): void
    {
        $this->mock(ServicioIdentidadInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('existeCorreo')->with('nuevo@example.test')->andReturn(false);
            $mock->shouldReceive('actualizarCorreo')->once()->with(1, 'nuevo@example.test');
        });

        $this->comoEncargado()->postJson(self::RUTA, ['correo_encargado' => 'nuevo@example.test'])
            ->assertOk()->assertJsonPath('correo_encargado', 'nuevo@example.test');

        $this->assertSame('nuevo@example.test', $this->solicitud->fresh()->correo_encargado);
    }

    public function test_no_acepta_un_correo_que_ya_usa_otra_cuenta(): void
    {
        $this->mock(ServicioIdentidadInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('existeCorreo')->andReturn(true);
            $mock->shouldNotReceive('actualizarCorreo');
        });

        $this->comoEncargado()->postJson(self::RUTA, ['correo_encargado' => 'ocupado@example.test'])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.correo_encargado.0', 'Ya existe una cuenta con este correo. Usá otro correo para la persona encargada.');

        $this->assertSame('persona@example.test', $this->solicitud->fresh()->correo_encargado);
    }

    public function test_si_el_nucleo_rechaza_el_correo_no_se_guarda_la_correccion(): void
    {
        $this->mock(ServicioIdentidadInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('existeCorreo')->andReturn(false);
            $mock->shouldReceive('actualizarCorreo')->andThrow(ErrorNucleoException::validacionFallida(['correo' => ['El correo ya está registrado.']]));
        });

        $this->comoEncargado()->postJson(self::RUTA, ['correo_encargado' => 'carrera@example.test', 'razon_social' => 'No debe quedar'])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.correo_encargado.0', 'Ya existe una cuenta con este correo. Usá otro correo para la persona encargada.');

        $this->assertNotSame('No debe quedar', $this->comercio->fresh()->razon_social);
        $this->assertSame(SolicitudComercio::RECHAZADA, $this->solicitud->fresh()->estado);
    }

    public function test_con_un_documento_rechazado_exige_subir_uno_nuevo(): void
    {
        $this->documento(['resultado' => 'invalido', 'observacion' => 'Ilegible']);

        $this->comoEncargado()->postJson(self::RUTA, ['razon_social' => 'Sin documento'])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.documentos.0', 'Tenés documentos rechazados o vencidos: subí uno nuevo para reemplazarlos antes de enviar la corrección.');
    }

    public function test_reemplaza_los_documentos_rechazados_y_conserva_los_validos(): void
    {
        $rechazado = $this->documento(['resultado' => 'invalido', 'observacion' => 'Ilegible']);
        $valido = $this->documento(['tipo' => 'cedula_juridica', 'ruta_cifrada' => 'm2/documentos/valido.enc']);

        $this->comoEncargado()->post(self::RUTA, [
            'documentos' => [[
                'tipo' => 'patente',
                'fecha_vencimiento' => now()->addYear()->toDateString(),
                'archivo' => UploadedFile::fake()->create('patente.pdf', 100, 'application/pdf'),
            ]],
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('requiere_documentos', true);

        $this->assertModelMissing($rechazado);
        $this->assertModelExists($valido);
        Storage::disk('local')->assertMissing('m2/documentos/viejo.enc');
        $nuevo = DocumentoComercio::query()->where('id_comercio', $this->comercio->id_comercio)->where('resultado', 'pendiente')->sole();
        $this->assertSame('patente', $nuevo->tipo);
        Storage::disk('local')->assertExists($nuevo->ruta_cifrada);
    }

    public function test_un_documento_vencido_tambien_debe_reemplazarse(): void
    {
        $vencido = $this->documento(['fecha_vencimiento' => now()->subDay()->toDateString()]);

        $this->comoEncargado()->getJson('/api/m2/v1/mi-solicitud')
            ->assertOk()
            ->assertJsonPath('documentos.0.id_documento_comercio', $vencido->id_documento_comercio)
            ->assertJsonPath('documentos.0.debe_reemplazarse', true);
    }

    public function test_mi_solicitud_trae_los_datos_para_mostrar_la_correccion_ya_llena(): void
    {
        $this->comoEncargado()->getJson('/api/m2/v1/mi-solicitud')
            ->assertOk()
            ->assertJsonPath('comercio.cedula_juridica', '3-101-777777')
            ->assertJsonPath('solicitud.correo_encargado', 'persona@example.test')
            ->assertJsonPath('solicitud.nombre_encargado', 'Persona');
    }

    public function test_no_corrige_una_solicitud_que_no_esta_rechazada(): void
    {
        $this->solicitud->update(['estado' => SolicitudComercio::PENDIENTE]);

        $this->comoEncargado()->postJson(self::RUTA, ['razon_social' => 'Otra'])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.estado.0', 'Solo se puede corregir una solicitud rechazada.');
    }

    public function test_solo_la_persona_encargada_puede_usarla(): void
    {
        $this->postJson(self::RUTA, ['razon_social' => 'Sin sesión'])->assertStatus(401);

        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->postJson(self::RUTA, ['razon_social' => 'Otro rol'])
            ->assertStatus(403)
            ->assertJsonPath('error.codigo', 'ERR-002');
    }
}
