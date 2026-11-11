<?php

declare(strict_types=1);

namespace Tests\Feature\M6_Pagos;

use App\Events\M6_Pagos\ReembolsoProcesado;
use App\Models\M6_Pagos\EstadoReembolso;
use App\Services\M6_Pagos\PagosService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Feature\M6_Pagos\Concerns\PreparaM6;
use Tests\TestCase;

/**
 * CU-007 Gestionar reembolso (RF-007, RN-006).
 * POST /api/m6/v1/pedidos/{id_pedido}/reembolsos
 *
 * Pedido 8942 (doble de M5): subpedido 1001 (comercio 11) = ₡35 400;
 * subpedido 1002 (comercio 12) = ₡24 440.
 * Decisión del subgrupo: un reembolso por subpedido, cada uno con su nota de crédito.
 */
class ReembolsosTest extends TestCase
{
    use PreparaM6;

    private const SUBPEDIDO_1001 = 1001;

    private const TOTAL_1001 = 35400.00;

    /** CA-M6-R01 · CU-007: reembolso parcial de un subpedido con su nota de crédito y el evento. */
    public function test_reembolso_parcial_genera_nota_de_credito_y_evento(): void
    {
        Event::fake([ReembolsoProcesado::class]);
        $this->procesarPago()->assertCreated();

        $this->reembolso(['id_subpedido' => self::SUBPEDIDO_1001, 'monto' => 5000, 'motivo' => 'Producto devuelto'])
            ->assertCreated()
            ->assertJsonPath('id_pedido', self::PEDIDO_CONFIRMADO)
            ->assertJsonPath('id_subpedido', self::SUBPEDIDO_1001)
            ->assertJsonPath('estado', 'procesado')
            ->assertJsonPath('nota_credito_id', fn ($id) => is_int($id));

        $this->assertDatabaseCount('reembolsos', 1);
        $this->assertDatabaseCount('notas_credito', 1);
        Event::assertDispatchedTimes(ReembolsoProcesado::class, 1);
        Event::assertDispatched(ReembolsoProcesado::class, fn (ReembolsoProcesado $e) => $e->idSubpedido === self::SUBPEDIDO_1001 && $e->idComercio === 11);
        $this->assertDatabaseHas('bitacora_auditoria', ['accion' => 'procesar_reembolso', 'modulo' => 'M6_Pagos']);
    }

    /** CA-M6-R02 · Decisión SG6: reembolso total → un reembolso y una nota por subpedido. */
    public function test_reembolso_total_crea_uno_por_subpedido(): void
    {
        Event::fake([ReembolsoProcesado::class]);
        $this->procesarPago()->assertCreated();

        $respuesta = $this->reembolso(['monto' => self::MONTO_PEDIDO, 'motivo' => 'Pedido cancelado'])->assertCreated();

        $this->assertCount(2, $respuesta->json());
        $this->assertEqualsCanonicalizing([1001, 1002], array_column($respuesta->json(), 'id_subpedido'));
        $this->assertDatabaseCount('notas_credito', 2);
        Event::assertDispatchedTimes(ReembolsoProcesado::class, 2);
    }

    /** CP-M6-R03 · RN-006: no se reembolsa más de lo pagado del subpedido → 422 ERR-M6-015. */
    public function test_reembolso_que_excede_el_monto_responde_err_015(): void
    {
        $this->procesarPago()->assertCreated();

        $this->reembolso(['id_subpedido' => self::SUBPEDIDO_1001, 'monto' => self::TOTAL_1001 + 0.01])
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-M6-015')
            ->assertJsonPath('error.detalles.motivo', 'REEMBOLSO_EXCEDE_MONTO');

        $this->assertDatabaseCount('reembolsos', 0);
    }

    /** CP-M6-R04 · RN-006: reembolsos sucesivos respetan lo ya reembolsado. */
    public function test_reembolsos_sucesivos_respetan_el_acumulado(): void
    {
        $this->procesarPago()->assertCreated();

        $this->reembolso(['id_subpedido' => self::SUBPEDIDO_1001, 'monto' => 20000])->assertCreated();

        $this->reembolso(['id_subpedido' => self::SUBPEDIDO_1001, 'monto' => 20000])
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-M6-015')
            ->assertJsonPath('error.detalles.disponible', fn ($disponible) => (float) $disponible === 15400.0);

        $this->reembolso(['id_subpedido' => self::SUBPEDIDO_1001, 'monto' => 15400])->assertCreated();
    }

    /** CP-M6-R05 · RN-006: sin pago confirmado no hay reembolso → 409 ERR-M6-014. */
    public function test_sin_pago_confirmado_responde_err_014(): void
    {
        $this->reembolso(['id_subpedido' => self::SUBPEDIDO_1001, 'monto' => 1000])
            ->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M6-014');

        $this->assertDatabaseCount('reembolsos', 0);
    }

    /** CP-M6-R06 · Decisión SG6: sin factura queda "solicitado" y se procesa al reintentar la facturación. */
    public function test_sin_factura_queda_solicitado_y_se_procesa_al_reintentar(): void
    {
        Event::fake([ReembolsoProcesado::class]);
        $this->procesarPago()->assertCreated();

        // Simula que la factura del subpedido 1001 no se pudo emitir (base de pruebas en memoria).
        DB::table('facturas_electronicas')->where('id_subpedido', self::SUBPEDIDO_1001)->delete();

        $this->reembolso(['id_subpedido' => self::SUBPEDIDO_1001, 'monto' => 5000])
            ->assertCreated()
            ->assertJsonPath('estado', 'aceptado')
            ->assertJsonPath('nota_credito_id', null);

        Event::assertNotDispatched(ReembolsoProcesado::class);

        app(PagosService::class)->generarFactura(self::PEDIDO_CONFIRMADO);

        $this->assertDatabaseHas('reembolsos', [
            'id_subpedido' => self::SUBPEDIDO_1001,
            'id_estado_reembolso' => EstadoReembolso::idDe(EstadoReembolso::PROCESADO),
        ]);
        $this->assertDatabaseCount('notas_credito', 1);
        Event::assertDispatchedTimes(ReembolsoProcesado::class, 1);
    }

    /** CP-M6-R07 · La fachada que usa M5 (solicitarReembolso) procesa el reembolso del subpedido. */
    public function test_la_fachada_para_m5_procesa_el_reembolso(): void
    {
        $this->procesarPago()->assertCreated();

        $respuesta = app(PagosService::class)->solicitarReembolso(self::PEDIDO_CONFIRMADO, 5000, 'Cancelación de subpedido', self::SUBPEDIDO_1001);

        $this->assertSame('procesado', $respuesta['estado']);
        $this->assertSame(5000.0, $respuesta['monto_reembolsado']);
        $this->assertDatabaseCount('notas_credito', 1);
    }

    /** @param array<string, mixed> $cuerpo */
    private function reembolso(array $cuerpo, int $idPedido = self::PEDIDO_CONFIRMADO)
    {
        return $this->postJson("/api/m6/v1/pedidos/{$idPedido}/reembolsos", $cuerpo + ['motivo' => 'Cancelación de prueba']);
    }
}
