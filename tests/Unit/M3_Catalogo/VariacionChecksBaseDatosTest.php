<?php

declare(strict_types=1);

namespace Tests\Unit\M3_Catalogo;

use App\Models\M3_Catalogo\Producto;
use App\Models\M3_Catalogo\ProductoVariacion;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\M3_Catalogo\Concerns\PreparaEsquemaM3;

/** Reglas CHECK de la variacion: precio, tarifa de IVA y descuento (D1). */
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
            'porcentaje mayor a 100' => [['tipo_descuento' => 'porcentaje', 'valor_descuento' => 150]],
            'monto fijo mayor al precio' => [['tipo_descuento' => 'monto_fijo', 'valor_descuento' => 12000]],
            'valor con tipo ninguno' => [['tipo_descuento' => 'ninguno', 'valor_descuento' => 5]],
            'tipo de descuento desconocido' => [['tipo_descuento' => 'cupon', 'valor_descuento' => 5]],
            'precio negativo' => [['precio' => -1]],
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

    public function test_descuento_porcentaje_valido_es_aceptado(): void
    {
        $variacion = $this->crearVariacion(['tipo_descuento' => 'porcentaje', 'valor_descuento' => 5, 'sku' => 'OK-1']);

        $this->assertSame('porcentaje', $variacion->tipo_descuento);
        $this->assertSame(1, (int) $variacion->id_comercio);
    }

    public function test_descuento_monto_fijo_valido_es_aceptado(): void
    {
        $variacion = $this->crearVariacion(['tipo_descuento' => 'monto_fijo', 'valor_descuento' => 1000, 'sku' => 'OK-2']);

        $this->assertSame('monto_fijo', $variacion->tipo_descuento);
    }
}