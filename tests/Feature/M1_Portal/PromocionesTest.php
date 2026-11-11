<?php

namespace Tests\Feature\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Enums\Nucleo\RolUsuario;
use App\Models\M1_Portal\Promocion;
use App\Models\Nucleo\Usuario;
use App\Services\M1_Portal\Dobles\DatosPrueba;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Doubles\M1\PortalPublicoFake;
use Tests\Feature\M1_Portal\Concerns\CreaTablasDeDestacados;
use Tests\Feature\M1_Portal\Concerns\ValidaEsquemaContrato;

/**
 * Promociones de los comercios (contrato M1 1.4.0, SC-08), por las rutas web
 * con los roles del núcleo y los dobles de M2, M3 y M4.
 *
 * Datos de prueba: Camisa de lino (1520, Tienda La Ceiba) con S y M a 18 500 y
 * L a 21 000; Café molido (1522, Café Nicoyano) con molido a 6 500 y grano a
 * 6 800.
 */
class PromocionesTest extends PruebaM1
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Crear
    // ---------------------------------------------------------------

    public function test_el_comercio_crea_una_promocion_por_porcentaje_para_todo_el_producto(): void
    {
        $respuesta = $this->crear()->assertCreated();

        $this->assertCumpleEsquema('Promocion', $respuesta->json());
        $respuesta->assertJsonPath('estado', 'vigente')
            ->assertJsonPath('id_comercio', DatosPrueba::COMERCIO_LA_CEIBA)
            ->assertJsonPath('alcance', 'producto')
            ->assertJsonPath('variaciones', []);
    }

    public function test_se_puede_programar_por_adelantado_y_se_activa_sola(): void
    {
        $this->crear(['fecha_inicio' => '2026-12-01T00:00:00Z', 'fecha_fin' => '2026-12-24T23:59:59Z'])
            ->assertCreated()
            ->assertJsonPath('estado', 'programada');

        $this->assertSame(18500.0, $this->precio(DatosPrueba::VARIACION_CAMISA_S)['precio_final']);

        Carbon::setTestNow('2026-12-01 00:00:00');
        $this->assertSame(14800.0, $this->precio(DatosPrueba::VARIACION_CAMISA_S)['precio_final']);

        Carbon::setTestNow('2026-12-25 00:00:00');
        $this->assertSame(18500.0, $this->precio(DatosPrueba::VARIACION_CAMISA_S)['precio_final']);
    }

    public function test_las_fechas_con_hora_de_costa_rica_se_guardan_en_el_instante_correcto(): void
    {
        // AHORA es 12:00 UTC (6:00 en Costa Rica). Inicia a las 7:00 de Costa Rica, o sea 13:00 UTC.
        $this->crear(['fecha_inicio' => '2026-11-10T07:00:00-06:00', 'fecha_fin' => '2026-11-10T20:00:00-06:00'])
            ->assertCreated()
            ->assertJsonPath('estado', 'programada')
            ->assertJsonPath('fecha_inicio', '2026-11-10T13:00:00+00:00')
            ->assertJsonPath('fecha_fin', '2026-11-11T02:00:00+00:00');

        $this->assertSame(18500.0, $this->precio(DatosPrueba::VARIACION_CAMISA_S)['precio_final']);

        Carbon::setTestNow('2026-11-10 13:00:00');
        $this->assertSame(14800.0, $this->precio(DatosPrueba::VARIACION_CAMISA_S)['precio_final']);
    }

    public function test_solo_hay_dos_tipos_y_el_porcentaje_va_de_0_a_100(): void
    {
        $this->crear(['tipo' => '2x1'])->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['tipo']]]);
        $this->crear(['valor' => 100])->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['valor']]]);
        $this->crear(['valor' => 0])->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['valor']]]);
    }

    public function test_el_precio_fijo_debe_ser_menor_que_el_precio_de_cada_variacion_afectada(): void
    {
        // La más barata del producto cuesta 18 500.
        $this->crear(['tipo' => 'precio_fijo', 'valor' => 19000])
            ->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['valor']]]);

        // Solo la L (21 000): 19 000 sí es menor.
        $this->crear(['tipo' => 'precio_fijo', 'valor' => 19000, 'alcance' => 'variaciones', 'variaciones' => [DatosPrueba::VARIACION_CAMISA_L_BAJO_STOCK]])
            ->assertCreated();
    }

    public function test_las_fechas_y_horas_son_obligatorias(): void
    {
        $this->crear(['fecha_inicio' => null, 'fecha_fin' => '2026-11-30'])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['detalles' => ['fecha_inicio', 'fecha_fin']]]);

        $this->crear(['fecha_inicio' => '2026-11-20T00:00:00Z', 'fecha_fin' => '2026-11-15T00:00:00Z'])
            ->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['fecha_fin']]]);
    }

    public function test_las_variaciones_deben_ser_del_producto(): void
    {
        $this->crear(['alcance' => 'variaciones', 'variaciones' => [DatosPrueba::VARIACION_CAFE_MOLIDO]])
            ->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['variaciones']]]);

        $this->crear(['alcance' => 'variaciones', 'variaciones' => []])
            ->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['variaciones']]]);
    }

    public function test_un_comercio_no_crea_promociones_para_productos_de_otro(): void
    {
        $this->crear(['id_producto' => DatosPrueba::PRODUCTO_CAFE_MOLIDO])
            ->assertStatus(404)->assertJsonPath('error.codigo', 'ERR-004');
    }

    public function test_solo_un_encargado_de_comercio_crea_promociones(): void
    {
        $this->postJson(route('api.m1.v1.comercio.promociones.crear'), $this->datos())->assertStatus(401);

        $this->como($this->cuentaConRol(9100, RolUsuario::Cliente))
            ->postJson(route('api.m1.v1.comercio.promociones.crear'), $this->datos())
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Traslapes
    // ---------------------------------------------------------------

    public function test_un_producto_no_tiene_dos_promociones_activas_que_se_traslapen(): void
    {
        $this->crear()->assertCreated();

        $this->crear(['fecha_inicio' => '2026-11-20T00:00:00Z', 'fecha_fin' => '2026-12-05T00:00:00Z'])
            ->assertStatus(409)->assertJsonPath('error.codigo', 'ERR-M1-004');
    }

    public function test_una_promocion_de_producto_choca_con_una_de_variacion_y_al_reves(): void
    {
        $this->crear(['alcance' => 'variaciones', 'variaciones' => [DatosPrueba::VARIACION_CAMISA_S]])->assertCreated();

        $this->crear()->assertStatus(409)->assertJsonPath('error.codigo', 'ERR-M1-004');
    }

    public function test_variaciones_distintas_del_mismo_producto_si_pueden_tener_promociones_a_la_vez(): void
    {
        $this->crear(['alcance' => 'variaciones', 'variaciones' => [DatosPrueba::VARIACION_CAMISA_S]])->assertCreated();

        $this->crear(['alcance' => 'variaciones', 'variaciones' => [DatosPrueba::VARIACION_CAMISA_L_BAJO_STOCK]])->assertCreated();
        $this->crear(['alcance' => 'variaciones', 'variaciones' => [DatosPrueba::VARIACION_CAMISA_S, DatosPrueba::VARIACION_CAMISA_M_AGOTADA]])
            ->assertStatus(409);
    }

    public function test_periodos_seguidos_no_se_traslapan_y_una_desactivada_no_cuenta(): void
    {
        $primera = $this->crear(['fecha_fin' => '2026-11-20T00:00:00Z'])->json('id_promocion');

        $this->crear(['fecha_inicio' => '2026-11-20T00:00:01Z', 'fecha_fin' => '2026-11-30T00:00:00Z'])->assertCreated();

        $this->comoLaCeiba()->postJson(route('api.m1.v1.comercio.promociones.desactivar', $primera))->assertOk();
        $this->crear(['fecha_inicio' => '2026-11-10T12:00:00Z', 'fecha_fin' => '2026-11-15T00:00:00Z'])->assertCreated();
    }

    // ---------------------------------------------------------------
    // Gestión del comercio
    // ---------------------------------------------------------------

    public function test_una_programada_se_edita_completa(): void
    {
        $id = $this->crear(['fecha_inicio' => '2026-12-01T00:00:00Z', 'fecha_fin' => '2026-12-24T00:00:00Z'])->json('id_promocion');

        $this->comoLaCeiba()
            ->putJson(route('api.m1.v1.comercio.promociones.actualizar', $id), $this->datos(['valor' => 30, 'titulo' => 'Navidad', 'fecha_inicio' => '2026-12-05T00:00:00Z', 'fecha_fin' => '2026-12-24T00:00:00Z']))
            ->assertOk()
            ->assertJsonPath('titulo', 'Navidad')
            ->assertJsonPath('valor', 30);
    }

    public function test_una_iniciada_solo_puede_adelantar_su_fin(): void
    {
        $id = $this->crear()->json('id_promocion');

        $this->comoLaCeiba()->putJson(route('api.m1.v1.comercio.promociones.actualizar', $id), ['valor' => 50])
            ->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['valor']]]);

        $this->comoLaCeiba()->putJson(route('api.m1.v1.comercio.promociones.actualizar', $id), ['fecha_fin' => '2026-12-31T00:00:00Z'])
            ->assertStatus(422);

        $this->comoLaCeiba()->putJson(route('api.m1.v1.comercio.promociones.actualizar', $id), ['fecha_fin' => '2026-11-15T00:00:00Z'])
            ->assertOk();
        $this->assertTrue(Promocion::query()->withoutGlobalScopes()->find($id)->fecha_fin->equalTo(Carbon::parse('2026-11-15T00:00:00Z')));
    }

    public function test_al_desactivarla_deja_de_rebajar_el_precio_y_ya_no_se_modifica(): void
    {
        $id = $this->crear()->json('id_promocion');
        $this->assertSame(14800.0, $this->precio(DatosPrueba::VARIACION_CAMISA_S)['precio_final']);

        $this->comoLaCeiba()->postJson(route('api.m1.v1.comercio.promociones.desactivar', $id))
            ->assertOk()->assertJsonPath('estado', 'desactivada');

        $this->assertSame(18500.0, $this->precio(DatosPrueba::VARIACION_CAMISA_S)['precio_final']);
        $this->comoLaCeiba()->postJson(route('api.m1.v1.comercio.promociones.desactivar', $id))
            ->assertStatus(409)->assertJsonPath('error.codigo', 'ERR-M1-005');
        $this->comoLaCeiba()->putJson(route('api.m1.v1.comercio.promociones.actualizar', $id), ['fecha_fin' => '2026-11-11T00:00:00Z'])
            ->assertStatus(409);
    }

    public function test_cada_comercio_solo_ve_y_modifica_sus_promociones(): void
    {
        $id = $this->crear()->json('id_promocion');
        $this->como($this->cuentaConRol(self::ENCARGADO_CAFE, RolUsuario::EncargadoComercio, DatosPrueba::COMERCIO_CAFE_NICOYANO))
            ->postJson(route('api.m1.v1.comercio.promociones.crear'), $this->datos(['id_producto' => DatosPrueba::PRODUCTO_CAFE_MOLIDO]))
            ->assertCreated();

        $this->comoLaCeiba()->getJson(route('api.m1.v1.comercio.promociones.listar'))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id_promocion', $id);

        $this->como($this->cuentaConRol(self::ENCARGADO_CAFE, RolUsuario::EncargadoComercio, DatosPrueba::COMERCIO_CAFE_NICOYANO))
            ->postJson(route('api.m1.v1.comercio.promociones.desactivar', $id))
            ->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // Administración: orden y ocultar
    // ---------------------------------------------------------------

    public function test_la_plataforma_decide_el_orden_de_la_seccion(): void
    {
        $camisa = $this->crear()->json('id_promocion');
        $cafe = $this->como($this->cuentaConRol(self::ENCARGADO_CAFE, RolUsuario::EncargadoComercio, DatosPrueba::COMERCIO_CAFE_NICOYANO))
            ->postJson(route('api.m1.v1.comercio.promociones.crear'), $this->datos(['id_producto' => DatosPrueba::PRODUCTO_CAFE_MOLIDO]))
            ->json('id_promocion');

        $this->comoAdministracion()->putJson(route('api.m1.v1.administracion.promociones.ordenar', $cafe), ['orden' => 1])->assertOk();

        $vigentes = $this->getJson(route('api.m1.v1.promociones'))->assertOk();
        $this->assertSame([$cafe, $camisa], array_column($vigentes->json(), 'id_promocion'));
        foreach ($vigentes->json() as $promocion) {
            $this->assertCumpleEsquema('PromocionVigente', $promocion);
        }

        $this->comoAdministracion()->putJson(route('api.m1.v1.administracion.promociones.ordenar', $cafe), ['orden' => 0])->assertStatus(422);
    }

    public function test_oculta_por_politicas_no_se_muestra_ni_rebaja_el_precio(): void
    {
        $id = $this->crear()->json('id_promocion');

        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.promociones.ocultar', $id), ['motivo' => ''])->assertStatus(422);
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.promociones.ocultar', $id), ['motivo' => 'Imagen engañosa.'])
            ->assertOk()->assertJsonPath('estado', 'oculta')->assertJsonPath('motivo_ocultamiento', 'Imagen engañosa.');

        $this->getJson(route('api.m1.v1.promociones'))->assertOk()->assertJsonCount(0);
        $this->assertSame(18500.0, $this->precio(DatosPrueba::VARIACION_CAMISA_S)['precio_final']);

        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.promociones.mostrar', $id))->assertOk()->assertJsonPath('estado', 'vigente');
        $this->assertSame(14800.0, $this->precio(DatosPrueba::VARIACION_CAMISA_S)['precio_final']);
    }

    public function test_una_oculta_sigue_contando_para_los_traslapes(): void
    {
        $id = $this->crear()->json('id_promocion');
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.promociones.ocultar', $id), ['motivo' => 'Revisión.'])->assertOk();

        $this->crear()->assertStatus(409);
    }

    public function test_un_comercio_no_puede_ocultar_ni_ordenar(): void
    {
        $id = $this->crear()->json('id_promocion');

        $this->comoLaCeiba()->postJson(route('api.m1.v1.administracion.promociones.ocultar', $id), ['motivo' => 'x'])->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Precios en el portal y para M5
    // ---------------------------------------------------------------

    public function test_los_precios_vigentes_para_m5_cumplen_el_esquema_y_redondean_a_2_decimales(): void
    {
        $this->crear(['valor' => 12.5, 'alcance' => 'variaciones', 'variaciones' => [DatosPrueba::VARIACION_CAMISA_L_BAJO_STOCK]])->assertCreated();

        $precios = app(PortalPublicoContract::class)->obtenerPreciosVigentes([
            DatosPrueba::VARIACION_CAMISA_S,
            DatosPrueba::VARIACION_CAMISA_L_BAJO_STOCK,
            DatosPrueba::VARIACION_INEXISTENTE,
        ]);

        $this->assertSame([DatosPrueba::VARIACION_CAMISA_S, DatosPrueba::VARIACION_CAMISA_L_BAJO_STOCK], array_keys($precios));
        foreach ($precios as $precio) {
            $this->assertCumpleEsquema('PrecioVigente', $precio);
        }

        // S no está en la promoción: precio normal y sin promoción.
        $this->assertSame(['id_variacion' => DatosPrueba::VARIACION_CAMISA_S, 'precio_base' => 18500.0, 'precio_final' => 18500.0, 'id_promocion' => null, 'fin_promocion' => null], $precios[DatosPrueba::VARIACION_CAMISA_S]);
        // L: 21 000 − 12,5 % = 18 375,00.
        $this->assertSame(18375.0, $precios[DatosPrueba::VARIACION_CAMISA_L_BAJO_STOCK]['precio_final']);
        $this->assertNotNull($precios[DatosPrueba::VARIACION_CAMISA_L_BAJO_STOCK]['fin_promocion']);
    }

    public function test_un_precio_fijo_se_cobra_tal_cual(): void
    {
        $this->crear(['tipo' => 'precio_fijo', 'valor' => 15990.5])->assertCreated();

        $this->assertSame(15990.5, $this->precio(DatosPrueba::VARIACION_CAMISA_L_BAJO_STOCK)['precio_final']);
    }

    public function test_los_precios_vigentes_validan_la_lista(): void
    {
        $this->expectException(DatosInvalidosException::class);

        app(PortalPublicoContract::class)->obtenerPreciosVigentes([0, 'abc']);
    }

    public function test_la_busqueda_y_la_ficha_muestran_el_precio_con_promocion(): void
    {
        $this->crear()->assertCreated();
        // La búsqueda y la ficha se consultan como visitante, sin sesión.
        $this->app['auth']->forgetGuards();

        $busqueda = $this->getJson(route('api.m1.v1.productos.busqueda', ['q' => 'camisa']))->assertOk();
        $camisa = collect($busqueda->json('productos'))->firstWhere('id_producto', DatosPrueba::PRODUCTO_CAMISA_LINO);
        $this->assertCumpleEsquema('ProductoResumen', $camisa);
        $this->assertSame(14800, (int) $camisa['promocion']['precio_promocional']);

        $ficha = $this->getJson(route('api.m1.v1.productos.ficha', DatosPrueba::PRODUCTO_CAMISA_LINO))->assertOk();
        $this->assertCumpleEsquema('FichaProducto', $ficha->json());
        $variaciones = collect($ficha->json('variaciones'))->keyBy('id_variacion');
        $this->assertEquals(16800, $variaciones[DatosPrueba::VARIACION_CAMISA_L_BAJO_STOCK]['precio_promocional']);
    }

    public function test_sin_promocion_las_respuestas_no_cambian(): void
    {
        $ficha = $this->getJson(route('api.m1.v1.productos.ficha', DatosPrueba::PRODUCTO_CAMISA_LINO))->assertOk();

        foreach ($ficha->json('variaciones') as $variacion) {
            $this->assertArrayNotHasKey('precio_promocional', $variacion);
        }
        $this->assertArrayNotHasKey('promocion', $ficha->json('relacionados.0') ?? []);
    }

    public function test_el_doble_de_m1_entrega_precios_vigentes_con_el_esquema(): void
    {
        $precios = (new PortalPublicoFake)->obtenerPreciosVigentes([PortalPublicoFake::VARIACION_CON_PROMOCION, PortalPublicoFake::VARIACION_SIN_PROMOCION, PortalPublicoFake::VARIACION_INEXISTENTE]);

        $this->assertCount(2, $precios);
        foreach ($precios as $precio) {
            $this->assertCumpleEsquema('PrecioVigente', $precio);
        }
    }

    // ---------------------------------------------------------------
    // Ayudantes
    // ---------------------------------------------------------------

    /**
     * Promoción de la Camisa de lino: 20 % al producto completo, del 10 al 30
     * de noviembre.
     *
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return [
            'id_producto' => DatosPrueba::PRODUCTO_CAMISA_LINO,
            'titulo' => 'Semana de la camisa',
            'tipo' => 'porcentaje',
            'valor' => 20,
            'alcance' => 'producto',
            'fecha_inicio' => '2026-11-10T00:00:00Z',
            'fecha_fin' => '2026-11-30T23:59:59Z',
            ...$cambios,
        ];
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function crear(array $cambios = []): TestResponse
    {
        return $this->comoLaCeiba()->postJson(route('api.m1.v1.comercio.promociones.crear'), $this->datos($cambios));
    }

    /**
     * @return array<string, mixed>
     */
    private function precio(int $idVariacion): array
    {
        return app(PortalPublicoContract::class)->obtenerPreciosVigentes([$idVariacion])[$idVariacion];
    }

    private function comoLaCeiba(): static
    {
        return $this->como($this->cuentaConRol(self::ENCARGADO_LA_CEIBA, RolUsuario::EncargadoComercio, DatosPrueba::COMERCIO_LA_CEIBA));
    }

    private function comoAdministracion(): static
    {
        return $this->como($this->cuentaConRol(self::ADMINISTRACION, RolUsuario::EncargadoPlataforma));
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
}
