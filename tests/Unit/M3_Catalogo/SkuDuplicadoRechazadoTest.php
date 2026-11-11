<?php

namespace Tests\Unit\M3_Catalogo;

use App\Exceptions\M3_Catalogo\ErrorCatalogoException;
use Illuminate\Support\Facades\DB;
use Tests\Feature\M3_Catalogo\EsquemaCatalogoPrueba;
use Tests\TestCase;

/** RN-M3-01: el SKU es único por comercio. */
class SkuDuplicadoRechazadoTest extends TestCase
{
    use EsquemaCatalogoPrueba;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCatalogo(1);
    }

    public function test_un_sku_repetido_en_el_mismo_comercio_es_rechazado(): void
    {
        $idProducto = $this->nuevoProducto();
        $this->servicioCatalogo()->crearVariacion($idProducto, $this->datosVariacion('CAM-AZUL-M'));

        try {
            $this->servicioCatalogo()->crearVariacion($idProducto, $this->datosVariacion('CAM-AZUL-M'));
            $this->fail('Debió rechazar el SKU repetido.');
        } catch (ErrorCatalogoException $error) {
            $this->assertSame('ERR-M3-001', $error->codigo);
            $this->assertSame(409, $error->http);
            $this->assertSame(['sku' => 'CAM-AZUL-M'], $error->detalles);
        }

        $this->assertSame(1, DB::table('producto_variaciones')->where('sku', 'CAM-AZUL-M')->count());
    }

    public function test_el_mismo_sku_en_otro_comercio_se_acepta(): void
    {
        $idProducto = $this->nuevoProducto();
        $this->servicioCatalogo()->crearVariacion($idProducto, $this->datosVariacion('CAM-AZUL-M'));

        $this->comoComercio(34);
        $idProductoOtroComercio = $this->nuevoProducto();
        $variacion = $this->servicioCatalogo()->crearVariacion(
            $idProductoOtroComercio,
            $this->datosVariacion('CAM-AZUL-M')
        );

        $this->assertSame('CAM-AZUL-M', $variacion['sku']);
        $this->assertSame(2, DB::table('producto_variaciones')->where('sku', 'CAM-AZUL-M')->count());
    }
}