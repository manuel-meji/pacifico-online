<?php

declare(strict_types=1);

namespace Tests\Feature\M6_Pagos;

use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Events\M6_Pagos\PagoConfirmado;
use App\Events\M6_Pagos\PagoRechazado;
use App\Services\M6_Pagos\Clients\ContabilidadClient;
use App\Services\M6_Pagos\Clients\PedidoClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Feature\M6_Pagos\Concerns\PreparaM6;
use Tests\TestCase;

/** CU-001 Procesar pago del pedido — POST /api/m6/v1/pagos */
class ProcesarPagoTest extends TestCase
{
    use PreparaM6;

    public function test_cobra_un_pedido_confirmado_y_publica_pago_confirmado(): void
    {
        Event::fake([PagoConfirmado::class, PagoRechazado::class]);

        $respuesta = $this->procesarPago();

        $respuesta->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'false')
            ->assertJsonPath('id_pedido', self::PEDIDO_CONFIRMADO)
            ->assertJsonPath('medio_pago', 'tarjeta')
            ->assertJsonPath('monto_total', fn ($monto) => (float) $monto === self::MONTO_PEDIDO)
            ->assertJsonPath('estado', 'confirmado');

        Event::assertDispatchedTimes(PagoConfirmado::class, 1);
        Event::assertNotDispatched(PagoRechazado::class);

        // M5 y M7 recibieron el aviso a través de sus puertos (dobles de Marenco).
        $this->assertSame('confirmado', app(PedidoClient::class)->notificaciones[0]['estado']);
        $this->assertCount(1, app(ContabilidadClient::class)->eventos);

        $this->assertDatabaseCount('pagos', 1);
        $this->assertDatabaseCount('notificaciones_pago', 1);
        $this->assertDatabaseHas('bitacora_auditoria', ['accion' => 'confirmar_pago', 'modulo' => 'M6_Pagos']);
    }

    public function test_reintento_con_la_misma_idempotency_key_no_cobra_dos_veces(): void
    {
        Event::fake([PagoConfirmado::class]);

        $primero = $this->procesarPago(clave: 'misma-clave');
        $segundo = $this->procesarPago(clave: 'misma-clave');

        $primero->assertCreated();
        $segundo->assertOk()->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('id', $primero->json('id'));

        $this->assertDatabaseCount('pagos', 1);
        Event::assertDispatchedTimes(PagoConfirmado::class, 1);
    }

    public function test_misma_clave_con_otro_monto_es_conflicto(): void
    {
        $this->procesarPago(clave: 'clave-x')->assertCreated();

        $this->procesarPago(['monto_total' => 100], 'clave-x')
            ->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M6-007')
            ->assertJsonPath('error.detalles.motivo', 'CLAVE_IDEMPOTENCIA_EN_CONFLICTO');
    }

    public function test_un_pedido_ya_pagado_no_admite_un_segundo_cobro_con_otra_clave(): void
    {
        $this->procesarPago(clave: 'clave-1')->assertCreated();

        $this->procesarPago(clave: 'clave-2')
            ->assertStatus(409)
            ->assertJsonPath('error.detalles.motivo', 'PEDIDO_CON_PAGO_ACTIVO');

        $this->assertDatabaseCount('pagos', 1);
    }

    public function test_rechaza_monto_distinto_al_informado_por_m5(): void
    {
        $this->procesarPago(['monto_total' => 1000])
            ->assertStatus(400)
            ->assertJsonPath('error.codigo', 'ERR-M6-003')
            ->assertJsonPath('error.detalles.motivo', 'MONTO_INCONSISTENTE');

        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_no_cobra_un_pedido_cancelado(): void
    {
        $this->procesarPago(['id_pedido' => self::PEDIDO_CANCELADO])
            ->assertStatus(409)
            ->assertJsonPath('error.detalles.motivo', 'PEDIDO_NO_CONFIRMADO');
    }

    public function test_pedido_inexistente_responde_404(): void
    {
        $this->procesarPago(['id_pedido' => 1])
            ->assertNotFound()
            ->assertJsonPath('error.detalles.motivo', 'PEDIDO_NO_ENCONTRADO');
    }

    public function test_medio_de_pago_no_habilitado(): void
    {
        $this->procesarPago(['medio_pago' => 'bitcoin'])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.motivo', 'MEDIO_PAGO_NO_DISPONIBLE');
    }

    public function test_sin_idempotency_key_ni_datos_responde_err_005(): void
    {
        $this->postJson('/api/m6/v1/pagos', [])
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonValidationErrors(['idempotency_key', 'id_pedido', 'medio_pago', 'monto_total'], 'error.detalles');
    }

    public function test_nunca_acepta_numero_de_tarjeta_ni_cvv(): void
    {
        $this->procesarPago(['numero_tarjeta' => '4111111111111111', 'cvv' => '123'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['numero_tarjeta', 'cvv'], 'error.detalles');
    }

    public function test_la_pasarela_rechaza_y_publica_pago_rechazado(): void
    {
        Event::fake([PagoConfirmado::class, PagoRechazado::class]);

        $this->procesarPago(['token_medio_pago' => 'tok_rechazado'])
            ->assertCreated()
            ->assertJsonPath('estado', 'rechazado');

        Event::assertDispatchedTimes(PagoRechazado::class, 1);
        Event::assertNotDispatched(PagoConfirmado::class);

        // Tras un rechazo el comprador puede intentar con otro medio y otra clave.
        $this->procesarPago(['medio_pago' => 'sinpe'], 'clave-nueva')
            ->assertCreated()
            ->assertJsonPath('estado', 'confirmado');
    }

    public function test_consultar_estado_del_pago(): void
    {
        $id = $this->procesarPago()->json('id');

        $this->getJson("/api/m6/v1/pagos/{$id}")
            ->assertOk()
            ->assertJsonPath('id', $id)
            ->assertJsonPath('estado', 'confirmado');

        $this->getJson('/api/m6/v1/pagos/999999')
            ->assertNotFound()
            ->assertJsonPath('error.detalles.motivo', 'PAGO_NO_ENCONTRADO');
    }

    public function test_la_fachada_que_consume_m5_mantiene_su_firma(): void
    {
        $resultado = app(PagosServiceInterface::class)
            ->procesarPago(self::PEDIDO_CONFIRMADO, self::MONTO_PEDIDO, 'tarjeta', ['idempotency_key' => 'desde-m5']);

        $this->assertSame('confirmado', $resultado['estado']);
        $this->assertArrayHasKey('urlPasarelaPago', $resultado);
        $this->assertSame(1, DB::table('pagos')->count());
    }
}
