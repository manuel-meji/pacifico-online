<?php

namespace Tests\Unit\M5_Pedidos;

use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Models\M5_Pedidos\PedidoLinea;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use Tests\TestCase;

class ModelosM5Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', [
            '--path' => 'database/migrations/M5_Pedidos',
            '--realpath' => false,
        ]);
    }

    public function test_carrito_obtener_o_crear_y_items(): void
    {
        $carritoModel = new Carrito;
        $carrito = $carritoModel->obtenerOCrearCarrito(42);

        $this->assertNotNull($carrito);
        $this->assertSame(42, $carrito->id_cliente);

        $itemModel = new ItemCarrito;
        $item = $itemModel->crearItemCarrito($carrito->id_carrito, 10, 2);

        $this->assertSame(10, $item->id_variacion);
        $this->assertSame(2, $item->cantidad);

        // Al agregar la misma variación, se incrementa la cantidad
        $item2 = $itemModel->crearItemCarrito($carrito->id_carrito, 10, 3);
        $this->assertSame($item->id_item_carrito, $item2->id_item_carrito);
        $this->assertSame(5, $item2->cantidad);

        // Actualizar cantidad
        $item2->actualizarCantidad(7);
        $this->assertSame(7, $item2->fresh()->cantidad);

        // Relación carrito -> items y recalcularTotales
        $this->assertCount(1, $carrito->fresh()->items);
        $carrito->recalcularTotales(1500.0);
        $this->assertSame(1500.0, (float) $carrito->monto_estimado);

        // Vaciar
        $carrito->vaciar();
        $this->assertCount(0, $carrito->fresh()->items);
    }

    public function test_composite_patron_pedido_maestro_subpedido_linea(): void
    {
        // 1. Crear Pedido Maestro (Root)
        $pedidoMaestro = new PedidoMaestro;
        $idPedido = $pedidoMaestro->crearPedidoMaestro(0.0, 5, 'CUPON10', 'REGALO20');

        $this->assertGreaterThan(0, $idPedido);
        $this->assertSame('confirmado', $pedidoMaestro->estado_pedido);
        $this->assertSame('CUPON10', $pedidoMaestro->cupon_descuento);
        $this->assertSame('REGALO20', $pedidoMaestro->tarjeta_regalo);

        // 2. Crear Subpedidos (Composite Nodes)
        $subpedidoModel = new Subpedido;
        $subpedidos = $subpedidoModel->crearSubpedidos([
            [
                'id_comercio' => 101,
                'subtotal' => 0.0,
                'costo_envio' => 500.0,
                'impuesto' => 0.0,
            ],
            [
                'id_comercio' => 102,
                'subtotal' => 0.0,
                'costo_envio' => 300.0,
                'impuesto' => 0.0,
            ],
        ], $idPedido);

        $this->assertCount(2, $subpedidos);
        $sub1 = $subpedidos[0];
        $sub2 = $subpedidos[1];

        // 3. Crear Líneas (Leaf Nodes)
        $lineaModel = new PedidoLinea;
        // Subpedido 1: 2 unidades a 1000 con 13% impuesto
        $linea1 = $lineaModel->crearLinea($sub1->id_subpedido, 201, 2, 1000.0, 13.0);
        // Subpedido 1: 1 unidad a 500 con 13% impuesto
        $linea2 = $lineaModel->crearLinea($sub1->id_subpedido, 202, 1, 500.0, 13.0);

        // Subpedido 2: 3 unidades a 2000 con 0% impuesto
        $linea3 = $lineaModel->crearLinea($sub2->id_subpedido, 203, 3, 2000.0, 0.0);

        // Test hoja cálculo
        $lineaObj1 = PedidoLinea::find($linea1);
        $this->assertSame(2000.0, $lineaObj1->calcularSubtotalLinea());
        $this->assertSame(260.0, $lineaObj1->obtenerMontoImpuesto());

        // Test nodo intermedio cálculo
        // Sub1: Lineas = 2000 + 500 = 2500. Impuesto = 260 + 65 = 325. Envio = 500. Total Sub1 = 3325.
        $totalSub1 = $sub1->calcularSubtotal();
        $this->assertSame(3325.0, $totalSub1);
        $this->assertSame(2500.0, (float) $sub1->subtotal);
        $this->assertSame(325.0, (float) $sub1->impuesto);

        // Sub2: Lineas = 6000. Impuesto = 0. Envio = 300. Total Sub2 = 6300.
        $totalSub2 = $sub2->calcularSubtotal();
        $this->assertSame(6300.0, $totalSub2);

        // Test raíz Composite cálculo
        // Total Maestro = 3325 + 6300 = 9625.
        $totalMaestro = $pedidoMaestro->calcularTotal();
        $this->assertSame(9625.0, $totalMaestro);
        $this->assertSame(9625.0, (float) $pedidoMaestro->fresh()->monto_total);
        $this->assertSame(9625.0, $pedidoMaestro->calcularMontoTotal());

        // Test agregarSubpedido y crearSubpedido
        $sub3 = new Subpedido([
            'id_comercio' => 103,
            'subtotal' => 100.0,
            'costo_envio' => 0.0,
            'impuesto' => 0.0,
            'estado_subpedido' => 'pendiente',
        ]);
        $pedidoMaestro->agregarSubpedido($sub3);
        $this->assertSame($pedidoMaestro->id_pedido, $sub3->fresh()->id_pedido);

        $sub4List = $subpedidoModel->crearSubpedido([['id_comercio' => 104, 'subtotal' => 50.0, 'costo_envio' => 0.0, 'impuesto' => 0.0]], $pedidoMaestro->id_pedido);
        $this->assertCount(1, $sub4List);
        $this->assertSame($pedidoMaestro->id_pedido, $sub4List[0]->id_pedido);

        // Test relaciones
        $this->assertCount(4, $pedidoMaestro->fresh()->subpedidos);
        $this->assertCount(3, $pedidoMaestro->lineas);
        $this->assertSame($pedidoMaestro->id_pedido, $sub1->pedidoMaestro->id_pedido);
        $this->assertCount(2, $sub1->lineas);
        $this->assertSame($sub1->id_subpedido, $lineaObj1->subpedido->id_subpedido);

        // Estados y transición
        $sub1->despachar('GUIA-123');
        $this->assertSame('despachado', $sub1->estado_subpedido);
        $this->assertSame('GUIA-123', $sub1->guia_seguimiento);
        $this->assertNotNull($sub1->fecha_despacho);

        $sub1->entregar();
        $this->assertSame('entregado', $sub1->estado_subpedido);

        $sub2->cancelar('Sin existencias');
        $this->assertSame('cancelado', $sub2->estado_subpedido);

        $pedidoMaestro->cancelar('Cliente solicitó anulación');
        $this->assertSame('cancelado', $pedidoMaestro->estado_pedido);
    }
}
