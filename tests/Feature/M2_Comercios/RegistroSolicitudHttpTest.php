<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\SolicitudComercio;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

class RegistroSolicitudHttpTest extends TestCase
{
    use PreparaM2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(ServicioIdentidadInterface::class);
        $this->mock(NucleoServiceInterface::class);
    }

    public function test_interesado_puede_registrar_solicitud_sin_sesion(): void
    {
        $respuesta = $this->postJson('/api/m2/v1/solicitudes', [
            'nombre_comercial' => 'Comercio HTTP',
            'cedula_juridica' => '3-101-123456',
            'razon_social' => 'Comercio HTTP S.A.',
            'direccion_fiscal' => 'Liberia, Guanacaste',
            'inscrito_como_contribuyente' => true,
            'nombre_encargado' => 'Persona Encargada',
            'correo_encargado' => 'encargado@example.com',
            'telefono_encargado' => '88888888',
        ]);

        $respuesta
            ->assertOk()
            ->assertJsonPath('estado', SolicitudComercio::PENDIENTE)
            ->assertJsonPath('correo_encargado', 'encargado@example.com');

        $this->assertDatabaseHas('comercios', [
            'cedula_juridica' => '3-101-123456',
            'estado' => Comercio::PENDIENTE,
        ]);

        $this->assertDatabaseHas('solicitudes_comercio', [
            'correo_encargado' => 'encargado@example.com',
            'estado' => SolicitudComercio::PENDIENTE,
        ]);
    }

    public function test_registrar_con_cedula_repetida_responde_err_005(): void
    {
        $this->crearComercio([
            'cedula_juridica' => '3-101-123456',
        ]);

        $respuesta = $this->postJson('/api/m2/v1/solicitudes', [
            'nombre_comercial' => 'Comercio Repetido',
            'cedula_juridica' => '3-101-123456',
            'razon_social' => 'Comercio Repetido S.A.',
            'direccion_fiscal' => 'Liberia, Guanacaste',
            'inscrito_como_contribuyente' => true,
            'nombre_encargado' => 'Otra Persona',
            'correo_encargado' => 'otra@example.com',
        ]);

        $respuesta
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005');
    }

    public function test_interesado_puede_corregir_solicitud_rechazada_sin_sesion(): void
{
    $comercio = $this->crearComercio([
        'cedula_juridica' => '3-101-654321',
        'nombre_comercial' => 'Comercio Original',
        'estado' => Comercio::RECHAZADO,
    ]);

    $solicitud = SolicitudComercio::create([
        'id_comercio' => $comercio->id_comercio,
        'nombre_encargado' => 'Persona Encargada',
        'correo_encargado' => 'encargado@example.com',
        'telefono_encargado' => '88888888',
        'estado' => SolicitudComercio::RECHAZADA,
        'requiere_documentos' => false,
        'motivo_rechazo' => 'Debe corregir información.',
    ]);

    $respuesta = $this->putJson(
        "/api/m2/v1/solicitudes/{$solicitud->id_solicitud}",
        [
            'cedula_juridica' => '3-101-654321',
            'correo_encargado' => 'encargado@example.com',
            'nombre_comercial' => 'Comercio Corregido',
            'direccion_fiscal' => 'Liberia centro',
        ]
    );

    $respuesta
        ->assertOk()
        ->assertJsonPath('estado', SolicitudComercio::PENDIENTE);

    $this->assertDatabaseHas('solicitudes_comercio', [
        'id_solicitud' => $solicitud->id_solicitud,
        'estado' => SolicitudComercio::PENDIENTE,
        'motivo_rechazo' => null,
    ]);

    $this->assertDatabaseHas('comercios', [
        'id_comercio' => $comercio->id_comercio,
        'nombre_comercial' => 'Comercio Corregido',
        'estado' => Comercio::PENDIENTE,
    ]);
}

public function test_corregir_con_datos_que_no_coinciden_responde_err_004(): void
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
        'motivo_rechazo' => 'Debe corregir información.',
    ]);

    $respuesta = $this->putJson(
        "/api/m2/v1/solicitudes/{$solicitud->id_solicitud}",
        [
            'cedula_juridica' => '3-101-654321',
            'correo_encargado' => 'incorrecto@example.com',
        ]
    );

    $respuesta
        ->assertStatus(404)
        ->assertJsonPath('error.codigo', 'ERR-004');
}

}