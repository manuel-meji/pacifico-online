<?php

declare(strict_types=1);

namespace Tests\Feature\M6_Pagos;

use App\Events\M6_Pagos\PagoConfirmado;
use App\Services\M6_Pagos\Clients\ContabilidadClient;
use App\Services\M6_Pagos\Clients\PedidoClient;
use Illuminate\Support\Facades\Event;
use Tests\Feature\M6_Pagos\Concerns\PreparaM6;
use Tests\TestCase;

/**
 * PC4, Sección 6.2 — Pruebas de aceptación de M6.
 *
 * Cada prueba recorre un criterio de aceptación completo por la API pública
 * (/api/m6/v1), como lo haría el comprador o la pasarela, y verifica el efecto
 * en los módulos vecinos a través de sus dobles (M5 y M7).
 *
 * Formato Dado / Cuando / Entonces del plan de pruebas (CA-M6-01 y CA-M6-02).
 */
class AceptacionM6Test extends TestCase
{
    use PreparaM6;

    /**
     * CA-M6-01 · CU-001 + CU-002 · RN-M6-01
     * Dado un pedido confirmado por M5 por ₡59 840,
     * cuando el comprador lo paga con tarjeta y luego consulta el pago,
     * entonces el pago queda "confirmado", M5 recibe el aviso, M7 el hecho
     * financiero y la bitácora del Núcleo registra la operación.
     */
    public function test_ca_m6_01_el_comprador_paga_un_pedido_confirmado_de_extremo_a_extremo(): void
    {
        Event::fake([PagoConfirmado::class]);

        // Cuando: el comprador paga.
        $pago = $this->procesarPago(['token_medio_pago' => 'tok_sim_aprobada'], 'ca-m6-01')
            ->assertCreated()
            ->assertJsonPath('estado', 'confirmado')
            ->assertJsonPath('id_pedido', self::PEDIDO_CONFIRMADO);

        // Y consulta el estado de su pago.
        $this->getJson('/api/m6/v1/pagos/'.$pago->json('id'))
            ->assertOk()
            ->assertJsonPath('estado', 'confirmado')
            ->assertJsonPath('referencia_proveedor', $pago->json('referencia_proveedor'));

        // Entonces: los efectos se publicaron una sola vez.
        Event::assertDispatchedTimes(PagoConfirmado::class, 1);
        // (El campo "motivo" no se verifica aquí: ver DEF-M6-02 en la Sección 9.1.)
        $avisosM5 = app(PedidoClient::class)->notificaciones;
        $this->assertCount(1, $avisosM5);
        $this->assertSame($pago->json('id'), $avisosM5[0]['idPago']);
        $this->assertSame('confirmado', $avisosM5[0]['estado']);
        $this->assertSame([[$pago->json('id'), 'PagoConfirmado']], app(ContabilidadClient::class)->eventos);
        $this->assertDatabaseHas('bitacora_auditoria', ['accion' => 'confirmar_pago', 'modulo' => 'M6_Pagos']);
    }

    /**
     * CA-M6-02 · CU-002 · RN-M6-02 · Verificación "Idempotencia del pago" del PC4
     * Dado un pago pendiente que la pasarela confirma por webhook,
     * cuando la pasarela envía dos veces la misma confirmación,
     * entonces el pedido se marca pagado una sola vez y el asiento de M7
     * no se duplica; la segunda respuesta se marca como duplicada.
     */
    public function test_ca_m6_02_la_misma_confirmacion_dos_veces_no_duplica_pedido_ni_asiento(): void
    {
        config(['m6.confirmacion_inmediata' => false]);
        Event::fake([PagoConfirmado::class]);

        // Dado: un pago pendiente con la referencia que asignó la pasarela.
        $pago = $this->procesarPago([], 'ca-m6-02')->assertCreated()->assertJsonPath('estado', 'pendiente');
        $cuerpo = ['referencia_proveedor' => $pago->json('referencia_proveedor'), 'estado' => 'aprobado'];

        // Cuando: la pasarela envía la confirmación dos veces.
        $this->webhook($pago->json('id'), $cuerpo)->assertOk()->assertJsonPath('duplicada', false);
        $this->webhook($pago->json('id'), $cuerpo)->assertOk()->assertJsonPath('duplicada', true)
            ->assertJsonPath('estado', 'confirmado');

        // Entonces: un solo cambio de estado, un solo aviso a M5 y un solo evento a M7.
        Event::assertDispatchedTimes(PagoConfirmado::class, 1);
        $this->assertCount(1, app(PedidoClient::class)->notificaciones);
        $this->assertCount(1, app(ContabilidadClient::class)->eventos);
        $this->assertDatabaseCount('pagos', 1);
        $this->assertDatabaseCount('notificaciones_pago', 1);
    }
}
