<?php

namespace Tests\Feature\M4_Inventario\Contrato;

use Illuminate\Testing\TestResponse;
use Symfony\Component\Yaml\Yaml;

/**
 * Lee el contrato publicado de M4 (contracts/m4-inventario/v1/openapi_M4_v1.3.0.yaml)
 * y compara una respuesta real con el esquema declarado: campos requeridos, tipos,
 * nulos, enumeraciones, mínimos y formatos uuid y date-time. Las propiedades extra no
 * fallan (OpenAPI 3.0 las permite), pero un campo publicado con otro tipo o un
 * requerido que falta sí: eso es una desviación del contrato.
 */
trait ValidaContratoOpenApiM4
{
    public const CONTRATO_M4 = 'contracts/m4-inventario/v1/openapi_M4_v1.3.0.yaml';

    /** @var array<string, mixed>|null */
    private static ?array $contratoM4 = null;

    /** @return array<string, mixed> */
    protected function contratoM4(): array
    {
        return self::$contratoM4 ??= Yaml::parseFile(base_path(self::CONTRATO_M4));
    }

    /** Esquema de la respuesta 2xx publicada para una ruta y un método. */
    protected function esquemaRespuesta(string $metodo, string $ruta, int $estado): array
    {
        $operacion = $this->contratoM4()['paths'][$ruta][strtolower($metodo)] ?? null;
        $this->assertNotNull($operacion, "El contrato no publica {$metodo} {$ruta}.");
        $respuesta = $operacion['responses'][(string) $estado] ?? null;
        $this->assertNotNull($respuesta, "El contrato no publica la respuesta {$estado} de {$metodo} {$ruta}.");

        return $respuesta['content']['application/json']['schema'];
    }

    /** La respuesta HTTP cumple el estado y el esquema publicados para la operación. */
    protected function assertCumpleContrato(TestResponse $respuesta, string $metodo, string $ruta, int $estado): array
    {
        $respuesta->assertStatus($estado);
        $cuerpo = $respuesta->json();
        $this->assertEsquema($this->esquemaRespuesta($metodo, $ruta, $estado), $cuerpo, "{$metodo} {$ruta}");

        return $cuerpo;
    }

    /**
     * Error con el formato estándar del núcleo, un estado HTTP publicado para la operación
     * y uno de los códigos esperados.
     *
     * @param  list<string>  $codigos
     */
    protected function assertErrorContrato(TestResponse $respuesta, string $metodo, string $ruta, int $estado, array $codigos): void
    {
        $respuesta->assertStatus($estado);
        $operacion = $this->contratoM4()['paths'][$ruta][strtolower($metodo)];
        $this->assertArrayHasKey((string) $estado, $operacion['responses'], "El contrato no publica {$estado} para {$metodo} {$ruta}.");
        $this->assertEsquema(['$ref' => '#/components/schemas/ErrorEstandar'], $respuesta->json(), "{$metodo} {$ruta} ({$estado})");
        $this->assertContains($respuesta->json('error.codigo'), $codigos, "{$metodo} {$ruta}: código de error no publicado.");
    }

    /** La carga útil de un evento cumple su esquema Evento{Nombre}. */
    protected function assertEventoCumpleContrato(string $nombre, array $carga): void
    {
        $this->assertEsquema(['$ref' => "#/components/schemas/Evento{$nombre}"], $carga, "evento {$nombre}");
    }

    protected function assertEsquema(array $esquema, mixed $valor, string $ruta): void
    {
        if (isset($esquema['$ref'])) {
            $nombre = substr($esquema['$ref'], strlen('#/components/schemas/'));
            $resuelto = $this->contratoM4()['components']['schemas'][$nombre] ?? null;
            $this->assertNotNull($resuelto, "{$ruta}: el contrato no define {$nombre}.");
            $this->assertEsquema($resuelto, $valor, $ruta);

            return;
        }

        if ($valor === null) {
            $this->assertTrue(($esquema['nullable'] ?? false) || ! isset($esquema['type']), "{$ruta}: llegó null y el contrato no lo permite.");

            return;
        }

        match ($esquema['type'] ?? null) {
            'object' => $this->assertObjeto($esquema, $valor, $ruta),
            'array' => $this->assertLista($esquema, $valor, $ruta),
            'integer' => $this->assertTrue(is_int($valor), "{$ruta}: se esperaba entero y llegó ".get_debug_type($valor).'.'),
            'number' => $this->assertTrue(is_int($valor) || is_float($valor), "{$ruta}: se esperaba número y llegó ".get_debug_type($valor).'.'),
            'boolean' => $this->assertIsBool($valor, "{$ruta}: se esperaba booleano."),
            'string' => $this->assertTexto($esquema, $valor, $ruta),
            default => null,
        };

        if (isset($esquema['enum'])) {
            $this->assertContains($valor, $esquema['enum'], "{$ruta}: valor fuera de la enumeración publicada.");
        }

        if (isset($esquema['minimum']) && is_numeric($valor)) {
            $this->assertGreaterThanOrEqual($esquema['minimum'], $valor, "{$ruta}: menor que el mínimo publicado.");
        }
    }

    private function assertObjeto(array $esquema, mixed $valor, string $ruta): void
    {
        $this->assertIsArray($valor, "{$ruta}: se esperaba un objeto.");

        foreach ($esquema['required'] ?? [] as $campo) {
            $this->assertArrayHasKey($campo, $valor, "{$ruta}: falta el campo requerido {$campo}.");
        }

        foreach ($esquema['properties'] ?? [] as $campo => $propiedad) {
            if (array_key_exists($campo, $valor)) {
                $this->assertEsquema($propiedad, $valor[$campo], "{$ruta}.{$campo}");
            }
        }
    }

    private function assertLista(array $esquema, mixed $valor, string $ruta): void
    {
        $this->assertTrue(is_array($valor) && array_is_list($valor), "{$ruta}: se esperaba una lista.");
        $this->assertGreaterThanOrEqual($esquema['minItems'] ?? 0, count($valor), "{$ruta}: menos elementos que minItems.");

        foreach ($valor as $i => $elemento) {
            $this->assertEsquema($esquema['items'] ?? [], $elemento, "{$ruta}[{$i}]");
        }
    }

    private function assertTexto(array $esquema, mixed $valor, string $ruta): void
    {
        $this->assertIsString($valor, "{$ruta}: se esperaba texto.");
        $this->assertGreaterThanOrEqual($esquema['minLength'] ?? 0, mb_strlen($valor), "{$ruta}: más corto que minLength.");

        match ($esquema['format'] ?? null) {
            'uuid' => $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $valor, "{$ruta}: no es uuid."),
            'date-time' => $this->assertNotFalse(date_create($valor), "{$ruta}: no es date-time."),
            'date' => $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $valor, "{$ruta}: no es date."),
            default => null,
        };
    }
}
