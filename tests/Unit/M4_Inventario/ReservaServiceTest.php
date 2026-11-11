<?php

namespace Tests\Unit\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\PedidosDependencia;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Events\M4_Inventario\ReservaVencida;
use App\Events\M4_Inventario\SalidaInventarioConfirmada;
use App\Jobs\M4_Inventario\VencerReservasJob;
use App\Services\M4_Inventario\Dobles\PedidosStub;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Feature\M4_Inventario\EsquemaInventarioPrueba;
use Tests\TestCase;

/**
 * Reglas del ciclo de vida de la reserva en ReservaService (por InventarioModuleInterface):
 * reserva, confirmación, salida por despacho, reingreso y vencimiento.
 * Casos de prueba CP-M4-05 a CP-M4-09 (sección 5 del PC4).
 */
class ReservaServiceTest extends TestCase
{
    use EsquemaInventarioPrueba;

    private int $idExistencia;

    protected function setUp(): void
    {
        parent::setUp();
        config(['integraciones.m4.nucleo.modo' => 'real']);
        $this->crearEsquemaNucleo();
        $this->crearEsquemaInventario();
        $this->app->instance(PedidosDependencia::class, (new PedidosStub)
            ->registrar(9101, 9001, 1, 'despachado')
            ->registrar(9102, 9002, 1, 'pendiente'));
        $this->idExistencia = $this->existencia(['id_variacion' => 10, 'id_ubicacion' => 1, 'cantidad_fisica' => 5, 'costo_promedio' => 8500]);
    }

    private function inventario(): InventarioModuleInterface
    {
        return app(InventarioModuleInterface::class);
    }

    /** @return array<string, mixed> */
    private function reservar(int $cantidad = 2, int $minutos = 15): array
    {
        return $this->inventario()->reservarExistencias(1, 10, $cantidad, 1, null, $minutos);
    }

    private function fila(): object
    {
        return DB::table('existencias')->where('id_existencia', $this->idExistencia)->first();
    }

    private function esperarError(string $codigo, callable $accion): void
    {
        try {
            $accion();
            $this->fail("Debió responder {$codigo}.");
        } catch (ErrorInventarioException $e) {
            $this->assertSame($codigo, $e->codigo);
        }
    }

    /** CP-M4-05 · RN-M4-005, RN-M4-006: la reserva compromete unidades sin tocar el físico y queda en el kardex. */
    public function test_la_reserva_compromete_unidades_y_se_traza_en_el_kardex(): void
    {
        $reserva = $this->reservar(2);

        $this->assertSame('activa', $reserva['estado_reserva']);
        $this->assertSame(5, (int) $this->fila()->cantidad_fisica);
        $this->assertSame(2, (int) $this->fila()->cantidad_reservada);
        $this->assertSame(3, $this->inventario()->consultarStockDisponible(1, 10)['cantidad_disponible']);
        $this->assertSame(['reserva', 'ninguno'], [
            DB::table('movimientos_inventario')->value('tipo'),
            DB::table('movimientos_inventario')->value('efecto'),
        ]);
        $this->esperarError('ERR-M4-001', fn () => $this->reservar(4));
    }

    /** CP-M4-06 · RN-M4-010: confirmar pasa a CONFIRMADA; repetir con el mismo vínculo no cambia nada y con otro, ERR-M4-007. */
    public function test_confirmar_es_idempotente_y_rechaza_otro_vinculo(): void
    {
        $id = $this->reservar()['id_reserva'];

        $confirmada = $this->inventario()->confirmarReserva($id, 9001, 9101);
        $repetida = $this->inventario()->confirmarReserva($id, 9001, 9101);

        $this->assertSame('confirmada', $confirmada['estado_reserva']);
        $this->assertSame($confirmada, $repetida);
        $this->assertSame(1, DB::table('movimientos_inventario')->where('tipo', 'confirmacion_reserva')->count());
        $this->esperarError('ERR-M4-007', fn () => $this->inventario()->confirmarReserva($id, 9002, 9102));
    }

