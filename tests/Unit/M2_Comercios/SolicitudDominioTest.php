<?php

declare(strict_types=1);

namespace Tests\Unit\M2_Comercios;

use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\DocumentoComercio;
use App\Models\M2_Comercios\SolicitudComercio;
use Illuminate\Support\Carbon;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

class SolicitudDominioTest extends TestCase
{
    use PreparaM2;

    public function test_aprobar_cambia_solicitud_pendiente_a_aprobada_y_la_persiste(): void
    {
        $solicitud = $this->crearSolicitudPendiente();

        $solicitud->aprobar();

        $this->assertSame(SolicitudComercio::APROBADA, $solicitud->estado);
        $this->assertDatabaseHas('solicitudes_comercio', [
            'id_solicitud' => $solicitud->id_solicitud,
            'estado' => SolicitudComercio::APROBADA,
        ]);
    }

    public function test_rechazar_cambia_solicitud_pendiente_a_rechazada_y_persiste_el_motivo(): void
    {
        $solicitud = $this->crearSolicitudPendiente();

        $solicitud->rechazar('Documentación incompleta');

        $this->assertSame(SolicitudComercio::RECHAZADA, $solicitud->estado);
        $this->assertSame('Documentación incompleta', $solicitud->motivo_rechazo);
        $this->assertDatabaseHas('solicitudes_comercio', [
            'id_solicitud' => $solicitud->id_solicitud,
            'estado' => SolicitudComercio::RECHAZADA,
            'motivo_rechazo' => 'Documentación incompleta',
        ]);
    }

    public function test_documento_con_resultado_valido_pero_vencido_no_es_valido(): void
    {
        $documento = new DocumentoComercio([
            'resultado' => 'valido',
            'fecha_vencimiento' => '2026-11-06',
        ]);
        $fecha = Carbon::parse('2026-11-07 00:00:00');

        $resultado = $documento->esValido($fecha);

        $this->assertFalse($resultado);
    }

    private function crearSolicitudPendiente(): SolicitudComercio
    {
        return $this->crearComercio(['estado' => Comercio::PENDIENTE])->solicitudes()->create([
            'nombre_encargado' => 'Encargado Prueba',
            'correo_encargado' => 'encargado@example.test',
            'estado' => SolicitudComercio::PENDIENTE,
        ]);
    }
}
