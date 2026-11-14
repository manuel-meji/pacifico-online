<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M1_Portal\ComercialPortalContract;
use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Contracts\M1_Portal\PortalServiceInterface;
use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M6_Pagos\ConsultaEventosPagosInterface;
use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Contracts\M7_Contabilidad\ContabilidadServiceInterface;
use App\Events\M5_Pedidos\PedidoConfirmado;
use App\Events\M6_Pagos\FacturaGenerada;
use App\Events\M6_Pagos\PagoConfirmado;
use App\Events\M6_Pagos\ReembolsoProcesado;
use App\Services\M5_Pedidos\Dobles\CatalogoStub;
use App\Services\M5_Pedidos\Dobles\ComerciosStub;
use App\Services\M5_Pedidos\Dobles\InventarioStub;
use App\Services\M5_Pedidos\Dobles\PortalStub;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM1;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM5;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM7;
use App\Services\M6_Pagos\Clients\ClienteClient;
use App\Services\M6_Pagos\Clients\ContabilidadClient;
use App\Services\M6_Pagos\Clients\PedidoClient;
use App\Services\M6_Pagos\ConsultaEventosPagosService;
use App\Services\M6_Pagos\PagosService;
use App\Services\M7_Contabilidad\DTOs\VentaDTO;
use App\Services\M7_Contabilidad\ProcesarPedidoConfirmadoService;
use App\Services\M7_Contabilidad\RecuperarEventosM6Service;
use App\Services\M7_Contabilidad\ValidadorPartidaDoble;
use App\Services\Nucleo\ContextoComercio;
use Database\Seeders\M7_PlanCuentasSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\Doubles\M1\ComercialPortalFake;
use Tests\Doubles\M1\PortalPublicoFake;
use Tests\Feature\M6_Pagos\Concerns\BaseSinSentenciasPostgres;
use Tests\Feature\M6_Pagos\Concerns\PreparaM6;
use Tests\TestCase;

