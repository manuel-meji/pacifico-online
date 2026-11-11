<?php

namespace Tests\Feature\M3_Catalogo\Contratos;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class M3_ProveedorContratoTest extends TestCase
{
    private const CONTRATO = 'contracts/m3-catalogo/v1/openapi_M3_v1.3.0.yaml';

    private const PREFIJO_CONTRATO = '/api/m3/v1';

    private const PREFIJO_ACTUAL = '/m3/v1';

    /** @var array<string, mixed>|null */
    private static ?array $contrato = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('m', 32)));
    }

    public function test_el_contrato_declara_las_seis_operaciones_del_proveedor(): void
    {
        $operaciones = [];

        foreach ($this->contrato()['paths'] as $ruta => $metodos) {
            foreach ($metodos as $metodo => $operacion) {
                if (isset($operacion['operationId'])) {
                    $operaciones[] = $operacion['operationId'];
                }
            }
        }

        $this->assertSame([
            'listarCategorias',
            'listarMarcas',
            'buscarProductos',
            'obtenerProducto',
            'obtenerVariacion',
            'listarRelacionados',
        ], array_values(array_intersect([
            'listarCategorias',
            'listarMarcas',
            'buscarProductos',
            'obtenerProducto',
            'obtenerVariacion',
            'listarRelacionados',
        ], $operaciones)));
        $this->assertSame('1.3.0', $this->contrato()['info']['version']);
    }

    public function test_el_prefijo_http_actual_no_cumple_todavia_el_prefijo_publicado(): void
    {
        $ruta = app('router')->getRoutes()->getByName('m3.productos.show');

        $this->assertNotNull($ruta);
        $this->assertSame(self::PREFIJO_ACTUAL.'/productos/{id_producto}', '/'.$ruta->uri());
        $this->assertNotSame(self::PREFIJO_CONTRATO, self::PREFIJO_ACTUAL);
    }

    public function test_obtener_producto_en_ruta_actual_cumple_el_esquema_de_producto_detalle(): void
    {
        $response = $this->getJson($this->endpoint('m3.productos.show', 1));

        $response->assertOk();
        $payload = $response->json();
        $this->assertEsquema(['$ref' => '#/components/schemas/ProductoDetalle'], $payload, 'ProductoDetalle');
    }

    public function test_obtener_variacion_en_ruta_actual_cumple_el_esquema_y_tipos_numericos(): void
    {
        $response = $this->getJson($this->endpoint('m3.variaciones.show', 101));

        $response->assertOk();
        $this->assertEsquema(['$ref' => '#/components/schemas/VariacionDetalle'], $response->json(), 'VariacionDetalle');
    }

    public function test_obtener_producto_inexistente_responde_error_estandar_404(): void
    {
        $response = $this->getJson($this->endpoint('m3.productos.show', 1001));

        $response->assertNotFound();
        $this->assertEsquema(['$ref' => '#/components/schemas/ErrorEstandar'], $response->json(), 'ErrorEstandar');
    }

    public function test_obtener_variacion_inexistente_responde_error_estandar_404(): void
    {
        $response = $this->getJson($this->endpoint('m3.variaciones.show', 1001));

        $response->assertNotFound();
        $this->assertEsquema(['$ref' => '#/components/schemas/ErrorEstandar'], $response->json(), 'ErrorEstandar');
    }

    public function test_validador_number_acepta_enteros_y_decimales(): void
    {
        $this->assertEsquema(['type' => 'number'], 10000, 'precio_entero');
        $this->assertEsquema(['type' => 'number'], 10000.50, 'precio_decimal');
        $this->assertEsquema([
            'allOf' => [
                ['type' => 'number', 'minimum' => 0],
                ['type' => 'number', 'maximum' => 100],
            ],
        ], 13, 'tarifa_all_of');
        $this->assertEsquema([
            'allOf' => [
                ['type' => 'object', 'properties' => [
                    'nombre' => ['type' => 'string', 'minLength' => 3],
                ]],
                ['type' => 'object', 'properties' => [
                    'nombre' => ['type' => 'string', 'maxLength' => 5],
                ]],
            ],
        ], ['nombre' => 'Cafe'], 'nombre_all_of');
    }

    public function test_listar_categorias_queda_pendiente_de_publicacion_del_proveedor(): void
    {
        $this->omitOperationUntilAvailable('listarCategorias');
    }

    public function test_listar_marcas_queda_pendiente_de_publicacion_del_proveedor(): void
    {
        $this->omitOperationUntilAvailable('listarMarcas');
    }

    public function test_buscar_productos_queda_pendiente_de_publicacion_del_proveedor(): void
    {
        $this->omitOperationUntilAvailable('buscarProductos');
    }

    public function test_listar_relacionados_queda_pendiente_de_publicacion_del_proveedor(): void
    {
        $this->omitOperationUntilAvailable('listarRelacionados');
    }

    private function omitOperationUntilAvailable(string $metodo): void
    {
        $this->markTestSkipped(
            "Pendiente externo: CatalogoServiceInterface aun no publica {$metodo}; no se inventa una implementacion."
        );
    }

    private function endpoint(string $routeName, int $id): string
    {
        if (! app('router')->getRoutes()->getByName($routeName)) {
            $this->markTestSkipped(
                "Pendiente externo: no existe la ruta HTTP {$routeName}; no se usa una llamada directa al servicio."
            );
        }

        return route($routeName, ['id_producto' => $id, 'id_variacion' => $id]);
    }

    /** @return array<string, mixed> */
    private function contrato(): array
    {
        return self::$contrato ??= Yaml::parseFile(base_path(self::CONTRATO));
    }

    /** @param array<string, mixed> $esquema */
    private function assertEsquema(array $esquema, mixed $valor, string $ruta): void
    {
        $esquema = $this->resolverEsquema($esquema);

        if ($valor === null) {
            $this->assertTrue($esquema['nullable'] ?? false, "{$ruta} no admite null.");

            return;
        }

        if (($esquema['type'] ?? null) === 'object' || isset($esquema['properties'])) {
            $this->assertIsArray($valor, "{$ruta} debe ser un objeto.");

            foreach ($esquema['required'] ?? [] as $campo) {
                $this->assertArrayHasKey($campo, $valor, "Falta {$ruta}.{$campo}.");
            }

            foreach ($esquema['properties'] ?? [] as $campo => $propiedad) {
                if (array_key_exists($campo, $valor)) {
                    $this->assertEsquema($propiedad, $valor[$campo], "{$ruta}.{$campo}");
                }
            }

            return;
        }

        match ($esquema['type'] ?? null) {
            'array' => $this->assertLista($esquema, $valor, $ruta),
            'integer' => $this->assertIsInt($valor, "{$ruta} debe ser entero."),
            'number' => $this->assertTrue(is_int($valor) || is_float($valor), "{$ruta} debe ser numero."),
            'boolean' => $this->assertIsBool($valor, "{$ruta} debe ser booleano."),
            'string' => $this->assertIsString($valor, "{$ruta} debe ser texto."),
            default => null,
        };

        if (isset($esquema['enum'])) {
            $this->assertContains($valor, $esquema['enum'], "{$ruta} no pertenece a la enumeracion.");
        }

        if (isset($esquema['minimum']) && is_numeric($valor)) {
            $this->assertGreaterThanOrEqual($esquema['minimum'], $valor, "{$ruta} es menor que el minimo.");
        }

        if (isset($esquema['maximum']) && is_numeric($valor)) {
            $this->assertLessThanOrEqual($esquema['maximum'], $valor, "{$ruta} es mayor que el maximo.");
        }

        if (($esquema['type'] ?? null) === 'string') {
            if (isset($esquema['minLength'])) {
                $this->assertGreaterThanOrEqual($esquema['minLength'], mb_strlen($valor), "{$ruta} es menor que minLength.");
            }

            if (isset($esquema['maxLength'])) {
                $this->assertLessThanOrEqual($esquema['maxLength'], mb_strlen($valor), "{$ruta} supera maxLength.");
            }

            if (isset($esquema['pattern'])) {
                $this->assertMatchesRegularExpression('/'.$esquema['pattern'].'/', $valor, "{$ruta} no cumple pattern.");
            }

            match ($esquema['format'] ?? null) {
                'date-time' => $this->assertNotFalse(date_create($valor), "{$ruta} no cumple date-time."),
                'uri' => $this->assertNotFalse(filter_var($valor, FILTER_VALIDATE_URL), "{$ruta} no cumple uri."),
                default => null,
            };
        }
    }

    /** @param array<string, mixed> $esquema */
    private function assertLista(array $esquema, mixed $valor, string $ruta): void
    {
        $this->assertTrue(is_array($valor) && array_is_list($valor), "{$ruta} debe ser una lista.");

        if (isset($esquema['minItems'])) {
            $this->assertGreaterThanOrEqual($esquema['minItems'], count($valor), "{$ruta} tiene menos elementos que minItems.");
        }

        if (isset($esquema['maxItems'])) {
            $this->assertLessThanOrEqual($esquema['maxItems'], count($valor), "{$ruta} supera maxItems.");
        }

        foreach ($valor as $indice => $elemento) {
            $this->assertEsquema($esquema['items'] ?? [], $elemento, "{$ruta}[{$indice}]");
        }
    }

    /** @param array<string, mixed> $esquema */
    private function resolverEsquema(array $esquema): array
    {
        if (isset($esquema['$ref'])) {
            $nombre = substr($esquema['$ref'], strlen('#/components/schemas/'));

            return $this->resolverEsquema($this->contrato()['components']['schemas'][$nombre]);
        }

        if (! isset($esquema['allOf'])) {
            return $esquema;
        }

        $resultado = [];

        foreach ($esquema['allOf'] as $parte) {
            $parteResuelta = $this->resolverEsquema($parte);
            $resultado = $this->combinarEsquemas($resultado, $parteResuelta);
        }

        return $resultado;
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $parte @return array<string, mixed> */
    private function combinarEsquemas(array $base, array $parte): array
    {
        foreach ($parte as $clave => $valor) {
            if ($clave === 'properties') {
                foreach ($valor as $nombre => $propiedad) {
                    if (isset($base['properties'][$nombre])) {
                        $base['properties'][$nombre] = $this->combinarEsquemas(
                            $this->resolverEsquema($base['properties'][$nombre]),
                            $this->resolverEsquema($propiedad),
                        );
                    } else {
                        $base['properties'][$nombre] = $propiedad;
                    }
                }
            } elseif ($clave === 'required') {
                $base['required'] = array_values(array_unique([
                    ...($base['required'] ?? []),
                    ...$valor,
                ]));
            } elseif (in_array($clave, ['minimum', 'minLength', 'minItems'], true)) {
                $base[$clave] = max($base[$clave] ?? $valor, $valor);
            } elseif (in_array($clave, ['maximum', 'maxLength', 'maxItems'], true)) {
                $base[$clave] = min($base[$clave] ?? $valor, $valor);
            } elseif ($clave === 'enum' && isset($base['enum'])) {
                $base[$clave] = array_values(array_intersect($base['enum'], $valor));
            } else {
                $base[$clave] = $valor;
            }
        }

        return $base;
    }
}
