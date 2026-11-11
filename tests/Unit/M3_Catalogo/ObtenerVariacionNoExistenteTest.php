<?php

declare(strict_types=1);

namespace Tests\Unit\M3_Catalogo;

use App\Services\M3_Catalogo\CatalogoService;
use Tests\TestCase;
use Tests\Unit\M3_Catalogo\Concerns\PreparaDatosConsultasM3;

/** obtenerVariacion: null si no existe (404 ERR-004 en la ruta) y VariacionDetalle con producto embebido si existe. */
class ObtenerVariacionNoExistenteTest extends TestCase
{
    use PreparaDatosConsultasM3;

    protected function setUp(): void
    {
        parent::setUp();
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
}
