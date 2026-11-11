<?php

namespace Tests\Feature\M4_Inventario\Contrato;

use App\Contracts\M4_Inventario\Dependencias\PedidosDependencia;
use App\Contracts\M4_Inventario\Dependencias\VerificadorAutorizacionesInterface;
use App\Events\M4_Inventario\AlertaStockMinimo;
use App\Events\M4_Inventario\EventoInventario;
use App\Events\M4_Inventario\ExistenciaAgotada;
use App\Events\M4_Inventario\ExistenciaRepuesta;
use App\Events\M4_Inventario\ExistenciaReservada;
use App\Events\M4_Inventario\ReservaVencida;
use App\Events\M4_Inventario\SalidaInventarioConfirmada;
use App\Jobs\M4_Inventario\VencerReservasJob;
use App\Models\Nucleo\Usuario;
use App\Services\M4_Inventario\Dobles\PedidosStub;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Feature\M4_Inventario\EsquemaInventarioPrueba;
use Tests\Feature\M4_Inventario\SesionesPruebaM4;
use Tests\TestCase;

/**
 * Pruebas de contrato de M4 como proveedor (sección 7.1 del PC4): cada operación publicada
 * en openapi_M4_v1.3.0 se llama por la API real (/api/m4/v1, núcleo real) y su respuesta se
 * compara con el esquema publicado; los errores, con ErrorEstandar y los códigos ERR-M4.
 * Los eventos que publica M4 se comparan con sus esquemas Evento*. Si alguien cambia un
 * nombre, un tipo o un código sin subir la versión del contrato, esta prueba falla.
 *
 * Convención de nombre y ubicación: tests/Feature/<Modulo>/Contrato/Proveedor<Modulo>Test.php.
 */
class ProveedorM4Test extends TestCase
{
    use EsquemaInventarioPrueba;
    use SesionesPruebaM4;
    use ValidaContratoOpenApiM4;

    private int $idExistencia;

    protected function setUp(): void
    {
        parent::setUp();
        config(['integraciones.m4.nucleo.modo' => 'real']);
        $this->crearEsquemaNucleo();
        $this->crearEsquemaInventario();
        $this->app->instance(PedidosDependencia::class, (new PedidosStub)->registrar(9101, 9001, 1, 'despachado'));
        $this->idExistencia = $this->existencia([
            'id_comercio' => 1, 'id_producto' => 300, 'id_variacion' => 10, 'id_ubicacion' => 1,
            'cantidad_fisica' => 10, 'costo_promedio' => 8500, 'stock_minimo' => 2,
        ]);
    }

    private function comoEncargado(): self
    {
        return $this->actingAs($this->encargado(1, idUsuario: 7), 'sanctum');
    }

    private function reservar(int $cantidad = 2): string
    {
        return $this->comoEncargado()
            ->postJson('/api/m4/v1/reservas', ['id_comercio' => 1, 'id_variacion' => 10, 'cantidad' => $cantidad])
            ->assertCreated()
            ->json('id_reserva');
    }

    // ------------------------------------------------------------ públicas (M1 y portal)

    public function test_op01_verificar_estado(): void
    {
        $this->assertCumpleContrato($this->getJson('/api/m4/v1/health'), 'GET', '/health', 200);
    }

    public function test_op02_consultar_stock_disponible(): void
    {
        $ruta = '/existencias/disponibilidad';
        $cuerpo = $this->assertCumpleContrato($this->getJson("/api/m4/v1{$ruta}?id_comercio=1&id_variacion=10"), 'GET', $ruta, 200);
        $this->assertSame(10, $cuerpo['cantidad_disponible']);

        $this->assertErrorContrato($this->getJson("/api/m4/v1{$ruta}?id_comercio=1"), 'GET', $ruta, 422, ['ERR-005']);
    }

    public function test_op03_consultar_disponibilidad_lote(): void
    {
        $ruta = '/existencias/disponibilidad/lote';
        $this->assertCumpleContrato($this->postJson("/api/m4/v1{$ruta}", [
            'items' => [['id_comercio' => 1, 'id_variacion' => 10], ['id_comercio' => 1, 'id_variacion' => 99]],
        ]), 'POST', $ruta, 200);

        $this->assertErrorContrato($this->postJson("/api/m4/v1{$ruta}", ['items' => []]), 'POST', $ruta, 422, ['ERR-005']);
    }

