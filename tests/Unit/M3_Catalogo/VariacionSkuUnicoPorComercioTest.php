<?php

declare(strict_types=1);

namespace Tests\Unit\M3_Catalogo;

use App\Models\M3_Catalogo\Producto;
use App\Models\M3_Catalogo\ProductoVariacion;
use Illuminate\Database\QueryException;
use Tests\TestCase;
use Tests\Unit\M3_Catalogo\Concerns\PreparaEsquemaM3;

/** RN-M3-01: el SKU es unico dentro de cada comercio. */
class VariacionSkuUnicoPorComercioTest extends TestCase
{
    use PreparaEsquemaM3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaM3();
    }

    private function producto(int $idComercio, string $nombre): Producto
    {
        return $this->comoSistema(fn () => Producto::create([
            'id_comercio' => $idComercio,
            'id_categoria' => 1,
            'nombre' => $nombre,
            'estado_producto' => 'publicado',
        ]));
    }

    private function variacion(Producto $producto, string $sku): ProductoVariacion
    {
        return $this->comoSistema(fn () => ProductoVariacion::create([
            'id_producto' => $producto->id_producto,
            'id_comercio' => $producto->id_comercio,
            'sku' => $sku,
            'precio' => 10000,
            'tarifa_impuesto' => 13,
            'disponible' => true,
        ]));
    }

    public function test_mismo_sku_en_el_mismo_comercio_es_rechazado(): void
    {
        $a = $this->producto(1, 'Producto A');
        $b = $this->producto(1, 'Producto B');
        $this->variacion($a, 'SKU-REPETIDO');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('UNIQUE constraint failed');

        $this->variacion($b, 'SKU-REPETIDO');
    }

    public function test_mismo_sku_en_comercios_distintos_es_permitido(): void
    {
        $a = $this->producto(1, 'Producto del 1');
        $b = $this->producto(2, 'Producto del 2');

        $this->variacion($a, 'SKU-COMPARTIDO');
        $segunda = $this->variacion($b, 'SKU-COMPARTIDO');

        $this->assertSame('SKU-COMPARTIDO', $segunda->sku);
        $this->assertSame(2, (int) $segunda->id_comercio);
    }
}