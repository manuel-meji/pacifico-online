<?php

namespace Tests\Feature\M1_Portal\Concerns;

use Symfony\Component\Yaml\Yaml;

/**
 * Valida un arreglo contra un esquema de contracts/m1-portal/v1/openapi.yaml:
 * campos obligatorios presentes, ningún campo que el esquema no declare y
 * valores del tipo declarado.
 */
trait ValidaEsquemaContrato
{
    /** @var array<string, mixed>|null */
    private static ?array $esquemasM1 = null;

    protected function assertCumpleEsquema(string $nombre, mixed $valor): void
    {
        $this->revisarEsquema(['$ref' => '#/components/schemas/'.$nombre], $valor, $nombre);
    }

    /**
     * @param  array<string, mixed>  $esquema
     */
    private function revisarEsquema(array $esquema, mixed $valor, string $ruta): void
    {
        $esquema = $this->resolverEsquema($esquema);

        if ($valor === null) {
            $this->assertTrue($esquema['nullable'] ?? false, "{$ruta} no puede ser null.");

            return;
        }

        if (isset($esquema['properties']) || ($esquema['type'] ?? null) === 'object') {
            $this->assertIsArray($valor, "{$ruta} debe ser objeto.");
            $propiedades = $esquema['properties'] ?? [];

            foreach ($esquema['required'] ?? [] as $campo) {
                $this->assertArrayHasKey($campo, $valor, "{$ruta} no trae {$campo}.");
            }

            foreach ($valor as $campo => $contenido) {
                $this->assertArrayHasKey($campo, $propiedades, "{$ruta}.{$campo} no está en el esquema.");
                $this->revisarEsquema($propiedades[$campo], $contenido, "{$ruta}.{$campo}");
            }

            return;
        }

        match ($esquema['type'] ?? null) {
            'array' => (function () use ($esquema, $valor, $ruta): void {
                $this->assertIsList($valor, "{$ruta} debe ser lista.");
                foreach ($valor as $indice => $elemento) {
                    $this->revisarEsquema($esquema['items'] ?? [], $elemento, "{$ruta}[{$indice}]");
                }
            })(),
            'integer' => $this->assertIsInt($valor, "{$ruta} debe ser entero."),
            'number' => $this->assertTrue(is_int($valor) || is_float($valor), "{$ruta} debe ser número."),
            'boolean' => $this->assertIsBool($valor, "{$ruta} debe ser booleano."),
            'string' => $this->assertIsString($valor, "{$ruta} debe ser texto."),
            default => null,
        };

        if (isset($esquema['enum'])) {
            $this->assertContains($valor, $esquema['enum'], "{$ruta} no es un valor permitido.");
        }
    }

    /**
     * Sigue los $ref y une los allOf del esquema.
     *
     * @param  array<string, mixed>  $esquema
     * @return array<string, mixed>
     */
    private function resolverEsquema(array $esquema): array
    {
        self::$esquemasM1 ??= Yaml::parseFile(base_path('contracts/m1-portal/v1/openapi.yaml'))['components']['schemas'];

        while (isset($esquema['$ref'])) {
            $esquema = self::$esquemasM1[substr($esquema['$ref'], strlen('#/components/schemas/'))];
        }

        if (isset($esquema['allOf'])) {
            $unido = ['type' => 'object', 'properties' => [], 'required' => []];

            foreach ($esquema['allOf'] as $parte) {
                $parte = $this->resolverEsquema($parte);
                $unido['properties'] += $parte['properties'] ?? [];
                $unido['required'] = [...$unido['required'], ...$parte['required'] ?? []];
            }

            return $unido;
        }

        return $esquema;
    }
}