    public function test_op04_consultar_disponibilidad_producto(): void
    {
        $ruta = '/existencias/disponibilidad/producto';
        $this->assertCumpleContrato($this->getJson("/api/m4/v1{$ruta}?id_comercio=1&id_producto=300"), 'GET', $ruta, 200);
    }

    public function test_op05_consultar_disponibilidad_producto_lote(): void
    {
        $ruta = '/existencias/disponibilidad/producto/lote';
        $this->assertCumpleContrato($this->postJson("/api/m4/v1{$ruta}", [
            'items' => [['id_comercio' => 1, 'id_producto' => 300]],
        ]), 'POST', $ruta, 200);
    }

    // ------------------------------------------------------------ reservas (M5)

    public function test_op06_reservar_existencias_y_su_evento(): void
    {
        Event::fake([ExistenciaReservada::class]);
        $ruta = '/reservas';

        $this->assertCumpleContrato($this->comoEncargado()->postJson("/api/m4/v1{$ruta}", [
            'id_comercio' => 1, 'id_variacion' => 10, 'cantidad' => 3,
        ]), 'POST', $ruta, 201);
        $this->assertErrorContrato($this->postJson("/api/m4/v1{$ruta}", [
            'id_comercio' => 1, 'id_variacion' => 10, 'cantidad' => 50,
        ]), 'POST', $ruta, 409, ['ERR-M4-001']);

        Event::assertDispatched(ExistenciaReservada::class, fn (EventoInventario $e): bool => $this->eventoCumple('ExistenciaReservada', $e));
    }

    public function test_op07_obtener_reserva(): void
    {
        $id = $this->reservar();
        $this->assertCumpleContrato($this->getJson("/api/m4/v1/reservas/{$id}"), 'GET', '/reservas/{id_reserva}', 200);
        $this->assertErrorContrato(
            $this->getJson('/api/m4/v1/reservas/00000000-0000-4000-8000-000000000000'),
            'GET', '/reservas/{id_reserva}', 404, ['ERR-M4-004', 'ERR-003'],
        );
    }

    public function test_op08_confirmar_reserva(): void
    {
        $id = $this->reservar();
        $cuerpo = $this->assertCumpleContrato(
            $this->postJson("/api/m4/v1/reservas/{$id}/confirmar", ['id_pedido' => 9001, 'id_subpedido' => 9101]),
            'POST', '/reservas/{id_reserva}/confirmar', 200,
        );
        $this->assertSame('confirmada', $cuerpo['estado_reserva']);
        $this->assertErrorContrato(
            $this->postJson("/api/m4/v1/reservas/{$id}/confirmar", ['id_pedido' => 9002, 'id_subpedido' => 9102]),
            'POST', '/reservas/{id_reserva}/confirmar', 409, ['ERR-M4-006', 'ERR-M4-007', 'ERR-M4-008'],
        );
    }

    public function test_op09_liberar_reserva(): void
    {
        $id = $this->reservar();
        $cuerpo = $this->assertCumpleContrato(
            $this->postJson("/api/m4/v1/reservas/{$id}/liberar", ['motivo' => 'pedido_cancelado']),
            'POST', '/reservas/{id_reserva}/liberar', 200,
        );
        $this->assertSame('liberada', $cuerpo['estado_reserva']);
        $this->assertErrorContrato(
            $this->postJson("/api/m4/v1/reservas/{$id}/liberar", ['motivo' => 'otro']),
            'POST', '/reservas/{id_reserva}/liberar', 422, ['ERR-005'],
        );
    }

