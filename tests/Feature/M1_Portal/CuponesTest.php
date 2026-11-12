<?php

namespace Tests\Feature\M1_Portal;

use App\Contracts\M1_Portal\ComercialPortalContract;
use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Enums\Nucleo\RolUsuario;
use App\Models\M1_Portal\Cupon;
use App\Models\M1_Portal\CuponUso;
use App\Models\Nucleo\Usuario;
use App\Services\M1_Portal\ClienteService;
use App\Services\M1_Portal\Dobles\DatosPrueba;
use App\Services\M1_Portal\DTO\ClienteDTO;
use App\Services\M1_Portal\DTO\DireccionClienteDTO;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Doubles\M1\ComercialPortalFake;
use Tests\Doubles\M1\IdentidadDoble;
use Tests\Feature\M1_Portal\Concerns\CreaTablasDeClientes;
use Tests\Feature\M1_Portal\Concerns\CreaTablasDeCupones;
use Tests\Feature\M1_Portal\Concerns\CreaTablasDeDestacados;
use Tests\Feature\M1_Portal\Concerns\ValidaEsquemaContrato;

/**
 * Cupones de descuento (contrato M1 1.5.0, SC-11): gestión por las rutas web
 * con los roles del núcleo, y validación y registro de usos por la interfaz
 * PHP que consume M5 (ComercialPortalContract).
 */
class CuponesTest extends PruebaM1
{
    use CreaTablasDeClientes;
    use CreaTablasDeCupones;
    use CreaTablasDeDestacados;
    use ValidaEsquemaContrato;

    private const AHORA = '2026-11-12 12:00:00';

    private const ADMINISTRACION = 9001;

    private const ENCARGADO_LA_CEIBA = 9002;

    private const ENCARGADO_CAFE = 9003;

    private const CLIENTE = 71;

    private const OTRO_CLIENTE = 72;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararDestacados();
        $this->prepararClientes();
        $this->prepararCupones();
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

    public function test_el_comercio_crea_un_cupon_para_sus_productos(): void
    {
        $respuesta = $this->crearDeLaCeiba(['codigo' => ' ceiba10 '])->assertCreated();

        $this->assertCumpleEsquema('Cupon', $respuesta->json());
        $respuesta->assertJsonPath('codigo', 'CEIBA10')
            ->assertJsonPath('id_comercio', DatosPrueba::COMERCIO_LA_CEIBA)
            ->assertJsonPath('estado', 'vigente')
            ->assertJsonPath('usos_actuales', 0)
            ->assertJsonPath('limite_usos_por_cliente', 1);
    }

    public function test_el_comercio_sale_de_la_sesion_y_no_del_cuerpo(): void
    {
        $this->crearDeLaCeiba(['id_comercio' => DatosPrueba::COMERCIO_CAFE_NICOYANO])
            ->assertCreated()
            ->assertJsonPath('id_comercio', DatosPrueba::COMERCIO_LA_CEIBA);
    }

    public function test_la_administracion_crea_un_cupon_global(): void
    {
        $respuesta = $this->crearGlobal()->assertCreated();

        $this->assertCumpleEsquema('Cupon', $respuesta->json());
        $respuesta->assertJsonPath('id_comercio', null)->assertJsonPath('codigo', 'PACIFICO10');
    }

    public function test_se_programa_por_adelantado(): void
    {
        $this->crearGlobal(['fecha_inicio' => '2026-12-01T00:00:00-06:00', 'fecha_fin' => '2026-12-24T23:59:59-06:00'])
            ->assertCreated()
            ->assertJsonPath('estado', 'programado')
            ->assertJsonPath('fecha_inicio', '2026-12-01T06:00:00+00:00');
    }

    public function test_el_codigo_es_unico_en_toda_la_plataforma(): void
    {
        $this->crearDeLaCeiba(['codigo' => 'VERANO'])->assertCreated();

        $this->crearDelCafe(['codigo' => 'verano'])->assertStatus(409)->assertJsonPath('error.codigo', 'ERR-M1-007');
        $this->crearGlobal(['codigo' => 'VERANO'])->assertStatus(409)->assertJsonPath('error.codigo', 'ERR-M1-007');
    }

