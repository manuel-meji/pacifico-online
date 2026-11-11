<?php

declare(strict_types=1);

namespace Tests\Unit\M3_Catalogo;

use App\Models\M3_Catalogo\Producto;
use App\Models\M3_Catalogo\ProductoVariacion;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\M3_Catalogo\Concerns\PreparaEsquemaM3;

/** Reglas CHECK de la variacion: precio y tarifa de IVA. */
class VariacionChecksBaseDatosTest extends TestCase
{
    use PreparaEsquemaM3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaM3();
    }

    /** @param  array<string, mixed>  $datos */
    private function crearVariacion(array $datos): ProductoVariacion
    {
        return $this->comoSistema(function () use ($datos) {
            $producto = Producto::create([
                'id_comercio' => 1,
                'id_categoria' => 1,
                'nombre' => 'Producto de prueba',
                'estado_producto' => 'publicado',
            ]);

            return ProductoVariacion::create($datos + [
                'id_producto' => $producto->id_producto,
                'id_comercio' => 1,
                'sku' => 'TEST-CHECK-001',
                'precio' => 10000,
                'tarifa_impuesto' => 13,
                'disponible' => true,
            ]);
        });
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function casosInvalidos(): array
    {
        return [
            'precio negativo' => [['precio' => -1]],
            'IVA negativo' => [['tarifa_impuesto' => -1]],
            'IVA mayor a 100' => [['tarifa_impuesto' => 101]],
        ];
    }

    /** @param  array<string, mixed>  $datos */
    #[DataProvider('casosInvalidos')]
    public function test_la_base_rechaza_valores_invalidos(array $datos): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('CHECK constraint failed');

        $this->crearVariacion($datos);
    }

    public function test_precio_e_iva_validos_son_aceptados(): void
    {
        $variacion = $this->crearVariacion(['precio' => 0, 'tarifa_impuesto' => 0, 'sku' => 'OK-1']);

        $this->assertSame(1, (int) $variacion->id_comercio);
        $this->assertSame('OK-1', $variacion->sku);
    }
}