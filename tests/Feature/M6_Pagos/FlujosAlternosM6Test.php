<?php

declare(strict_types=1);

namespace Tests\Feature\M6_Pagos;

use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Services\M6_Pagos\Clients\ContabilidadClient;
use App\Services\M6_Pagos\Clients\PedidoClient;
use App\Services\M6_Pagos\ProcesamientoPagosService;
use App\Services\Nucleo\ContextoCliente;
use Tests\Feature\M6_Pagos\Concerns\PreparaM6;
use Tests\TestCase;

/**
 * PC4, Sección 8 — Pruebas que cierran zonas sin cubrir del análisis de cobertura:
 * flujos alternos de CU-001, aislamiento RN-12 / RN-M6-08 y la fachada que usa M5
 * cuando el cobro es rechazado.
 */
class FlujosAlternosM6Test extends TestCase
{
    use PreparaM6;

    /** CP-M6-F01 · CU-001 flujo alterno: la pasarela no responde → el pago queda pendiente. */
    public function test_si_la_pasarela_no_responde_el_pago_queda_pendiente_sin_avisar_a_m5(): void
    {
        $this->procesarPago(['token_medio_pago' => 'tok_sim_timeout'])
            ->assertCreated()
            ->assertJsonPath('estado', 'pendiente')
            ->assertJsonPath('referencia_proveedor', null);

        // Sin resultado de la pasarela no se publica nada hacia M5 ni M7.
        $this->assertSame([], app(PedidoClient::class)->notificaciones);
        $this->assertSame([], app(ContabilidadClient::class)->eventos);
    }

    /** CP-M6-F02 · RN-12 / RN-M6-08: un cliente no puede consultar el pago de otro (404, no 403). */
    public function test_un_cliente_no_puede_consultar_el_pago_de_otro_cliente(): void
    {
        $id = $this->procesarPago()->assertCreated()->json('id');

        // El pedido 8942 pertenece al cliente 77; en sesión está el cliente 78.
        app(ContextoCliente::class)->establecer(78);

        try {
            app(ProcesamientoPagosService::class)->consultar($id);
            $this->fail('Debió responder RECURSO_NO_ACCESIBLE.');
        } catch (ErrorNucleoException $e) {
            $this->assertSame(404, $e->getCodigoHttp());
        }
    }

    /** CP-M6-F03 · RN-12: el dueño del pago sí puede consultarlo. */
    public function test_el_cliente_dueno_si_puede_consultar_su_pago(): void
    {
        $id = $this->procesarPago()->assertCreated()->json('id');

        app(ContextoCliente::class)->establecer(77);

        $this->assertSame($id, app(ProcesamientoPagosService::class)->consultar($id)->id_pago);
    }

    /** CP-M6-F04 · Contrato M5-M6: la fachada lanza PAGO_RECHAZADO (ERR-M6-001) cuando la pasarela rechaza. */
    public function test_la_fachada_que_usa_m5_lanza_pago_rechazado(): void
    {
        try {
            app(PagosServiceInterface::class)->procesarPago(
                self::PEDIDO_CONFIRMADO,
                self::MONTO_PEDIDO,
                'tarjeta',
                ['idempotency_key' => 'm5-rechazo', 'token_medio_pago' => 'tok_sim_rechazada'],
            );
            $this->fail('Debió lanzar PAGO_RECHAZADO.');
        } catch (ErrorContratoException $e) {
            $this->assertSame('PAGO_RECHAZADO', $e->codigo);
            $this->assertSame('ERR-M6-001', $e->getCodigo());
            $this->assertSame(402, $e->getCodigoHttp());
            $this->assertSame('rechazado', $e->getDetalles()['estado_pago']);
        }
    }

    /** CP-M6-F05 · ADR-005: si M7 falla al recibir el aviso, el cobro ya registrado no se deshace. */
    public function test_si_m7_falla_el_pago_igual_queda_confirmado(): void
    {
        $this->app->instance(ContabilidadClient::class, new class implements ContabilidadClient
        {
            public function enviarEventoFinanciero(int $idPago, string $tipo): void
            {
                throw new \RuntimeException('M7 no responde');
            }

            public function consultarRegistroContablePago(int $idPago): bool
            {
                return false;
            }

            public function subpedidosConCobro(int $idPago): ?array
            {
                return null;
            }
        });

        $this->procesarPago()
            ->assertCreated()
            ->assertJsonPath('estado', 'confirmado');

        // M5 recibió el aviso aunque M7 haya fallado.
        $this->assertSame('confirmado', app(PedidoClient::class)->notificaciones[0]['estado']);
    }
}
