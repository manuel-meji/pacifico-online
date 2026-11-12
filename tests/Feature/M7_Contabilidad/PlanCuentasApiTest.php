<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Models\Nucleo\Usuario;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlanCuentasApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return [
            '--path' => database_path('migrations/Nucleo'),
            '--realpath' => true,
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
                'ERR-002');
    }

    public function test_usuario_no_autenticado_no_puede_consultar_plan_de_cuentas(): void
    {
        $respuesta = $this->getJson(
            '/api/m7/v1/cuentas'
        );

        $respuesta->assertUnauthorized();
    }
}
