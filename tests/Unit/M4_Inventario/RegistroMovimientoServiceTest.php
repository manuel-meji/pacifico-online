<?php

namespace Tests\Unit\M4_Inventario;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Events\M4_Inventario\AlertaStockMinimo;
use App\Models\M4_Inventario\MovimientoInventario;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LogicException;
use Tests\Feature\M4_Inventario\EsquemaInventarioPrueba;
use Tests\TestCase;

/**
 * Reglas de negocio de los movimientos manuales (CU-M4-01) en el servicio:
 * entrada con costo, salida sin tocar lo reservado y alerta de mínimo.
 * Casos de prueba CP-M4-01, CP-M4-02 y CP-M4-04 (sección 5 del PC4).
 */
class RegistroMovimientoServiceTest extends TestCase
{
    use EsquemaInventarioPrueba;

    protected function setUp(): void
    {
        parent::setUp();
        config(['integraciones.m4.nucleo.modo' => 'real']);
        $this->crearEsquemaNucleo();
        $this->crearEsquemaInventario();
    }

    private function inventario(): InventarioModuleInterface
    {
        return app(InventarioModuleInterface::class);
    }

    /** @return array<string, mixed> */
    private function movimiento(string $tipo, int $cantidad, ?float $costo = null, int $variacion = 10): array
    {
        return $this->inventario()->registrarMovimientoManual(array_filter([
            'id_comercio' => 1,
            'id_variacion' => $variacion,
            'id_ubicacion' => 1,
            'tipo_movimiento' => $tipo,
            'cantidad' => $cantidad,
            'costo_unitario' => $costo,
            'justificacion' => "Prueba de {$tipo}",
        ], fn ($valor): bool => $valor !== null));
    }

    /** CP-M4-01 · RN-M4-003: la entrada crea la existencia, suma el físico y deja una línea de kardex inmutable. */
    public function test_la_entrada_suma_el_fisico_y_asienta_el_kardex(): void
    {
        $movimiento = $this->movimiento('entrada', 20, 8500.0);

        $this->assertSame('entrada', $movimiento['tipo_movimiento']);
        $this->assertSame('aumenta', $movimiento['efecto_stock']);
        $this->assertSame(20, $movimiento['saldo_disponible']);
        $this->assertEquals(8500.0, $movimiento['costo_unitario']);
        $this->assertSame(20, (int) DB::table('existencias')->where('id_variacion', 10)->value('cantidad_fisica'));
        $this->assertSame(1, DB::table('kardex_inventario')->where('id_movimiento', $movimiento['id_movimiento'])->count());
        $this->assertEquals(170000.0, (float) DB::table('kardex_inventario')->value('monto_total'));
    }

    /** CP-M4-02 · RN-M4-001: una salida mayor que el disponible se rechaza y no toca las unidades reservadas. */
    public function test_la_salida_sin_existencias_disponibles_responde_err_m4_001(): void
    {
        $this->existencia(['id_variacion' => 10, 'id_ubicacion' => 1, 'cantidad_fisica' => 5, 'cantidad_reservada' => 3]);

        try {
            $this->movimiento('salida', 3);
            $this->fail('Debió responder ERR-M4-001.');
        } catch (ErrorInventarioException $e) {
            $this->assertSame('ERR-M4-001', $e->codigo);
            $this->assertSame(409, $e->http);
        }

        $this->assertSame(5, (int) DB::table('existencias')->where('id_variacion', 10)->value('cantidad_fisica'));
        $this->assertSame(0, DB::table('movimientos_inventario')->count());
    }

    /** CP-M4-03 · RN-M4-008: costo promedio ponderado; la salida sale al promedio vigente. */
    public function test_el_costo_promedio_pondera_las_entradas_y_valoriza_la_salida(): void
    {
        $this->movimiento('entrada', 10, 8000.0);
        $this->movimiento('entrada', 30, 9000.0);
        $salida = $this->movimiento('salida', 4);

        $this->assertEquals(8750.0, (float) DB::table('existencias')->where('id_variacion', 10)->value('costo_promedio'));
        $this->assertEquals(8750.0, $salida['costo_unitario']);
        $this->assertEquals(35000.0, (float) DB::table('kardex_inventario')->where('id_movimiento', $salida['id_movimiento'])->value('monto_total'));
    }

    /** CP-M4-04 · RN-M4-004: al bajar hasta el mínimo se publica AlertaStockMinimo una sola vez. */
    public function test_al_cruzar_el_stock_minimo_se_publica_la_alerta_una_vez(): void
    {
        Event::fake([AlertaStockMinimo::class]);
        $idExistencia = $this->existencia(['id_variacion' => 10, 'id_ubicacion' => 1, 'cantidad_fisica' => 10, 'stock_minimo' => 4, 'costo_promedio' => 8500]);

        $this->movimiento('salida', 5);
        Event::assertNotDispatched(AlertaStockMinimo::class);

        $this->movimiento('salida', 2);
        $this->movimiento('salida', 1);

        Event::assertDispatchedTimes(AlertaStockMinimo::class, 1);
        Event::assertDispatched(AlertaStockMinimo::class, fn (AlertaStockMinimo $e): bool => $e->payload()['cantidad_disponible'] === 3
            && $e->payload()['stock_minimo'] === 4
            && $e->payload()['id_ubicacion'] === 1
            && $idExistencia > 0);
        $this->assertSame(1, DB::table('bitacora_auditoria')->where('accion', 'alerta_stock_minimo')->count(), 'La alerta queda en el historial (RN-M4-004).');
    }

    /** CP-M4-10 · RN-M4-003: el kardex es inmutable; las correcciones son movimientos nuevos. */
    public function test_los_movimientos_y_el_kardex_no_se_pueden_modificar_ni_borrar(): void
    {
        $movimiento = $this->movimiento('entrada', 5, 1000.0);
        app(ContextoComercio::class)->establecer(1);
        $modelo = MovimientoInventario::query()->with('lineaKardex')->findOrFail($movimiento['id_movimiento']);

        foreach ([fn () => $modelo->update(['cantidad' => 50]), fn () => $modelo->delete(), fn () => $modelo->lineaKardex->update(['saldo_cantidad' => 0])] as $intento) {
            try {
                $intento();
                $this->fail('Debió rechazar la modificación.');
            } catch (LogicException $e) {
                $this->assertStringContainsString('RN-M4-003', $e->getMessage());
            }
        }

        $this->assertSame(5, (int) DB::table('movimientos_inventario')->value('cantidad'));
    }
}
