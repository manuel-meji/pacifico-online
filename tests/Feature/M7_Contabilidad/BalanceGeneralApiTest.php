<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use Database\Factories\UsuarioFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BalanceGeneralApiTest extends TestCase
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

    private function cuenta(int $comercio, string $tipo): int
    {
        return (int) DB::table('cuentas_contables')->insertGetId([
            'id_comercio' => $comercio,
            'codigo' => Str::random(8),
            'nombre' => $tipo . ' comercio ' . $comercio,
            'tipo' => $tipo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function movimiento(
        int $comercio,
        int $cuenta,
        string $fecha,
        int $debe,
        int $haber,
        string $estado = 'REGISTRADO'
    ): void {
        $asiento = DB::table('asientos_contables')->insertGetId([
            'id_comercio' => $comercio,
            'evento_id' => (string) Str::uuid(),
            'tipo_evento' => 'PRUEBA_M7',
            'tipo' => 'AJUSTE',
            'fecha' => $fecha,
            'estado' => $estado,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('detalle_asientos')->insert([
            'id_asiento' => $asiento,
            'id_cuenta_contable' => $cuenta,
            'numero_linea' => 1,
            'debe' => $debe,
            'haber' => $haber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_endpoint_balance_general_con_fecha_corte(): void
    {
        $activo = $this->cuenta(1, 'ACTIVO');
        $ingreso = $this->cuenta(1, 'INGRESO');
        $externo = $this->cuenta(2, 'ACTIVO');

        $this->movimiento(1, $activo, '2025-12-15', 100, 0);
        $this->movimiento(1, $ingreso, '2025-12-15', 0, 100);

        $this->movimiento(1, $activo, '2026-06-15', 50, 0);
        $this->movimiento(1, $ingreso, '2026-06-15', 0, 50);

        $this->movimiento(1, $activo, '2026-12-15', 200, 0);
        $this->movimiento(1, $activo, '2026-05-15', 300, 0, 'ANULADO');
        $this->movimiento(2, $externo, '2026-05-15', 999, 0);

        $usuario = UsuarioFactory::new()->create([
            'rol' => 'encargado_comercio',
            'id_comercio' => 1,
            'activo' => true,
        ]);

        $respuesta = $this->actingAs($usuario, 'web')
            ->getJson('/api/m7/v1/balance-general?fecha_corte=2026-11-14');

        $respuesta
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id_comercio', 1)
            ->assertJsonPath('data.fecha_corte', '2026-11-14')
            ->assertJsonPath('data.activos_centimos', 15000)
            ->assertJsonPath('data.cuadra', true)
            ->assertJsonCount(2, 'data.cuentas')
            ->assertJsonMissing([
                'nombre' => 'ACTIVO comercio 2',
            ]);
    }

    public function test_fecha_corte_es_obligatoria(): void
    {
        $usuario = UsuarioFactory::new()->create([
            'rol' => 'encargado_comercio',
            'id_comercio' => 1,
            'activo' => true,
        ]);

        $this->actingAs($usuario, 'web')
            ->getJson('/api/m7/v1/balance-general')
            ->assertUnprocessable();
    }

    public function test_usuario_no_autenticado_no_puede_consultar(): void
    {
        $this->getJson(
            '/api/m7/v1/balance-general?fecha_corte=2026-11-14'
        )->assertUnauthorized();
    }

    public function test_cliente_no_puede_consultar(): void
    {
        $usuario = UsuarioFactory::new()->create([
            'rol' => 'cliente',
            'id_comercio' => null,
            'activo' => true,
        ]);

        $this->actingAs($usuario, 'web')
            ->getJson('/api/m7/v1/balance-general?fecha_corte=2026-11-14')
            ->assertForbidden();
    }

    public function test_no_permite_consultar_otro_comercio(): void
    {
        $usuario = UsuarioFactory::new()->create([
            'rol' => 'encargado_comercio',
            'id_comercio' => 1,
            'activo' => true,
        ]);

        $respuesta = $this->actingAs($usuario, 'web')
            ->getJson(
                '/api/m7/v1/balance-general?fecha_corte=2026-11-14&id_comercio=2'
            );

        $this->assertNotEquals(200, $respuesta->status());
    }
}
