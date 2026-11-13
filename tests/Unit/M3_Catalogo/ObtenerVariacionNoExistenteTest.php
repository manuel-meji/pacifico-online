<?php

declare(strict_types=1);

namespace Tests\Unit\M3_Catalogo;

use App\Services\M3_Catalogo\CatalogoService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Unit\M3_Catalogo\Concerns\PreparaDatosConsultasM3;

/** obtenerVariacion: null si no existe (404 ERR-004 en la ruta) y VariacionDetalle con producto embebido si existe. */
class ObtenerVariacionNoExistenteTest extends TestCase
{
    use PreparaDatosConsultasM3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('m', 32)));
        $this->crearEsquemaConsultasM3();
    }

    public function test_servicio_devuelve_null_para_variacion_inexistente(): void
    {
        $this->assertNull((new CatalogoService)->obtenerVariacion(999));
    }

    public function test_ruta_responde_404_con_error_estandar(): void
    {
        $this->getJson('/m3/v1/variaciones/999')
            ->assertStatus(404)
            ->assertJsonPath('error.codigo', 'ERR-004')
            ->assertJsonStructure(['error' => ['codigo', 'mensaje', 'detalles']]);
    }

    public function test_variacion_existente_trae_el_producto_embebido(): void
    {
        $categoria = $this->categoria('General');
        $producto = $this->producto(4, $categoria, 'Producto');
        $variacion = $this->variacion($producto, 4, 'SKU-OK', 15000);

        $detalle = (new CatalogoService)->obtenerVariacion($variacion);

        $this->assertSame($variacion, $detalle['id_variacion']);
        $this->assertSame($producto, $detalle['id_producto']);
        $this->assertSame(15000.0, $detalle['precio']);
        $this->assertSame(4, $detalle['producto']['id_comercio']);
    }

    public function test_variacion_prioriza_su_imagen_propia_sobre_la_del_producto(): void
    {
        $categoria = $this->categoria('General');
        $producto = $this->producto(4, $categoria, 'Producto');
        $variacion = $this->variacion($producto, 4, 'SKU-IMAGEN', 15000);

        DB::table('imagenes_producto')->insert([
            ['id_producto' => $producto, 'id_variacion' => null, 'imagen_url' => 'https://img.test/producto.jpg', 'orden' => 1],
            ['id_producto' => $producto, 'id_variacion' => $variacion, 'imagen_url' => 'https://img.test/variacion.jpg', 'orden' => 2],
        ]);

        $detalle = (new CatalogoService)->obtenerVariacion($variacion);

        $this->assertArrayHasKey('imagen_url', $detalle);
        $this->assertSame('https://img.test/variacion.jpg', $detalle['imagen_url']);
    }

    public function test_variacion_usa_la_primera_imagen_del_producto_por_orden(): void
    {
        $categoria = $this->categoria('General');
        $producto = $this->producto(4, $categoria, 'Producto');
        $variacion = $this->variacion($producto, 4, 'SKU-FALLBACK', 15000);

        DB::table('imagenes_producto')->insert([
            ['id_producto' => $producto, 'id_variacion' => null, 'imagen_url' => 'https://img.test/segunda.jpg', 'orden' => 2],
            ['id_producto' => $producto, 'id_variacion' => null, 'imagen_url' => 'https://img.test/primera.jpg', 'orden' => 1],
        ]);

        $detalle = (new CatalogoService)->obtenerVariacion($variacion);

        $this->assertSame('https://img.test/primera.jpg', $detalle['imagen_url']);
    }

    public function test_variacion_sin_imagenes_devuelve_imagen_url_nulo(): void
    {
        $categoria = $this->categoria('General');
        $producto = $this->producto(4, $categoria, 'Producto');
        $variacion = $this->variacion($producto, 4, 'SKU-SIN-IMAGEN', 15000);

        $detalle = (new CatalogoService)->obtenerVariacion($variacion);

        $this->assertArrayHasKey('imagen_url', $detalle);
        $this->assertNull($detalle['imagen_url']);
        $this->assertArrayNotHasKey('imagen_url', $detalle['producto']);
    }
}
