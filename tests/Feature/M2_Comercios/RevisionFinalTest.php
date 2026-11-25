<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Contracts\Nucleo\ServicioIdentidadInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

/**
 * Revisión final de M2 (24/11): hallazgos 40 a 47 del documento «Revisión de M2: guía para continuar».
 * Aquí van los que tocan el servidor; los de la interfaz están en RevisionFinal.test.jsx.
 */
class RevisionFinalTest extends TestCase
{
    use PreparaM2;

    private const SOLICITUD = [
        'nombre_comercial' => 'Soda La Pacífica',
        'razon_social' => 'Soda La Pacífica S.A.',
        'direccion_fiscal' => 'Liberia, Guanacaste, 200 m norte del parque',
        'inscrito_como_contribuyente' => true,
        'nombre_encargado' => 'María Pérez Solano',
        'correo_encargado' => 'maria@sodalapacifica.cr',
        'contrasena_encargado' => 'MiClave2026',
        'contrasena_encargado_confirmation' => 'MiClave2026',
    ];

    private function identidadQueCreaCuentas(): void
    {
        $this->mock(ServicioIdentidadInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('existeCorreo')->andReturn(false);
            $mock->shouldReceive('crearCuenta')->andReturnUsing(fn (array $datos, $rol, $idComercio) => $this->usuario('encargado_comercio', $idComercio));
        });
        $this->mock(NucleoServiceInterface::class);
    }

    private function registrar(string $cedula, string $correo = 'maria@sodalapacifica.cr'): TestResponse
    {
        return $this->postJson('/api/m2/v1/solicitudes', ['cedula_juridica' => $cedula, 'correo_encargado' => $correo] + self::SOLICITUD);
    }

    // --- Hallazgo 46: la cédula jurídica empieza con 2, 3, 4 o 5 ---

    public function test_hallazgo_46_acepta_cedulas_juridicas_que_empiezan_con_2_3_4_o_5(): void
    {
        $this->identidadQueCreaCuentas();

        foreach (['2-100-042005', '3-101-765432', '4-000-123456', '5-200-654321'] as $i => $cedula) {
            $this->registrar($cedula, "encargado{$i}@sodalapacifica.cr")->assertOk();
        }

        $this->assertDatabaseCount('comercios', 4);
    }

    public function test_hallazgo_46_rechaza_otros_primeros_digitos_con_un_mensaje_que_dice_cuales_valen(): void
    {
        $this->identidadQueCreaCuentas();

        foreach (['1-101-123456', '6-101-123456', '9-101-123456', '0-101-123456'] as $cedula) {
            $detalles = $this->registrar($cedula)->assertStatus(422)->json('error.detalles');

            $this->assertSame(
                ['La cédula jurídica debe tener el formato X-XXX-XXXXXX y empezar con 2, 3, 4 o 5, por ejemplo 3-101-123456.'],
                $detalles['cedula_juridica'],
                "La cédula {$cedula} no debería aceptarse."
            );
        }

        $this->assertDatabaseCount('comercios', 0);
    }

    // --- Hallazgo 48: el límite de los documentos es el que deja subir PHP (2 MB) ---

    private function registrarConDocumento(UploadedFile $archivo): TestResponse
    {
        return $this->post('/api/m2/v1/solicitudes', ['cedula_juridica' => '3-101-765432', 'documentos' => [
            ['tipo' => 'patente', 'fecha_vencimiento' => now()->addYear()->toDateString(), 'archivo' => $archivo],
        ]] + self::SOLICITUD, ['Accept' => 'application/json']);
    }

    public function test_hallazgo_48_un_documento_de_mas_de_2_mb_se_rechaza_en_espanol(): void
    {
        Storage::fake('local');
        $this->identidadQueCreaCuentas();

        $detalles = $this->registrarConDocumento(UploadedFile::fake()->create('patente.pdf', 3000, 'application/pdf'))
            ->assertStatus(422)->json('error.detalles');

        $this->assertSame(['El documento no puede pesar más de 2 MB.'], $detalles['documentos.0.archivo']);
        $this->assertDatabaseCount('comercios', 0);
    }

    public function test_hallazgo_48_un_documento_de_2_mb_se_acepta(): void
    {
        Storage::fake('local');
        $this->identidadQueCreaCuentas();

        $this->registrarConDocumento(UploadedFile::fake()->create('patente.pdf', 2000, 'application/pdf'))->assertOk();
    }

    public function test_hallazgo_48_si_php_no_deja_subir_el_archivo_el_mensaje_va_en_espanol(): void
    {
        $this->identidadQueCreaCuentas();
        $ruta = tempnam(sys_get_temp_dir(), 'm2');
        file_put_contents($ruta, '%PDF-1.4');
        // Así llega un archivo que pasó de upload_max_filesize: con error y sin contenido válido.
        $rechazado = new UploadedFile($ruta, 'patente.pdf', 'application/pdf', UPLOAD_ERR_INI_SIZE, true);

        $detalles = $this->registrarConDocumento($rechazado)->assertStatus(422)->json('error.detalles');

        $this->assertContains('No se pudo subir el archivo: puede pesar como máximo 2 MB.', $detalles['documentos.0.archivo']);
    }

    // --- Hallazgo 51: la plataforma puede dar una contraseña inicial y no depender del correo ---

    /** @return array<string, mixed> lo que M2 le pasa al núcleo para crear la cuenta */
    private function registrarComoPlataforma(array $extra): array
    {
        $recibido = [];
        $this->mock(NucleoServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('registrarAuditoria'));
        $this->mock(ServicioIdentidadInterface::class, function (MockInterface $mock) use (&$recibido): void {
            $mock->shouldReceive('existeCorreo')->andReturn(false);
            $mock->shouldReceive('crearCuenta')->once()->andReturnUsing(function (array $datos, $rol, $idComercio) use (&$recibido) {
                $recibido = $datos;

                return $this->usuario('encargado_comercio', $idComercio);
            });
        });

        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->postJson('/api/m2/v1/solicitudes', ['cedula_juridica' => '3-101-765432'] + $extra + self::SOLICITUD)
            ->assertOk();

        return $recibido;
    }

    public function test_hallazgo_51_con_contrasena_inicial_la_cuenta_queda_lista_sin_invitacion(): void
    {
        $datos = $this->registrarComoPlataforma([]);

        // Con la contraseña cifrada, el núcleo crea la cuenta lista y no envía la invitación (ServicioIdentidad::crearCuenta).
        $this->assertArrayHasKey('contrasena_cifrada', $datos);
        $this->assertTrue(Hash::check('MiClave2026', $datos['contrasena_cifrada']));
        $this->assertDatabaseHas('solicitudes_comercio', ['correo_encargado' => 'maria@sodalapacifica.cr', 'cuenta_creada' => true]);
    }

    public function test_hallazgo_51_sin_contrasena_sigue_la_invitacion_por_correo(): void
    {
        $datos = $this->registrarComoPlataforma(['contrasena_encargado' => null, 'contrasena_encargado_confirmation' => null]);

        $this->assertArrayNotHasKey('contrasena_cifrada', $datos);
    }
}
