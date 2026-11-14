<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Enums\Nucleo\RolUsuario;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\Nucleo\Usuario;
use App\Services\M7_Contabilidad\PlanCuentasService;
use App\Services\Nucleo\ContextoComercio;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanCuentasApiTest extends TestCase
{
    use DatabaseMigrations;

    private const MIGRACIONES = [
        'database/migrations/Nucleo/2026_09_30_000001_crear_tabla_usuarios.php',
        'database/migrations/Nucleo/2026_09_30_000002_crear_tabla_bitacora_auditoria.php',
        'database/migrations/Nucleo/2026_09_30_000003_crear_tablas_sesiones_y_restablecimientos.php',
        'database/migrations/Nucleo/2026_09_30_000004_crear_tablas_trabajos_cola.php',
        'database/migrations/Nucleo/2026_11_02_191532_agregar_inmutabilidad_a_bitacora_auditoria.php',

        'database/migrations/M7_Contabilidad/2026_10_20_191725_create_centros_costo_table.php',
        'database/migrations/M7_Contabilidad/2026_10_20_191737_create_asientos_contables_table.php',
        'database/migrations/M7_Contabilidad/2026_10_20_191742_create_detalle_asientos_table.php',
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
    }

    private function crearCuenta(
        int $idComercio,
        string $codigo,
        string $nombre,
        string $tipo = 'ACTIVO',
        int $nivel = 1,
        ?int $idCuentaPadre = null,
        bool $activa = true
    ): int {
        return (int) DB::table('cuentas_contables')->insertGetId([
            'id_comercio' => $idComercio,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'nivel' => $nivel,
            'id_cuenta_padre' => $idCuentaPadre,
            'activa' => $activa,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function usuarioComercio(int $idComercio): Usuario
    {
        return UsuarioFactory::new()->create([
            'rol' => 'encargado_comercio',
            'id_comercio' => $idComercio,
            'activo' => true,
        ]);
    }

    private function usuarioPlataforma(): Usuario
    {
        return UsuarioFactory::new()->create([
            'rol' => RolUsuario::EncargadoPlataforma,
            'id_comercio' => null,
            'activo' => true,
        ]);
    }

    public function test_lista_cuentas_del_comercio_autenticado(): void
    {
        $this->crearCuenta(
            1,
            '1.1.01',
            'Caja y bancos'
        );

        $this->crearCuenta(
            2,
            '1.1.01',
            'Caja comercio 2'
        );

        $usuario = $this->usuarioComercio(1);

        $respuesta = $this->actingAs($usuario, 'web')
            ->getJson('/api/m7/v1/cuentas');

        $respuesta
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id_comercio', 1)
            ->assertJsonCount(1, 'data.cuentas')
            ->assertJsonPath(
                'data.cuentas.0.nombre',
                'Caja y bancos'
            )
            ->assertJsonPath(
                'data.cuentas.0.codigo',
                '1.1.01'
            )
            ->assertJsonPath(
                'data.cuentas.0.naturaleza',
                'DEUDORA'
            );
    }

    public function test_encargado_plataforma_puede_consultar_distintos_comercios_y_registra_auditoria(): void
    {
        $this->crearCuenta(
            1,
            '1.1.01',
            'Caja comercio 1'
        );

        $this->crearCuenta(
            2,
            '1.1.01',
            'Caja comercio 2'
        );

        $usuario = $this->usuarioPlataforma();

        // Consulta el comercio 1.
        $respuestaComercio1 = $this->actingAs($usuario, 'web')
            ->getJson('/api/m7/v1/cuentas?id_comercio=1');

        $respuestaComercio1
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id_comercio', 1)
            ->assertJsonCount(1, 'data.cuentas')
            ->assertJsonPath(
                'data.cuentas.0.nombre',
                'Caja comercio 1'
            );

        // Consulta el comercio 2.
        $respuestaComercio2 = $this->getJson(
            '/api/m7/v1/cuentas?id_comercio=2'
        );

        $respuestaComercio2
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id_comercio', 2)
            ->assertJsonCount(1, 'data.cuentas')
            ->assertJsonPath(
                'data.cuentas.0.nombre',
                'Caja comercio 2'
            );

        // Cada concesión de acceso debe dejar auditoría.
        $this->assertDatabaseHas('bitacora_auditoria', [
            'id_usuario' => $usuario->getKey(),
            'accion' => 'acceso_total_comercios',
            'entidad' => 'contexto_comercio',
            'entidad_id' => $usuario->getKey(),
            'resultado' => 'exitoso',
        ]);
    }

    public function test_no_expone_cuentas_de_otro_comercio(): void
    {
        $this->crearCuenta(
            1,
            '1.1.01',
            'Caja comercio 1'
        );

        $this->crearCuenta(
            2,
            '4.1.01',
            'Ventas comercio 2',
            'INGRESO'
        );

        $usuario = $this->usuarioComercio(1);

        $respuesta = $this->actingAs($usuario, 'web')
            ->getJson('/api/m7/v1/cuentas');

        $respuesta
            ->assertOk()
            ->assertJsonCount(1, 'data.cuentas')
            ->assertJsonMissing([
                'nombre' => 'Ventas comercio 2',
            ]);
    }

    public function test_puede_consultar_una_cuenta_del_propio_comercio(): void
    {
        $idCuenta = $this->crearCuenta(
            1,
            '5.1.01',
            'Costo de ventas',
            'GASTO'
        );

        $usuario = $this->usuarioComercio(1);

        $respuesta = $this->actingAs($usuario, 'web')
            ->getJson(
                "/api/m7/v1/cuentas/{$idCuenta}"
            );

        $respuesta
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.id_cuenta_contable',
                $idCuenta
            )
            ->assertJsonPath(
                'data.codigo',
                '5.1.01'
            )
            ->assertJsonPath(
                'data.nombre',
                'Costo de ventas'
            )
            ->assertJsonPath(
                'data.tipo',
                'GASTO'
            )
            ->assertJsonPath(
                'data.naturaleza',
                'DEUDORA'
            );
    }

    public function test_no_puede_consultar_cuenta_de_otro_comercio(): void
    {
        $idCuenta = $this->crearCuenta(
            2,
            '4.1.01',
            'Ventas comercio 2',
            'INGRESO'
        );

        $usuario = $this->usuarioComercio(1);

        $respuesta = $this->actingAs($usuario, 'web')
            ->getJson(
                "/api/m7/v1/cuentas/{$idCuenta}"
            );

        $respuesta->assertNotFound();
    }

    public function test_puede_filtrar_solo_cuentas_activas(): void
    {
        $this->crearCuenta(
            1,
            '1.1.01',
            'Caja',
            'ACTIVO',
            1,
            null,
            true
        );

        $this->crearCuenta(
            1,
            '1.1.02',
            'Cuenta inactiva',
            'ACTIVO',
            1,
            null,
            false
        );

        $usuario = $this->usuarioComercio(1);

        $respuesta = $this->actingAs($usuario, 'web')
            ->getJson('/api/m7/v1/cuentas?solo_activas=1');

        $respuesta
            ->assertOk()
            ->assertJsonCount(1, 'data.cuentas')
            ->assertJsonPath(
                'data.cuentas.0.nombre',
                'Caja'
            )
            ->assertJsonPath(
                'data.cuentas.0.activa',
                true
            );
    }

    public function test_cliente_no_tiene_permiso_para_consultar_plan_de_cuentas(): void
    {
        $usuario = UsuarioFactory::new()->create([
            'rol' => 'cliente',
            'id_comercio' => null,
            'activo' => true,
        ]);

        $respuesta = $this->actingAs($usuario, 'web')
            ->getJson('/api/m7/v1/cuentas');

        $respuesta
            ->assertForbidden()
            ->assertJsonPath(
                'error.codigo',
                'ERR-002'
            );
    }

    public function test_usuario_no_autenticado_no_puede_consultar_plan_de_cuentas(): void
    {
        $respuesta = $this->getJson(
            '/api/m7/v1/cuentas'
        );

        $respuesta->assertUnauthorized();
    }

    public function test_encargado_puede_crear_cuenta_y_registra_auditoria(): void
    {
        $usuario = $this->usuarioComercio(1);

        $this->actingAs($usuario, 'web');

        app(ContextoComercio::class)->establecer(1);

        $cuenta = app(PlanCuentasService::class)->crear(1, [
            'codigo' => '1.1.01',
            'nombre' => 'Caja y bancos',
            'tipo' => 'ACTIVO',
        ]);

        $idCuenta = (int) $cuenta->getKey();

        $this->assertSame('1.1.01', $cuenta->codigo);
        $this->assertSame('Caja y bancos', $cuenta->nombre);
        $this->assertSame('ACTIVO', $cuenta->tipo);
        $this->assertTrue($cuenta->activa);

        $this->assertDatabaseHas('cuentas_contables', [
            'id_cuenta_contable' => $idCuenta,
            'id_comercio' => 1,
            'codigo' => '1.1.01',
            'nombre' => 'Caja y bancos',
            'tipo' => 'ACTIVO',
            'activa' => true,
        ]);

        $this->assertDatabaseHas('bitacora_auditoria', [
            'id_usuario' => $usuario->getKey(),
            'id_comercio' => 1,
            'accion' => 'cuenta_contable_creada',
            'modulo' => 'M7_CONTABILIDAD',
            'entidad' => 'cuenta_contable',
            'entidad_id' => $idCuenta,
            'resultado' => 'exitoso',
        ]);
    }

    public function test_encargado_puede_actualizar_cuenta_y_registra_auditoria(): void
    {
        $idCuenta = $this->crearCuenta(
            1,
            '1.1.01',
            'Caja'
        );

        $usuario = $this->usuarioComercio(1);

        $this->actingAs($usuario, 'web');

        app(ContextoComercio::class)->establecer(1);

        $cuenta = app(PlanCuentasService::class)->actualizar(
            1,
            $idCuenta,
            [
                'nombre' => 'Caja y bancos',
                'activa' => true,
            ]
        );

        $this->assertSame($idCuenta, (int) $cuenta->getKey());
        $this->assertSame('Caja y bancos', $cuenta->nombre);
        $this->assertTrue($cuenta->activa);

        $this->assertDatabaseHas('cuentas_contables', [
            'id_cuenta_contable' => $idCuenta,
            'id_comercio' => 1,
            'nombre' => 'Caja y bancos',
            'activa' => true,
        ]);

        $this->assertDatabaseHas('bitacora_auditoria', [
            'id_usuario' => $usuario->getKey(),
            'id_comercio' => 1,
            'accion' => 'cuenta_contable_actualizada',
            'modulo' => 'M7_CONTABILIDAD',
            'entidad' => 'cuenta_contable',
            'entidad_id' => $idCuenta,
            'resultado' => 'exitoso',
        ]);
    }

    public function test_encargado_puede_desactivar_cuenta_y_registra_auditoria(): void
    {
        $idCuenta = $this->crearCuenta(
            1,
            '1.1.01',
            'Caja'
        );

        $usuario = $this->usuarioComercio(1);

        $this->actingAs($usuario, 'web');

        app(ContextoComercio::class)->establecer(1);

        $cuenta = app(PlanCuentasService::class)->desactivar(
            1,
            $idCuenta
        );

        $this->assertSame($idCuenta, (int) $cuenta->getKey());
        $this->assertFalse($cuenta->activa);

        $this->assertDatabaseHas('cuentas_contables', [
            'id_cuenta_contable' => $idCuenta,
            'id_comercio' => 1,
            'activa' => false,
        ]);

        $this->assertDatabaseHas('bitacora_auditoria', [
            'id_usuario' => $usuario->getKey(),
            'id_comercio' => 1,
            'accion' => 'cuenta_contable_desactivada',
            'modulo' => 'M7_CONTABILIDAD',
            'entidad' => 'cuenta_contable',
            'entidad_id' => $idCuenta,
            'resultado' => 'exitoso',
        ]);
    }

    public function test_no_permite_desactivar_cuenta_con_subcuentas_activas(): void
    {
        $idPadre = $this->crearCuenta(
            1,
            '1.1',
            'Activo'
        );

        $this->crearCuenta(
            1,
            '1.1.01',
            'Caja',
            'ACTIVO',
            2,
            $idPadre
        );

        $usuario = $this->usuarioComercio(1);

        $this->actingAs($usuario, 'web');

        app(ContextoComercio::class)->establecer(1);

        try {
            app(PlanCuentasService::class)->desactivar(1, $idPadre);

            $this->fail('Se esperaba una excepción al desactivar una cuenta con subcuentas activas.');
        } catch (ErrorNucleoException $e) {
            $this->assertSame('ERR-M7-002', $e->getCodigo());
            $this->assertSame(409, $e->getCodigoHttp());
        }

        $this->assertDatabaseHas('cuentas_contables', [
            'id_cuenta_contable' => $idPadre,
            'id_comercio' => 1,
            'activa' => true,
        ]);
    }

    public function test_no_permite_desactivar_cuenta_con_movimientos(): void
    {
        $idCuenta = $this->crearCuenta(
            1,
            '1.1.01',
            'Caja'
        );

        $idAsiento = DB::table('asientos_contables')->insertGetId([
            'id_comercio' => 1,
            'evento_id' => (string) Str::uuid(),
            'tipo_evento' => 'PRUEBA_M7',
            'tipo' => 'AJUSTE',
            'fecha' => now(),
            'descripcion' => 'Movimiento de prueba',
            'estado' => 'REGISTRADO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('detalle_asientos')->insert([
            'id_asiento' => $idAsiento,
            'id_cuenta_contable' => $idCuenta,
            'numero_linea' => 1,
            'debe' => 100,
            'haber' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $usuario = $this->usuarioComercio(1);

        $this->actingAs($usuario, 'web');

        app(ContextoComercio::class)->establecer(1);

        try {
            app(PlanCuentasService::class)->desactivar(1, $idCuenta);

            $this->fail('Se esperaba una excepción al desactivar una cuenta con movimientos.');
        } catch (ErrorNucleoException $e) {
            $this->assertSame('ERR-M7-002', $e->getCodigo());
            $this->assertSame(409, $e->getCodigoHttp());
        }

        $this->assertDatabaseHas('cuentas_contables', [
            'id_cuenta_contable' => $idCuenta,
            'id_comercio' => 1,
            'activa' => true,
        ]);

        $this->assertDatabaseMissing('bitacora_auditoria', [
            'id_usuario' => $usuario->getKey(),
            'id_comercio' => 1,
            'accion' => 'cuenta_contable_desactivada',
            'entidad' => 'cuenta_contable',
            'entidad_id' => $idCuenta,
            'resultado' => 'exitoso',
        ]);
    }

    public function test_no_permite_crear_cuentas_con_codigo_duplicado_en_el_mismo_comercio(): void
    {
        $this->crearCuenta(
            1,
            '1.1.01',
            'Caja'
        );

        $usuario = $this->usuarioComercio(1);

        $this->actingAs($usuario, 'web');

        app(ContextoComercio::class)->establecer(1);

        try {
            app(PlanCuentasService::class)->crear(1, [
                'codigo' => '1.1.01',
                'nombre' => 'Caja secundaria',
                'tipo' => 'ACTIVO',
            ]);

            $this->fail('Se esperaba una excepción por código de cuenta duplicado.');
        } catch (ErrorNucleoException $e) {
            $this->assertSame('ERR-M7-003', $e->getCodigo());
            $this->assertSame(422, $e->getCodigoHttp());
        }

        $this->assertDatabaseCount('cuentas_contables', 1);

        $this->assertDatabaseMissing('bitacora_auditoria', [
            'id_usuario' => $usuario->getKey(),
            'id_comercio' => 1,
            'accion' => 'cuenta_contable_creada',
            'entidad' => 'cuenta_contable',
            'resultado' => 'exitoso',
        ]);
    }

    public function test_no_permite_crear_subcuentas_bajo_cuentas_con_movimientos(): void
    {
        $idCuentaPadre = $this->crearCuenta(
            1,
            '1.1',
            'Activos'
        );

        $ahora = now();

        $idAsiento = DB::table('asientos_contables')->insertGetId([
            'id_comercio' => 1,
            'evento_id' => (string) Str::uuid(),
            'tipo_evento' => 'PRUEBA_M7',
            'tipo' => 'AJUSTE',
            'fecha' => $ahora,
            'descripcion' => 'Asiento de prueba para validar movimientos',
            'estado' => 'REGISTRADO',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ], 'id_asiento');

        DB::table('detalle_asientos')->insert([
            'id_asiento' => $idAsiento,
            'id_cuenta_contable' => $idCuentaPadre,
            'numero_linea' => 1,
            'debe' => 100,
            'haber' => 0,
            'descripcion' => 'Movimiento de prueba',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        $usuario = $this->usuarioComercio(1);

        $this->actingAs($usuario, 'web');

        app(ContextoComercio::class)->establecer(1);

        try {
            app(PlanCuentasService::class)->crear(1, [
                'codigo' => '1.1.01',
                'nombre' => 'Caja',
                'tipo' => 'ACTIVO',
                'id_cuenta_padre' => $idCuentaPadre,
            ]);

            $this->fail('Se esperaba una excepción al crear una subcuenta bajo una cuenta con movimientos.');
        } catch (ErrorNucleoException $e) {
            $this->assertSame('ERR-M7-002', $e->getCodigo());
            $this->assertSame(409, $e->getCodigoHttp());
            $this->assertSame(
                'No se pueden crear subcuentas bajo una cuenta que ya tiene movimientos.',
                $e->getMessage()
            );
        }

        $this->assertDatabaseCount('cuentas_contables', 1);

        $this->assertDatabaseMissing('bitacora_auditoria', [
            'id_usuario' => $usuario->getKey(),
            'id_comercio' => 1,
            'accion' => 'cuenta_contable_creada',
            'entidad' => 'cuenta_contable',
            'resultado' => 'exitoso',
        ]);
    }

    public function test_no_permite_modificar_datos_estructurales_de_una_cuenta_con_movimientos(): void
    {
        $idCuenta = $this->crearCuenta(
            1,
            '1.1.01',
            'Caja'
        );

        $ahora = now();

        $idAsiento = DB::table('asientos_contables')->insertGetId([
            'id_comercio' => 1,
            'evento_id' => (string) Str::uuid(),
            'tipo_evento' => 'PRUEBA_M7',
            'tipo' => 'AJUSTE',
            'fecha' => $ahora,
            'descripcion' => 'Asiento de prueba',
            'estado' => 'REGISTRADO',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ], 'id_asiento');

        DB::table('detalle_asientos')->insert([
            'id_asiento' => $idAsiento,
            'id_cuenta_contable' => $idCuenta,
            'numero_linea' => 1,
            'debe' => 100,
            'haber' => 0,
            'descripcion' => 'Movimiento de prueba',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        $contexto = app(ContextoComercio::class);
        $contexto->establecer(1);

        $servicio = app(
            PlanCuentasService::class
        );

        $cambiosProhibidos = [
            'tipo' => 'PASIVO',
            'codigo' => '1.1.99',
            'id_cuenta_padre' => 999,
        ];

        foreach ($cambiosProhibidos as $campo => $valor) {
            try {
                $servicio->actualizar(
                    1,
                    $idCuenta,
                    [$campo => $valor]
                );

                $this->fail(
                    "Se permitió modificar '{$campo}' de una cuenta con movimientos."
                );
            } catch (ErrorNucleoException $e) {
                $this->assertStringContainsString(
                    "No se puede modificar '{$campo}'",
                    $e->getMessage()
                );
                $this->assertSame('ERR-M7-002', $e->getCodigo());
                $this->assertSame(409, $e->getCodigoHttp());
            }
        }

        $this->assertDatabaseHas('cuentas_contables', [
            'id_cuenta_contable' => $idCuenta,
            'id_comercio' => 1,
            'codigo' => '1.1.01',
            'tipo' => 'ACTIVO',
            'nombre' => 'Caja',
        ]);

        $this->assertDatabaseMissing('bitacora_auditoria', [
            'accion' => 'cuenta_contable_actualizada',
            'entidad' => 'cuenta_contable',
            'entidad_id' => $idCuenta,
            'resultado' => 'exitoso',
        ]);
    }
}
