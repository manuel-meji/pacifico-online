<?php

declare(strict_types=1);

namespace Tests\Feature\M5_Pedidos;

use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemDeseo;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use App\Models\Nucleo\Usuario;
use Tests\TestCase;

class AlcanceDeseosYDevolucionesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', [
            '--path' => 'database/migrations/M5_Pedidos',
            '--realpath' => false,
        ]);
    }

    public function test_cliente_puede_gestionar_lista_de_deseos(): void
    {
        // 1. Agregar variación a lista de deseos
        $res = $this->postJson('/api/m5/v1/deseos', [
            'id_cliente' => 5,
            'id_variacion' => 101,
        ]);
        $res->assertStatus(201)
            ->assertJsonPath('item.id_variacion', 101);

        $this->assertDatabaseHas('pedidos_deseos', [
            'id_cliente' => 5,
            'id_variacion' => 101,
        ]);

        // 2. Listar deseos
        $resList = $this->getJson('/api/m5/v1/deseos?id_cliente=5');
        $resList->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.id_variacion', 101);

        // 3. Mover al carrito
        $deseo = ItemDeseo::where('id_cliente', 5)->first();
        $resMover = $this->postJson("/api/m5/v1/deseos/{$deseo->id_deseo}/mover-al-carrito?id_cliente=5");
        $resMover->assertStatus(200);

        // Debe haberse eliminado de deseos y existir en el carrito
        $this->assertDatabaseMissing('pedidos_deseos', ['id_deseo' => $deseo->id_deseo]);
        $carrito = Carrito::where('id_cliente', 5)->first();
        $this->assertNotNull($carrito);
        $this->assertDatabaseHas('items_carrito', [
            'id_carrito' => $carrito->id_carrito,
            'id_variacion' => 101,
        ]);

        // 4. Eliminar directamente de deseos
        $otroDeseo = ItemDeseo::create(['id_cliente' => 5, 'id_variacion' => 202]);
        $resDel = $this->deleteJson("/api/m5/v1/deseos/{$otroDeseo->id_deseo}?id_cliente=5");
        $resDel->assertStatus(200);
        $this->assertDatabaseMissing('pedidos_deseos', ['id_deseo' => $otroDeseo->id_deseo]);
    }

    public function test_cliente_puede_solicitar_devolucion_de_subpedido_entregado(): void
    {
        $pedido = PedidoMaestro::create([
            'id_cliente' => 5,
            'id_direccion_cliente' => 1,
            'monto_total' => 15000.00,
            'fecha_creacion' => now(),
            'estado_pedido' => 'entregado',
            'medio_pago' => 'tarjeta',
        ]);

        $subpedido = Subpedido::create([
            'id_pedido' => $pedido->id_pedido,
            'id_comercio' => 10,
            'subtotal' => 15000.00,
            'total' => 15000.00,
            'estado_subpedido' => 'entregado',
        ]);

        $res = $this->postJson("/api/m5/v1/subpedidos/{$subpedido->id_subpedido}/devolucion", [
            'motivo' => 'Producto llegó con defecto en la pantalla',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('requiere_reembolso', true);

        $this->assertDatabaseHas('cancelaciones_pedido', [
            'id_subpedido' => $subpedido->id_subpedido,
            'requiere_reembolso' => true,
        ]);
    }

    public function test_no_se_puede_solicitar_devolucion_duplicada_sobre_el_mismo_subpedido(): void
    {
        $pedido = PedidoMaestro::create([
            'id_cliente' => 5,
            'id_direccion_cliente' => 1,
            'monto_total' => 15000.00,
            'fecha_creacion' => now(),
            'estado_pedido' => 'entregado',
            'medio_pago' => 'tarjeta',
        ]);

        $subpedido = Subpedido::create([
            'id_pedido' => $pedido->id_pedido,
            'id_comercio' => 10,
            'subtotal' => 15000.00,
            'total' => 15000.00,
            'estado_subpedido' => 'entregado',
        ]);

        $this->postJson("/api/m5/v1/subpedidos/{$subpedido->id_subpedido}/devolucion", [
            'motivo' => 'Talla incorrecta',
        ])->assertStatus(200);

        $resDuplicada = $this->postJson("/api/m5/v1/subpedidos/{$subpedido->id_subpedido}/devolucion", [
            'motivo' => 'Segundo intento de devolución',
        ]);

        $resDuplicada->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M5-002');

        $this->assertSame(1, $subpedido->cancelaciones()->count());
    }

    public function test_no_se_puede_solicitar_devolucion_si_no_ha_sido_entregado(): void
    {
        $pedido = PedidoMaestro::create([
            'id_cliente' => 5,
            'id_direccion_cliente' => 1,
            'monto_total' => 10000.00,
            'fecha_creacion' => now(),
            'estado_pedido' => 'pendiente',
            'medio_pago' => 'tarjeta',
        ]);

        $subpedido = Subpedido::create([
            'id_pedido' => $pedido->id_pedido,
            'id_comercio' => 10,
            'subtotal' => 10000.00,
            'total' => 10000.00,
            'estado_subpedido' => 'pendiente',
        ]);

        $res = $this->postJson("/api/m5/v1/subpedidos/{$subpedido->id_subpedido}/devolucion", [
            'motivo' => 'No lo quiero',
        ]);

        $res->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M5-002');
    }

    public function test_lista_de_deseos_usa_dobles_de_m3_y_m1_cuando_modo_es_doble(): void
    {
        config()->set('integraciones.m5.m3.modo', 'doble');
        config()->set('integraciones.m5.m1.modo', 'doble');

        $usuario = new Usuario;
        $usuario->forceFill(['id_usuario' => 501]);

        ItemDeseo::create(['id_cliente' => 1, 'id_variacion' => 2]);

        $res = $this->actingAs($usuario)->getJson('/api/m5/v1/deseos');

        $res->assertOk()
            ->assertJsonPath('id_cliente', 1)
            ->assertJsonPath('items.0.producto.id_variacion', 2)
            ->assertJsonPath('items.0.producto.nombre', 'Café Tarrazú Reserva Especial');
    }
}
