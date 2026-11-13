<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M6_Pagos\ConsultaEventosPagosInterface;
use App\Contracts\M7_Contabilidad\ContabilidadServiceInterface;
use App\Events\M6_Pagos\FacturaGenerada;
use App\Events\M6_Pagos\ReembolsoProcesado;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM7;
use App\Services\M7_Contabilidad\RecuperarEventosM6Service;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Doubles\M6\ConsultaEventosPagosFake;
use Tests\TestCase;

class IntegracionFinancieraM6Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new LogicException('Estas pruebas requieren SQLite en memoria.');
        }

        Schema::create('cuentas_contables', function (Blueprint $tabla): void {
            $tabla->id('id_cuenta_contable');
            $tabla->unsignedBigInteger('id_comercio');
            $tabla->string('codigo');
            $tabla->boolean('activa');
        });
        Schema::create('eventos_procesados', function (Blueprint $tabla): void {
            $tabla->id('id_evento_procesado');
            $tabla->uuid('evento_id')->unique();
            $tabla->string('tipo_evento');
            $tabla->string('estado');
            $tabla->timestamp('fecha_recepcion');
            $tabla->timestamp('fecha_procesamiento')->nullable();
            $tabla->text('ultimo_error')->nullable();
            $tabla->timestamps();
        });
        Schema::create('asientos_contables', function (Blueprint $tabla): void {
            $tabla->id('id_asiento');
            $tabla->unsignedBigInteger('id_comercio');
            $tabla->unsignedBigInteger('id_pedido')->nullable();
            $tabla->unsignedBigInteger('id_subpedido')->nullable();
            $tabla->unsignedBigInteger('id_pago')->nullable();
            $tabla->unsignedBigInteger('id_asiento_original')->nullable();
            $tabla->uuid('evento_id');
            $tabla->string('tipo_evento');
            $tabla->string('tipo');
            $tabla->timestamp('fecha');
            $tabla->string('estado');
            $tabla->timestamps();
        });
        Schema::create('detalle_asientos', function (Blueprint $tabla): void {
            $tabla->id('id_detalle_asiento');
            $tabla->unsignedBigInteger('id_asiento');
            $tabla->unsignedBigInteger('id_cuenta_contable');
            $tabla->unsignedInteger('numero_linea');
            $tabla->decimal('debe', 15, 2);
            $tabla->decimal('haber', 15, 2);
            $tabla->timestamps();
        });
        $migracion = require base_path('database/migrations/M7_Contabilidad/2026_11_13_061033_crear_registros_financieros_m6.php');
        $migracion->up();

        foreach (['1.1.01', '1.1.02', '2.1.02', '4.1.01', '4.1.02'] as $codigo) {
            DB::table('cuentas_contables')->insert(['id_comercio' => 11, 'codigo' => $codigo, 'activa' => true]);
        }
    }

    public function test_factura_genera_cobro_balanceado_y_no_duplica_venta_ni_pago(): void
    {
        $this->crearVenta(30000);
        $factura = $this->factura();

        event($factura);
        event($factura);
        event($this->factura('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'));

        $cobro = DB::table('asientos_contables')->where('tipo', 'COBRO')->first();
        $this->assertNotNull($cobro);
        $this->assertSame(7, (int) $cobro->id_pago);
        $this->assertSame([
            '1.1.01' => ['35400.00', '0.00'],
            '1.1.02' => ['0.00', '30000.00'],
            '2.1.02' => ['0.00', '3900.00'],
            '4.1.02' => ['0.00', '1500.00'],
        ], $this->lineas((int) $cobro->id_asiento));
        $this->assertDatabaseCount('asientos_contables', 2);
        $this->assertTrue(app(ContabilidadServiceInterface::class)->consultarRegistroContablePago(7)['registrado']);
        $this->assertDatabaseCount('registros_financieros_m6', 1);
    }

    public function test_reembolsos_parciales_son_independientes_y_se_prorratean_en_centimos(): void
    {
        $this->crearVenta(30000);
        event($this->factura());
        $primero = $this->reembolso(21, 5000);

        event($primero);
        event($primero);
        event($this->reembolso(21, 5000, 'cccccccc-cccc-4ccc-8ccc-cccccccccccc'));
        event($this->reembolso(22, 5000, 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'));

        $cobro = DB::table('asientos_contables')->where('tipo', 'COBRO')->first();
        $reversiones = DB::table('asientos_contables')->where('tipo', 'REEMBOLSO')->orderBy('id_asiento')->get();
        $this->assertCount(2, $reversiones);
        $this->assertSame((int) $cobro->id_asiento, (int) $reversiones[0]->id_asiento_original);
        $this->assertSame(7, (int) $reversiones[0]->id_pago);
        $this->assertSame([
            '4.1.01' => ['4237.29', '0.00'],
            '2.1.02' => ['550.85', '0.00'],
            '4.1.02' => ['211.86', '0.00'],
            '1.1.01' => ['0.00', '5000.00'],
        ], $this->lineas((int) $reversiones[0]->id_asiento));
        $this->assertDatabaseCount('registros_financieros_m6', 3);
    }

    public function test_reembolsos_minimos_no_generan_componentes_negativos_ni_superan_saldos(): void
    {
        $this->crearVenta(0.01);
        event($this->factura(subtotal: 0.01, impuesto: 0.01, envio: 0.0));

        event($this->reembolso(21, 0.01));
        event($this->reembolso(22, 0.01, 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'));

        $registros = DB::table('registros_financieros_m6')->where('tipo', 'REEMBOLSO')->get();
        $this->assertCount(2, $registros);
        $this->assertSame(1, (int) $registros->sum('subtotal_centimos'));
        $this->assertSame(1, (int) $registros->sum('impuesto_centimos'));
        $this->assertSame(0, (int) $registros->sum('envio_centimos'));
    }

    public function test_listener_registra_el_error_sin_propagacion_ni_exito_falso(): void
    {
        Log::spy();

        event($this->factura());

        $this->assertDatabaseCount('asientos_contables', 0);
        $this->assertDatabaseCount('eventos_procesados', 0);
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $mensaje): bool => str_contains($mensaje, 'factura de M6'));
    }

    public function test_reembolso_excesivo_y_operacion_repetida_con_monto_distinto_no_modifican_asientos(): void
    {
        $this->crearVenta(30000);
        event($this->factura());
        event($this->reembolso(21, 5000));
        Log::spy();

        event($this->reembolso(21, 5001, 'cccccccc-cccc-4ccc-8ccc-cccccccccccc'));
        event($this->reembolso(22, 40000, 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'));

        $this->assertDatabaseCount('asientos_contables', 3);
        $this->assertDatabaseCount('registros_financieros_m6', 2);
        $this->assertDatabaseMissing('eventos_procesados', ['evento_id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc']);
        $this->assertDatabaseMissing('eventos_procesados', ['evento_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb']);
        Log::shouldHaveReceived('error')->twice();
    }

    public function test_recupera_una_factura_anterior_al_periodo_del_reembolso_y_es_idempotente(): void
    {
        $this->crearVenta(30000);
        $factura = $this->factura();
        $reembolso = $this->reembolso(21, 5000);
        $this->app->instance(ConsultaEventosPagosInterface::class, new ConsultaEventosPagosFake(
            reembolsos: [$reembolso->payload()],
            facturas: [$factura->payload()],
        ));

        $servicio = app(RecuperarEventosM6Service::class);
        $resultado = $servicio->recuperar('2026-11-12', '2026-11-12', 11);
        $repeticion = $servicio->recuperar('2026-11-12', '2026-11-12', 11);

        $this->assertSame(['facturas' => 0, 'reembolsos' => 1, 'errores' => []], $resultado);
        $this->assertSame($resultado, $repeticion);
        $this->assertDatabaseCount('registros_financieros_m6', 2);
        $this->assertDatabaseCount('asientos_contables', 3);
    }

    public function test_listener_recupera_el_cobro_faltante_antes_de_procesar_el_reembolso(): void
    {
        $this->crearVenta(30000);
        $this->app->instance(ConsultaEventosPagosInterface::class, new ConsultaEventosPagosFake(facturas: [$this->factura()->payload()]));

        event($this->reembolso(21, 5000));

        $this->assertDatabaseCount('registros_financieros_m6', 2);
        $this->assertDatabaseHas('asientos_contables', ['id_pago' => 7, 'id_subpedido' => 1001, 'tipo' => 'COBRO']);
        $this->assertDatabaseHas('asientos_contables', ['id_pago' => 7, 'id_subpedido' => 1001, 'tipo' => 'REEMBOLSO']);
    }

    public function test_reembolso_sin_factura_disponible_se_registra_como_error_y_puede_recuperarse(): void
    {
        $this->crearVenta(30000);
        $consulta = new ConsultaEventosPagosFake;
        $this->app->instance(ConsultaEventosPagosInterface::class, $consulta);
        Log::spy();

        event($this->reembolso(21, 5000));

        $this->assertDatabaseMissing('eventos_procesados', ['evento_id' => '22222222-2222-4222-8222-222222222222']);
        Log::shouldHaveReceived('error')->once();

        $consulta->facturas = [$this->factura()->payload()];
        event($this->reembolso(21, 5000));

        $this->assertDatabaseCount('registros_financieros_m6', 2);
    }

    public function test_comando_recupera_todos_los_comercios_del_dia_anterior(): void
    {
        $this->travelTo('2026-11-12 12:00:00');
        $this->crearVenta(30000);
        foreach (['1.1.01', '1.1.02', '2.1.02', '4.1.01', '4.1.02'] as $codigo) {
            DB::table('cuentas_contables')->insert(['id_comercio' => 12, 'codigo' => $codigo, 'activa' => true]);
        }
        $this->crearVenta(24440, 12, 1002);
        $otra = new FacturaGenerada('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 32, 8942, 1002, 12, 24440, '<factura/>', '2026-11-11T12:00:00Z', 7, 24440, 0, 0);
        $this->app->instance(ConsultaEventosPagosInterface::class, new ConsultaEventosPagosFake(facturas: [$this->factura()->payload(), $otra->payload()]));

        $this->artisan('m7:recuperar-eventos-m6')->assertExitCode(0);

        $this->assertDatabaseCount('registros_financieros_m6', 2);
        $this->assertSame([1001, 1002], app(ContabilidadServiceInterface::class)->consultarRegistroContablePago(7)['subpedidos_registrados']);
    }

    public function test_recuperacion_esta_programada_diariamente_sin_solapamiento(): void
    {
        $eventos = collect(app(Schedule::class)->events())->filter(fn ($evento): bool => str_contains((string) $evento->command, 'm7:recuperar-eventos-m6'));

        $this->assertCount(1, $eventos);
        $this->assertSame('0 0 * * *', $eventos->first()->expression);
        $this->assertTrue($eventos->first()->withoutOverlapping);
    }

    public function test_comando_con_comercio_solo_recupera_facturas_de_ese_comercio(): void
    {
        $this->crearVenta(30000);
        $otra = new FacturaGenerada('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 32, 8942, 1002, 12, 24440, '<factura/>', '2026-11-11T12:00:00Z', 7, 24440, 0, 0);
        $this->app->instance(ConsultaEventosPagosInterface::class, new ConsultaEventosPagosFake(facturas: [$this->factura()->payload(), $otra->payload()]));

        $this->artisan('m7:recuperar-eventos-m6', ['desde' => '2026-11-11', 'hasta' => '2026-11-11', '--comercio' => '11'])->assertExitCode(0);

        $this->assertDatabaseCount('registros_financieros_m6', 1);
        $this->assertDatabaseHas('registros_financieros_m6', ['id_comercio' => 11, 'id_subpedido' => 1001]);
    }

    public function test_comando_informa_y_registra_los_eventos_que_no_pudo_procesar(): void
    {
        $this->app->instance(ConsultaEventosPagosInterface::class, new ConsultaEventosPagosFake(facturas: [$this->factura()->payload()]));
        Log::spy();

        $this->artisan('m7:recuperar-eventos-m6', ['desde' => '2026-11-11', 'hasta' => '2026-11-11'])->assertExitCode(1);

        $this->assertDatabaseCount('registros_financieros_m6', 0);
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $mensaje): bool => str_contains($mensaje, 'recuperación'));
    }

    public function test_recuperacion_informa_fallos_y_no_marca_un_evento_como_procesado(): void
    {
        $factura = $this->factura();
        $this->app->instance(ConsultaEventosPagosInterface::class, new ConsultaEventosPagosFake(facturas: [$factura->payload()]));

        $resultado = app(RecuperarEventosM6Service::class)->recuperar('2026-11-11', '2026-11-11', 11);

        $this->assertSame(0, $resultado['facturas']);
        $this->assertStringContainsString('La venta del subpedido no existe', $resultado['errores'][0]);
        $this->assertDatabaseCount('eventos_procesados', 0);
    }

    public function test_mismo_pago_genera_un_cobro_independiente_por_comercio(): void
    {
        $this->crearVenta(30000);
        foreach (['1.1.01', '1.1.02', '2.1.02', '4.1.01', '4.1.02'] as $codigo) {
            DB::table('cuentas_contables')->insert(['id_comercio' => 12, 'codigo' => $codigo, 'activa' => true]);
        }
        $this->crearVenta(24440, 12, 1002);
        $otra = new FacturaGenerada('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 32, 8942, 1002, 12, 24440, '<factura/>', '2026-11-11T12:00:00Z', 7, 24440, 0, 0);

        event($this->factura());
        event($otra);

        $this->assertDatabaseHas('asientos_contables', ['id_pago' => 7, 'id_comercio' => 11, 'id_subpedido' => 1001, 'tipo' => 'COBRO']);
        $this->assertDatabaseHas('asientos_contables', ['id_pago' => 7, 'id_comercio' => 12, 'id_subpedido' => 1002, 'tipo' => 'COBRO']);
        $this->assertDatabaseCount('registros_financieros_m6', 2);
        $this->assertSame([1001, 1002], app(AdaptadorM7::class)->subpedidosConCobro(7));
    }

    private function crearVenta(float $subtotal, int $comercio = 11, int $subpedido = 1001): void
    {
        $id = DB::table('asientos_contables')->insertGetId([
            'id_comercio' => $comercio, 'id_pedido' => 8942, 'id_subpedido' => $subpedido,
            'evento_id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'tipo_evento' => 'PedidoConfirmado',
            'tipo' => 'VENTA', 'fecha' => '2026-11-10 10:00:00', 'estado' => 'REGISTRADO',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([['1.1.02', $subtotal, 0], ['4.1.01', 0, $subtotal]] as $indice => [$codigo, $debe, $haber]) {
            DB::table('detalle_asientos')->insert([
                'id_asiento' => $id,
                'id_cuenta_contable' => DB::table('cuentas_contables')->where('id_comercio', $comercio)->where('codigo', $codigo)->value('id_cuenta_contable'),
                'numero_linea' => $indice + 1, 'debe' => $debe, 'haber' => $haber,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function factura(string $idEvento = '11111111-1111-4111-8111-111111111111', float $subtotal = 30000, float $impuesto = 3900, float $envio = 1500): FacturaGenerada
    {
        return new FacturaGenerada($idEvento, 31, 8942, 1001, 11, $subtotal + $impuesto + $envio, '<factura/>', '2026-11-11T12:00:00Z', 7, $subtotal, $impuesto, $envio);
    }

    private function reembolso(int $id, float $monto, string $evento = '22222222-2222-4222-8222-222222222222'): ReembolsoProcesado
    {
        return new ReembolsoProcesado($evento, 8942, 1001, 11, $id, $monto, 40 + $id, '2026-11-12T12:00:00Z', 7);
    }

    /** @return array<string, array{string,string}> */
    private function lineas(int $asiento): array
    {
        $resultado = [];
        $lineas = DB::table('detalle_asientos as d')->join('cuentas_contables as c', 'c.id_cuenta_contable', '=', 'd.id_cuenta_contable')
            ->where('d.id_asiento', $asiento)->orderBy('d.numero_linea')->get(['c.codigo', 'd.debe', 'd.haber']);
        foreach ($lineas as $linea) {
            $resultado[$linea->codigo] = [number_format((float) $linea->debe, 2, '.', ''), number_format((float) $linea->haber, 2, '.', '')];
        }

        return $resultado;
    }
}
