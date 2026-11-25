<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Contracts\Nucleo\ServicioIdentidadInterface;
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
}
