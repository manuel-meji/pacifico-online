<?php

namespace Tests\Feature\M4_Inventario\Aceptacion;

use App\Events\M4_Inventario\AlertaStockMinimo;
use Illuminate\Support\Facades\Event;
use Tests\Feature\M4_Inventario\EsquemaInventarioPrueba;
use Tests\Feature\M4_Inventario\SesionesPruebaM4;
use Tests\TestCase;

/**
 * Prueba de aceptación 2 (CU-M4-01 y CU-M4-08, RN-M4-003 y RN-M4-004): el encargado
 * del comercio, desde la API que usa la pantalla de inventario, registra una entrada,
 * fija el stock mínimo, registra una salida, ve los dos movimientos en el kardex y
 * recibe el aviso de mínimo. Recorre el caso de uso completo con el núcleo real
 * (sesión, rol y aislamiento por comercio).
 */
class EntradaKardexAlertaMinimoTest extends TestCase
{
    use EsquemaInventarioPrueba;
    use SesionesPruebaM4;

    protected function setUp(): void
    {
        parent::setUp();
        config(['integraciones.m4.nucleo.modo' => 'real']);
        $this->crearEsquemaNucleo();
        $this->crearEsquemaInventario();
    }

    public function test_entrada_kardex_y_alerta_de_minimo_de_punta_a_punta(): void
    {
        Event::fake([AlertaStockMinimo::class]);
        $this->actingAs($this->encargado(1, idUsuario: 7), 'sanctum');

        // 1. Entrada de 12 unidades a ₡8 500 en la sede 1.
        $entrada = $this->postJson('/api/m4/v1/movimientos', [
            'id_comercio' => 1, 'id_variacion' => 10, 'id_ubicacion' => 1,
            'tipo_movimiento' => 'entrada', 'cantidad' => 12, 'costo_unitario' => 8500, 'justificacion' => 'Compra a proveedor, factura 1032',
        ])->assertCreated()
            ->assertJsonPath('tipo_movimiento', 'entrada')
            ->assertJsonPath('id_usuario', 7)
            ->assertJsonPath('saldo_disponible', 12);

        // 2. La existencia aparece en el listado; se fija el mínimo en 5 (sin alerta: hay 12).
        $existencia = $this->getJson('/api/m4/v1/existencias?id_comercio=1&id_variacion=10')
            ->assertOk()
            ->assertJsonPath('paginacion.total', 1)
            ->json('datos.0');
        $this->putJson("/api/m4/v1/existencias/{$existencia['id_existencia']}/stock-minimo", ['stock_minimo' => 5])
            ->assertOk()
            ->assertJsonPath('stock_minimo', 5)
            ->assertJsonPath('bajo_minimo', false);
        Event::assertNotDispatched(AlertaStockMinimo::class);

        // 3. Salida de 8: el disponible baja a 4, bajo el mínimo.
        $this->postJson('/api/m4/v1/movimientos', [
            'id_comercio' => 1, 'id_variacion' => 10, 'id_ubicacion' => 1,
            'tipo_movimiento' => 'salida', 'cantidad' => 8, 'justificacion' => 'Venta en tienda física',
        ])->assertCreated()->assertJsonPath('saldo_disponible', 4);

        // 4. El kardex muestra los dos movimientos, del más reciente al más antiguo, con saldos y costo.
        $kardex = $this->getJson('/api/m4/v1/kardex?id_comercio=1&id_variacion=10&id_ubicacion=1')
            ->assertOk()
            ->assertJsonPath('paginacion.total', 2)
            ->json('datos');
        $this->assertSame(['salida', 'entrada'], array_column($kardex, 'tipo_movimiento'));
        $this->assertSame([4, 12], array_column($kardex, 'saldo_cantidad'));
        $this->assertEquals(68000.0, $kardex[0]['monto_total']);
        $this->assertSame($entrada->json('id_movimiento'), $kardex[1]['id_movimiento']);

        // 5. Aviso de mínimo: evento publicado y la existencia marcada bajo el mínimo.
        Event::assertDispatched(AlertaStockMinimo::class, fn (AlertaStockMinimo $e): bool => $e->payload()['cantidad_disponible'] === 4);
        $this->getJson('/api/m4/v1/existencias?id_comercio=1&bajo_minimo=true')
            ->assertOk()
            ->assertJsonPath('datos.0.id_variacion', 10)
            ->assertJsonPath('datos.0.bajo_minimo', true);
    }

    public function test_la_entrada_sin_costo_y_un_cliente_sin_permiso_se_rechazan(): void
    {
        $this->actingAs($this->encargado(1), 'sanctum')
            ->postJson('/api/m4/v1/movimientos', [
                'id_comercio' => 1, 'id_variacion' => 10, 'id_ubicacion' => 1,
                'tipo_movimiento' => 'entrada', 'cantidad' => 3, 'justificacion' => 'Sin costo',
            ])->assertStatus(422)->assertJsonPath('error.codigo', 'ERR-005');

        $this->actingAs($this->cliente(), 'sanctum')
            ->getJson('/api/m4/v1/existencias?id_comercio=1')
            ->assertForbidden()
            ->assertJsonPath('error.codigo', 'ERR-002');
    }
}
