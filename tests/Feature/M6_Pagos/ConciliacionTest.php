<?php

declare(strict_types=1);

namespace Tests\Feature\M6_Pagos;

use App\Contracts\M6_Pagos\PasarelaPagoInterface;
use App\Models\M6_Pagos\TipoDiferencia;
use App\Services\M6_Pagos\Clients\ContabilidadClient;
use Tests\Doubles\M7\FakeContabilidadClient;
use Tests\Feature\M6_Pagos\Concerns\PreparaM6;
use Tests\TestCase;

/**
 * CU-003 Conciliar pagos del período (RF-003, RN-007).
 * POST /api/m6/v1/conciliaciones
 *
 * Las diferencias se provocan con una pasarela falsa que reporta lo que la prueba indica.
 */
class ConciliacionTest extends TestCase
{
    use PreparaM6;

    /** CA-M6-C01 · CU-003: sin diferencias y con M7 confirmando → "completa". */
    public function test_conciliacion_sin_diferencias(): void
    {
        $this->procesarPago()->assertCreated();

        $this->conciliarHoy()
            ->assertOk()
            ->assertJsonPath('estado', 'completa')
            ->assertJsonPath('total_cobrado', fn ($total) => (float) $total === self::MONTO_PEDIDO)
            ->assertJsonCount(0, 'diferencias');

        $this->assertDatabaseCount('conciliaciones', 1);
        $this->assertDatabaseCount('diferencias_conciliacion', 0);
    }

    /** CP-M6-C02 · RN-007: la pasarela reporta otro monto → monto_distinto. */
    public function test_detecta_monto_distinto(): void
    {
        $referencia = $this->procesarPago()->assertCreated()->json('referencia_proveedor');
        $this->pasarelaQueReporta([['referencia_proveedor' => $referencia, 'monto' => 59000.00]]);

        $this->conciliarHoy()
            ->assertOk()
            ->assertJsonCount(1, 'diferencias')
            ->assertJsonPath('diferencias.0.monto_sistema', fn ($m) => (float) $m === self::MONTO_PEDIDO)
            ->assertJsonPath('diferencias.0.monto_pasarela', fn ($m) => (float) $m === 59000.0)
            ->assertJsonPath('diferencias.0.estado', 'pendiente_de_revision');

        $this->assertDatabaseHas('diferencias_conciliacion', ['id_tipo_diferencia' => TipoDiferencia::idDe(TipoDiferencia::MONTO_DISTINTO)]);
    }

    /** CP-M6-C03 · RN-007: el pago no aparece en la pasarela → faltante_en_pasarela. */
    public function test_detecta_faltante_en_pasarela(): void
    {
        $this->procesarPago()->assertCreated();
        $this->pasarelaQueReporta([]);

        $this->conciliarHoy()
            ->assertOk()
            ->assertJsonCount(1, 'diferencias')
            ->assertJsonPath('diferencias.0.monto_pasarela', fn ($m) => (float) $m === 0.0);

        $this->assertDatabaseHas('diferencias_conciliacion', ['id_tipo_diferencia' => TipoDiferencia::idDe(TipoDiferencia::FALTANTE_EN_PASARELA)]);
    }

    /** CP-M6-C04 · RN-007: la pasarela reporta un cobro que en M6 sigue pendiente → faltante_en_sistema. */
    public function test_detecta_faltante_en_sistema(): void
    {
        config(['m6.confirmacion_inmediata' => false]);
        $pago = $this->procesarPago()->assertCreated()->assertJsonPath('estado', 'pendiente');
        $this->pasarelaQueReporta([['referencia_proveedor' => $pago->json('referencia_proveedor'), 'monto' => self::MONTO_PEDIDO]]);

        $this->conciliarHoy()
            ->assertOk()
            ->assertJsonCount(1, 'diferencias')
            ->assertJsonPath('diferencias.0.id_pago', $pago->json('id'))
            ->assertJsonPath('diferencias.0.monto_sistema', fn ($m) => (float) $m === 0.0);

        $this->assertDatabaseHas('diferencias_conciliacion', ['id_tipo_diferencia' => TipoDiferencia::idDe(TipoDiferencia::FALTANTE_EN_SISTEMA)]);
    }

    /** CP-M6-C05 · CU-003 flujo alterno: M7 no responde → la conciliación queda "parcial" sin fallar. */
    public function test_m7_caido_deja_la_conciliacion_parcial(): void
    {
        $this->procesarPago()->assertCreated();
        $this->app->instance(ContabilidadClient::class, new FakeContabilidadClient(false));

        $this->conciliarHoy()
            ->assertOk()
            ->assertJsonPath('estado', 'parcial');

        $this->assertDatabaseCount('conciliaciones', 1);
    }

    /** CP-M6-C06 · Período con fecha final anterior a la inicial → 422 ERR-M6-019. */
    public function test_periodo_invalido_responde_err_019(): void
    {
        $this->postJson('/api/m6/v1/conciliaciones', ['fecha_inicio' => '2026-11-10', 'fecha_fin' => '2026-11-01'])
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-M6-019')
            ->assertJsonPath('error.detalles.motivo', 'PERIODO_INVALIDO');

        $this->assertDatabaseCount('conciliaciones', 0);
    }

    private function conciliarHoy()
    {
        $hoy = now()->toDateString();

        return $this->postJson('/api/m6/v1/conciliaciones', ['fecha_inicio' => $hoy, 'fecha_fin' => $hoy]);
    }

    /** @param list<array{referencia_proveedor: string, monto: float}> $movimientos */
    private function pasarelaQueReporta(array $movimientos): void
    {
        $this->app->instance(PasarelaPagoInterface::class, new class($movimientos) implements PasarelaPagoInterface
        {
            public function __construct(private readonly array $movimientos) {}

            public function procesarCobro(string $referencia, float $monto, array $datosPago): array
            {
                return ['exitoso' => true, 'referencia_proveedor' => 'NO-USADA'];
            }

            public function reportarMovimientos(string $fechaInicio, string $fechaFin): array
            {
                return $this->movimientos;
            }
        });
    }
}
