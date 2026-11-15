<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M7_Contabilidad\DirectorioComerciosM7GatewayInterface;
use App\Models\M7_Contabilidad\AsientoContable;
use App\Models\M7_Contabilidad\DetalleAsiento;
use App\Services\M7_Contabilidad\AsientoRepository;
use App\Services\M7_Contabilidad\Gateways\M2DirectorioComerciosGateway;
use App\Services\Nucleo\ContextoComercio;
use Database\Factories\UsuarioFactory;
use Database\Seeders\M7_PlanCuentasSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Feature\M6_Pagos\Concerns\BaseSinSentenciasPostgres;
use Tests\Feature\M6_Pagos\Concerns\PreparaM6;
use Tests\TestCase;

class ConsultasOperativasTest extends TestCase
{
    use PreparaM6;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new LogicException('Estas pruebas requieren SQLite en memoria.');
        }
        BaseSinSentenciasPostgres::durante(fn () => $this->artisan('migrate', [
            '--path' => 'database/migrations/M7_Contabilidad', '--no-interaction' => true,
        ])->assertExitCode(0)->run());
    }

    public function test_saldos_netos_incluyen_reembolsos_y_excluyen_anulados_y_otros_comercios(): void
    {
        $this->prepararComercios();
        $this->movimiento(1, '1.1.02', '100.25', '0.00');
        $this->movimiento(1, '1.1.02', '0.00', '25.10');
        $this->movimiento(1, '1.1.04', '40.50', '0.00');
        $this->movimiento(1, '1.1.04', '0.00', '50.75', 'REEMBOLSO');
        $this->movimiento(1, '2.1.01', '0.00', '80.00');
        $this->movimiento(1, '2.1.01', '20.00', '0.00');
        $this->movimiento(1, '1.1.02', '999.00', '0.00', estado: 'ANULADO');
        $this->movimiento(1, '1.1.02', '999.00', '0.00', estado: 'PENDIENTE');
        $this->movimiento(2, '1.1.02', '900.00', '0.00');
        $this->actingAs(UsuarioFactory::new()->create(['rol' => 'encargado_comercio', 'id_comercio' => 1]));

        $respuesta = $this->getJson('/api/m7/v1/saldos-cobrar-pagar')->assertOk();

        $respuesta->assertJsonPath('data.resumen.por_cobrar', '64.90')
            ->assertJsonPath('data.resumen.por_pagar', '60.00')
            ->assertJsonCount(3, 'data.cuentas')
            ->assertJsonPath('data.cuentas.1.saldo', '-10.25');
        $this->assertSame(['codigo', 'id_cuenta_contable', 'nombre', 'saldo', 'tipo'], collect(array_keys($respuesta->json('data.cuentas.0')))->sort()->values()->all());
    }

    public function test_centros_reportan_rentabilidad_y_agrupan_lo_no_imputado(): void
    {
        $this->prepararComercios();
        $centro = (int) DB::table('centros_costo')->where('id_comercio', 1)->value('id_centro_costo');
        DB::table('centros_costo')->insert([
            'id_comercio' => 1, 'codigo' => 'SIN', 'nombre' => 'Sin movimientos', 'activo' => false,
        ]);
        $this->movimiento(1, '4.1.01', '0.00', '500.00', centro: $centro);
        $asiento = $this->movimiento(1, '5.1.01', '120.30', '0.00', centro: $centro);
        DB::table('asientos_contables')->where('id_asiento', $asiento)->update([
            'descripcion' => 'Costo del subpedido', 'id_pedido' => 10, 'id_subpedido' => 11, 'id_pago' => 12,
        ]);
        DB::table('detalle_asientos')->where('id_asiento', $asiento)->update(['descripcion' => 'Costo imputado a logística']);
        $this->movimiento(1, '5.1.01', '0.00', '20.10', 'REVERSION', centro: $centro);
        $this->movimiento(1, '1.1.02', '999.00', '0.00', centro: $centro);
        $this->movimiento(1, '5.1.01', '999.00', '0.00', estado: 'ANULADO', centro: $centro);
        $this->movimiento(1, '5.1.01', '300.00', '0.00');
        $this->movimiento(1, '4.1.02', '0.00', '50.00');
        $otroCentro = (int) DB::table('centros_costo')->where('id_comercio', 2)->value('id_centro_costo');
        $this->movimiento(2, '5.1.01', '700.00', '0.00', centro: $otroCentro);
        $this->actingAs(UsuarioFactory::new()->create(['rol' => 'encargado_comercio', 'id_comercio' => 1]));

        $this->getJson('/api/m7/v1/centros-costo')->assertOk()
            ->assertJsonPath('data.hay_movimientos', true)
            ->assertJsonPath('data.centros.0.ingresos', '500.00')
            ->assertJsonPath('data.centros.0.costos', '100.20')
            ->assertJsonPath('data.centros.0.utilidad', '399.80')
            ->assertJsonPath('data.centros.0.margen', '79.96')
            ->assertJsonPath('data.centros.1.utilidad', '0.00')
            ->assertJsonPath('data.centros.1.margen', null)
            ->assertJsonPath('data.centros.1.activo', false)
            ->assertJsonPath('data.sin_centro.ingresos', '50.00')
            ->assertJsonPath('data.sin_centro.costos', '300.00')
            ->assertJsonPath('data.sin_centro.utilidad', '-250.00')
            ->assertJsonPath('data.sin_centro.margen', '-500.00')
            ->assertJsonPath('data.resumen.ingresos', '550.00')
            ->assertJsonPath('data.resumen.costos', '400.20')
            ->assertJsonPath('data.resumen.utilidad', '149.80')
            ->assertJsonPath('data.resumen.margen', '27.24')
            ->assertJsonPath('data.resumen.cantidad', 2)
            ->assertJsonPath('data.resumen.activos', 1);

        $this->getJson("/api/m7/v1/centros-costo/{$centro}/movimientos")->assertOk()
            ->assertJsonCount(3, 'data.movimientos')
            ->assertJsonPath('data.ingresos', '500.00')
            ->assertJsonPath('data.costos', '100.20')
            ->assertJsonPath('data.utilidad', '399.80')
            ->assertJsonPath('data.movimientos.0.tipo_cuenta', 'INGRESO')
            ->assertJsonPath('data.movimientos.0.saldo', '500.00')
            ->assertJsonPath('data.movimientos.1.codigo_cuenta', '5.1.01')
            ->assertJsonPath('data.movimientos.1.descripcion', 'Costo imputado a logística')
            ->assertJsonPath('data.movimientos.1.id_pedido', 10)
            ->assertJsonPath('data.movimientos.1.id_subpedido', 11)
            ->assertJsonPath('data.movimientos.1.id_pago', 12)
            ->assertJsonPath('data.movimientos.1.saldo', '379.70')
            ->assertJsonPath('data.movimientos.2.tipo', 'REVERSION')
            ->assertJsonPath('data.movimientos.2.saldo', '399.80');

        $this->getJson('/api/m7/v1/centros-costo/sin-asignar/movimientos')->assertOk()
            ->assertJsonPath('data.centro', null)
            ->assertJsonCount(2, 'data.movimientos')
            ->assertJsonPath('data.utilidad', '-250.00');
    }

    public function test_centros_filtran_por_periodo_y_avisan_cuando_no_hay_movimientos(): void
    {
        $this->prepararComercios();
        $centro = (int) DB::table('centros_costo')->where('id_comercio', 1)->value('id_centro_costo');
        $this->movimiento(1, '4.1.01', '0.00', '80.00', centro: $centro, fecha: '2026-10-31 23:59:59');
        $this->movimiento(1, '4.1.01', '0.00', '200.00', centro: $centro, fecha: '2026-11-01 00:00:00');
        $this->movimiento(1, '5.1.01', '15.00', '0.00', fecha: '2026-12-01 00:00:00');
        $this->actingAs(UsuarioFactory::new()->create(['rol' => 'encargado_comercio', 'id_comercio' => 1]));
        $noviembre = 'fecha_desde=2026-11-01&fecha_hasta=2026-11-30';

        $this->getJson("/api/m7/v1/centros-costo?{$noviembre}")->assertOk()
            ->assertJsonPath('data.fecha_desde', '2026-11-01')
            ->assertJsonPath('data.centros.0.ingresos', '200.00')
            ->assertJsonPath('data.sin_centro', null)
            ->assertJsonPath('data.resumen.utilidad', '200.00');
        $this->getJson("/api/m7/v1/centros-costo/{$centro}/movimientos?{$noviembre}")->assertOk()
            ->assertJsonCount(1, 'data.movimientos');
        $this->getJson('/api/m7/v1/centros-costo?fecha_desde=2027-01-01&fecha_hasta=2027-01-31')->assertOk()
            ->assertJsonPath('data.hay_movimientos', false)
            ->assertJsonPath('data.resumen.utilidad', '0.00')
            ->assertJsonCount(1, 'data.centros');
        $this->getJson('/api/m7/v1/centros-costo?fecha_desde=2026-11-30&fecha_hasta=2026-11-01')->assertUnprocessable();
        $this->getJson('/api/m7/v1/centros-costo/sin-asignar/movimientos?fecha_desde=14-11-2026')->assertUnprocessable();
    }

    public function test_asientos_nuevos_imputan_ingresos_y_gastos_al_centro_general(): void
    {
        $this->prepararComercios();
        $general = (int) DB::table('centros_costo')->where('id_comercio', 1)->where('codigo', 'GEN')->value('id_centro_costo');

        $venta = $this->guardarAsiento(1, 'VENTA', ['1.1.02' => true, '4.1.01' => false]);
        $costo = $this->guardarAsiento(1, 'COSTO_VENTA', ['5.1.01' => true, '1.1.03' => false]);

        $this->assertSame([null, $general], $this->centrosDeLineas($venta));
        $this->assertSame([$general, null], $this->centrosDeLineas($costo));

        DB::table('centros_costo')->where('id_centro_costo', $general)->update(['activo' => false]);
        $sinGeneralActivo = $this->guardarAsiento(1, 'VENTA', ['1.1.02' => true, '4.1.01' => false], subpedido: 2);

        $this->assertSame([null, null], $this->centrosDeLineas($sinGeneralActivo));
    }

    #[DataProvider('rutas')]
    public function test_consultas_requieren_sesion_rol_y_aislamiento(string $ruta): void
    {
        $this->getJson($ruta)->assertUnauthorized();
        $this->actingAs(UsuarioFactory::new()->create());
        $this->getJson($ruta)->assertForbidden();
        $this->actingAs(UsuarioFactory::new()->create(['rol' => 'encargado_comercio', 'id_comercio' => 1]));
        $this->getJson($ruta.'?id_comercio=2')->assertForbidden();
        $this->getJson($ruta.'?id_comercio=0')->assertUnprocessable();
    }

    #[DataProvider('rutas')]
    public function test_consultas_vacias_no_inventan_registros(string $ruta): void
    {
        $this->actingAs(UsuarioFactory::new()->create(['rol' => 'encargado_comercio', 'id_comercio' => 1]));
        $respuesta = $this->getJson($ruta)->assertOk();
        $campo = str_ends_with($ruta, 'centros-costo') ? 'centros' : 'cuentas';
        $respuesta->assertJsonCount(0, 'data.'.$campo);
    }

    public static function rutas(): array
    {
        return [['/api/m7/v1/saldos-cobrar-pagar'], ['/api/m7/v1/centros-costo']];
    }

    #[DataProvider('rutas')]
    public function test_plataforma_debe_seleccionar_un_comercio_sin_agregar_todos(string $ruta): void
    {
        $this->prepararComercios();
        $this->movimiento(1, '1.1.02', '10.00', '0.00');
        $this->movimiento(2, '1.1.02', '90.00', '0.00');
        $this->actingAs(UsuarioFactory::new()->create(['rol' => 'encargado_plataforma']));

        $this->getJson($ruta)->assertUnprocessable();
        $respuesta = $this->getJson($ruta.'?id_comercio=2')->assertOk()->assertJsonPath('data.id_comercio', 2);
        if (str_ends_with($ruta, 'saldos-cobrar-pagar')) {
            $respuesta->assertJsonPath('data.resumen.por_cobrar', '90.00');
        } else {
            $respuesta->assertJsonCount(1, 'data.centros');
        }
    }

    public function test_subcuentas_no_duplican_el_saldo_del_padre_ni_incluyen_iva(): void
    {
        $this->prepararComercios();
        $padre = DB::table('cuentas_contables')->where('id_comercio', 1)->where('codigo', '1.1.02')->value('id_cuenta_contable');
        DB::table('cuentas_contables')->insert([
            'id_comercio' => 1, 'codigo' => '1.1.02.01', 'nombre' => 'Clientes nacionales',
            'tipo' => 'ACTIVO', 'nivel' => 4, 'id_cuenta_padre' => $padre,
        ]);
        $this->movimiento(1, '1.1.02', '10.00', '0.00');
        $this->movimiento(1, '1.1.02.01', '20.00', '0.00');
        $this->movimiento(1, '2.1.02', '0.00', '50.00');
        $this->actingAs(UsuarioFactory::new()->create(['rol' => 'encargado_comercio', 'id_comercio' => 1]));

        $this->getJson('/api/m7/v1/saldos-cobrar-pagar')->assertOk()
            ->assertJsonCount(4, 'data.cuentas')
            ->assertJsonPath('data.resumen.por_cobrar', '30.00')
            ->assertJsonPath('data.resumen.por_pagar', '0.00')
            ->assertJsonPath('data.cuentas.0.saldo', '10.00')
            ->assertJsonPath('data.cuentas.1.saldo', '20.00');
    }

    public function test_detalle_de_cuenta_explica_el_saldo_negativo_del_listado(): void
    {
        $this->prepararComercios();
        $this->movimiento(1, '1.1.04', '40.50', '0.00');
        $this->movimiento(1, '1.1.04', '0.00', '50.75', 'REEMBOLSO');
        $this->movimiento(1, '1.1.04', '999.00', '0.00', estado: 'ANULADO');
        $cuenta = DB::table('cuentas_contables')->where('id_comercio', 1)->where('codigo', '1.1.04')->value('id_cuenta_contable');
        $this->actingAs(UsuarioFactory::new()->create(['rol' => 'encargado_comercio', 'id_comercio' => 1]));

        $this->getJson("/api/m7/v1/libro-mayor?id_cuenta_contable={$cuenta}&id_comercio=1")->assertOk()
            ->assertJsonCount(2, 'data.movimientos')
            ->assertJsonPath('data.total_debe', '40.50')
            ->assertJsonPath('data.total_haber', '50.75')
            ->assertJsonPath('data.saldo_final', '-10.25')
            ->assertJsonPath('data.movimientos.1.saldo', '-10.25');
    }

    public function test_detalle_de_centro_inactivo_sin_movimientos_es_consultable(): void
    {
        $this->prepararComercios();
        $centro = (int) DB::table('centros_costo')->where('id_comercio', 1)->value('id_centro_costo');
        DB::table('centros_costo')->where('id_centro_costo', $centro)->update(['activo' => false]);
        $this->actingAs(UsuarioFactory::new()->create(['rol' => 'encargado_comercio', 'id_comercio' => 1]));

        $this->getJson("/api/m7/v1/centros-costo/{$centro}/movimientos")->assertOk()
            ->assertJsonPath('data.centro.activo', false)
            ->assertJsonPath('data.ingresos', '0.00')
            ->assertJsonPath('data.costos', '0.00')
            ->assertJsonPath('data.utilidad', '0.00')
            ->assertJsonCount(0, 'data.movimientos');
    }

    public function test_detalle_de_centro_exige_autorizacion_y_no_expone_centros_ajenos(): void
    {
        $this->prepararComercios();
        $centro = (int) DB::table('centros_costo')->where('id_comercio', 2)->value('id_centro_costo');
        $ruta = "/api/m7/v1/centros-costo/{$centro}/movimientos";
        $this->getJson($ruta)->assertUnauthorized();
        $this->actingAs(UsuarioFactory::new()->create());
        $this->getJson($ruta)->assertForbidden();
        $this->actingAs(UsuarioFactory::new()->create(['rol' => 'encargado_comercio', 'id_comercio' => 1]));
        $this->getJson($ruta)->assertNotFound();
        $this->getJson('/api/m7/v1/centros-costo/999/movimientos')->assertNotFound();
        $this->getJson($ruta.'?id_comercio=2')->assertForbidden();
        $this->getJson($ruta.'?id_comercio=0')->assertUnprocessable();
        $this->getJson('/api/m7/v1/centros-costo/sin-asignar/movimientos?id_comercio=2')->assertForbidden();

        $this->actingAs(UsuarioFactory::new()->create(['rol' => 'encargado_plataforma']));
        $this->getJson($ruta)->assertUnprocessable();
        $this->getJson('/api/m7/v1/centros-costo/sin-asignar/movimientos')->assertUnprocessable();
        $this->getJson($ruta.'?id_comercio=2')->assertOk()
            ->assertJsonPath('data.id_comercio', 2)
            ->assertJsonPath('data.centro.id_centro_costo', $centro);
    }

    public function test_asiento_completo_muestra_todas_sus_lineas_con_centro_y_totales(): void
    {
        $this->prepararComercios();
        $venta = $this->guardarAsiento(1, 'VENTA', ['1.1.02' => true, '4.1.01' => false]);
        $ajeno = $this->guardarAsiento(2, 'VENTA', ['1.1.02' => true, '4.1.01' => false]);
        $ruta = "/api/m7/v1/asientos/{$venta}";
        $this->getJson($ruta)->assertUnauthorized();
        $this->actingAs(UsuarioFactory::new()->create());
        $this->getJson($ruta)->assertForbidden();

        $this->actingAs(UsuarioFactory::new()->create(['rol' => 'encargado_comercio', 'id_comercio' => 1]));
        $this->getJson($ruta)->assertOk()
            ->assertJsonPath('data.tipo', 'VENTA')
            ->assertJsonPath('data.id_subpedido', 1)
            ->assertJsonPath('data.total_debe', '10.00')
            ->assertJsonPath('data.total_haber', '10.00')
            ->assertJsonPath('data.cuadrado', true)
            ->assertJsonPath('data.lineas.0.codigo_cuenta', '1.1.02')
            ->assertJsonPath('data.lineas.0.centro', null)
            ->assertJsonPath('data.lineas.1.codigo_cuenta', '4.1.01')
            ->assertJsonPath('data.lineas.1.centro.codigo', 'GEN')
            ->assertJsonPath('data.lineas.1.haber', '10.00');
        $this->getJson("/api/m7/v1/asientos/{$ajeno}")->assertNotFound();
        $this->getJson($ruta.'?id_comercio=2')->assertForbidden();

        $this->actingAs(UsuarioFactory::new()->create(['rol' => 'encargado_plataforma']));
        $this->getJson($ruta)->assertUnprocessable();
        $this->getJson("/api/m7/v1/asientos/{$ajeno}?id_comercio=2")->assertOk()->assertJsonPath('data.id_comercio', 2);
    }

    public function test_plataforma_lista_comercios_contables_por_nombre_de_m2(): void
    {
        $this->prepararComercios();
        app(M7_PlanCuentasSeeder::class)->sembrarComercio(3);
        $directorio = Mockery::mock(DirectorioComerciosM7GatewayInterface::class);
        $directorio->shouldReceive('obtenerNombres')->once()->with([1, 2, 3])->andReturn([1 => 'Soluciones del Milenio', 2 => 'Café Volcán']);
        $this->app->instance(DirectorioComerciosM7GatewayInterface::class, $directorio);
        $this->actingAs(UsuarioFactory::new()->create(['rol' => 'encargado_plataforma']));

        $this->getJson('/api/m7/v1/comercios')->assertOk()->assertExactJson(['success' => true, 'data' => ['comercios' => [
            ['id_comercio' => 2, 'nombre' => 'Café Volcán', 'nombre_disponible' => true],
            ['id_comercio' => 1, 'nombre' => 'Soluciones del Milenio', 'nombre_disponible' => true],
            ['id_comercio' => 3, 'nombre' => 'Comercio #3', 'nombre_disponible' => false],
        ]]]);
    }

    public function test_lista_de_comercios_usa_identificadores_si_m2_falla(): void
    {
        $this->prepararComercios();
        $directorio = Mockery::mock(DirectorioComerciosM7GatewayInterface::class);
        $directorio->shouldReceive('obtenerNombres')->andThrow(new RuntimeException('M2 no disponible'));
        $this->app->instance(DirectorioComerciosM7GatewayInterface::class, $directorio);
        $this->actingAs(UsuarioFactory::new()->create(['rol' => 'encargado_plataforma']));

        $this->getJson('/api/m7/v1/comercios')->assertOk()
            ->assertJsonPath('data.comercios.0.nombre', 'Comercio #1')
            ->assertJsonPath('data.comercios.1.nombre', 'Comercio #2');
    }

    public function test_lista_de_comercios_es_exclusiva_de_plataforma(): void
    {
        $this->getJson('/api/m7/v1/comercios')->assertUnauthorized();
        $this->actingAs(UsuarioFactory::new()->create(['rol' => 'encargado_comercio', 'id_comercio' => 1]));
        $this->getJson('/api/m7/v1/comercios')->assertForbidden();
    }

    public function test_directorio_consulta_m2_en_lotes_de_cincuenta(): void
    {
        $ids = range(1, 51);
        $comercios = Mockery::mock(ComerciosServiceInterface::class);
        $comercios->shouldReceive('obtenerResumenComerciosLote')->once()->with(range(1, 50))
            ->andReturn(['comercios' => [['id_comercio' => 7, 'nombre_comercial' => 'Temulandia'], ['id_comercio' => 99, 'nombre_comercial' => 'Ajeno']]]);
        $comercios->shouldReceive('obtenerResumenComerciosLote')->once()->with([51])->andReturn(['comercios' => []]);

        $nombres = (new M2DirectorioComerciosGateway($comercios))->obtenerNombres($ids);

        $this->assertSame([7 => 'Temulandia'], $nombres);
    }

    private function prepararComercios(): void
    {
        foreach ([1, 2] as $comercio) {
            app(M7_PlanCuentasSeeder::class)->sembrarComercio($comercio);
        }
    }

    /** @param array<string, bool> $lineas código de cuenta => true si va al debe */
    private function guardarAsiento(int $comercio, string $tipo, array $lineas, int $subpedido = 1): int
    {
        $asiento = new AsientoContable(0, $comercio, 1, $subpedido, (string) Str::uuid(), 'PRUEBA', $tipo, '2026-11-14 12:00:00');
        foreach ($lineas as $codigo => $alDebe) {
            $cuenta = (int) DB::table('cuentas_contables')->where('id_comercio', $comercio)->where('codigo', $codigo)->value('id_cuenta_contable');
            $asiento->agregarDetalle(new DetalleAsiento($cuenta, $alDebe ? '10.00' : '0', $alDebe ? '0' : '10.00'));
        }

        return app(ContextoComercio::class)->ejecutarComoSistema(fn () => app(AsientoRepository::class)->guardar($asiento))->id;
    }

    /** @return list<int|null> */
    private function centrosDeLineas(int $asiento): array
    {
        return DB::table('detalle_asientos')->where('id_asiento', $asiento)->orderBy('numero_linea')->pluck('id_centro_costo')
            ->map(fn (int|string|null $centro): ?int => $centro === null ? null : (int) $centro)->all();
    }

    private function movimiento(int $comercio, string $codigo, string $debe, string $haber, string $tipo = 'MANUAL', string $estado = 'REGISTRADO', ?int $centro = null, string $fecha = '2026-11-14 12:00:00'): int
    {
        $cuenta = DB::table('cuentas_contables')->where('id_comercio', $comercio)->where('codigo', $codigo)->value('id_cuenta_contable');
        $asiento = DB::table('asientos_contables')->insertGetId([
            'id_comercio' => $comercio, 'evento_id' => (string) Str::uuid(),
            'tipo_evento' => 'PRUEBA', 'tipo' => $tipo, 'estado' => $estado, 'fecha' => $fecha,
        ], 'id_asiento');
        DB::table('detalle_asientos')->insert([
            'id_asiento' => $asiento, 'id_cuenta_contable' => $cuenta,
            'id_centro_costo' => $centro, 'numero_linea' => 1, 'debe' => $debe, 'haber' => $haber,
        ]);

        return (int) $asiento;
    }
}
