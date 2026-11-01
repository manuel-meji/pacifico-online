<?php

declare(strict_types=1);

namespace Tests\Unit\Nucleo;

use App\Enums\Nucleo\CodigoErrorNucleo;
use App\Services\Nucleo\FormateadorErrores;
use Illuminate\Http\JsonResponse;
use Tests\TestCase;

class FormateadorErroresTest extends TestCase
{
    public function test_formatear_genera_la_estructura_exacta_requerida(): void
    {
        $salida = FormateadorErrores::formatear(
            'ERR-003',
            'El recurso solicitado no existe o no es accesible.',
            null
        );

        $this->assertSame([
            'error' => [
                'codigo' => 'ERR-003',
                'mensaje' => 'El recurso solicitado no existe o no es accesible.',
                'detalles' => null,
            ],
        ], $salida);
    }

    public function test_formatear_incluye_detalles_cuando_se_proporcionan(): void
    {
        $detalles = ['email' => ['El formato del correo es inválido.']];

        $salida = FormateadorErrores::formatear(
            'ERR-005',
            'Revisa los datos marcados (detalle por campo en detalles).',
            $detalles
        );

        $this->assertSame([
            'error' => [
                'codigo' => 'ERR-005',
                'mensaje' => 'Revisa los datos marcados (detalle por campo en detalles).',
                'detalles' => $detalles,
            ],
        ], $salida);
    }

    public function test_respuesta_devuelve_json_response_con_codigo_http_correcto(): void
    {
        $respuesta = FormateadorErrores::respuesta(
            'ERR-004',
            'No se encontró lo que buscas.',
            404
        );

        $this->assertInstanceOf(JsonResponse::class, $respuesta);
        $this->assertSame(404, $respuesta->getStatusCode());
        $this->assertSame([
            'error' => [
                'codigo' => 'ERR-004',
                'mensaje' => 'No se encontró lo que buscas.',
                'detalles' => null,
            ],
        ], $respuesta->getData(true));
    }

    public function test_respuesta_desde_codigo_resuelve_el_enum_automaticamente(): void
    {
        $respuesta = FormateadorErrores::respuestaDesdeCodigo(CodigoErrorNucleo::SesionInvalida);

        $this->assertSame(401, $respuesta->getStatusCode());
        $this->assertSame([
            'error' => [
                'codigo' => 'ERR-001',
                'mensaje' => 'Tu sesión expiró, inicia sesión de nuevo.',
                'detalles' => null,
            ],
        ], $respuesta->getData(true));
    }
}
