<?php

declare(strict_types=1);

namespace Tests\Unit\M2_Comercios;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\DocumentoComercio;
use App\Models\M2_Comercios\SolicitudComercio;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

class RegistroSolicitudTest extends TestCase
{
    use PreparaM2;

    private function servicio(bool $correoConCuenta = false): ComerciosService
    {
        $identidad = $this->mock(ServicioIdentidadInterface::class);
        $identidad->shouldReceive('existeCorreo')->andReturn($correoConCuenta);

        return new ComerciosService(
            $identidad,
            $this->mock(NucleoServiceInterface::class),
        );
    }

    public function test_registrar_crea_comercio_solicitud_y_documento_cifrado(): void
    {
        Storage::fake('local');

        $archivo = UploadedFile::fake()->create(
            'personeria.pdf',
            10,
            'application/pdf'
        );

        $contenidoOriginal = $archivo->getContent();

        $solicitud = $this->servicio()->registrarSolicitud([
            'nombre_comercial' => 'Comercio de Prueba',
            'cedula_juridica' => '3-101-123456',
            'razon_social' => 'Comercio de Prueba S.A.',
            'direccion_fiscal' => 'Liberia, Guanacaste',
            'inscrito_como_contribuyente' => true,
            'nombre_encargado' => 'Persona Encargada',
            'correo_encargado' => 'encargado@example.com',
            'telefono_encargado' => '88888888',
        ], [
            [
                'archivo' => $archivo,
                'tipo' => 'personeria_juridica',
                'fecha_vencimiento' => now()->addYear()->toDateString(),
            ],
        ]);

        $comercio = Comercio::query()->findOrFail($solicitud->id_comercio);
        $documento = DocumentoComercio::query()
            ->where('id_comercio', $comercio->id_comercio)
            ->firstOrFail();

        $this->assertSame(Comercio::PENDIENTE, $comercio->estado);
        $this->assertSame(SolicitudComercio::PENDIENTE, $solicitud->estado);
        $this->assertTrue($solicitud->requiere_documentos);
        $this->assertSame('pendiente', $documento->resultado);

        Storage::disk('local')->assertExists($documento->ruta_cifrada);

        $contenidoCifrado = Storage::disk('local')
            ->get($documento->ruta_cifrada);

        $this->assertNotSame($contenidoOriginal, $contenidoCifrado);
        $this->assertSame(
            $contenidoOriginal,
            Crypt::decryptString($contenidoCifrado)
        );
    }

    public function test_registrar_no_permite_una_cedula_juridica_repetida(): void
    {
        $this->crearComercio([
            'cedula_juridica' => '3-101-123456',
        ]);

        $this->expectException(ErrorNucleoException::class);

        $this->servicio()->registrarSolicitud([
            'nombre_comercial' => 'Otro Comercio',
            'cedula_juridica' => '3-101-123456',
            'razon_social' => 'Otro Comercio S.A.',
            'direccion_fiscal' => 'Liberia, Guanacaste',
            'inscrito_como_contribuyente' => true,
            'nombre_encargado' => 'Otra Persona',
            'correo_encargado' => 'otra@example.com',
            'telefono_encargado' => null,
        ], []);
    }

    public function test_registrar_no_permite_un_correo_de_encargado_con_cuenta(): void
    {
        try {
            $this->servicio(correoConCuenta: true)->registrarSolicitud([
                'nombre_comercial' => 'Comercio Nuevo',
                'cedula_juridica' => '3-101-654321',
                'razon_social' => 'Comercio Nuevo S.A.',
                'direccion_fiscal' => 'Liberia, Guanacaste',
                'inscrito_como_contribuyente' => true,
                'nombre_encargado' => 'Persona con Cuenta',
                'correo_encargado' => 'con.cuenta@example.com',
                'telefono_encargado' => null,
            ], []);
            $this->fail('Se esperaba ERR-005 por el correo del encargado.');
        } catch (ErrorNucleoException $error) {
            $this->assertSame('ERR-005', $error->getCodigo());
            $this->assertSame(['correo_encargado'], array_keys($error->getDetalles()));
        }

        $this->assertSame(0, Comercio::query()->where('cedula_juridica', '3-101-654321')->count());
        $this->assertSame(0, SolicitudComercio::query()->where('correo_encargado', 'con.cuenta@example.com')->count());
    }

