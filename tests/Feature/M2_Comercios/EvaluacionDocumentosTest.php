<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios;

use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M2_Comercios\DocumentoComercio;
use App\Models\M2_Comercios\SolicitudComercio;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

class EvaluacionDocumentosTest extends TestCase
{
    use PreparaM2;

    #[TestWith(['valido', 'Documento verificado'])]
    #[TestWith(['invalido', 'Información ilegible'])]
    #[TestWith(['valido', null])]
    public function test_evaluacion_guarda_resultado_y_observacion_sin_exponer_ruta(string $resultado, ?string $observacion): void
    {
        $solicitud = $this->crearSolicitud();
        $documento = $this->crearDocumento($solicitud);

        $respuesta = $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->postJson($this->ruta($solicitud->id_solicitud, $documento->id_documento_comercio), [
                'resultado' => $resultado, 'observacion' => $observacion,
            ])->assertOk()->assertJsonPath('resultado', $resultado)
            ->assertJsonPath('observacion', $observacion)->assertJsonMissingPath('ruta_cifrada');

        $this->assertStringNotContainsString('secreto-cifrado', $respuesta->getContent());
        $this->assertSame($resultado, $documento->fresh()->resultado);
        $this->assertSame($observacion, $documento->fresh()->observacion);
        $this->assertSame('secreto-cifrado', $documento->fresh()->ruta_cifrada);
    }

    public function test_observacion_omitida_borra_la_anterior(): void
    {
        $solicitud = $this->crearSolicitud();
        $documento = $this->crearDocumento($solicitud);
        $documento->evaluar('invalido', 'Anterior');

        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->postJson($this->ruta($solicitud->id_solicitud, $documento->id_documento_comercio), ['resultado' => 'valido'])
            ->assertOk()->assertJsonPath('observacion', null);
        $this->assertNull($documento->fresh()->observacion);
    }

    public function test_solicitud_inexistente_responde_404_sin_modificar_documento(): void
    {
        $documento = $this->crearDocumento($this->crearSolicitud());
        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->postJson($this->ruta(999999, $documento->id_documento_comercio), ['resultado' => 'valido'])
            ->assertNotFound()->assertJsonPath('error.codigo', 'ERR-004');
        $this->assertSame('pendiente', $documento->fresh()->resultado);
    }

    public function test_documento_ajeno_o_inexistente_responde_404(): void
    {
        $solicitud = $this->crearSolicitud();
        $documento = $this->crearDocumento($this->crearSolicitud());
        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum');
        foreach ([$documento->id_documento_comercio, 999999] as $idDocumento) {
            $this->postJson($this->ruta($solicitud->id_solicitud, $idDocumento), ['resultado' => 'valido'])
                ->assertNotFound()->assertJsonPath('error.codigo', 'ERR-004');
        }
        $this->assertSame('pendiente', $documento->fresh()->resultado);
    }

    #[TestWith(['cliente', true, false])]
    #[TestWith(['encargado_comercio', true, false])]
    #[TestWith(['encargado_plataforma', false, false])]
    #[TestWith(['encargado_plataforma', true, true])]
    public function test_permiso_de_evaluacion_exige_plataforma_activa(string $rol, bool $activo, bool $permitido): void
    {
        $this->assertSame($permitido, Gate::forUser($this->usuario($rol, null, $activo))
            ->allows('evaluarDocumento', new SolicitudComercio));
    }

    public function test_cliente_no_puede_evaluar_y_no_se_modifica_documento(): void
    {
        $solicitud = $this->crearSolicitud();
        $documento = $this->crearDocumento($solicitud);
        $this->actingAs($this->usuario('cliente'), 'sanctum')
            ->postJson($this->ruta($solicitud->id_solicitud, $documento->id_documento_comercio), ['resultado' => 'valido'])
            ->assertForbidden()->assertJsonPath('error.codigo', 'ERR-002');
        $this->assertSame('pendiente', $documento->fresh()->resultado);
    }

    public function test_sin_sesion_responde_401(): void
    {
        $this->postJson($this->ruta(1, 1), ['resultado' => 'valido'])
            ->assertUnauthorized()->assertJsonPath('error.codigo', 'ERR-001');
    }

    #[TestWith([[]])]
    #[TestWith([['resultado' => 'pendiente']])]
    #[TestWith([['resultado' => 'aprobado']])]
    #[TestWith([['resultado' => ['valido']]])]
    #[TestWith([['resultado' => 'valido', 'observacion' => ['texto']]])]
    public function test_entrada_incorrecta_responde_422_sin_modificar_documento(array $datos): void
    {
        $solicitud = $this->crearSolicitud();
        $documento = $this->crearDocumento($solicitud);
        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->postJson($this->ruta($solicitud->id_solicitud, $documento->id_documento_comercio), $datos)
            ->assertUnprocessable()->assertJsonPath('error.codigo', 'ERR-005');
        $this->assertSame('pendiente', $documento->fresh()->resultado);
    }

    public function test_servicio_rechaza_resultado_pendiente(): void
    {
        $solicitud = $this->crearSolicitud();
        $documento = $this->crearDocumento($solicitud);
        try {
            app(ComerciosService::class)->evaluarDocumento($solicitud->id_solicitud, $documento->id_documento_comercio, 'pendiente', null);
            $this->fail('Se aceptó un resultado no permitido.');
        } catch (ErrorNucleoException $error) {
            $this->assertSame('ERR-005', $error->getCodigo());
        }
        $this->assertSame('pendiente', $documento->fresh()->resultado);
    }

    #[TestWith(['valido', null, true])]
    #[TestWith(['valido', '2026-11-04', false])]
    #[TestWith(['valido', '2026-11-05', true])]
    #[TestWith(['valido', '2026-11-06', true])]
    #[TestWith(['invalido', null, false])]
    #[TestWith(['pendiente', null, false])]
    public function test_vigencia_incluye_todo_el_dia_de_vencimiento(string $resultado, ?string $vencimiento, bool $esperado): void
    {
        $fecha = Carbon::parse('2026-11-05 23:59:59.999999');
        $documento = new DocumentoComercio(['resultado' => $resultado, 'fecha_vencimiento' => $vencimiento]);
        $this->assertSame($esperado, $documento->esValido($fecha));
        $this->assertSame('2026-11-05 23:59:59.999999', $fecha->format('Y-m-d H:i:s.u'));
        $this->assertSame($vencimiento, $documento->fecha_vencimiento?->toDateString());
    }

    private function crearSolicitud(): SolicitudComercio
    {
        return $this->crearComercio()->solicitudes()->create([
            'nombre_encargado' => 'Prueba', 'correo_encargado' => 'prueba@example.test',
        ]);
    }

    private function crearDocumento(SolicitudComercio $solicitud): DocumentoComercio
    {
        return $solicitud->documentos()->create([
            'tipo' => 'patente', 'ruta_cifrada' => 'secreto-cifrado',
        ]);
    }

    private function ruta(int $idSolicitud, int $idDocumento): string
    {
        return '/api/m2/v1/solicitudes/'.$idSolicitud.'/documentos/'.$idDocumento.'/evaluar';
    }
}
