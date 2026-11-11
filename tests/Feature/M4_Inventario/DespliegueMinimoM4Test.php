<?php

namespace Tests\Feature\M4_Inventario;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M4_Inventario\Dependencias\CatalogoDependencia;
use App\Contracts\M4_Inventario\Dependencias\ComerciosDependencia;
use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Events\M4_Inventario\ExistenciaAgotada;
use App\Events\M4_Inventario\ExistenciaRepuesta;
use App\Events\M4_Inventario\ExistenciaReservada;
use App\Models\M2_Comercios\Comercio;
use App\Services\M4_Inventario\Dependencias\CatalogoReal;
use App\Services\M4_Inventario\Dependencias\ComerciosReal;
use App\Services\M4_Inventario\Dependencias\NucleoReal;
use App\Services\M4_Inventario\Dobles\CatalogoStub;
use App\Services\M4_Inventario\Dobles\ComerciosStub;
use App\Services\M4_Inventario\Dobles\NucleoStub;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use App\Services\M4_Inventario\InventarioService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Despliegue mínimo de M4: cambio de doble a servicio real por configuración,
 * rutas del contrato bajo /api/m4/v1, disponibilidad, reserva atómica y liberación.
 */
class DespliegueMinimoM4Test extends TestCase
{
    use EsquemaInventarioPrueba;

    private int $ultimaUnidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaInventario();

