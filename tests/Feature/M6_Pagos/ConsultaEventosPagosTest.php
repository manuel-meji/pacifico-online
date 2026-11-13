<?php

declare(strict_types=1);

namespace Tests\Feature\M6_Pagos;

use App\Contracts\M6_Pagos\ConsultaEventosPagosInterface;
use App\Events\M6_Pagos\FacturaGenerada;
use App\Events\M6_Pagos\PagoConfirmado;
use App\Events\M6_Pagos\ReembolsoProcesado;
use App\Services\M6_Pagos\ConsultaEventosPagosService;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\Doubles\M6\ConsultaEventosPagosFake;
use Tests\Feature\M6_Pagos\Concerns\PreparaM6;
use Tests\TestCase;

/**
 * Contrato M6 v1.4.0 (conexión M6–M7): ConsultaEventosPagosInterface.
 *
 * Lo que se recupera por período debe ser idéntico (payload e id_evento) a lo
 * que M6 publicó, para que M7 lo descarte por idempotencia si ya lo procesó.
 *
 * Pedido 8942 (dobles): 1001 → comercio 11 (35 400); 1002 → comercio 12 (24 440).
 */
class ConsultaEventosPagosTest extends TestCase
{
    use PreparaM6;

    private const DIA = '2026-11-12';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::DIA.' 15:30:00');
    }

    public function test_el_contenedor_entrega_el_servicio_real_de_m6(): void
    {
        $this->assertInstanceOf(ConsultaEventosPagosService::class, app(ConsultaEventosPagosInterface::class));
    }

    public function test_lo_recuperado_es_identico_a_lo_publicado(): void
    {
        Event::fake([PagoConfirmado::class, FacturaGenerada::class, ReembolsoProcesado::class]);
        $this->publicarPagoYReembolso();

        $consulta = app(ConsultaEventosPagosInterface::class);

        $this->assertSame($this->publicados(PagoConfirmado::class), $consulta->listarPagosConfirmados(self::DIA, self::DIA));
        $this->assertSame($this->publicados(FacturaGenerada::class), $consulta->listarFacturasGeneradas(self::DIA, self::DIA));
        $this->assertSame($this->publicados(ReembolsoProcesado::class), $consulta->listarReembolsosProcesados(self::DIA, self::DIA));
    }

    public function test_las_facturas_recuperadas_traen_el_desglose_por_comercio(): void
    {
        $this->publicarPagoYReembolso();

        $facturas = app(ConsultaEventosPagosInterface::class)->listarFacturasGeneradas(self::DIA, self::DIA);

        $this->assertSame([11, 12], array_column($facturas, 'id_comercio'));
        $this->assertEqualsWithDelta(30000.00, $facturas[0]['subtotal'], 0.001);
        $this->assertEqualsWithDelta(3900.00, $facturas[0]['impuesto'], 0.001);
        $this->assertEqualsWithDelta(1500.00, $facturas[0]['envio'], 0.001);
        $this->assertNotNull($facturas[0]['id_pago']);
    }

    public function test_filtra_por_periodo(): void
    {
        $this->publicarPagoYReembolso();
        $consulta = app(ConsultaEventosPagosInterface::class);

        $this->assertSame([], $consulta->listarPagosConfirmados('2026-11-13', '2026-11-30'));
        $this->assertSame([], $consulta->listarReembolsosProcesados('2026-11-01', '2026-11-11'));
        $this->assertCount(1, $consulta->listarPagosConfirmados(self::DIA.'T15:00:00Z', self::DIA.'T16:00:00Z'));
        // Con zona horaria: 09:30 en Costa Rica (-06:00) son las 15:30 UTC.
        $this->assertCount(1, $consulta->listarPagosConfirmados(self::DIA.'T09:00:00-06:00', self::DIA.'T10:00:00-06:00'));
    }

    public function test_con_id_comercio_solo_devuelve_lo_de_ese_comercio(): void
    {
        $this->publicarPagoYReembolso();
        $consulta = app(ConsultaEventosPagosInterface::class);

        $this->assertCount(1, $consulta->listarFacturasGeneradas(self::DIA, self::DIA, 11));
        $this->assertCount(1, $consulta->listarReembolsosProcesados(self::DIA, self::DIA, 11));
        $this->assertCount(1, $consulta->listarPagosConfirmados(self::DIA, self::DIA, 11));

        $this->assertSame([], $consulta->listarFacturasGeneradas(self::DIA, self::DIA, 99));
        $this->assertSame([], $consulta->listarReembolsosProcesados(self::DIA, self::DIA, 12));
        $this->assertSame([], $consulta->listarPagosConfirmados(self::DIA, self::DIA, 99));
    }

    public function test_un_periodo_invalido_se_rechaza(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ConsultaEventosPagosInterface::class)->listarPagosConfirmados('2026-11-30', '2026-11-01');
    }

    public function test_una_fecha_vacia_se_rechaza(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ConsultaEventosPagosInterface::class)->listarReembolsosProcesados('', self::DIA);
    }

    public function test_el_doble_para_consumidores_cumple_el_contrato(): void
    {
        $fake = new ConsultaEventosPagosFake(reembolsos: [
            ['id_evento' => 'a', 'id_comercio' => 11, 'fecha' => '2026-11-12T10:00:00+00:00'],
            ['id_evento' => 'b', 'id_comercio' => 12, 'fecha' => '2026-11-20T10:00:00+00:00'],
        ]);
        app()->instance(ConsultaEventosPagosInterface::class, $fake);

        $consulta = app(ConsultaEventosPagosInterface::class);

        $this->assertSame(['a'], array_column($consulta->listarReembolsosProcesados(self::DIA, self::DIA), 'id_evento'));
        $this->assertSame(['b'], array_column($consulta->listarReembolsosProcesados('2026-11-01', '2026-11-30', 12), 'id_evento'));
    }

    private function publicarPagoYReembolso(): void
    {
        $this->procesarPago()->assertCreated();
        $this->postJson('/api/m6/v1/pedidos/'.self::PEDIDO_CONFIRMADO.'/reembolsos', [
            'id_subpedido' => 1001, 'monto' => 5000, 'motivo' => 'Producto devuelto',
        ])->assertCreated();
    }

    /** @return list<array<string, mixed>> */
    private function publicados(string $evento): array
    {
        return Event::dispatched($evento)->map(fn (array $args) => $args[0]->payload())->values()->all();
    }
}
