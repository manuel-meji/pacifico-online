<?php

namespace Tests\Feature\M1_Portal;

use App\Contracts\M1_Portal\ComercialPortalContract;
use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Enums\Nucleo\RolUsuario;
use App\Models\M1_Portal\CargoDestacado;
use App\Models\M1_Portal\ProductoDestacado;
use App\Models\M1_Portal\SolicitudDestacado;
use App\Models\M1_Portal\TarifaDestacado;
use App\Models\Nucleo\Usuario;
use App\Services\M1_Portal\Dobles\DatosPrueba;
use App\Services\M1_Portal\PortadaService;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Doubles\M1\CatalogoNoDisponible;
use Tests\Doubles\M1\ComercialPortalFake;
use Tests\Feature\M1_Portal\Concerns\CreaTablasDeDestacados;
use Tests\Feature\M1_Portal\Concerns\ValidaEsquemaContrato;

/**
 * Solicitud, aprobación y cobro de productos destacados (contrato M1 1.4.0,
 * SC-08), por las rutas web con los roles del núcleo y el doble de M3.
 *
 * - El comercio solicita por 7, 15 o 30 días; se guarda la tarifa del momento.
 * - La administración aprueba (el periodo empieza al aprobar y se genera el
 *   cargo) o rechaza con motivo, y destaca por su cuenta sin costo.
 * - Máximo 3 destacados activos por comercio, contando todos.
 */
class DestacadosPagadosTest extends PruebaM1
{
    use CreaTablasDeDestacados;
    use ValidaEsquemaContrato;

    private const AHORA = '2026-11-10 12:00:00';

    private const ADMINISTRACION = 9001;

    private const ENCARGADO_LA_CEIBA = 9002;

    private const ENCARGADO_CAFE = 9003;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararDestacados();
        Carbon::setTestNow(self::AHORA);

        TarifaDestacado::query()->create(['periodo_dias' => 7, 'monto' => 15000]);
        TarifaDestacado::query()->create(['periodo_dias' => 15, 'monto' => 25000]);
        TarifaDestacado::query()->create(['periodo_dias' => 30, 'monto' => 40000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Tarifas
    // ---------------------------------------------------------------

    public function test_las_tarifas_son_publicas_y_cumplen_el_esquema(): void
    {
        $respuesta = $this->getJson(route('api.m1.v1.destacados.tarifas'))->assertOk();

        $this->assertSame([7, 15, 30], array_column($respuesta->json(), 'periodo_dias'));
        $this->assertEquals(15000, $respuesta->json('0.monto'));
        foreach ($respuesta->json() as $tarifa) {
            $this->assertCumpleEsquema('TarifaDestacado', $tarifa);
        }
    }

    public function test_la_administracion_define_la_tarifa_de_un_periodo(): void
    {
        $respuesta = $this->comoAdministracion()
            ->putJson(route('api.m1.v1.administracion.destacados.tarifas.definir', 15), ['monto' => 27500.5])
            ->assertOk();

        $this->assertCumpleEsquema('TarifaDestacado', $respuesta->json());
        $this->assertEquals(27500.5, (float) TarifaDestacado::query()->where('periodo_dias', 15)->value('monto'));
    }

    public function test_una_tarifa_invalida_responde_422(): void
    {
        $this->comoAdministracion()
            ->putJson(route('api.m1.v1.administracion.destacados.tarifas.definir', 10), ['monto' => -1])
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonStructure(['error' => ['detalles' => ['periodo_dias', 'monto']]]);
    }

    public function test_solo_la_administracion_define_tarifas(): void
    {
        $this->putJson(route('api.m1.v1.administracion.destacados.tarifas.definir', 7), ['monto' => 1])
            ->assertStatus(401);

        $this->comoComercio(self::ENCARGADO_LA_CEIBA, DatosPrueba::COMERCIO_LA_CEIBA)
            ->putJson(route('api.m1.v1.administracion.destacados.tarifas.definir', 7), ['monto' => 1])
            ->assertStatus(403)
            ->assertJsonPath('error.codigo', 'ERR-002');
    }

    // ---------------------------------------------------------------
    // El comercio solicita
    // ---------------------------------------------------------------

    public function test_el_comercio_solicita_destacar_su_producto_con_la_tarifa_vigente(): void
    {
        $respuesta = $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 7)->assertCreated();

        $this->assertCumpleEsquema('SolicitudDestacado', $respuesta->json());
        $respuesta->assertJsonPath('estado', 'pendiente')
            ->assertJsonPath('id_comercio', DatosPrueba::COMERCIO_LA_CEIBA)
            ->assertJsonPath('periodo_dias', 7)
            ->assertJsonPath('fecha_inicio', null);
        $this->assertEquals(15000, $respuesta->json('monto'));
    }

    public function test_el_comercio_no_puede_elegir_otro_comercio_en_el_cuerpo(): void
    {
        $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 7, ['id_comercio' => DatosPrueba::COMERCIO_CAFE_NICOYANO])
            ->assertCreated()
            ->assertJsonPath('id_comercio', DatosPrueba::COMERCIO_LA_CEIBA);
    }