        // Variación 10: la última unidad. Variación 11: sin stock en la ubicación 1 y 4 disponibles en la 2.
        $this->ultimaUnidad = $this->existencia(['id_variacion' => 10, 'id_ubicacion' => 1, 'cantidad_fisica' => 1, 'stock_minimo' => 2]);
        $this->existencia(['id_variacion' => 11, 'id_ubicacion' => 1, 'cantidad_fisica' => 0]);
        $this->existencia(['id_variacion' => 11, 'id_ubicacion' => 2, 'cantidad_fisica' => 5, 'cantidad_reservada' => 1]);
    }

    private function inventario(): InventarioModuleInterface
    {
        return app(InventarioModuleInterface::class);
    }

    private function esperarError(string $codigo, int $http, callable $accion): void
    {
        try {
            $accion();
            $this->fail("Debía lanzar {$codigo}.");
        } catch (ErrorInventarioException $e) {
            $this->assertSame($codigo, $e->codigo);
            $this->assertSame($http, $e->http);
        }
    }

    // ---------------------------------------------------------------- cambio de doble a real

    public function test_por_defecto_las_dependencias_de_m4_son_los_dobles(): void
    {
        $this->assertInstanceOf(ComerciosStub::class, app(ComerciosDependencia::class));
        $this->assertInstanceOf(CatalogoStub::class, app(CatalogoDependencia::class));
        $this->assertInstanceOf(NucleoStub::class, app(NucleoDependencia::class));
        $this->assertInstanceOf(InventarioService::class, $this->inventario());
        $this->assertInstanceOf(InventarioService::class, app(InventarioServiceInterface::class));
    }

    public function test_cada_dependencia_cambia_a_real_por_configuracion(): void
    {
        config([
            'integraciones.m4.m2.modo' => 'real',
            'integraciones.m4.m3.modo' => 'real',
            'integraciones.m4.nucleo.modo' => 'real',
        ]);

        $this->assertInstanceOf(ComerciosReal::class, app(ComerciosDependencia::class));
        $this->assertInstanceOf(CatalogoReal::class, app(CatalogoDependencia::class));
        $this->assertInstanceOf(NucleoReal::class, app(NucleoDependencia::class));
    }

    public function test_un_doble_se_reemplaza_cambiando_la_clase_en_la_configuracion(): void
    {
        $otro = new class extends ComerciosStub {};
        config(['integraciones.m4.m2.doble' => $otro::class]);

        $this->assertInstanceOf($otro::class, app(ComerciosDependencia::class));
    }

    public function test_un_modo_desconocido_falla_con_un_mensaje_claro(): void
    {
        config(['integraciones.m4.m3.modo' => 'otro']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("integraciones.m4.m3.modo debe ser 'doble' o 'real'.");

        app(CatalogoDependencia::class);
    }

    public function test_con_m3_real_la_reserva_usa_el_servicio_de_catalogo(): void
    {
        config(['integraciones.m4.m3.modo' => 'real']);

        $reserva = $this->inventario()->reservarExistencias(1, 10, 1);

        $this->assertSame('activa', $reserva['estado_reserva']);
        $this->esperarError('ERR-M4-013', 404, fn () => $this->inventario()->reservarExistencias(1, 5000, 1));
    }

    public function test_con_m2_real_m4_usa_el_estado_del_comercio(): void
    {
        // Tablas de M2 y dos comercios: uno activo y uno suspendido.
        $this->assertSame(0, Artisan::call('migrate', ['--path' => 'database/migrations/M2_Comercios']));
        $activo = $this->comercioM2(Comercio::ACTIVO);
        $suspendido = $this->comercioM2(Comercio::SUSPENDIDO);

        // Una sola instancia de M2 real en la prueba: resolver ComerciosServiceInterface dos veces
        // en el mismo proceso hoy entra en un ciclo M2 -> M5 -> M2 (reportado a M2 y M5).
        $this->app->instance(ComerciosServiceInterface::class, app(ComerciosServiceInterface::class));
        config(['integraciones.m4.m2.modo' => 'real']);
        $m2 = app(ComerciosDependencia::class);
        $this->assertInstanceOf(ComerciosReal::class, $m2);

        // ObtenerComercioActivo de M2 real llega a M4 con estado y activo.
        $this->assertSame(
            ['id_comercio' => $activo, 'estado' => 'Activo', 'activo' => true, 'motivo_estado' => null],
            $m2->obtenerComercioActivo($activo),
        );
        $respuesta = $m2->obtenerComercioActivo($suspendido);
        $this->assertSame('Suspendido', $respuesta['estado']);
        $this->assertFalse($respuesta['activo']);

        // Con el comercio suspendido, M4 no reserva (RN-M4-009).
        $this->esperarError('ERR-M4-015', 409, fn () => $this->inventario()->reservarExistencias($suspendido, 10, 1));
        $this->assertSame(0.0, (float) DB::table('existencias')->where('id_existencia', $this->ultimaUnidad)->value('cantidad_reservada'));
    }

    private function comercioM2(string $estado): int
    {
        static $secuencia = 400000;
        $secuencia++;

        return Comercio::query()->create([
            'cedula_juridica' => '3-101-'.$secuencia,
            'nombre_comercial' => 'Comercio '.$secuencia,
            'razon_social' => 'Comercio '.$secuencia.' S.A.',
            'direccion_fiscal' => 'Liberia, Guanacaste',
            'inscrito_como_contribuyente' => true,
            'estado' => $estado,
        ])->id_comercio;
    }

    // ---------------------------------------------------------------- disponibilidad

    public function test_m4_expone_la_api_del_contrato_en_api_m4_v1(): void
    {
        $this->getJson('/api/m4/v1/health')->assertOk()->assertExactJson(['status' => 'ok', 'module' => 'm4-inventario']);
        $this->getJson('/api/m4/v1/existencias/disponibilidad?id_comercio=1&id_variacion=10')
            ->assertOk()
            ->assertJsonPath('cantidad_disponible', 1);
        $this->getJson('/api/m4/v1/existencias/disponibilidad?id_comercio=0&id_variacion=10')
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005');
    }

    public function test_la_disponibilidad_resta_lo_reservado_y_suma_ubicaciones(): void
    {
        $this->assertSame([
            'id_comercio' => 1,
            'id_variacion' => 11,
            'id_ubicacion' => null,
            'cantidad_disponible' => 4,
            'estado_disponibilidad' => 'disponible',
            'bajo_stock' => false,
        ], $this->inventario()->consultarStockDisponible(1, 11));
    }

    public function test_sin_existencia_registrada_devuelve_agotado_sin_error(): void
    {
        $disponibilidad = $this->inventario()->consultarStockDisponible(1, 99);

        $this->assertSame(0, $disponibilidad['cantidad_disponible']);
        $this->assertSame('agotado', $disponibilidad['estado_disponibilidad']);
        $this->assertFalse($disponibilidad['bajo_stock']);
    }

    public function test_la_disponibilidad_por_producto_agrega_sus_variaciones(): void
    {
        $this->assertSame(
            ['id_comercio' => 1, 'id_producto' => 1, 'estado_disponibilidad' => 'disponible'],
            $this->inventario()->consultarDisponibilidadProducto(1, 1),
        );
        $this->assertSame('agotado', $this->inventario()->consultarDisponibilidadProducto(1, 77)['estado_disponibilidad']);
    }

    public function test_los_lotes_conservan_el_orden(): void
    {
        $lote = $this->inventario()->consultarDisponibilidadLote([
            ['id_comercio' => 1, 'id_variacion' => 99],
            ['id_comercio' => 1, 'id_variacion' => 11, 'id_ubicacion' => 2],
        ]);

        $this->assertSame('agotado', $lote[0]['estado_disponibilidad']);
        $this->assertSame(4, $lote[1]['cantidad_disponible']);
        $this->assertSame(2, $lote[1]['id_ubicacion']);

        $productos = $this->inventario()->consultarDisponibilidadProductoLote([
            ['id_comercio' => 1, 'id_producto' => 1],
            ['id_comercio' => 1, 'id_producto' => 77],
        ]);

        $this->assertSame(['disponible', 'agotado'], array_column($productos, 'estado_disponibilidad'));
    }

    public function test_un_lote_de_mas_de_100_elementos_responde_err_005_en_formato_estandar(): void
    {
        try {
            $this->inventario()->consultarDisponibilidadLote(array_fill(0, 101, ['id_comercio' => 1, 'id_variacion' => 10]));
            $this->fail('Debía lanzar ERR-005.');
        } catch (ErrorInventarioException $e) {
            $this->assertSame(422, $e->http);
            $this->assertSame(['error' => ['codigo', 'mensaje', 'detalles']], [array_keys($e->toArray())[0] => array_keys($e->toArray()['error'])]);
            $this->assertSame('ERR-005', $e->toArray()['error']['codigo']);
        }
    }

    public function test_un_identificador_invalido_responde_err_005_con_el_campo(): void
    {
        try {
            $this->inventario()->consultarStockDisponible(1, 0);
            $this->fail('Debía lanzar ERR-005.');
        } catch (ErrorInventarioException $e) {
            $this->assertSame('ERR-005', $e->codigo);
            $this->assertArrayHasKey('id_variacion', $e->detalles);
        }
    }

    // ---------------------------------------------------------------- reserva

    public function test_la_ultima_unidad_solo_se_reserva_una_vez(): void
    {
        Event::fake();

        $reserva = $this->inventario()->reservarExistencias(1, 10, 1);

        $this->assertSame('activa', $reserva['estado_reserva']);
        $this->assertSame(10, $reserva['id_variacion']);
        $this->assertSame(1, $reserva['id_ubicacion']);
        $this->assertSame(1, (int) DB::table('existencias')->where('id_existencia', $this->ultimaUnidad)->value('cantidad_reservada'));

        $this->esperarError('ERR-M4-001', 409, fn () => $this->inventario()->reservarExistencias(1, 10, 1));

        $this->assertSame(1, (int) DB::table('existencias')->where('id_existencia', $this->ultimaUnidad)->value('cantidad_reservada'));
        $this->assertSame(1, DB::table('reservas')->count());

        Event::assertDispatched(ExistenciaReservada::class, fn ($e) => $e->payload()['id_reserva'] === $reserva['id_reserva']);
        Event::assertDispatched(ExistenciaAgotada::class, fn ($e) => $e->payload()['id_variacion'] === 10 && $e->payload()['id_ubicacion'] === null);
    }

    public function test_sin_ubicacion_se_elige_una_con_toda_la_cantidad(): void
    {
        $reserva = $this->inventario()->reservarExistencias(1, 11, 3);

        $this->assertSame(2, $reserva['id_ubicacion']);
    }

    public function test_una_ubicacion_que_no_es_del_comercio_responde_err_m4_014(): void
    {
        $this->esperarError('ERR-M4-014', 404, fn () => $this->inventario()->reservarExistencias(1, 10, 1, 7));
    }

    public function test_una_variacion_sin_existencia_responde_err_m4_002(): void
    {
        $this->esperarError('ERR-M4-002', 404, fn () => $this->inventario()->reservarExistencias(1, 99, 1));
    }

    public function test_una_variacion_de_otro_comercio_responde_err_003(): void
    {
        $this->esperarError('ERR-003', 404, fn () => $this->inventario()->reservarExistencias(2, 10, 1));
    }

    public function test_un_comercio_suspendido_no_puede_reservar(): void
    {
        $this->esperarError('ERR-M4-015', 409, fn () => $this->inventario()->reservarExistencias(ComerciosStub::COMERCIO_SUSPENDIDO, 10, 1));
    }

    public function test_la_misma_clave_de_idempotencia_devuelve_la_misma_reserva(): void
    {
        $a = $this->inventario()->reservarExistencias(1, 11, 2, null, 'clave-1');
        $b = $this->inventario()->reservarExistencias(1, 11, 2, null, 'clave-1');

        $this->assertSame($a['id_reserva'], $b['id_reserva']);
        $this->assertSame(1, DB::table('reservas')->count());

        $this->esperarError('ERR-M4-003', 409, fn () => $this->inventario()->reservarExistencias(1, 11, 1, null, 'clave-1'));
    }

    public function test_una_reserva_vencida_se_libera_al_reservar_de_nuevo(): void
    {
        $vieja = $this->inventario()->reservarExistencias(1, 10, 1);

        Carbon::setTestNow(now()->addMinutes(16));

        $nueva = $this->inventario()->reservarExistencias(1, 10, 1);

        $this->assertNotSame($vieja['id_reserva'], $nueva['id_reserva']);
        $this->assertSame(5, (int) DB::table('reservas')->where('id_reserva', $vieja['id_reserva'])->value('id_estado_reserva'));
        $this->assertSame(1, (int) DB::table('existencias')->where('id_existencia', $this->ultimaUnidad)->value('cantidad_reservada'));

        Carbon::setTestNow();
    }

    // ---------------------------------------------------------------- liberación

    public function test_liberar_devuelve_la_existencia_una_sola_vez(): void
    {
        Event::fake();
        $reserva = $this->inventario()->reservarExistencias(1, 10, 1);

        $liberada = $this->inventario()->liberarReserva($reserva['id_reserva'], 'pago_rechazado');

        $this->assertSame('liberada', $liberada['estado_reserva']);
        $this->assertSame('pago_rechazado', $liberada['motivo_cierre']);
        $this->assertNotNull($liberada['fecha_resolucion']);
        $this->assertSame(0, (int) DB::table('existencias')->where('id_existencia', $this->ultimaUnidad)->value('cantidad_reservada'));

        $this->inventario()->liberarReserva($reserva['id_reserva']);
        $this->assertSame(0, (int) DB::table('existencias')->where('id_existencia', $this->ultimaUnidad)->value('cantidad_reservada'));

        Event::assertDispatchedTimes(ExistenciaRepuesta::class, 1);
    }

    public function test_liberar_una_reserva_inexistente_o_con_id_invalido_responde_err_m4_004(): void
    {
        $this->esperarError('ERR-M4-004', 404, fn () => $this->inventario()->liberarReserva('00000000-0000-0000-0000-000000000000'));
        $this->esperarError('ERR-M4-004', 404, fn () => $this->inventario()->liberarReserva('no-es-uuid'));
    }

    public function test_no_se_libera_una_reserva_consumida(): void
    {
        $reserva = $this->inventario()->reservarExistencias(1, 10, 1);
        DB::table('reservas')->where('id_reserva', $reserva['id_reserva'])->update(['id_estado_reserva' => 3, 'id_pedido' => 1]);

        $this->esperarError('ERR-M4-008', 409, fn () => $this->inventario()->liberarReserva($reserva['id_reserva']));
    }

    // ---------------------------------------------------------------- interfaz anterior

    public function test_la_interfaz_anterior_sigue_funcionando_para_m5(): void
    {
        $servicio = app(InventarioServiceInterface::class);

        $this->assertSame(1, $servicio->consultarExistencias(10));

        $reserva = $servicio->solicitarReserva(10, 1, 55);

        $this->assertSame('RESERVADO', $reserva['estado']);
        $this->assertSame(55, (int) DB::table('reservas')->where('id_reserva', $reserva['reserva_id'])->value('id_pedido'));
    }
}
