<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Models\M2_Comercios\DocumentoComercio;
use App\Models\M2_Comercios\SolicitudComercio;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

/** CU-0022: el Encargado de la plataforma abre el archivo original de un documento (RN-002). */
class ArchivoDocumentoHttpTest extends TestCase
{
    use PreparaM2;

    private const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_la_plataforma_recibe_el_archivo_original_descifrado_y_queda_auditado(): void
    {
        $this->mock(NucleoServiceInterface::class, fn ($m) => $m->shouldReceive('registrarAuditoria')
            ->once()->withArgs(fn (string $accion, string $entidad): bool => $accion === 'consultar_documento_comercio' && $entidad === 'documento_comercio'));
        $solicitud = $this->crearSolicitud();
        $documento = $this->crearDocumento($solicitud, self::PDF);

        $respuesta = $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->get($this->ruta($solicitud->id_solicitud, $documento->id_documento_comercio));

        $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertSame(self::PDF, $respuesta->getContent());
        $this->assertNotSame(self::PDF, Storage::disk('local')->get($documento->ruta_cifrada));
    }

    public function test_solo_la_plataforma_puede_abrir_documentos(): void
    {
        $solicitud = $this->crearSolicitud();
        $documento = $this->crearDocumento($solicitud, self::PDF);
        $ruta = $this->ruta($solicitud->id_solicitud, $documento->id_documento_comercio);

        $this->getJson($ruta)->assertUnauthorized()->assertJsonPath('error.codigo', 'ERR-001');
        foreach ([$this->usuario('cliente'), $this->usuario('encargado_comercio', $solicitud->id_comercio)] as $usuario) {
            $this->actingAs($usuario, 'sanctum')->getJson($ruta)
                ->assertForbidden()->assertJsonPath('error.codigo', 'ERR-002');
        }
    }

    public function test_documento_de_otro_comercio_o_inexistente_responde_404(): void
    {
        $this->mock(NucleoServiceInterface::class, fn ($m) => $m->shouldReceive('registrarAuditoria')->never());
        $solicitud = $this->crearSolicitud();
        $ajeno = $this->crearDocumento($this->crearSolicitud(), self::PDF);
        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum');

        foreach ([$ajeno->id_documento_comercio, 999999] as $idDocumento) {
            $this->getJson($this->ruta($solicitud->id_solicitud, $idDocumento))
                ->assertNotFound()->assertJsonPath('error.codigo', 'ERR-004');
        }
    }

    private function crearSolicitud(): SolicitudComercio
    {
        return $this->crearComercio(['estado' => 'Pendiente'])->solicitudes()->create([
            'nombre_encargado' => 'Prueba', 'correo_encargado' => 'prueba@example.test', 'requiere_documentos' => true,
        ]);
    }

    private function crearDocumento(SolicitudComercio $solicitud, string $contenido): DocumentoComercio
    {
        $ruta = 'm2/documentos/'.$solicitud->id_comercio.'/prueba-'.uniqid().'.enc';
        Storage::disk('local')->put($ruta, Crypt::encryptString($contenido));

        return $solicitud->documentos()->create(['tipo' => 'patente', 'ruta_cifrada' => $ruta]);
    }

    private function ruta(int $idSolicitud, int $idDocumento): string
    {
        return '/api/m2/v1/solicitudes/'.$idSolicitud.'/documentos/'.$idDocumento.'/archivo';
    }
}
