<?php

namespace Tests\Unit\M3_Catalogo;

use App\Events\M3_Catalogo\ProductoPublicado;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Tests\Feature\M3_Catalogo\EsquemaCatalogoPrueba;
use Tests\TestCase;

/**
 * RN-12: un comercio no puede tocar productos de otro. Responde 404 (el manejador
 * del núcleo convierte ModelNotFoundException en ERR-004), no 403.
 */
class AislamientoPorComercioTest extends TestCase
{
    use EsquemaCatalogoPrueba;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCatalogo(1);
        Event::fake([ProductoPublicado::class]);
    }

    public function test_agregar_una_variacion_a_un_producto_de_otro_comercio_responde_404(): void
    {
        $idProducto = $this->nuevoProducto();

        $this->comoComercio(34);

        $this->expectException(ModelNotFoundException::class);
        $this->servicioCatalogo()->crearVariacion($idProducto, $this->datosVariacion());
    }

    public function test_publicar_un_producto_de_otro_comercio_responde_404(): void
    {
        $idProducto = $this->nuevoProducto();

        $this->comoComercio(34);

        $this->expectException(ModelNotFoundException::class);
        $this->servicioCatalogo()->cambiarEstado($idProducto, ['estado_producto' => 'publicado']);
    }

    public function test_relacionar_con_un_producto_de_otro_comercio_es_rechazado(): void
    {
        $propio = $this->nuevoProducto();
        $this->comoComercio(34);
        $ajeno = $this->nuevoProducto();

        $this->comoComercio(1);

        try {
            $this->servicioCatalogo()->asociarRelacionado($propio, $ajeno);
            $this->fail('Debió rechazar el producto de otro comercio.');
        } catch (\App\Exceptions\M3_Catalogo\ErrorCatalogoException $error) {
            $this->assertSame('ERR-M3-002', $error->codigo);
            $this->assertSame(422, $error->http);
        }
    }
}