    public function test_no_se_puede_solicitar_un_producto_de_otro_comercio_ni_uno_inexistente(): void
    {
        $this->solicitar(DatosPrueba::PRODUCTO_CAFE_MOLIDO, 7)->assertStatus(404)->assertJsonPath('error.codigo', 'ERR-004');
        $this->solicitar(DatosPrueba::PRODUCTO_INEXISTENTE, 7)->assertStatus(404)->assertJsonPath('error.codigo', 'ERR-004');

        $this->assertSame(0, SolicitudDestacado::query()->withoutGlobalScopes()->count());
    }

    public function test_el_periodo_debe_ser_7_15_o_30_y_tener_tarifa(): void
    {
        $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 10)
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.periodo_dias', 'Debe ser 7, 15 o 30.');

        TarifaDestacado::query()->where('periodo_dias', 30)->delete();

        $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 30)
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['detalles' => ['periodo_dias']]]);
    }

    public function test_un_producto_no_puede_tener_dos_solicitudes_pendientes(): void
    {
        $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 7)->assertCreated();

        $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 15)
            ->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M1-003');
    }

    public function test_no_se_puede_solicitar_un_producto_que_ya_esta_destacado(): void
    {
        $this->destacadoExistente(DatosPrueba::PRODUCTO_CAMISA_LINO, DatosPrueba::COMERCIO_LA_CEIBA);

        $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 7)
            ->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M1-003');
    }

    public function test_solo_un_encargado_de_comercio_puede_solicitar(): void
    {
        $this->postJson(route('api.m1.v1.comercio.destacados.solicitar'), ['id_producto' => 1520, 'periodo_dias' => 7])
            ->assertStatus(401);

        $this->actingAs($this->cuentaConRol(9100, RolUsuario::Cliente), 'web')
            ->postJson(route('api.m1.v1.comercio.destacados.solicitar'), ['id_producto' => 1520, 'periodo_dias' => 7])
            ->assertStatus(403)
            ->assertJsonPath('error.codigo', 'ERR-002');
    }

    public function test_si_m3_no_responde_la_solicitud_responde_503(): void
    {
        config(['integraciones.m1.m3.doble' => CatalogoNoDisponible::class]);

        $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 7)
            ->assertStatus(503)
            ->assertJsonPath('error.codigo', 'ERR-006');
    }

    public function test_cada_comercio_solo_ve_sus_solicitudes(): void
    {
        $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 7)->assertCreated();
        $this->comoComercio(self::ENCARGADO_CAFE, DatosPrueba::COMERCIO_CAFE_NICOYANO)
            ->postJson(route('api.m1.v1.comercio.destacados.solicitar'), ['id_producto' => DatosPrueba::PRODUCTO_CAFE_MOLIDO, 'periodo_dias' => 15])
            ->assertCreated();

        $respuesta = $this->comoComercio(self::ENCARGADO_LA_CEIBA, DatosPrueba::COMERCIO_LA_CEIBA)
            ->getJson(route('api.m1.v1.comercio.destacados.solicitudes'))
            ->assertOk();

        $this->assertSame([DatosPrueba::PRODUCTO_CAMISA_LINO], array_column($respuesta->json(), 'id_producto'));
        $this->assertSame([DatosPrueba::COMERCIO_LA_CEIBA], array_column($respuesta->json(), 'id_comercio'));
    }

    // ---------------------------------------------------------------
    // La administración aprueba o rechaza
    // ---------------------------------------------------------------

    public function test_al_aprobar_el_periodo_empieza_ahora_y_se_genera_el_cargo(): void
    {
        $id = $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 15)->json('id_solicitud_destacado');

        $respuesta = $this->comoAdministracion()
            ->postJson(route('api.m1.v1.administracion.destacados.aprobar', $id))
            ->assertOk();

        $this->assertCumpleEsquema('SolicitudDestacado', $respuesta->json());
        $respuesta->assertJsonPath('estado', 'aprobada');
        $this->assertTrue(Carbon::parse($respuesta->json('fecha_inicio'))->equalTo(Carbon::parse(self::AHORA)));
        $this->assertTrue(Carbon::parse($respuesta->json('fecha_fin'))->equalTo(Carbon::parse(self::AHORA)->addDays(15)));

        $destacado = ProductoDestacado::query()->sole();
        $this->assertSame(ProductoDestacado::ORIGEN_SOLICITUD, $destacado->origen);
        $this->assertSame(DatosPrueba::COMERCIO_LA_CEIBA, $destacado->id_comercio);
        $this->assertSame($id, $destacado->id_solicitud_destacado);

        $cargo = CargoDestacado::query()->withoutGlobalScopes()->sole();
        $this->assertEquals(25000, (float) $cargo->monto);
        $this->assertSame(DatosPrueba::COMERCIO_LA_CEIBA, $cargo->id_comercio);
        $this->assertSame(15, $cargo->periodo_dias);
    }

    public function test_se_cobra_la_tarifa_de_la_solicitud_aunque_cambie_despues(): void
    {
        $id = $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 7)->json('id_solicitud_destacado');
        $this->comoAdministracion()->putJson(route('api.m1.v1.administracion.destacados.tarifas.definir', 7), ['monto' => 99000])->assertOk();

        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.destacados.aprobar', $id))->assertOk();

        $this->assertEquals(15000, (float) CargoDestacado::query()->withoutGlobalScopes()->value('monto'));
    }

    public function test_un_periodo_con_tarifa_cero_no_genera_cargo(): void
    {
        TarifaDestacado::query()->where('periodo_dias', 7)->update(['monto' => 0]);
        $id = $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 7)->json('id_solicitud_destacado');

        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.destacados.aprobar', $id))->assertOk();

        $this->assertSame(0, CargoDestacado::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, ProductoDestacado::query()->count());
    }

    public function test_no_se_aprueba_si_el_comercio_ya_tiene_3_destacados_activos(): void
    {
        // Tres destacados activos de La Ceiba (uno puesto por la administración también cuenta).
        $this->destacadoExistente(7001, DatosPrueba::COMERCIO_LA_CEIBA);
        $this->destacadoExistente(7002, DatosPrueba::COMERCIO_LA_CEIBA);
        $this->destacadoExistente(7003, DatosPrueba::COMERCIO_LA_CEIBA, ProductoDestacado::ORIGEN_PLATAFORMA);
        $id = $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 7)->json('id_solicitud_destacado');

        $this->comoAdministracion()
            ->postJson(route('api.m1.v1.administracion.destacados.aprobar', $id))
            ->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M1-002');

        $this->assertSame(SolicitudDestacado::ESTADO_PENDIENTE, SolicitudDestacado::query()->withoutGlobalScopes()->value('estado'));
        $this->assertSame(0, CargoDestacado::query()->withoutGlobalScopes()->count());
    }

    public function test_un_destacado_vencido_o_inactivo_no_cuenta_para_el_maximo(): void
    {
        $this->destacadoExistente(7001, DatosPrueba::COMERCIO_LA_CEIBA);
        $this->destacadoExistente(7002, DatosPrueba::COMERCIO_LA_CEIBA);
        $this->destacadoExistente(7003, DatosPrueba::COMERCIO_LA_CEIBA, inicio: '2026-10-01 00:00:00', fin: '2026-10-31 23:59:59');
        $this->destacadoExistente(7004, DatosPrueba::COMERCIO_LA_CEIBA, activo: false);
        $id = $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 7)->json('id_solicitud_destacado');

        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.destacados.aprobar', $id))->assertOk();
    }

    public function test_una_solicitud_ya_resuelta_no_se_vuelve_a_resolver(): void
    {
        $id = $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 7)->json('id_solicitud_destacado');
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.destacados.aprobar', $id))->assertOk();

        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.destacados.aprobar', $id))
            ->assertStatus(409)->assertJsonPath('error.codigo', 'ERR-M1-003');
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.destacados.rechazar', $id), ['motivo' => 'Tarde'])
            ->assertStatus(409)->assertJsonPath('error.codigo', 'ERR-M1-003');
        $this->assertSame(1, CargoDestacado::query()->withoutGlobalScopes()->count());
    }

    public function test_una_solicitud_inexistente_responde_404(): void
    {
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.destacados.aprobar', 999))
            ->assertStatus(404)->assertJsonPath('error.codigo', 'ERR-004');
    }

    public function test_el_rechazo_exige_un_motivo_y_no_genera_cargo(): void
    {
        $id = $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 7)->json('id_solicitud_destacado');

        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.destacados.rechazar', $id), ['motivo' => '  '])
            ->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['motivo']]]);

        $respuesta = $this->comoAdministracion()
            ->postJson(route('api.m1.v1.administracion.destacados.rechazar', $id), ['motivo' => 'La imagen no cumple las políticas.'])
            ->assertOk();

        $this->assertCumpleEsquema('SolicitudDestacado', $respuesta->json());
        $respuesta->assertJsonPath('estado', 'rechazada')->assertJsonPath('motivo_rechazo', 'La imagen no cumple las políticas.');
        $this->assertSame(0, CargoDestacado::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, ProductoDestacado::query()->count());
    }

    public function test_la_administracion_lista_todas_las_solicitudes_y_filtra(): void
    {
        $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 7)->assertCreated();
        $this->comoComercio(self::ENCARGADO_CAFE, DatosPrueba::COMERCIO_CAFE_NICOYANO)
            ->postJson(route('api.m1.v1.comercio.destacados.solicitar'), ['id_producto' => DatosPrueba::PRODUCTO_CAFE_MOLIDO, 'periodo_dias' => 15])
            ->assertCreated();

        $this->comoAdministracion()->getJson(route('api.m1.v1.administracion.destacados.solicitudes'))
            ->assertOk()->assertJsonCount(2);
        $this->comoAdministracion()->getJson(route('api.m1.v1.administracion.destacados.solicitudes', ['id_comercio' => DatosPrueba::COMERCIO_CAFE_NICOYANO]))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id_producto', DatosPrueba::PRODUCTO_CAFE_MOLIDO);
        $this->comoAdministracion()->getJson(route('api.m1.v1.administracion.destacados.solicitudes', ['estado' => 'otro']))
            ->assertStatus(422);
    }

    public function test_un_encargado_de_comercio_no_puede_aprobar(): void
    {
        $id = $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 7)->json('id_solicitud_destacado');

        $this->comoComercio(self::ENCARGADO_LA_CEIBA, DatosPrueba::COMERCIO_LA_CEIBA)
            ->postJson(route('api.m1.v1.administracion.destacados.aprobar', $id))
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // La administración destaca por su cuenta
    // ---------------------------------------------------------------

    public function test_la_administracion_destaca_un_producto_sin_costo_con_fechas_libres(): void
    {
        $respuesta = $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.destacados.crear'), [
            'id_producto' => DatosPrueba::PRODUCTO_CAFE_MOLIDO,
            'fecha_inicio' => '2026-12-01T00:00:00-06:00',
            'fecha_fin' => '2026-12-24T23:59:59-06:00',
        ])->assertCreated();

        $this->assertCumpleEsquema('DestacadoPlataforma', $respuesta->json());
        $respuesta->assertJsonPath('origen', 'plataforma')->assertJsonPath('id_comercio', DatosPrueba::COMERCIO_CAFE_NICOYANO);
        $this->assertSame(0, CargoDestacado::query()->withoutGlobalScopes()->count());
    }

    public function test_los_destacados_de_la_administracion_tambien_respetan_el_maximo_de_3(): void
    {
        $this->destacadoExistente(7001, DatosPrueba::COMERCIO_CAFE_NICOYANO);
        $this->destacadoExistente(7002, DatosPrueba::COMERCIO_CAFE_NICOYANO);
        $this->destacadoExistente(7003, DatosPrueba::COMERCIO_CAFE_NICOYANO);

        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.destacados.crear'), [
            'id_producto' => DatosPrueba::PRODUCTO_CAFE_MOLIDO,
            'fecha_inicio' => '2026-11-15T00:00:00-06:00',
            'fecha_fin' => '2026-11-20T00:00:00-06:00',
        ])->assertStatus(409)->assertJsonPath('error.codigo', 'ERR-M1-002');
    }

    public function test_un_producto_no_se_destaca_dos_veces_en_fechas_que_se_cruzan(): void
    {
        $this->destacadoExistente(DatosPrueba::PRODUCTO_CAFE_MOLIDO, DatosPrueba::COMERCIO_CAFE_NICOYANO);

        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.destacados.crear'), [
            'id_producto' => DatosPrueba::PRODUCTO_CAFE_MOLIDO,
            'fecha_inicio' => '2026-11-20T00:00:00-06:00',
            'fecha_fin' => '2026-12-20T00:00:00-06:00',
        ])->assertStatus(409)->assertJsonPath('error.codigo', 'ERR-M1-003');
    }

    public function test_las_fechas_de_un_destacado_de_la_administracion_se_validan(): void
    {
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.destacados.crear'), [
            'id_producto' => DatosPrueba::PRODUCTO_CAFE_MOLIDO,
            'fecha_inicio' => '2026-12-10T00:00:00-06:00',
            'fecha_fin' => '2026-12-01T00:00:00-06:00',
        ])->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['fecha_fin']]]);

        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.destacados.crear'), [
            'id_producto' => DatosPrueba::PRODUCTO_CAFE_MOLIDO,
            'fecha_inicio' => '1 de diciembre',
        ])->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['fecha_inicio', 'fecha_fin']]]);
    }

    // ---------------------------------------------------------------
    // Portada y cargos para la liquidación
    // ---------------------------------------------------------------

    public function test_un_destacado_aprobado_aparece_en_la_portada(): void
    {
        $id = $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 7)->json('id_solicitud_destacado');
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.destacados.aprobar', $id))->assertOk();

        $productos = app(PortadaService::class)->listarDestacados(PortadaService::LIMITE_POR_DEFECTO)->productos;

        $this->assertSame([DatosPrueba::PRODUCTO_CAMISA_LINO], array_map(fn ($producto): int => $producto->idProducto, $productos));
    }

    public function test_los_cargos_se_consultan_por_comercio_y_rango_de_fechas(): void
    {
        $camisa = $this->solicitar(DatosPrueba::PRODUCTO_CAMISA_LINO, 7)->json('id_solicitud_destacado');
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.destacados.aprobar', $camisa))->assertOk();

        $cafe = $this->comoComercio(self::ENCARGADO_CAFE, DatosPrueba::COMERCIO_CAFE_NICOYANO)
            ->postJson(route('api.m1.v1.comercio.destacados.solicitar'), ['id_producto' => DatosPrueba::PRODUCTO_CAFE_MOLIDO, 'periodo_dias' => 30])
            ->json('id_solicitud_destacado');
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.destacados.aprobar', $cafe))->assertOk();

        $contrato = app(ComercialPortalContract::class);
        $cargos = $contrato->listarCargosDestacado(DatosPrueba::COMERCIO_LA_CEIBA, '2026-11-01', '2026-11-30');

        $this->assertCount(1, $cargos);
        $this->assertCumpleEsquema('CargoDestacado', $cargos[0]);
        $this->assertSame(DatosPrueba::PRODUCTO_CAMISA_LINO, $cargos[0]['id_producto']);
        $this->assertSame(15000.0, $cargos[0]['monto']);
        $this->assertSame([], $contrato->listarCargosDestacado(DatosPrueba::COMERCIO_LA_CEIBA, '2026-12-01', '2026-12-31'));
        // El día límite se incluye completo.
        $this->assertCount(1, $contrato->listarCargosDestacado(DatosPrueba::COMERCIO_LA_CEIBA, '2026-11-10', '2026-11-10'));
    }

    public function test_los_cargos_validan_las_fechas(): void
    {
        $this->expectException(DatosInvalidosException::class);

        app(ComercialPortalContract::class)->listarCargosDestacado(DatosPrueba::COMERCIO_LA_CEIBA, '2026-11-30', '2026-11-01');
    }

    public function test_el_doble_del_contrato_comercial_se_comporta_como_el_contrato(): void
    {
        $doble = new ComercialPortalFake;

        $this->assertInstanceOf(ComercialPortalContract::class, $doble);
        $this->assertCount(2, $doble->listarCargosDestacado(ComercialPortalFake::COMERCIO_CON_CARGOS, '2026-11-01', '2026-11-30'));
        $this->assertCount(1, $doble->listarCargosDestacado(ComercialPortalFake::COMERCIO_CON_CARGOS, '2026-11-01', '2026-11-10'));
        foreach ($doble->listarCargosDestacado(ComercialPortalFake::COMERCIO_CON_UN_CARGO, '2026-11-01', '2026-11-30') as $cargo) {
            $this->assertCumpleEsquema('CargoDestacado', $cargo);
        }

        $this->expectException(DatosInvalidosException::class);
        $doble->listarCargosDestacado(ComercialPortalFake::COMERCIO_CON_CARGOS, '30/11/2026', '2026-11-01');
    }

    // ---------------------------------------------------------------
    // Ayudantes
    // ---------------------------------------------------------------

    private function comoAdministracion(): static
    {
        return $this->como($this->cuentaConRol(self::ADMINISTRACION, RolUsuario::EncargadoPlataforma));
    }

    private function comoComercio(int $idUsuario, int $idComercio): static
    {
        return $this->como($this->cuentaConRol($idUsuario, RolUsuario::EncargadoComercio, $idComercio));
    }

    /**
     * Cambia la persona con sesión. El guard de Sanctum recuerda al primer
     * usuario de la prueba, así que se olvidan los guards antes de cambiarlo.
     */
    private function como(Usuario $usuario): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($usuario, 'web');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function solicitar(int $idProducto, int $periodo, array $extra = []): TestResponse
    {
        return $this->comoComercio(self::ENCARGADO_LA_CEIBA, DatosPrueba::COMERCIO_LA_CEIBA)
            ->postJson(route('api.m1.v1.comercio.destacados.solicitar'), ['id_producto' => $idProducto, 'periodo_dias' => $periodo, ...$extra]);
    }

    private function destacadoExistente(
        int $idProducto,
        int $idComercio,
        string $origen = ProductoDestacado::ORIGEN_PLATAFORMA,
        string $inicio = '2026-11-01 00:00:00',
        string $fin = '2026-11-30 23:59:59',
        bool $activo = true,
    ): void {
        ProductoDestacado::query()->create([
            'id_producto' => $idProducto,
            'id_comercio' => $idComercio,
            'origen' => $origen,
            'posicion' => ProductoDestacado::query()->count() + 1,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'activo' => $activo,
        ]);
    }
}