    public function test_op10_confirmar_salida_por_reserva_y_su_evento(): void
    {
        Event::fake([SalidaInventarioConfirmada::class]);
        $id = $this->reservar();
        $this->postJson("/api/m4/v1/reservas/{$id}/confirmar", ['id_pedido' => 9001, 'id_subpedido' => 9101])->assertOk();

        $cuerpo = $this->assertCumpleContrato(
            $this->postJson("/api/m4/v1/reservas/{$id}/salida", ['id_pedido' => 9001, 'id_subpedido' => 9101]),
            'POST', '/reservas/{id_reserva}/salida', 201,
        );
        $this->assertEquals(17000, $cuerpo['monto_total']);
        Event::assertDispatched(SalidaInventarioConfirmada::class, fn (EventoInventario $e): bool => $this->eventoCumple('SalidaInventarioConfirmada', $e));
    }

    public function test_op11_registrar_reingreso_por_reserva_y_su_evento(): void
    {
        Event::fake([ExistenciaRepuesta::class]);
        $id = $this->reservar(10); // agota la variación: el reingreso la repone (ExistenciaRepuesta)
        $this->postJson("/api/m4/v1/reservas/{$id}/confirmar", ['id_pedido' => 9001, 'id_subpedido' => 9101])->assertOk();
        $this->postJson("/api/m4/v1/reservas/{$id}/salida", ['id_pedido' => 9001, 'id_subpedido' => 9101])->assertCreated();

        $this->assertCumpleContrato($this->postJson("/api/m4/v1/reservas/{$id}/reingreso", [
            'id_pedido' => 9001, 'id_subpedido' => 9101, 'id_recepcion' => 'REC-1', 'justificacion' => 'Devolución del cliente',
        ]), 'POST', '/reservas/{id_reserva}/reingreso', 201);
        $this->assertErrorContrato($this->postJson("/api/m4/v1/reservas/{$id}/reingreso", [
            'id_pedido' => 9001, 'id_subpedido' => 9101, 'justificacion' => 'Sin recepción',
        ]), 'POST', '/reservas/{id_reserva}/reingreso', 422, ['ERR-005']);

        Event::assertDispatched(ExistenciaRepuesta::class, fn (EventoInventario $e): bool => $this->eventoCumple('ExistenciaRepuesta', $e));
    }

    // ------------------------------------------------------------ panel del comercio

    public function test_op12_listar_existencias(): void
    {
        $ruta = '/existencias';
        $cuerpo = $this->assertCumpleContrato($this->comoEncargado()->getJson("/api/m4/v1{$ruta}?id_comercio=1"), 'GET', $ruta, 200);
        $this->assertSame(1, $cuerpo['paginacion']['total']);
    }

    public function test_op13_fijar_stock_minimo_y_alerta(): void
    {
        Event::fake([AlertaStockMinimo::class]);
        $ruta = '/existencias/{id_existencia}/stock-minimo';

        $this->assertCumpleContrato(
            $this->comoEncargado()->putJson("/api/m4/v1/existencias/{$this->idExistencia}/stock-minimo", ['stock_minimo' => 12]),
            'PUT', $ruta, 200,
        );
        $this->assertErrorContrato(
            $this->putJson('/api/m4/v1/existencias/999999/stock-minimo', ['stock_minimo' => 1]),
            'PUT', $ruta, 404, ['ERR-M4-002', 'ERR-003'],
        );
        Event::assertDispatched(AlertaStockMinimo::class, fn (EventoInventario $e): bool => $this->eventoCumple('AlertaStockMinimo', $e));
    }

    public function test_op14_registrar_movimiento_manual(): void
    {
        Event::fake([ExistenciaAgotada::class]);
        $ruta = '/movimientos';

        $this->assertCumpleContrato($this->comoEncargado()->postJson("/api/m4/v1{$ruta}", [
            'id_comercio' => 1, 'id_variacion' => 10, 'id_ubicacion' => 1,
            'tipo_movimiento' => 'entrada', 'cantidad' => 5, 'costo_unitario' => 9100, 'justificacion' => 'Compra a proveedor',
        ]), 'POST', $ruta, 201);
        $this->assertCumpleContrato($this->postJson("/api/m4/v1{$ruta}", [
            'id_comercio' => 1, 'id_variacion' => 10, 'id_ubicacion' => 1,
            'tipo_movimiento' => 'salida', 'cantidad' => 15, 'justificacion' => 'Venta en tienda',
        ]), 'POST', $ruta, 201);
        $this->assertErrorContrato($this->postJson("/api/m4/v1{$ruta}", [
            'id_comercio' => 1, 'id_variacion' => 10, 'id_ubicacion' => 1,
            'tipo_movimiento' => 'salida', 'cantidad' => 1, 'justificacion' => 'Sin existencias',
        ]), 'POST', $ruta, 409, ['ERR-M4-001']);

        Event::assertDispatched(ExistenciaAgotada::class, fn (EventoInventario $e): bool => $this->eventoCumple('ExistenciaAgotada', $e));
    }