    public function test_registrar_con_cedula_repetida_y_correo_con_cuenta_informa_ambos(): void
    {
        $this->crearComercio([
            'cedula_juridica' => '3-101-123456',
        ]);

        try {
            $this->servicio(correoConCuenta: true)->registrarSolicitud([
                'nombre_comercial' => 'Otro Comercio',
                'cedula_juridica' => '3-101-123456',
                'razon_social' => 'Otro Comercio S.A.',
                'direccion_fiscal' => 'Liberia, Guanacaste',
                'inscrito_como_contribuyente' => true,
                'nombre_encargado' => 'Persona con Cuenta',
                'correo_encargado' => 'con.cuenta@example.com',
                'telefono_encargado' => null,
            ], []);
            $this->fail('Se esperaba ERR-005 por la cédula y el correo.');
        } catch (ErrorNucleoException $error) {
            $this->assertSame('ERR-005', $error->getCodigo());
            $this->assertEqualsCanonicalizing(['cedula_juridica', 'correo_encargado'], array_keys($error->getDetalles()));
        }

        $this->assertSame(0, SolicitudComercio::query()->where('correo_encargado', 'con.cuenta@example.com')->count());
    }

    public function test_corregir_rechazada_la_devuelve_a_pendiente_y_limpia_motivo(): void
    {
        $comercio = $this->crearComercio([
            'cedula_juridica' => '3-101-654321',
            'estado' => Comercio::RECHAZADO,
        ]);

        $solicitud = SolicitudComercio::create([
            'id_comercio' => $comercio->id_comercio,
            'nombre_encargado' => 'Persona Encargada',
            'correo_encargado' => 'encargado@example.com',
            'telefono_encargado' => '88888888',
            'estado' => SolicitudComercio::RECHAZADA,
            'requiere_documentos' => false,
            'motivo_rechazo' => 'Debe corregir la información.',
        ]);

        $resultado = $this->servicio()->corregirSolicitud(
            $solicitud->id_solicitud,
            [
                'cedula_juridica' => '3-101-654321',
                'correo_encargado' => 'encargado@example.com',
                'nombre_comercial' => 'Comercio Corregido',
                'direccion_fiscal' => 'Liberia centro',
            ]
        );

        $resultado->refresh();
        $comercio->refresh();

        $this->assertSame(
            SolicitudComercio::PENDIENTE,
            $resultado->estado
        );

        $this->assertNull($resultado->motivo_rechazo);
        $this->assertNotNull($resultado->fecha_reenvio);

        $this->assertSame(Comercio::PENDIENTE, $comercio->estado);
        $this->assertSame(
            'Comercio Corregido',
            $comercio->nombre_comercial
        );
        $this->assertSame(
            'Liberia centro',
            $comercio->direccion_fiscal
        );
    }

    public function test_corregir_con_correo_que_no_coincide_responde_como_no_encontrada(): void
    {
        $comercio = $this->crearComercio([
            'cedula_juridica' => '3-101-654321',
            'estado' => Comercio::RECHAZADO,
        ]);

        $solicitud = SolicitudComercio::create([
            'id_comercio' => $comercio->id_comercio,
            'nombre_encargado' => 'Persona Encargada',
            'correo_encargado' => 'correcto@example.com',
            'telefono_encargado' => null,
            'estado' => SolicitudComercio::RECHAZADA,
            'requiere_documentos' => false,
            'motivo_rechazo' => 'Información incorrecta.',
        ]);

        $this->expectException(ErrorNucleoException::class);

        $this->servicio()->corregirSolicitud(
            $solicitud->id_solicitud,
            [
                'cedula_juridica' => '3-101-654321',
                'correo_encargado' => 'incorrecto@example.com',
            ]
        );
    }
}