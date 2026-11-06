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
}