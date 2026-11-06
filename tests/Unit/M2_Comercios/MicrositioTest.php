<?php

declare(strict_types=1);

namespace Tests\Unit\M2_Comercios;

use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

class MicrositioTest extends TestCase
{
    use PreparaM2;

    private ComerciosService $comercios;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comercios = $this->app->make(ComerciosService::class);
    }

    public function test_horario_con_apertura_mayor_o_igual_al_cierre_lanza_err_005(): void
    {
        $comercio = $this->crearComercio();

        $horarios = $this->horariosValidos();

        $horarios[0] = [
            'dia_semana' => 1,
            'cerrado' => false,
            'hora_apertura' => '18:00',
            'hora_cierre' => '18:00',
        ];

        try {
            $this->comercios->guardarHorarios(
                $comercio->id_comercio,
                $horarios
            );

            $this->fail('Se esperaba ERR-005 por un horario inválido.');
        } catch (ErrorNucleoException $e) {
            $this->assertSame('ERR-005', $e->codigo);
        }
    }

    public function test_dia_cerrado_sin_horas_es_aceptado(): void
    {
        $comercio = $this->crearComercio();

        $horarios = $this->horariosValidos();

        $horarios[6] = [
            'dia_semana' => 7,
            'cerrado' => true,
            'hora_apertura' => null,
            'hora_cierre' => null,
        ];

        $resultado = $this->comercios->guardarHorarios(
            $comercio->id_comercio,
            $horarios
        );

        $this->assertCount(7, $resultado);

        $domingo = collect($resultado)
            ->firstWhere('dia_semana', 7);

        $this->assertNotNull($domingo);
        $this->assertTrue((bool) $domingo['cerrado']);
        $this->assertNull($domingo['hora_apertura']);
        $this->assertNull($domingo['hora_cierre']);
    }

    public function test_politica_de_envio_devuelve_las_tarifas_guardadas(): void
    {
        $comercio = $this->crearComercio();

        $this->comercios->guardarPoliticas(
            $comercio->id_comercio,
            [
                [
                    'tipo' => 'envio',
                    'contenido' => 'Envíos dentro de Costa Rica.',
                    'configuracion_envio' => [
                        [
                            'zona' => 'Liberia',
                            'costo' => 2500,
                            'moneda' => 'CRC',
                        ],
                        [
                            'zona' => 'Nicoya',
                            'costo' => 3500,
                            'moneda' => 'CRC',
                        ],
                    ],
                ],
            ]
        );

        $resultado = $this->comercios->obtenerPoliticasEnvio(
            $comercio->id_comercio
        );

        $this->assertSame($comercio->id_comercio, $resultado['id_comercio']);

        $this->assertSame([
            [
                'zona' => 'Liberia',
                'costo' => 2500.0,
                'moneda' => 'CRC',
            ],
            [
                'zona' => 'Nicoya',
                'costo' => 3500.0,
                'moneda' => 'CRC',
            ],
        ], $resultado['tarifas']);
    }

    public function test_logotipo_guardado_aparece_en_consulta_publica(): void
    {
        Storage::fake('public');

        $comercio = $this->crearComercio();

        $archivo = UploadedFile::fake()->image('logo.png');

        $micrositio = $this->comercios->guardarLogotipo(
            $comercio->id_comercio,
            $archivo
        );

        $archivos = Storage::disk('public')->allFiles(
            "m2/comercios/{$comercio->id_comercio}/logotipo"
        );

        $this->assertCount(1, $archivos);

        $publico = $this->comercios->obtenerDatosComercioPublico(
            $comercio->id_comercio
        );

        $this->assertNotNull($micrositio->logotipo);
        $this->assertSame(asset($micrositio->logotipo), $publico['logo_url']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function horariosValidos(): array
    {
        $horarios = [];

        for ($dia = 1; $dia <= 7; $dia++) {
            $horarios[] = [
                'dia_semana' => $dia,
                'cerrado' => false,
                'hora_apertura' => '08:00',
                'hora_cierre' => '17:00',
            ];
        }

        return $horarios;
    }
}