    /** CP-M4-07 · RN-M4-007, RN-M4-008: la salida descuenta físico y reservado, se valoriza y se publica una sola vez. */
    public function test_la_salida_por_despacho_consume_la_reserva_una_sola_vez(): void
    {
        Event::fake([SalidaInventarioConfirmada::class]);
        $id = $this->reservar(2)['id_reserva'];
        $this->esperarError('ERR-M4-006', fn () => $this->inventario()->confirmarSalidaPorReserva($id, 9001, 9101));
        $this->inventario()->confirmarReserva($id, 9001, 9101);

        $salida = $this->inventario()->confirmarSalidaPorReserva($id, 9001, 9101);
        $repetida = $this->inventario()->confirmarSalidaPorReserva($id, 9001, 9101);

        $this->assertSame($salida['id_movimiento'], $repetida['id_movimiento']);
        $this->assertEquals(17000.0, $salida['monto_total']);
        $this->assertSame([3, 0], [(int) $this->fila()->cantidad_fisica, (int) $this->fila()->cantidad_reservada]);
        $this->assertSame('consumida', $this->inventario()->obtenerReserva($id)['estado_reserva']);
        Event::assertDispatchedTimes(SalidaInventarioConfirmada::class, 1);
        $this->assertSame(17000.0, $this->inventario()->obtenerCostoProductosVendidos(9101)['costo_total']);
    }

    /** CP-M4-08 · RN-M4-003: el reingreso entra al costo de la salida original y no se registra dos veces. */
    public function test_el_reingreso_devuelve_las_unidades_al_costo_de_la_salida(): void
    {
        $id = $this->reservar(2)['id_reserva'];
        $this->inventario()->confirmarReserva($id, 9001, 9101);
        $this->inventario()->confirmarSalidaPorReserva($id, 9001, 9101);
        $solicitud = ['id_pedido' => 9001, 'id_subpedido' => 9101, 'id_recepcion' => 'recepcion-0001', 'justificacion' => 'Devolución completa verificada'];

        $reingreso = $this->inventario()->registrarReingresoPorReserva($id, $solicitud);

        $this->assertSame('reingreso', $reingreso['tipo_movimiento']);
        $this->assertEquals(8500.0, $reingreso['costo_unitario']);
        $this->assertSame(5, (int) $this->fila()->cantidad_fisica);
        $this->esperarError('ERR-M4-010', fn () => $this->inventario()->registrarReingresoPorReserva($id, $solicitud));
    }

    /** CP-M4-09 · RN-M4-002, RN-M4-010, RN-M4-014: el vencimiento libera solo las ACTIVA, una vez, y publica ReservaVencida. */
    public function test_el_vencimiento_libera_las_activas_vencidas_una_sola_vez(): void
    {
        Event::fake([ReservaVencida::class]);
        $activa = $this->reservar(1, minutos: 1)['id_reserva'];
        $confirmada = $this->reservar(2, minutos: 1)['id_reserva'];
        $this->inventario()->confirmarReserva($confirmada, 9001, 9101);
        Carbon::setTestNow(Carbon::now()->addMinutes(2));

        $this->assertSame(1, VencerReservasJob::dispatchSync());
        $this->assertSame(0, VencerReservasJob::dispatchSync());

        $this->assertSame('vencida', $this->inventario()->obtenerReserva($activa)['estado_reserva']);
        $this->assertSame('confirmada', $this->inventario()->obtenerReserva($confirmada)['estado_reserva']);
        $this->assertSame(2, (int) $this->fila()->cantidad_reservada);
        Event::assertDispatchedTimes(ReservaVencida::class, 1);
        $this->esperarError('ERR-M4-005', fn () => $this->inventario()->confirmarReserva($activa, 9001, 9101));
        Carbon::setTestNow();
    }

    /** CP-M4-25 · RN-M4-014, RN-M4-013: confirmar una reserva cuyo plazo venció antes de que corra el proceso la marca VENCIDA, devuelve las unidades y responde ERR-M4-005. */
    public function test_confirmar_una_reserva_vencida_sin_procesar_la_vence_y_responde_err_m4_005(): void
    {
        $id = $this->reservar(2, minutos: 1)['id_reserva'];
        Carbon::setTestNow(Carbon::now()->addMinutes(2));

        $this->esperarError('ERR-M4-005', fn () => $this->inventario()->confirmarReserva($id, 9001, 9101));

        $this->assertSame('vencida', $this->inventario()->obtenerReserva($id)['estado_reserva'], 'Se conserva en el historial (RN-M4-013).');
        $this->assertSame(0, (int) $this->fila()->cantidad_reservada);
        Carbon::setTestNow();
    }
}
