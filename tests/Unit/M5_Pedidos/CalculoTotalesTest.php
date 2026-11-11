<?php

namespace Tests\Unit\M5_Pedidos;

use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Services\M5_Pedidos\PedidosService;
use Illuminate\Database\Eloquent\Collection;
use Tests\Doubles\M5\CatalogoPreciosFijos;
use Tests\Doubles\M5\ComerciosEnvioPorComercio;
use Tests\TestCase;

/**
 * CP-001, CP-002 y CP-003: cálculo de subtotal, IVA y envío (RN-M5-01 y RN-M5-02).
 * Se prueba PedidosService::calcularDesgloseCarrito con un carrito armado en memoria,
 * sin tocar la base de datos.
 */
class CalculoTotalesTest extends TestCase
{
    /**
     * @param  list<array{0: int, 1: int}>  $lineas  Pares [id_variacion, cantidad].
     */
    private function carritoEnMemoria(array $lineas): Carrito
    {
        $carrito = new Carrito(['id_cliente' => 7]);
        $carrito->setRelation('items', new Collection(array_map(
            fn (array $linea) => new ItemCarrito([
                'id_carrito' => 1,
                'id_variacion' => $linea[0],
                'cantidad' => $linea[1],
            ]),
            $lineas
        )));

        return $carrito;
    }

    public function test_cp001_subtotal_suma_las_lineas_de_productos_distintos(): void
    {
        $catalogo = new CatalogoPreciosFijos([
            10 => ['precio' => 1000.0, 'id_comercio' => 2],
            11 => ['precio' => 2000.0, 'id_comercio' => 2],
        ]);
        $comercios = (new ComerciosEnvioPorComercio)->conCostosDeEnvio([2 => 0.0]);

        // 3 unidades de ₡1.000 y 2 unidades de ₡2.000.
        $desglose = (new PedidosService)->calcularDesgloseCarrito(
            $this->carritoEnMemoria([[10, 3], [11, 2]]),
            $catalogo,
            $comercios
        );

        $this->assertEqualsWithDelta(3000.0, $desglose['items'][0]['subtotal'], 0.001);
        $this->assertEqualsWithDelta(4000.0, $desglose['items'][1]['subtotal'], 0.001);
        $this->assertEqualsWithDelta(7000.0, $desglose['resumen']['subtotal'], 0.001);
    }

    public function test_cp002_iva_se_calcula_por_linea_y_nunca_sobre_el_envio(): void
    {
        $catalogo = new CatalogoPreciosFijos([
            12 => ['precio' => 10000.0, 'id_comercio' => 2, 'tarifa_impuesto' => 13.0],
        ]);
        $comercios = (new ComerciosEnvioPorComercio)->conCostosDeEnvio([2 => 1500.0]);

        // Monto gravable de ₡10.000 con envío de ₡1.500 en el mismo comercio.
        $desglose = (new PedidosService)->calcularDesgloseCarrito(
            $this->carritoEnMemoria([[12, 1]]),
            $catalogo,
            $comercios
        );

        $this->assertEqualsWithDelta(1300.0, $desglose['resumen']['impuesto'], 0.001);
        $this->assertEqualsWithDelta(1500.0, $desglose['resumen']['costo_envio'], 0.001);
        // El total confirma que el envío entra sin IVA: 10.000 + 1.300 + 1.500.
        $this->assertEqualsWithDelta(12800.0, $desglose['resumen']['total'], 0.001);
    }

    public function test_cp003_envio_consolidado_suma_el_costo_de_cada_comercio(): void
    {
        $catalogo = new CatalogoPreciosFijos([
            20 => ['precio' => 5000.0, 'id_comercio' => 1],
            21 => ['precio' => 3000.0, 'id_comercio' => 4],
        ]);
        $comercios = (new ComerciosEnvioPorComercio)->conCostosDeEnvio([1 => 1500.0, 4 => 2500.0]);

        $desglose = (new PedidosService)->calcularDesgloseCarrito(
            $this->carritoEnMemoria([[20, 1], [21, 1]]),
            $catalogo,
            $comercios
        );

        $this->assertSame([1, 4], array_keys($desglose['grupos_comercio']));
        $this->assertEqualsWithDelta(1500.0, $desglose['grupos_comercio'][1]['costo_envio'], 0.001);
        $this->assertEqualsWithDelta(2500.0, $desglose['grupos_comercio'][4]['costo_envio'], 0.001);
        $this->assertEqualsWithDelta(4000.0, $desglose['resumen']['costo_envio'], 0.001);
        // RN-M5-01: total = subtotales (8.000) + IVA (1.040) + envíos (4.000).
        $this->assertEqualsWithDelta(13040.0, $desglose['resumen']['total'], 0.001);
    }
}