class FlujoRealM6M7Test extends TestCase
{
    use PreparaM6;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new LogicException('Esta prueba requiere SQLite en memoria; nunca utiliza Supabase.');
        }
        BaseSinSentenciasPostgres::durante(fn () => $this->artisan('migrate', [
            '--path' => 'database/migrations/M7_Contabilidad',
            '--no-interaction' => true,
        ])->assertExitCode(0)->run());

        config([
            'm6.usar_dobles' => true,
            'm6.doble_por_modulo.m1' => true,
            'm6.doble_por_modulo.m2' => true,
            'm6.doble_por_modulo.m5' => true,
            'm6.confirmacion_inmediata' => true,
            'm6.hacienda.respuesta' => 'aceptada',
        ]);
        $this->app->instance(ContabilidadClient::class, app(AdaptadorM7::class));
        $this->app->bind(ConsultaEventosPagosInterface::class, ConsultaEventosPagosService::class);
        Http::preventStrayRequests();
        $this->travelTo('2026-11-13 12:00:00');
    }

    public function test_pago_multicomercio_reembolso_y_recuperacion_operan_con_servicios_reales_m6_m7(): void
    {
        $this->prepararVentas();

        $idPago = $this->procesarPago()->assertCreated()->assertJsonPath('estado', 'confirmado')->json('id');

        $this->assertDatabaseCount('facturas_electronicas', 2);
        $this->assertDatabaseCount('pagos_contables_m6', 1);
        $this->assertDatabaseCount('registros_financieros_m6', 2);
        $this->assertDatabaseCount('asientos_plataforma_m7', 3);
        $this->assertDatabaseHas('cuadres_pagos_m6', [
            'id_pago' => $idPago, 'estado' => 'COMPLETO_POR_IMPORTES', 'facturado_centimos' => 5984000,
        ]);
        $this->assertSame([1001, 1002], app(ContabilidadServiceInterface::class)->consultarRegistroContablePago($idPago)['subpedidos_registrados']);
        $this->assertSame(5984000, $this->saldoPlataforma('P-BANCOS'));
        $this->assertSame(0, $this->saldoPlataforma('P-PENDIENTE'));
        $this->assertSame(3540000, $this->saldoComercio(11, '1.1.04'));
        $this->assertSame(2444000, $this->saldoComercio(12, '1.1.04'));

        $this->procesarPago()->assertOk()->assertJsonPath('id', $idPago);
        $this->assertDatabaseCount('pagos', 1);
        $this->assertDatabaseCount('asientos_plataforma_m7', 3);

        $this->postJson('/api/m6/v1/pedidos/8942/reembolsos', [
            'id_subpedido' => 1001, 'monto' => 5000, 'motivo' => 'Devolución parcial de productos',
        ])->assertCreated();

        $this->assertDatabaseCount('reembolsos', 1);
        $this->assertDatabaseCount('notas_credito', 1);
        $this->assertDatabaseHas('bandeja_eventos_m6', [
            'tipo_evento' => 'ReembolsoProcesado',
            'estado' => 'PROCESADO',
            'ultimo_error' => null,
        ]);
        $this->assertSame(1, DB::table('asientos_plataforma_m7')->where('tipo', 'REEMBOLSO')->count());
        $this->assertSame(5484000, $this->saldoPlataforma('P-BANCOS'));
        $this->assertSame(3040000, $this->saldoComercio(11, '1.1.04'));
        $this->assertSame(-2576271, $this->saldoComercio(11, '4.1.01'));
        $this->assertSame(-334915, $this->saldoComercio(11, '2.1.02'));
        $this->assertSame(-128814, $this->saldoComercio(11, '4.1.02'));

        foreach ([[1001, 30400], [1002, 24440]] as [$subpedido, $monto]) {
            $this->postJson('/api/m6/v1/pedidos/8942/reembolsos', [
                'id_subpedido' => $subpedido, 'monto' => $monto, 'motivo' => 'Devolución del saldo restante',
            ])->assertCreated();
        }
        $resultado = app(RecuperarEventosM6Service::class)->recuperar('2026-11-13', '2026-11-13');

        $this->assertSame([], $resultado['errores']);
        $this->assertSame(0, $resultado['pendientes']);
        $this->assertDatabaseCount('reembolsos', 3);
        $this->assertDatabaseCount('notas_credito', 3);
        $this->assertDatabaseCount('asientos_plataforma_m7', 6);
        $this->assertDatabaseCount('asientos_contables', 7);
        $this->assertDatabaseCount('registros_financieros_m6', 5);
        $this->assertSame(0, $this->saldoPlataforma('P-BANCOS'));
        $this->assertSame(0, $this->saldoPlataforma('P-COMERCIOS'));
        foreach ([11, 12] as $comercio) {
            foreach (['1.1.02', '1.1.04', '4.1.01', '2.1.02', '4.1.02'] as $codigo) {
                $this->assertSame(0, $this->saldoComercio($comercio, $codigo), "Saldo neto del comercio {$comercio} en {$codigo}");
            }
        }
    }

    public function test_recuperacion_consulta_m6_real_y_restaura_eventos_no_recibidos_por_m7(): void
    {
        $this->prepararVentas();
        Event::fake([PagoConfirmado::class, FacturaGenerada::class, ReembolsoProcesado::class]);
        $idPago = $this->procesarPago()->assertCreated()->json('id');
        Event::assertDispatchedTimes(PagoConfirmado::class, 1);
        $this->assertDatabaseCount('facturas_electronicas', 2);
        $this->assertDatabaseCount('asientos_plataforma_m7', 0);

        $resultado = app(RecuperarEventosM6Service::class)->recuperar('2026-11-13', '2026-11-13');
        $repeticion = app(RecuperarEventosM6Service::class)->recuperar('2026-11-13', '2026-11-13');

        $this->assertSame([], $resultado['errores']);
        $this->assertSame($resultado, $repeticion);
        $this->assertDatabaseCount('asientos_plataforma_m7', 3);
        $this->assertDatabaseCount('registros_financieros_m6', 2);
        $this->assertDatabaseMissing('bandeja_eventos_m6', ['estado' => 'PENDIENTE']);
        $this->assertTrue(app(AdaptadorM7::class)->consultarRegistroContablePago($idPago));
        $this->assertDatabaseHas('cuadres_pagos_m6', ['estado' => 'COMPLETO_POR_IMPORTES']);
    }

    public function test_checkout_con_cupon_conserva_subtotales_entre_m5_m6_y_m7_y_cuadra_con_el_cobro(): void
    {
        ['pedido' => $pedido, 'facturas' => $facturas, 'id_pago' => $idPago] = $this->checkoutM5('PACIFICO10');

        $this->assertDatabaseHas('pedidos_maestros', ['id_pedido' => $pedido->id_pedido, 'descuento' => 70.03]);
        $this->assertSame(752.4, $pedido->monto_total);
        foreach ($facturas as $factura) {
            $subpedido = collect($pedido->subpedidos)->firstWhere('id_subpedido', $factura->idSubpedido);
            $this->assertNotNull($subpedido);
            $this->assertSame($subpedido['subtotal'], $factura->subtotal);
            $this->assertSame($subpedido['impuesto'], $factura->impuesto);
            $this->assertSame($subpedido['costo_envio'], $factura->envio);
            $this->assertDatabaseHas('asientos_contables', [
                'id_subpedido' => $factura->idSubpedido, 'id_comercio' => $factura->idComercio, 'tipo' => 'VENTA',
            ]);
        }
        $this->assertDatabaseHas('cuadres_pagos_m6', [
            'id_pago' => $idPago, 'estado' => 'COMPLETO_POR_IMPORTES',
            'facturado_centimos' => 75240, 'exceso_centimos' => 0,
        ]);
        $this->assertDatabaseCount('registros_financieros_m6', 2);
        $this->assertDatabaseMissing('bandeja_eventos_m6', ['estado' => 'PENDIENTE']);
    }

    public function test_facturas_de_checkout_real_publican_y_recuperan_componentes_con_maximo_dos_decimales(): void
    {
        ['pedido' => $pedido, 'facturas' => $facturas, 'id_pago' => $idPago] = $this->checkoutM5();

        $this->assertSame(822.43, $pedido->monto_total);
        $this->assertSame([300.15, 400.14], array_column($pedido->subpedidos, 'subtotal'));
        $this->assertSame([39.02, 52.02], array_column($pedido->subpedidos, 'impuesto'));
        $this->assertSame([15.55, 15.55], array_column($pedido->subpedidos, 'costo_envio'));
        $recuperadas = app(ConsultaEventosPagosInterface::class)->listarFacturasGeneradas('2026-11-13', '2026-11-13');
        $this->assertCount(2, $recuperadas);
        foreach ($facturas as $factura) {
            $publicada = $factura->payload();
            $recuperada = collect($recuperadas)->firstWhere('id_factura', $factura->idFactura);
            $this->assertSame($publicada, $recuperada);
            foreach ([$publicada, $recuperada] as $datos) {
                foreach (['subtotal', 'impuesto', 'envio', 'monto'] as $campo) {
                    $this->assertMatchesRegularExpression(
                        '/\A[0-9]+(?:\.[0-9]{1,2})?\z/',
                        json_encode($datos[$campo], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
                        "Factura {$factura->idFactura}: {$campo} no debe requerir redondeo en M7.",
                    );
                }
            }
        }
        $this->assertDatabaseHas('cuadres_pagos_m6', ['id_pago' => $idPago, 'estado' => 'COMPLETO_POR_IMPORTES']);
        $this->assertDatabaseCount('registros_financieros_m6', 2);
        $this->assertDatabaseMissing('bandeja_eventos_m6', ['estado' => 'PENDIENTE']);
    }

    /** @return array{pedido: PedidoConfirmado, facturas: list<FacturaGenerada>, id_pago: int} */
    private function checkoutM5(?string $cupon = null): array
    {
        $this->artisan('migrate', ['--path' => 'database/migrations/M5_Pedidos', '--no-interaction' => true])->assertExitCode(0)->run();
        $portal = new PortalStub;
        $this->app->instance(PortalServiceInterface::class, $portal);
        $this->app->instance(PortalPublicoContract::class, $portal);
        $this->app->instance(ComercialPortalContract::class, new ComercialPortalFake);
        $this->app->instance(ComerciosServiceInterface::class, (new ComerciosStub)->setCostoEnvio(15.55));
        $this->app->instance(InventarioServiceInterface::class, new InventarioStub);
        $this->app->instance(CatalogoServiceInterface::class, new class extends CatalogoStub
        {
            public function obtenerVariacion(int $variacionId): ?array
            {
                return match ($variacionId) {
                    101 => [
                        'id_variacion' => 101, 'id_producto' => 10, 'id_comercio' => 11,
                        'nombre' => 'Producto fraccionario A', 'sku' => 'FRACCION-A',
                        'precio' => 100.05, 'tarifa_impuesto' => 13.0,
                    ],
                    202 => [
                        'id_variacion' => 202, 'id_producto' => 20, 'id_comercio' => 12,
                        'nombre' => 'Producto fraccionario B', 'sku' => 'FRACCION-B',
                        'precio' => 200.07, 'tarifa_impuesto' => 13.0,
                    ],
                    default => null,
                };
            }
        });
        config([
            'integraciones.m5.m1.modo' => 'real',
            'integraciones.m5.m6.modo' => 'real',
            'm6.doble_por_modulo.m5' => false,
        ]);
        $this->app->instance(ClienteClient::class, new AdaptadorM1(new PortalPublicoFake));
        $this->app->instance(PedidoClient::class, app(AdaptadorM5::class));
        $this->app->instance(PagosServiceInterface::class, app(PagosService::class));
        foreach ([11, 12] as $comercio) {
            app(M7_PlanCuentasSeeder::class)->sembrarComercio($comercio);
        }

        $pedido = null;
        $facturas = [];
        Event::listen(PedidoConfirmado::class, function (PedidoConfirmado $evento) use (&$pedido): void {
            $pedido = $evento;
        });
        Event::listen(FacturaGenerada::class, function (FacturaGenerada $evento) use (&$facturas): void {
            $facturas[] = $evento;
        });
        foreach ([[101, 3], [202, 2]] as [$variacion, $cantidad]) {
            $this->postJson('/api/m5/v1/carrito/items?id_cliente=1', [
                'id_variacion' => $variacion, 'cantidad' => $cantidad,
            ])->assertCreated();
        }
        $respuesta = $this->postJson('/api/m5/v1/pedidos?id_cliente=1', [
            'id_direccion_cliente' => 1, 'metodoPago' => 'tarjeta', 'cupon_descuento' => $cupon,
        ])->assertCreated();
        $this->assertInstanceOf(PedidoConfirmado::class, $pedido);
        $idPago = $this->procesarPago([
            'id_pedido' => $respuesta->json('id_pedido'),
            'monto_total' => $respuesta->json('monto_total'),
        ])->assertCreated()->assertJsonPath('estado', 'confirmado')->json('id');
        $this->assertCount(2, $facturas);

        return ['pedido' => $pedido, 'facturas' => $facturas, 'id_pago' => $idPago];
    }

    private function prepararVentas(): void
    {
        foreach ([11, 12] as $comercio) {
            app(M7_PlanCuentasSeeder::class)->sembrarComercio($comercio);
        }
        $evento = '11111111-1111-4111-8111-111111111111';
        app(ContextoComercio::class)->ejecutarComoSistema(fn () => app(ProcesarPedidoConfirmadoService::class)->procesar($evento, [
            new VentaDTO($evento, '2026-11-13T11:00:00Z', 8942, 1001, 11, '30000.00'),
            new VentaDTO($evento, '2026-11-13T11:00:00Z', 8942, 1002, 12, '21000.00'),
        ]));
    }

    private function saldoPlataforma(string $codigo): int
    {
        $movimientos = DB::table('detalles_plataforma_m7 as d')
            ->join('cuentas_plataforma_m7 as c', 'c.id_cuenta', '=', 'd.id_cuenta')
            ->where('c.codigo', $codigo)->get(['d.debe', 'd.haber']);

        return $this->saldo($movimientos->all());
    }

    private function saldoComercio(int $comercio, string $codigo): int
    {
        $movimientos = DB::table('detalle_asientos as d')
            ->join('cuentas_contables as c', 'c.id_cuenta_contable', '=', 'd.id_cuenta_contable')
            ->where('c.id_comercio', $comercio)->where('c.codigo', $codigo)->get(['d.debe', 'd.haber']);

        return $this->saldo($movimientos->all());
    }

    /** @param list<\stdClass> $movimientos */
    private function saldo(array $movimientos): int
    {
        $validador = new ValidadorPartidaDoble;
        $saldo = 0;
        foreach ($movimientos as $movimiento) {
            $saldo += $validador->aCentimos((string) $movimiento->debe) - $validador->aCentimos((string) $movimiento->haber);
        }

        return $saldo;
    }
}