    public function test_valida_los_datos_del_cupon(): void
    {
        $this->crearGlobal(['codigo' => 'AB'])->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['codigo']]]);
        $this->crearGlobal(['codigo' => 'CON ESPACIO'])->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['codigo']]]);
        $this->crearGlobal(['tipo_descuento' => '2x1'])->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['tipo_descuento']]]);
        $this->crearGlobal(['valor' => 100])->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['valor']]]);
        $this->crearGlobal(['valor' => 0])->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['valor']]]);
        $this->crearGlobal(['valor' => 10.555])->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['valor']]]);
        $this->crearGlobal(['monto_minimo_compra' => -1])->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['monto_minimo_compra']]]);
        $this->crearGlobal(['fecha_inicio' => null, 'fecha_fin' => '2026-11-30'])
            ->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['fecha_inicio', 'fecha_fin']]]);
        $this->crearGlobal(['fecha_inicio' => '2026-11-20T00:00:00Z', 'fecha_fin' => '2026-11-15T00:00:00Z'])
            ->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['fecha_fin']]]);
        $this->crearGlobal(['fecha_inicio' => '2026-11-01T00:00:00Z', 'fecha_fin' => '2026-11-10T00:00:00Z'])
            ->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['fecha_fin']]]);
        $this->crearGlobal(['limite_usos_total' => 0])->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['limite_usos_total']]]);
        $this->crearGlobal(['limite_usos_total' => 2, 'limite_usos_por_cliente' => 3])
            ->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['limite_usos_por_cliente']]]);

        $this->assertSame(0, Cupon::query()->withoutGlobalScopes()->count());
    }

    public function test_un_monto_fijo_puede_ser_mayor_que_100(): void
    {
        $this->crearGlobal(['tipo_descuento' => 'monto_fijo', 'valor' => 3000])->assertCreated()->assertJsonPath('valor', 3000);
    }

    public function test_solo_los_encargados_crean_cupones(): void
    {
        $this->postJson(route('api.m1.v1.comercio.cupones.crear'), $this->datos())->assertStatus(401);
        $this->postJson(route('api.m1.v1.administracion.cupones.crear'), $this->datos())->assertStatus(401);

        $this->como($this->cuentaConRol(9100, RolUsuario::Cliente))
            ->postJson(route('api.m1.v1.comercio.cupones.crear'), $this->datos())->assertStatus(403);
        $this->comoLaCeiba()->postJson(route('api.m1.v1.administracion.cupones.crear'), $this->datos())->assertStatus(403);
        $this->comoAdministracion()->postJson(route('api.m1.v1.comercio.cupones.crear'), $this->datos())->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Listar y desactivar
    // ---------------------------------------------------------------

    public function test_cada_comercio_ve_solo_sus_cupones_y_la_administracion_todos(): void
    {
        $this->crearDeLaCeiba(['codigo' => 'CEIBA10'])->assertCreated();
        $this->crearDelCafe(['codigo' => 'CAFE10'])->assertCreated();
        $this->crearGlobal()->assertCreated();

        $propios = $this->comoLaCeiba()->getJson(route('api.m1.v1.comercio.cupones.listar'))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonCount(1);
        $propios->assertJsonPath('0.codigo', 'CEIBA10');
        $this->assertCumpleEsquema('Cupon', $propios->json('0'));

        $this->comoAdministracion()->getJson(route('api.m1.v1.administracion.cupones.listar'))
            ->assertOk()->assertJsonCount(3)->assertJsonPath('0.codigo', 'PACIFICO10');
        $this->comoAdministracion()->getJson(route('api.m1.v1.administracion.cupones.listar', ['ambito' => 'global']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id_comercio', null);
        $this->comoAdministracion()->getJson(route('api.m1.v1.administracion.cupones.listar', ['id_comercio' => DatosPrueba::COMERCIO_CAFE_NICOYANO]))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.codigo', 'CAFE10');
    }

    public function test_el_listado_filtra_por_estado(): void
    {
        $this->crearDeLaCeiba(['codigo' => 'VIGENTE'])->assertCreated();
        $this->crearDeLaCeiba(['codigo' => 'FUTURO', 'fecha_inicio' => '2026-12-01T00:00:00Z', 'fecha_fin' => '2026-12-31T00:00:00Z'])->assertCreated();

        $this->comoLaCeiba()->getJson(route('api.m1.v1.comercio.cupones.listar', ['estado' => 'programado']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.codigo', 'FUTURO');
        $this->comoLaCeiba()->getJson(route('api.m1.v1.comercio.cupones.listar', ['estado' => 'otro']))
            ->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['estado']]]);
    }

    public function test_el_comercio_desactiva_los_suyos_y_no_los_de_otro(): void
    {
        $deLaCeiba = $this->crearDeLaCeiba(['codigo' => 'CEIBA10'])->json('id_cupon');
        $global = $this->crearGlobal()->json('id_cupon');

        $this->comoCafe()->postJson(route('api.m1.v1.comercio.cupones.desactivar', $deLaCeiba))
            ->assertStatus(404)->assertJsonPath('error.codigo', 'ERR-004');
        $this->comoLaCeiba()->postJson(route('api.m1.v1.comercio.cupones.desactivar', $global))
            ->assertStatus(404)->assertJsonPath('error.codigo', 'ERR-004');

        $this->comoLaCeiba()->postJson(route('api.m1.v1.comercio.cupones.desactivar', $deLaCeiba))
            ->assertOk()->assertJsonPath('activo', false)->assertJsonPath('estado', 'desactivado');

        $this->assertSame('no_disponible', $this->validar('CEIBA10', 20000, [DatosPrueba::COMERCIO_LA_CEIBA])['motivo']);
    }

    public function test_la_administracion_desactiva_cualquier_cupon(): void
    {
        $deLaCeiba = $this->crearDeLaCeiba(['codigo' => 'CEIBA10'])->json('id_cupon');

        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.cupones.desactivar', $deLaCeiba))
            ->assertOk()->assertJsonPath('estado', 'desactivado');
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.cupones.desactivar', 9999))
            ->assertStatus(404)->assertJsonPath('error.codigo', 'ERR-004');
    }

    // ---------------------------------------------------------------
    // Validar (interfaz PHP para M5)
    // ---------------------------------------------------------------

    public function test_un_cupon_global_por_porcentaje_descuenta_sobre_todo_el_subtotal(): void
    {
        $idCupon = $this->crearGlobal()->json('id_cupon');

        $resultado = $this->validar('pacifico10', 25000, [1, 3]);

        $this->assertCumpleEsquema('ResultadoCupon', $resultado);
        $this->assertSame([
            'valido' => true,
            'id_cupon' => $idCupon,
            'id_comercio' => null,
            'tipo_descuento' => 'porcentaje',
            'valor' => 10.0,
            'monto_descuento' => 2500.0,
            'motivo' => null,
            'mensaje' => 'Cupón de 10 % aplicado.',
        ], $resultado);
    }

    public function test_el_descuento_va_a_dos_decimales(): void
    {
        $this->crearGlobal(['valor' => 12.5])->assertCreated();

        $this->assertSame(1234.57, $this->validar('PACIFICO10', 9876.54)['monto_descuento']);
    }

    public function test_un_monto_fijo_nunca_descuenta_mas_que_la_base(): void
    {
        $this->crearGlobal(['tipo_descuento' => 'monto_fijo', 'valor' => 3000])->assertCreated();

        $this->assertSame(3000.0, $this->validar('PACIFICO10', 25000)['monto_descuento']);
        $this->assertSame(1800.0, $this->validar('PACIFICO10', 1800)['monto_descuento']);
        $this->assertSame('sin_descuento', $this->validar('PACIFICO10', 0)['motivo']);
    }

    public function test_un_cupon_de_comercio_descuenta_solo_sobre_el_subtotal_de_ese_comercio(): void
    {
        $this->crearDeLaCeiba(['codigo' => 'CEIBA10'])->assertCreated();

        // Carrito de dos comercios con el desglose: 10 % de los 18 500 de La Ceiba.
        $resultado = $this->validar('CEIBA10', 25000, [], [DatosPrueba::COMERCIO_LA_CEIBA => 18500, DatosPrueba::COMERCIO_CAFE_NICOYANO => 6500]);
        $this->assertCumpleEsquema('ResultadoCupon', $resultado);
        $this->assertTrue($resultado['valido']);
        $this->assertSame(DatosPrueba::COMERCIO_LA_CEIBA, $resultado['id_comercio']);
        $this->assertSame(1850.0, $resultado['monto_descuento']);

        // Carrito solo de La Ceiba, sin desglose: todo el subtotal es de ese comercio.
        $this->assertSame(1850.0, $this->validar('CEIBA10', 18500, [DatosPrueba::COMERCIO_LA_CEIBA])['monto_descuento']);
    }

    public function test_un_cupon_de_comercio_no_aplica_sin_productos_de_ese_comercio(): void
    {
        $this->crearDeLaCeiba(['codigo' => 'CEIBA10'])->assertCreated();

        $resultado = $this->validar('CEIBA10', 6500, [DatosPrueba::COMERCIO_CAFE_NICOYANO]);

        $this->assertCumpleEsquema('ResultadoCupon', $resultado);
        $this->assertFalse($resultado['valido']);
        $this->assertSame('comercio_no_aplica', $resultado['motivo']);
        $this->assertSame(0.0, $resultado['monto_descuento']);
        $this->assertSame('comercio_no_aplica', $this->validar('CEIBA10', 6500)['motivo']);
    }

    public function test_en_un_carrito_de_varios_comercios_hace_falta_el_subtotal_del_comercio(): void
    {
        $this->crearDeLaCeiba(['codigo' => 'CEIBA10'])->assertCreated();

        $resultado = $this->validar('CEIBA10', 25000, [DatosPrueba::COMERCIO_LA_CEIBA, DatosPrueba::COMERCIO_CAFE_NICOYANO]);

        $this->assertFalse($resultado['valido']);
        $this->assertSame('requiere_subtotal_comercio', $resultado['motivo']);
    }

    public function test_el_monto_minimo_se_compara_con_la_base_del_descuento(): void
    {
        $this->crearDeLaCeiba(['codigo' => 'CEIBA10', 'monto_minimo_compra' => 20000])->assertCreated();

        // El carrito suma 25 000, pero solo 18 500 son de La Ceiba.
        $resultado = $this->validar('CEIBA10', 25000, [], [DatosPrueba::COMERCIO_LA_CEIBA => 18500, DatosPrueba::COMERCIO_CAFE_NICOYANO => 6500]);
        $this->assertSame('monto_minimo', $resultado['motivo']);
        $this->assertSame('El cupón aplica a compras desde ₡20 000.', $resultado['mensaje']);

        $this->assertTrue($this->validar('CEIBA10', 20000, [DatosPrueba::COMERCIO_LA_CEIBA])['valido']);
    }

    public function test_solo_aplica_entre_su_fecha_de_inicio_y_de_fin(): void
    {
        $this->crearGlobal(['fecha_inicio' => '2026-11-20T00:00:00Z', 'fecha_fin' => '2026-11-30T23:59:59Z'])->assertCreated();

        $this->assertSame('no_iniciado', $this->validar('PACIFICO10', 25000)['motivo']);

        Carbon::setTestNow('2026-11-20 00:00:00');
        $this->assertTrue($this->validar('PACIFICO10', 25000)['valido']);

        Carbon::setTestNow('2026-12-01 00:00:00');
        $this->assertSame('vencido', $this->validar('PACIFICO10', 25000)['motivo']);
    }

    public function test_un_codigo_inexistente_responde_que_no_esta_disponible(): void
    {
        $resultado = $this->validar('NOEXISTE', 25000);

        $this->assertCumpleEsquema('ResultadoCupon', $resultado);
        $this->assertFalse($resultado['valido']);
        $this->assertSame('no_disponible', $resultado['motivo']);
        $this->assertSame('El cupón no existe o no está disponible.', $resultado['mensaje']);
    }

    public function test_validar_rechaza_parametros_mal_formados(): void
    {
        foreach ([
            'codigo' => fn () => $this->contrato()->validarCupon('  ', self::CLIENTE, 25000),
            'id_cliente' => fn () => $this->contrato()->validarCupon('PACIFICO10', 0, 25000),
            'subtotal' => fn () => $this->contrato()->validarCupon('PACIFICO10', self::CLIENTE, -1),
            'ids_comercios' => fn () => $this->contrato()->validarCupon('PACIFICO10', self::CLIENTE, 25000, [0]),
            'subtotales_por_comercio' => fn () => $this->contrato()->validarCupon('PACIFICO10', self::CLIENTE, 25000, [], [34 => -5]),
        ] as $campo => $llamada) {
            try {
                $llamada();
                $this->fail("Se esperaba ERR-005 por {$campo}.");
            } catch (DatosInvalidosException $error) {
                $this->assertArrayHasKey($campo, $error->detalles());
            }
        }
    }

    // ---------------------------------------------------------------
    // Registrar usos (interfaz PHP para M5)
    // ---------------------------------------------------------------

    public function test_registrar_el_uso_suma_y_es_idempotente_por_pedido(): void
    {
        $idCupon = $this->crearGlobal(['limite_usos_por_cliente' => 2])->json('id_cupon');
        $this->assertTrue($this->contrato()->registrarUsoCupon($idCupon, self::CLIENTE, 500));
        $this->assertTrue($this->contrato()->registrarUsoCupon($idCupon, self::CLIENTE, 500));

        $this->assertSame(1, $this->cupon($idCupon)->usos_actuales);
        $this->assertSame(1, CuponUso::query()->count());
    }

    public function test_respeta_el_limite_de_usos_por_cliente(): void
    {
        $idCupon = $this->crearGlobal()->json('id_cupon');
        $this->assertTrue($this->contrato()->registrarUsoCupon($idCupon, self::CLIENTE, 500));

        $this->assertSame('limite_cliente', $this->validar('PACIFICO10', 25000)['motivo']);
        $this->assertFalse($this->contrato()->registrarUsoCupon($idCupon, self::CLIENTE, 501));

        // Otra persona todavía puede usarlo.
        $this->assertTrue($this->contrato()->validarCupon('PACIFICO10', self::OTRO_CLIENTE, 25000)['valido']);
        $this->assertSame(1, $this->cupon($idCupon)->usos_actuales);
    }

    public function test_respeta_el_limite_de_usos_total(): void
    {
        $idCupon = $this->crearGlobal(['limite_usos_total' => 1])->json('id_cupon');
        $this->assertTrue($this->contrato()->registrarUsoCupon($idCupon, self::CLIENTE, 500));

        $this->assertSame('agotado', $this->contrato()->validarCupon('PACIFICO10', self::OTRO_CLIENTE, 25000)['motivo']);
        $this->assertFalse($this->contrato()->registrarUsoCupon($idCupon, self::OTRO_CLIENTE, 501));
        $this->assertSame('agotado', $this->cupon($idCupon)->estado(Carbon::now()));
    }

    public function test_no_registra_usos_de_un_cupon_inexistente_desactivado_o_vencido(): void
    {
        $this->assertFalse($this->contrato()->registrarUsoCupon(9999, self::CLIENTE, 500));

        $idCupon = $this->crearGlobal()->json('id_cupon');
        Carbon::setTestNow('2026-12-15 00:00:00');
        $this->assertFalse($this->contrato()->registrarUsoCupon($idCupon, self::CLIENTE, 500));

        Carbon::setTestNow(self::AHORA);
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.cupones.desactivar', $idCupon))->assertOk();
        $this->assertFalse($this->contrato()->registrarUsoCupon($idCupon, self::CLIENTE, 500));
        $this->assertSame(0, CuponUso::query()->count());
    }

    public function test_registrar_el_uso_valida_los_identificadores(): void
    {
        $this->expectException(DatosInvalidosException::class);

        $this->contrato()->registrarUsoCupon(1, self::CLIENTE, 0);
    }

    // ---------------------------------------------------------------
    // POST /cupones/validar (checkout)
    // ---------------------------------------------------------------

    public function test_la_persona_con_sesion_valida_un_cupon_en_el_checkout(): void
    {
        $this->crearGlobal()->assertCreated();
        $ana = $this->registrarAna();

        $respuesta = $this->comoAna()->postJson(route('api.m1.v1.cupones.validar'), [
            'codigo' => 'pacifico10',
            'id_cliente' => $ana,
            'subtotal' => 25000.00,
            'ids_comercios' => [1, 3],
        ])->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        $this->assertCumpleEsquema('ResultadoCupon', $respuesta->json());
        $respuesta->assertJsonPath('valido', true)->assertJsonPath('monto_descuento', 2500);
    }

    public function test_el_checkout_envia_el_subtotal_de_cada_comercio(): void
    {
        $this->crearDeLaCeiba(['codigo' => 'CEIBA10'])->assertCreated();
        $this->registrarAna();

        $this->comoAna()->postJson(route('api.m1.v1.cupones.validar'), [
            'codigo' => 'CEIBA10',
            'subtotal' => 25000,
            'ids_comercios' => [DatosPrueba::COMERCIO_LA_CEIBA, DatosPrueba::COMERCIO_CAFE_NICOYANO],
            'subtotales_por_comercio' => [
                ['id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA, 'subtotal' => 18500],
                ['id_comercio' => DatosPrueba::COMERCIO_CAFE_NICOYANO, 'subtotal' => 6500],
            ],
        ])->assertOk()->assertJsonPath('valido', true)->assertJsonPath('monto_descuento', 1850);
    }

    public function test_un_cupon_que_no_aplica_responde_200_con_valido_en_falso(): void
    {
        $this->registrarAna();

        $respuesta = $this->comoAna()->postJson(route('api.m1.v1.cupones.validar'), ['codigo' => 'NOEXISTE', 'subtotal' => 25000])
            ->assertOk()->assertJsonPath('valido', false)->assertJsonPath('monto_descuento', 0);

        $this->assertCumpleEsquema('ResultadoCupon', $respuesta->json());
    }

    public function test_nadie_valida_cupones_a_nombre_de_otro_cliente(): void
    {
        $this->crearGlobal()->assertCreated();
        $ana = $this->registrarAna();

        $this->comoAna()->postJson(route('api.m1.v1.cupones.validar'), ['codigo' => 'PACIFICO10', 'id_cliente' => $ana + 1, 'subtotal' => 25000])
            ->assertStatus(404)->assertJsonPath('error.codigo', 'ERR-003');
    }

    public function test_validar_exige_sesion_de_cliente_y_un_cuerpo_valido(): void
    {
        $cuerpo = ['codigo' => 'PACIFICO10', 'subtotal' => 25000];

        $this->postJson(route('api.m1.v1.cupones.validar'), $cuerpo)->assertStatus(401);
        $this->comoLaCeiba()->postJson(route('api.m1.v1.cupones.validar'), $cuerpo)
            ->assertStatus(404)->assertJsonPath('error.codigo', 'ERR-003');

        $this->registrarAna();
        $this->comoAna()->postJson(route('api.m1.v1.cupones.validar'), ['codigo' => 123, 'subtotal' => 'mucho', 'subtotales_por_comercio' => 'x'])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['detalles' => ['codigo', 'subtotal', 'subtotales_por_comercio']]]);
    }

    // ---------------------------------------------------------------
    // Doble del contrato
    // ---------------------------------------------------------------

    public function test_el_doble_del_contrato_se_comporta_como_el_contrato_con_los_cupones(): void
    {
        $doble = new ComercialPortalFake;

        $global = $doble->validarCupon('pacifico10', self::CLIENTE, 25000, [1, 3]);
        $this->assertCumpleEsquema('ResultadoCupon', $global);
        $this->assertTrue($global['valido']);
        $this->assertSame(2500.0, $global['monto_descuento']);

        $deComercio = $doble->validarCupon(ComercialPortalFake::CUPON_DE_COMERCIO, self::CLIENTE, 25000, [], [ComercialPortalFake::COMERCIO_CON_CARGOS => 18500, 35 => 6500]);
        $this->assertCumpleEsquema('ResultadoCupon', $deComercio);
        $this->assertSame(3000.0, $deComercio['monto_descuento']);
        $this->assertSame(ComercialPortalFake::COMERCIO_CON_CARGOS, $deComercio['id_comercio']);

        $this->assertSame('requiere_subtotal_comercio', $doble->validarCupon(ComercialPortalFake::CUPON_DE_COMERCIO, self::CLIENTE, 25000, [34, 35])['motivo']);
        $this->assertSame('comercio_no_aplica', $doble->validarCupon(ComercialPortalFake::CUPON_DE_COMERCIO, self::CLIENTE, 25000, [35])['motivo']);
        $this->assertSame('monto_minimo', $doble->validarCupon(ComercialPortalFake::CUPON_DE_COMERCIO, self::CLIENTE, 5000, [34])['motivo']);
        $this->assertSame('vencido', $doble->validarCupon(ComercialPortalFake::CUPON_VENCIDO, self::CLIENTE, 25000)['motivo']);

        $inexistente = $doble->validarCupon('NOEXISTE', self::CLIENTE, 25000);
        $this->assertCumpleEsquema('ResultadoCupon', $inexistente);
        $this->assertSame('no_disponible', $inexistente['motivo']);

        $this->assertTrue($doble->registrarUsoCupon(ComercialPortalFake::ID_CUPON_GLOBAL, self::CLIENTE, 500));
        $this->assertTrue($doble->registrarUsoCupon(ComercialPortalFake::ID_CUPON_GLOBAL, self::CLIENTE, 500));
        $this->assertFalse($doble->registrarUsoCupon(ComercialPortalFake::ID_CUPON_GLOBAL, self::CLIENTE, 501));
        $this->assertFalse($doble->registrarUsoCupon(9999, self::CLIENTE, 500));
        $this->assertSame('limite_cliente', $doble->validarCupon(ComercialPortalFake::CUPON_GLOBAL, self::CLIENTE, 25000)['motivo']);

        $this->expectException(DatosInvalidosException::class);
        $doble->validarCupon('', self::CLIENTE, 25000);
    }

    // ---------------------------------------------------------------
    // Ayudantes
    // ---------------------------------------------------------------

    /**
     * Cupón de 10 %, vigente del 10 al 30 de noviembre, un uso por cliente.
     *
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return [
            'codigo' => 'PACIFICO10',
            'descripcion' => '10 % en toda la compra',
            'tipo_descuento' => 'porcentaje',
            'valor' => 10,
            'fecha_inicio' => '2026-11-10T00:00:00Z',
            'fecha_fin' => '2026-11-30T23:59:59Z',
            ...$cambios,
        ];
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function crearGlobal(array $cambios = []): TestResponse
    {
        return $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.cupones.crear'), $this->datos($cambios));
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function crearDeLaCeiba(array $cambios = []): TestResponse
    {
        return $this->comoLaCeiba()->postJson(route('api.m1.v1.comercio.cupones.crear'), $this->datos($cambios));
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function crearDelCafe(array $cambios = []): TestResponse
    {
        return $this->comoCafe()->postJson(route('api.m1.v1.comercio.cupones.crear'), $this->datos($cambios));
    }

    /**
     * Valida por la interfaz PHP, como M5, sin sesión de usuario.
     *
     * @param  list<int>  $idsComercios
     * @param  array<int, float|int>  $subtotalesPorComercio
     * @return array<string, mixed>
     */
    private function validar(string $codigo, float $subtotal, array $idsComercios = [], array $subtotalesPorComercio = []): array
    {
        return $this->contrato()->validarCupon($codigo, self::CLIENTE, $subtotal, $idsComercios, $subtotalesPorComercio);
    }

    /**
     * La interfaz PHP como la recibe M5. Se pide cada vez al contenedor porque
     * como() reinicia los contextos de aislamiento entre peticiones.
     */
    private function contrato(): ComercialPortalContract
    {
        return app(ComercialPortalContract::class);
    }

    private function cupon(int $idCupon): Cupon
    {
        return Cupon::query()->withoutGlobalScopes()->findOrFail($idCupon);
    }

    private function registrarAna(): int
    {
        return app(ClienteService::class)->registrar(
            new ClienteDTO(null, 'Ana', 'Mora Rojas', 'ana@example.com', '8888-0000', '5-0123-0456', ''),
            new DireccionClienteDTO(null, null, 'Casa', 'Ana Mora', '8888-0000', 'Guanacaste', 'Liberia', 'Liberia', '200 m norte del parque', true),
            'secreta123',
        );
    }

    private function comoAna(): static
    {
        $usuario = new Usuario;
        $usuario->forceFill(['id_usuario' => IdentidadDoble::PRIMER_ID]);

        return $this->como($usuario);
    }

    private function comoLaCeiba(): static
    {
        return $this->como($this->cuentaConRol(self::ENCARGADO_LA_CEIBA, RolUsuario::EncargadoComercio, DatosPrueba::COMERCIO_LA_CEIBA));
    }

    private function comoCafe(): static
    {
        return $this->como($this->cuentaConRol(self::ENCARGADO_CAFE, RolUsuario::EncargadoComercio, DatosPrueba::COMERCIO_CAFE_NICOYANO));
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
        // Cada petición real estrena los contextos de aislamiento del núcleo y sus controladores; en la
        // prueba se reinician a mano para que no quede el comercio ni el acceso auditado de la anterior.
        $this->app->forgetScopedInstances();
        foreach ($this->app['router']->getRoutes() as $ruta) {
            $ruta->flushController();
        }
        $this->app['auth']->forgetGuards();

        return $this->actingAs($usuario, 'web');
    }
}