    public function test_op15_registrar_ajuste_inventario(): void
    {
        config(['integraciones.m4.autorizaciones.registradas.autorizacion-contrato' => [
            'operacion' => VerificadorAutorizacionesInterface::OPERACION_AJUSTE, 'id_comercio' => 1, 'id_variacion' => 10,
            'ubicaciones' => [1], 'cantidad' => 1, 'id_autorizador' => 2,
            'fecha_autorizacion' => '2026-11-09T09:00:00-06:00', 'vence' => '2099-12-31T23:59:59-06:00',
        ]]);
        $ruta = '/ajustes';
        $this->assertCumpleContrato($this->comoEncargado()->postJson("/api/m4/v1{$ruta}", [
            'id_comercio' => 1, 'id_variacion' => 10, 'id_ubicacion' => 1, 'cantidad_sistema_vista' => 10, 'cantidad_contada' => 9,
            'motivo_ajuste' => 'merma', 'justificacion' => 'Conteo mensual', 'id_autorizacion' => 'autorizacion-contrato',
        ]), 'POST', $ruta, 201);
        $this->assertErrorContrato($this->postJson("/api/m4/v1{$ruta}", [
            'id_comercio' => 1, 'id_variacion' => 99, 'id_ubicacion' => 1,
            'cantidad_sistema_vista' => 0, 'cantidad_contada' => 1, 'motivo_ajuste' => 'sobrante', 'justificacion' => 'Conteo',
        ]), 'POST', $ruta, 404, ['ERR-M4-002']);
    }

    public function test_op16_consultar_kardex(): void
    {
        $ruta = '/kardex';
        $this->assertCumpleContrato($this->comoEncargado()->getJson("/api/m4/v1{$ruta}?id_comercio=1&id_variacion=10"), 'GET', $ruta, 200);
        $this->assertErrorContrato($this->getJson("/api/m4/v1{$ruta}?id_comercio=1"), 'GET', $ruta, 422, ['ERR-005']);
    }

    public function test_op17_obtener_kardex_valorizado(): void
    {
        $ruta = '/kardex/valorizado';
        $hoy = now()->toDateString();
        $this->assertCumpleContrato(
            $this->comoEncargado()->getJson("/api/m4/v1{$ruta}?id_comercio=1&fecha_inicio={$hoy}&fecha_fin={$hoy}"),
            'GET', $ruta, 200,
        );
        $this->assertErrorContrato($this->getJson("/api/m4/v1{$ruta}?id_comercio=1"), 'GET', $ruta, 422, ['ERR-005']);
    }

    public function test_op18_obtener_costo_productos_vendidos(): void
    {
        $id = $this->reservar(3);
        $this->postJson("/api/m4/v1/reservas/{$id}/confirmar", ['id_pedido' => 9001, 'id_subpedido' => 9101])->assertOk();
        $this->postJson("/api/m4/v1/reservas/{$id}/salida", ['id_pedido' => 9001, 'id_subpedido' => 9101])->assertCreated();

        $ruta = '/subpedidos/{id_subpedido}/costos-productos-vendidos';
        $cuerpo = $this->assertCumpleContrato($this->getJson('/api/m4/v1/subpedidos/9101/costos-productos-vendidos'), 'GET', $ruta, 200);
        $this->assertEquals(25500, $cuerpo['costo_total']);
        $this->assertErrorContrato($this->getJson('/api/m4/v1/subpedidos/9999/costos-productos-vendidos'), 'GET', $ruta, 404, ['ERR-M4-018', 'ERR-003', 'ERR-004']);
    }

