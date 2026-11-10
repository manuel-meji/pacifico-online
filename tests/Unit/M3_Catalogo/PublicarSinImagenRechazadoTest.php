<?php

namespace Tests\Unit\M3_Catalogo;

use App\Events\M3_Catalogo\ProductoPublicado;
use App\Exceptions\Nucleo\ErrorNucleoException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Feature\M3_Catalogo\EsquemaCatalogoPrueba;
use Tests\TestCase;

/** RN-M3-09: para publicar hace falta al menos 1 variación y 1 imagen. */
class PublicarSinImagenRechazadoTest extends TestCase
{
    use EsquemaCatalogoPrueba;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCatalogo(1);
        Event::fake([ProductoPublicado::class]);
    }

    private function estadoDe(int $idProducto): string
    {
        return (string) DB::table('productos')->where('id_producto', $idProducto)->value('estado_producto');
    }

    public function test_publicar_sin_variacion_ni_imagen_devuelve_error_de_validacion(): void
    {
        $idProducto = $this->nuevoProducto();

        try {
            $this->servicioCatalogo()->cambiarEstado($idProducto, ['estado_producto' => 'publicado']);
            $this->fail('Debió rechazar la publicación.');
        } catch (ErrorNucleoException $error) {
            $this->assertSame('ERR-005', $error->codigo);
            $this->assertSame(422, $error->http);
            $this->assertArrayHasKey('variaciones', $error->detalles);
            $this->assertArrayHasKey('imagenes', $error->detalles);
        }

        $this->assertSame('borrador', $this->estadoDe($idProducto));
        Event::assertNotDispatched(ProductoPublicado::class);
    }

    public function test_publicar_con_variacion_pero_sin_imagen_es_rechazado(): void
    {
        $idProducto = $this->nuevoProducto();
        $this->servicioCatalogo()->crearVariacion($idProducto, $this->datosVariacion());

        try {
            $this->servicioCatalogo()->cambiarEstado($idProducto, ['estado_producto' => 'publicado']);
            $this->fail('Debió rechazar la publicación.');
        } catch (ErrorNucleoException $error) {
            $this->assertSame('ERR-005', $error->codigo);
            $this->assertArrayHasKey('imagenes', $error->detalles);
            $this->assertArrayNotHasKey('variaciones', $error->detalles);
        }

        $this->assertSame('borrador', $this->estadoDe($idProducto));
    }

    public function test_publicar_con_una_variacion_y_una_imagen_funciona(): void
    {
        $idProducto = $this->nuevoProducto();
        $this->servicioCatalogo()->crearVariacion($idProducto, $this->datosVariacion());
        $this->servicioCatalogo()->agregarImagen($idProducto, ['imagen_url' => 'https://ejemplo.test/foto.jpg']);

        $producto = $this->servicioCatalogo()->cambiarEstado($idProducto, ['estado_producto' => 'publicado']);

        $this->assertSame('publicado', $producto['estado_producto']);
        $this->assertSame('publicado', $this->estadoDe($idProducto));
    }
}