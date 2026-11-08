<?php

namespace Tests\Unit\M5_Pedidos;

use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Services\M5_Pedidos\Dobles\InventarioStub;
use App\Services\M5_Pedidos\Dobles\PagosStub;
use App\Services\M5_Pedidos\PedidosService;
use Tests\Doubles\M5\CatalogoPreciosFijos;
use Tests\Doubles\M5\ComerciosEnvioPorComercio;
use Tests\TestCase;
use Tests\Unit\M5_Pedidos\Concerns\PreparaBaseM5;

/**
 * CP-005 y CP-006: partición del pedido en subpedidos por comercio (RN-M5-01, RF-009)
 * y estado inicial pendiente (RN-M5-13).
 */
class ParticionSubpedidosTest extends TestCase
{
    use PreparaBaseM5;

    private const ID_CLIENTE = 31;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrarTablasM5();
    }

    private function servicio(): PedidosService
    {
        $catalogo = new CatalogoPreciosFijos([
            10 => ['precio' => 1000.0, 'id_comercio' => 1],
            11 => ['precio' => 2000.0, 'id_comercio' => 4],
        ]);
        $comercios = (new ComerciosEnvioPorComercio)->conCostosDeEnvio([1 => 1500.0, 4 => 2500.0]);

        return new PedidosService($comercios, $catalogo, new InventarioStub, new PagosStub);
    }

    /**
     * Carrito del cliente con 2 unidades del Comercio 1 y 1 unidad del Comercio 4.
     *
     * @return array<string, mixed>
     */
    private function generarPedidoDesdeCarrito(): array
    {
        $carrito = (new Carrito)->obtenerOCrearCarrito(self::ID_CLIENTE);
        $item = new ItemCarrito;
        $item->crearItemCarrito($carrito->id_carrito, 10, 2);
        $item->crearItemCarrito($carrito->id_carrito, 11, 1);

        return $this->servicio()->crearPedidoCheckout(['id_cliente' => self::ID_CLIENTE]);
    }

    public function test_cp005_el_carrito_de_dos_comercios_se_divide_en_dos_subpedidos(): void
    {
        $pedido = $this->generarPedidoDesdeCarrito();

        $this->assertCount(2, $pedido['subpedidos']);
        $this->assertEqualsCanonicalizing([1, 4], array_column($pedido['subpedidos'], 'id_comercio'));

        $this->assertDatabaseCount('pedidos_maestros', 1);
        $this->assertDatabaseCount('pedidos_subpedidos', 2);
        $this->assertDatabaseCount('pedidos_lineas', 2);
        // El checkout vacía el carrito.
        $this->assertDatabaseCount('items_carrito', 0);

        // RN-M5-01: subtotales (4.000) + IVA (520) + envíos (1.500 + 2.500).
        $this->assertEqualsWithDelta(8520.0, $pedido['monto_total'], 0.001);
    }

    public function test_cp006_el_pedido_y_sus_subpedidos_nacen_en_estado_pendiente(): void
    {
        $pedido = $this->generarPedidoDesdeCarrito();

        $this->assertSame('pendiente', $pedido['estado_pedido']);
        foreach ($pedido['subpedidos'] as $subpedido) {
            $this->assertSame('pendiente', $subpedido['estado_subpedido']);
        }

        $this->assertSame('pendiente', PedidoMaestro::find($pedido['id_pedido'])->estado_pedido);
    }
}
