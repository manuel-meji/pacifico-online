<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad\Contratos;

use App\Models\Nucleo\Usuario;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pruebas de contrato de M7 como proveedor (OpenAPI v1.1.0, propuesta):
 * forma de las respuestas, códigos de error y autorización por rol.
 */
class M7_ProveedorContratoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ERROR = ['error' => ['codigo', 'mensaje', 'detalles']];

    private const MIGRACIONES = [
        'database/migrations/Nucleo/2026_09_30_000001_crear_tabla_usuarios.php',
        'database/migrations/Nucleo/2026_09_30_000002_crear_tabla_bitacora_auditoria.php',
        'database/migrations/Nucleo/2026_09_30_000003_crear_tablas_sesiones_y_restablecimientos.php',
        'database/migrations/Nucleo/2026_09_30_000004_crear_tablas_trabajos_cola.php',
        'database/migrations/Nucleo/2026_11_02_191532_agregar_inmutabilidad_a_bitacora_auditoria.php',
    ];

    protected function migrateUsing(): array
    {
        return [
            '--path' => self::MIGRACIONES,
            '--seed' => false,
        ];
    }

    protected function migrateFreshUsing(): array
    {
        return [
            '--path' => self::MIGRACIONES,
            '--drop-views' => false,
            '--drop-types' => false,
            '--seed' => false,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('comercios', function (Blueprint $table): void {
            $table->id('id_comercio');
        });

        DB::table('comercios')->insert([
            ['id_comercio' => 1],
            ['id_comercio' => 2],
        ]);

        Schema::create('cuentas_contables', function (Blueprint $table): void {
            $table->id('id_cuenta_contable');
            $table->unsignedBigInteger('id_comercio');
            $table->string('codigo', 30);
            $table->string('nombre', 150);
            $table->string('tipo', 20);
            $table->unsignedInteger('nivel')->default(1);
            $table->unsignedBigInteger('id_cuenta_padre')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('asientos_contables', function (Blueprint $table): void {
            $table->id('id_asiento');
            $table->unsignedBigInteger('id_comercio');
            $table->unsignedBigInteger('id_pedido')->nullable();
            $table->unsignedBigInteger('id_subpedido')->nullable();
            $table->unsignedBigInteger('id_pago')->nullable();
            $table->uuid('evento_id');
            $table->string('tipo_evento', 80);
            $table->string('tipo', 40);
            $table->timestamp('fecha')->useCurrent();
            $table->string('descripcion', 255)->nullable();
            $table->string('estado', 20)->default('REGISTRADO');
            $table->unsignedBigInteger('id_asiento_original')->nullable();
            $table->timestamps();
        });

        Schema::create('detalle_asientos', function (Blueprint $table): void {
            $table->id('id_detalle_asiento');
            $table->unsignedBigInteger('id_asiento');
            $table->unsignedBigInteger('id_cuenta_contable');
            $table->unsignedBigInteger('id_centro_costo')->nullable();
            $table->unsignedInteger('numero_linea');
            $table->decimal('debe', 15, 2)->default(0);
            $table->decimal('haber', 15, 2)->default(0);
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });
    }

    // ---- Datos de prueba -------------------------------------------------

    private function crearCuenta(
        int $idComercio,
        string $codigo,
        string $nombre,
        string $tipo = 'ACTIVO',
        int $nivel = 1,
        ?int $idPadre = null
    ): int {
        return (int) DB::table('cuentas_contables')->insertGetId([
            'id_comercio' => $idComercio,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'nivel' => $nivel,
            'id_cuenta_padre' => $idPadre,
            'activa' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearAsiento(
        int $idComercio,
        string $tipo,
        int $idCuenta,
        float $debe,
        float $haber
    ): int {
        $idAsiento = (int) DB::table('asientos_contables')->insertGetId([
            'id_comercio' => $idComercio,
            'evento_id' => (string) Str::uuid(),
            'tipo_evento' => 'PRUEBA_CONTRATO',
            'tipo' => $tipo,
            'fecha' => '2026-11-08 12:00:00',
            'descripcion' => 'Asiento de prueba de contrato',
            'estado' => 'REGISTRADO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('detalle_asientos')->insert([
            'id_asiento' => $idAsiento,
            'id_cuenta_contable' => $idCuenta,
            'numero_linea' => 1,
            'debe' => $debe,
            'haber' => $haber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $idAsiento;
    }

    private function encargado(int $idComercio): Usuario
    {
        return UsuarioFactory::new()->create([
            'rol' => 'encargado_comercio',
            'id_comercio' => $idComercio,
            'activo' => true,
        ]);
    }

    private function cliente(): Usuario
    {
        return UsuarioFactory::new()->create([
            'rol' => 'cliente',
            'id_comercio' => null,
            'activo' => true,
        ]);
    }

    // listarCuentasContables / consultarCuentaContable

    public function test_listar_cuentas_cumple_el_esquema(): void
    {
        $this->crearCuenta(1, '1.1.01', 'Caja y bancos');

        $this->actingAs($this->encargado(1), 'web')->getJson('/api/m7/v1/cuentas')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id_comercio', 1)
            ->assertJsonCount(1, 'data.cuentas')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id_comercio',
                    'cuentas' => [
                        '*' => [
                            'id_cuenta_contable',
                            'codigo',
                            'nombre',
                            'tipo',
                            'naturaleza',
                            'nivel',
                            'id_cuenta_padre',
                            'activa',
                        ]
                    ],
                ],
            ]);
    }

    public function test_plataforma_lista_las_cuentas_del_comercio_indicado(): void
    {
        $this->crearCuenta(1, '1.1.01', 'Caja comercio 1');
        $this->crearCuenta(2, '1.1.01', 'Caja comercio 2');

        $plataforma = UsuarioFactory::new()->create([
            'rol' => 'encargado_plataforma',
            'id_comercio' => null,
            'activo' => true,
        ]);

        $this->actingAs($plataforma, 'web')->getJson('/api/m7/v1/cuentas?id_comercio=2')
            ->assertOk()
            ->assertJsonPath('data.id_comercio', 2)
            ->assertJsonCount(1, 'data.cuentas')
            ->assertJsonPath('data.cuentas.0.nombre', 'Caja comercio 2');
    }

    public function test_consultar_cuenta_cumple_el_esquema_y_expone_la_jerarquia(): void
    {
        $idPadre = $this->crearCuenta(1, '1.1', 'Activo corriente');
        $idHija = $this->crearCuenta(1, '1.1.01', 'Caja', 'ACTIVO', 2, $idPadre);

        $this->actingAs($this->encargado(1), 'web')->getJson("/api/m7/v1/cuentas/{$idHija}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id_cuenta_padre', $idPadre)
            ->assertJsonPath('data.nivel', 2)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id_cuenta_contable',
                    'codigo',
                    'nombre',
                    'tipo',
                    'naturaleza',
                    'nivel',
                    'id_cuenta_padre',
                    'activa',
                ],
            ]);
    }

    public function test_cuenta_ajena_o_inexistente_responde_404_err_004(): void
    {
        $idAjena = $this->crearCuenta(2, '4.1.01', 'Ventas comercio 2', 'INGRESO');
        $encargado = $this->encargado(1);

        foreach ([$idAjena, 999999] as $id) {
            $this->actingAs($encargado, 'web')->getJson("/api/m7/v1/cuentas/{$id}")
                ->assertNotFound()
                ->assertJsonStructure(self::ERROR)
                ->assertJsonPath('error.codigo', 'ERR-004');
        }
    }

    public function test_la_ruta_de_cuenta_solo_acepta_digitos(): void
    {
        $this->actingAs($this->encargado(1), 'web')->getJson('/api/m7/v1/cuentas/abc')
            ->assertNotFound();
    }

    // consultarLibroDiario

    public function test_libro_diario_cumple_el_esquema(): void
    {
        $idCuenta = $this->crearCuenta(1, '4.1.01', 'Ventas', 'INGRESO');
        $this->crearAsiento(1, 'VENTA', $idCuenta, 0, 1000);

        $this->actingAs($this->encargado(1), 'web')->getJson('/api/m7/v1/libro-diario')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cantidad', 1)
            ->assertJsonCount(1, 'data.asientos')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'asientos' => [
                        '*' => [
                            'id',
                            'numero',
                            'fecha',
                            'descripcion',
                            'tipo',
                            'estado',
                            'referencia',
                            'totalDebe',
                            'totalHaber',
                            'lineas' => ['*' => ['codigo', 'cuenta', 'debe', 'haber', 'descripcion']],
                        ]
                    ],
                    'totalDebe',
                    'totalHaber',
                    'cantidad',
                ],
            ]);
    }

    public function test_libro_diario_con_estado_invalido_responde_422_err_005(): void
    {
        $this->actingAs($this->encargado(1), 'web')->getJson('/api/m7/v1/libro-diario?estado=INVENTADO')
            ->assertStatus(422)
            ->assertJsonStructure(self::ERROR)
            ->assertJsonPath('error.codigo', 'ERR-005');
    }

    // consultarLibroMayor / listarCuentasLibroMayor

    public function test_libro_mayor_cumple_el_esquema(): void
    {
        $idCuenta = $this->crearCuenta(1, '1.1.01', 'Caja');
        $this->crearAsiento(1, 'VENTA', $idCuenta, 100, 0);

        $respuesta = $this->actingAs($this->encargado(1), 'web')
            ->getJson("/api/m7/v1/libro-mayor?id_cuenta_contable={$idCuenta}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.incluir_anulados', false)
            ->assertJsonCount(1, 'data.movimientos')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'cuenta' => ['id_cuenta_contable', 'codigo', 'nombre', 'naturaleza'],
                    'id_comercio',
                    'fecha_desde',
                    'fecha_hasta',
                    'incluir_anulados',
                    'saldo_inicial',
                    'total_debe',
                    'total_haber',
                    'saldo_final',
                    'movimientos' => [
                        '*' => [
                            'id_asiento',
                            'fecha',
                            'numero_linea',
                            'asiento',
                            'tipo',
                            'descripcion',
                            'estado',
                            'debe',
                            'haber',
                            'saldo',
                        ]
                    ],
                ],
            ]);

        // El contrato define los importes del mayor como cadenas.
        $this->assertIsString($respuesta->json('data.saldo_final'));
        $this->assertIsString($respuesta->json('data.movimientos.0.saldo'));
    }

    public function test_libro_mayor_sin_cuenta_responde_422_err_005(): void
    {
        $this->actingAs($this->encargado(1), 'web')->getJson('/api/m7/v1/libro-mayor')
            ->assertStatus(422)
            ->assertJsonStructure(self::ERROR)
            ->assertJsonPath('error.codigo', 'ERR-005');
    }

    public function test_libro_mayor_de_cuenta_inexistente_responde_404(): void
    {
        $this->actingAs($this->encargado(1), 'web')->getJson('/api/m7/v1/libro-mayor?id_cuenta_contable=999999')
            ->assertNotFound()
            ->assertJsonStructure(self::ERROR);
    }

    public function test_cuentas_del_libro_mayor_cumplen_el_esquema(): void
    {
        $this->crearCuenta(1, '1.1.01', 'Caja');

        $this->actingAs($this->encargado(1), 'web')->getJson('/api/m7/v1/libro-mayor/cuentas')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id_comercio',
                    'cuentas' => ['*' => ['id_cuenta_contable', 'codigo', 'nombre', 'tipo', 'naturaleza']],
                ],
            ]);
    }

    // consultarReporteGerencial

    public function test_reporte_gerencial_cumple_el_esquema(): void
    {
        $ventas = $this->crearCuenta(1, '4.1.01', 'Ventas', 'INGRESO');
        $costos = $this->crearCuenta(1, '5.1.01', 'Costo de ventas', 'GASTO');
        $this->crearAsiento(1, 'VENTA', $ventas, 0, 1000);
        $this->crearAsiento(1, 'COSTO_VENTA', $costos, 400, 0);

        $respuesta = $this->actingAs($this->encargado(1), 'web')
            ->getJson('/api/m7/v1/reporte-gerencial?fecha_desde=2026-11-01&fecha_hasta=2026-11-30')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id_comercio',
                    'fecha_desde',
                    'fecha_hasta',
                    'resumen' => ['ingresos', 'costos', 'utilidad_bruta'],
                    'mensual' => ['*' => ['periodo', 'ingresos', 'costos', 'utilidad_bruta']],
                ],
            ]);

        $this->assertIsString($respuesta->json('data.resumen.ingresos'));
        $this->assertNotEmpty($respuesta->json('data.mensual'));
    }

    public function test_reporte_gerencial_con_fechas_invertidas_responde_422_err_005(): void
    {
        $this->actingAs($this->encargado(1), 'web')
            ->getJson('/api/m7/v1/reporte-gerencial?fecha_desde=2026-11-30&fecha_hasta=2026-11-01')
            ->assertStatus(422)
            ->assertJsonStructure(self::ERROR)
            ->assertJsonPath('error.codigo', 'ERR-005');
    }

    // Autorización de las rutas protegidas

    /** @return array<string, array{string}> */
    public static function rutasProtegidas(): array
    {
        return [
            'listarCuentasContables' => ['/api/m7/v1/cuentas'],
            'consultarCuentaContable' => ['/api/m7/v1/cuentas/1'],
            'consultarLibroDiario' => ['/api/m7/v1/libro-diario'],
            'consultarLibroMayor' => ['/api/m7/v1/libro-mayor?id_cuenta_contable=1'],
            'listarCuentasLibroMayor' => ['/api/m7/v1/libro-mayor/cuentas'],
            'consultarReporteGerencial' => ['/api/m7/v1/reporte-gerencial'],
        ];
    }

    #[DataProvider('rutasProtegidas')]
    public function test_sin_sesion_responde_401_err_001(string $ruta): void
    {
        $this->getJson($ruta)
            ->assertUnauthorized()
            ->assertJsonStructure(self::ERROR)
            ->assertJsonPath('error.codigo', 'ERR-001');
    }

    #[DataProvider('rutasProtegidas')]
    public function test_cliente_responde_403_err_002(string $ruta): void
    {
        $this->actingAs($this->cliente(), 'web')->getJson($ruta)
            ->assertForbidden()
            ->assertJsonStructure(self::ERROR)
            ->assertJsonPath('error.codigo', 'ERR-002');
    }

    // Rutas web (sin autenticación según el contrato v1.1.0)

    public function test_estado_del_modulo(): void
    {
        $this->getJson('/m7')
            ->assertOk()
            ->assertExactJson(['module' => 'M7_Contabilidad', 'status' => 'online']);
    }

    /**
     * El contrato v1.1.0 declara esta ruta con "security: []" y la marca como riesgo
     * pendiente. La prueba documenta el comportamiento actual, no lo aprueba.
     */
    public function test_registro_contable_de_pago_sin_asiento_devuelve_registrado_false(): void
    {
        $this->getJson('/m7/pagos/999/registro-contable')
            ->assertOk()
            ->assertJsonStructure(['id_pago', 'registrado', 'asiento_id', 'fecha_registro'])
            ->assertJsonPath('id_pago', 999)
            ->assertJsonPath('registrado', false)
            ->assertJsonPath('asiento_id', null);
    }
}