    public function test_op19_consultar_inventario_auditoria(): void
    {
        $plataforma = new Usuario(['nombre' => 'Plataforma', 'correo' => 'plataforma@prueba.cr', 'rol' => 'encargado_plataforma', 'activo' => true]);
        $plataforma->setAttribute('id_usuario', 2);

        $ruta = '/auditoria/inventario';
        $this->assertCumpleContrato($this->actingAs($plataforma, 'sanctum')->getJson("/api/m4/v1{$ruta}?ids_comercio=1"), 'GET', $ruta, 200);
        $this->assertErrorContrato($this->getJson("/api/m4/v1{$ruta}"), 'GET', $ruta, 422, ['ERR-005']);
    }

    public function test_op20_registrar_transferencia(): void
    {
        foreach (['autorizacion-traslado-contrato' => 3, 'autorizacion-traslado-excedido' => 30] as $id => $cantidad) {
            config(["integraciones.m4.autorizaciones.registradas.{$id}" => [
                'operacion' => VerificadorAutorizacionesInterface::OPERACION_TRANSFERENCIA, 'id_comercio' => 1, 'id_variacion' => 10,
                'ubicaciones' => [1, 2], 'cantidad' => $cantidad, 'id_autorizador' => 2,
                'fecha_autorizacion' => '2026-11-09T09:00:00-06:00', 'vence' => '2099-12-31T23:59:59-06:00',
            ]]);
        }
        $ruta = '/transferencias';
        $solicitud = [
            'id_comercio' => 1, 'id_variacion' => 10, 'id_ubicacion_origen' => 1, 'id_ubicacion_destino' => 2,
            'cantidad' => 3, 'justificacion' => 'Reposición de la bodega', 'id_autorizacion' => 'autorizacion-traslado-contrato',
        ];

        $cuerpo = $this->assertCumpleContrato($this->comoEncargado()->postJson("/api/m4/v1{$ruta}", $solicitud), 'POST', $ruta, 201);
        $this->assertSame(['transferencia_salida', 'transferencia_entrada'], array_column($cuerpo['movimientos'], 'tipo_movimiento'));
        $this->assertSame([$cuerpo['id_transferencia'], $cuerpo['id_transferencia']], array_column($cuerpo['movimientos'], 'referencia_id'));

        $this->assertErrorContrato($this->postJson("/api/m4/v1{$ruta}", ['cantidad' => 30, 'id_autorizacion' => 'autorizacion-traslado-excedido'] + $solicitud), 'POST', $ruta, 409, ['ERR-M4-001']);
        $this->assertErrorContrato($this->postJson("/api/m4/v1{$ruta}", ['id_ubicacion_destino' => 77] + $solicitud), 'POST', $ruta, 404, ['ERR-M4-014']);
        $this->assertErrorContrato($this->postJson("/api/m4/v1{$ruta}", ['id_ubicacion_destino' => 1] + $solicitud), 'POST', $ruta, 422, ['ERR-005']);
        $this->assertErrorContrato($this->postJson("/api/m4/v1{$ruta}", $solicitud), 'POST', $ruta, 403, ['ERR-002']);
    }

    // ------------------------------------------------------------ comunes

    public function test_sin_sesion_el_panel_responde_err_001(): void
    {
        $this->assertErrorContrato($this->getJson('/api/m4/v1/existencias?id_comercio=1'), 'GET', '/existencias', 401, ['ERR-001']);
    }

    public function test_el_proceso_de_vencimiento_publica_reserva_vencida_con_su_esquema(): void
    {
        Event::fake([ReservaVencida::class]);
        $this->reservar();
        Carbon::setTestNow(now()->addMinutes(30));

        app()->call([new VencerReservasJob, 'handle']);

        Event::assertDispatched(ReservaVencida::class, fn (EventoInventario $e): bool => $this->eventoCumple('ReservaVencida', $e));
        Carbon::setTestNow();
    }

    private function eventoCumple(string $nombre, EventoInventario $evento): bool
    {
        $this->assertEventoCumpleContrato($nombre, $evento->payload());

        return true;
    }
}
