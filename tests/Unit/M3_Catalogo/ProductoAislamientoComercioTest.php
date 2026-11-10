<?php

declare(strict_types=1);

namespace Tests\Unit\M3_Catalogo;

use App\Models\M3_Catalogo\Producto;
use Tests\TestCase;
use Tests\Unit\M3_Catalogo\Concerns\PreparaEsquemaM3;

/** RN-12: un comercio solo ve sus propios productos al usar el scope delComercio. */
class ProductoAislamientoComercioTest extends TestCase
{
    use PreparaEsquemaM3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaM3();
    }

    public function test_scope_del_comercio_filtra_correctamente(): void
    {
        $this->comoSistema(function (): void {
            Producto::create(['id_comercio' => 2, 'id_categoria' => 1, 'nombre' => 'Producto Comercio 2', 'estado_producto' => 'publicado']);
            Producto::create(['id_comercio' => 3, 'id_categoria' => 1, 'nombre' => 'Producto Comercio 3', 'estado_producto' => 'publicado']);
        });

        $productos = $this->comoSistema(fn () => Producto::delComercio(2)->get());

        $this->assertCount(1, $productos);
        $this->assertSame('Producto Comercio 2', $productos->first()->nombre);
    }

    public function test_scope_del_comercio_sin_productos_devuelve_vacio(): void
    {
        $this->comoSistema(function (): void {
            Producto::create(['id_comercio' => 2, 'id_categoria' => 1, 'nombre' => 'Solo del 2', 'estado_producto' => 'publicado']);
        });

        $this->assertCount(0, $this->comoSistema(fn () => Producto::delComercio(3)->get()));
    }
}