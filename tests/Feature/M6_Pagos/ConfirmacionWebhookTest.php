<?php

declare(strict_types=1);

namespace Tests\Feature\M6_Pagos;

use App\Events\M6_Pagos\PagoConfirmado;
use App\Events\M6_Pagos\PagoRechazado;
use Illuminate\Support\Facades\Event;
use Tests\Feature\M6_Pagos\Concerns\PreparaM6;
use Tests\TestCase;

/**
 * CU-002 Confirmar pago recibido — POST /api/m6/v1/pagos/{id}/confirmaciones
 * Escenario ESC-04 (idempotencia del pago, ADR-003).
 */
class ConfirmacionWebhookTest extends TestCase
{
    use PreparaM6;

    protected function setUp(): void
    {
        parent::setUp();

        // El pago queda "pendiente": la confirmación llega solo por el webhook.
        config(['m6.confirmacion_inmediata' => false]);
    }

    public function test_el_webhook_confirma_el_pago_pendiente(): void
    {
        Event::fake([PagoConfirmado::class]);

        $pago = $this->procesarPago()->assertCreated()->assertJsonPath('estado', 'pendiente');
        $referencia = $pago->json('referencia_proveedor');

        $this->webhook($pago->json('id'), ['referencia_proveedor' => $referencia, 'estado' => 'aprobado'])
            ->assertOk()
            ->assertJsonPath('estado', 'confirmado')
            ->assertJsonPath('duplicada', false);

        Event::assertDispatchedTimes(PagoConfirmado::class, 1);
    }

    public function test_notificacion_repetida_no_publica_el_evento_dos_veces(): void
    {
        Event::fake([PagoConfirmado::class]);

        $pago = $this->procesarPago();
        $cuerpo = ['referencia_proveedor' => $pago->json('referencia_proveedor'), 'estado' => 'aprobado'];

        $this->webhook($pago->json('id'), $cuerpo)->assertOk()->assertJsonPath('duplicada', false);
        $this->webhook($pago->json('id'), $cuerpo)->assertOk()->assertJsonPath('duplicada', true);
        $this->webhook($pago->json('id'), $cuerpo)->assertOk()->assertJsonPath('duplicada', true);

        Event::assertDispatchedTimes(PagoConfirmado::class, 1);
        $this->assertDatabaseCount('notificaciones_pago', 1);
    }

    public function test_rechazo_tardio_de_un_pago_pendiente(): void
    {
        Event::fake([PagoConfirmado::class, PagoRechazado::class]);

        $pago = $this->procesarPago();

        $this->webhook($pago->json('id'), [
            'referencia_proveedor' => $pago->json('referencia_proveedor'),
            'estado' => 'rechazado',
            'motivo' => 'Fondos insuficientes',
        ])->assertOk()->assertJsonPath('estado', 'rechazado');

        Event::assertDispatched(PagoRechazado::class, fn (PagoRechazado $e) => $e->motivo === 'Fondos insuficientes');
        Event::assertNotDispatched(PagoConfirmado::class);
    }

    public function test_firma_invalida_responde_401(): void
    {
        $pago = $this->procesarPago();

        $this->webhook($pago->json('id'), ['referencia_proveedor' => 'X', 'estado' => 'aprobado'], 'firma-falsa')
            ->assertStatus(401)
            ->assertJsonPath('error.codigo', 'ERR-M6-010')
            ->assertJsonPath('error.detalles.motivo', 'NOTIFICACION_NO_AUTENTICA');

        $this->assertDatabaseCount('notificaciones_pago', 0);
    }

    public function test_referencia_distinta_a_la_asignada_por_la_pasarela_es_rechazada(): void
    {
        $pago = $this->procesarPago();

        $this->webhook($pago->json('id'), ['referencia_proveedor' => 'OTRA-REFERENCIA', 'estado' => 'aprobado'])
            ->assertStatus(401)
            ->assertJsonPath('error.detalles.motivo', 'NOTIFICACION_NO_AUTENTICA');
    }

    public function test_intento_inexistente_responde_404(): void
    {
        $this->webhook(424242, ['referencia_proveedor' => 'REF-1', 'estado' => 'aprobado'])
            ->assertNotFound()
            ->assertJsonPath('error.detalles.motivo', 'TRANSACCION_NO_ENCONTRADA');
    }

    public function test_estado_invalido_responde_err_005(): void
    {
        $pago = $this->procesarPago();

        $this->webhook($pago->json('id'), ['referencia_proveedor' => 'REF-1', 'estado' => 'quizas'])
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005');
    }
}
