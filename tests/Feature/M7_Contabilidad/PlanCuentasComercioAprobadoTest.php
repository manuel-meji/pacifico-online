<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\DirectorioComerciosM7GatewayInterface;
use App\Events\M2_Comercios\ComercioAprobado;
use App\Services\M7_Contabilidad\PlanCuentasBaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Mockery;
use Tests\Feature\M6_Pagos\Concerns\BaseSinSentenciasPostgres;
use Tests\Feature\M6_Pagos\Concerns\PreparaM6;
use Tests\TestCase;

class PlanCuentasComercioAprobadoTest extends TestCase
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

    public function test_comercio_aprobado_recibe_plan_base_y_centro_general_sin_duplicar(): void
    {
        $this->aprobar(40);
        $this->aprobar(40);

        $this->assertSame(count(PlanCuentasBaseService::PLAN_BASE), DB::table('cuentas_contables')->where('id_comercio', 40)->count());
        $this->assertSame(1, DB::table('centros_costo')->where('id_comercio', 40)->where('codigo', 'GEN')->where('activo', true)->count());
        $ventas = DB::table('cuentas_contables')->where('id_comercio', 40)->where('codigo', '4.1.01')->first();
        $padre = DB::table('cuentas_contables')->where('id_cuenta_contable', $ventas->id_cuenta_padre)->first();
        $this->assertSame(['4.1', 3], [$padre->codigo, (int) $ventas->nivel]);
        $this->assertSame(['M7'], DB::table('bitacora_auditoria')->where('accion', 'plan_cuentas_base_creado')->where('entidad_id', 40)->pluck('modulo')->all());
    }

    public function test_conserva_cuentas_existentes_y_solo_completa_lo_que_falta(): void
    {
        $existente = DB::table('cuentas_contables')->insertGetId([
            'id_comercio' => 41, 'codigo' => '4.1.01', 'nombre' => 'Ingresos por ventas', 'tipo' => 'INGRESO',
            'nivel' => 3, 'id_cuenta_padre' => null, 'activa' => true, 'created_at' => now(), 'updated_at' => now(),
        ], 'id_cuenta_contable');

        $this->aprobar(41);

        $cuenta = DB::table('cuentas_contables')->where('id_cuenta_contable', $existente)->first();
        $this->assertSame('Ingresos por ventas', $cuenta->nombre);
        $this->assertSame('4.1', DB::table('cuentas_contables')->where('id_cuenta_contable', $cuenta->id_cuenta_padre)->value('codigo'));
        $this->assertSame(count(PlanCuentasBaseService::PLAN_BASE), DB::table('cuentas_contables')->where('id_comercio', 41)->count());
    }

    public function test_un_fallo_de_m7_no_interrumpe_la_aprobacion_y_no_deja_plan_parcial(): void
    {
        Schema::drop('centros_costo');
        $erroresM7 = [];
        Log::listen(function ($mensaje) use (&$erroresM7): void {
            if ($mensaje->level === 'error' && str_starts_with($mensaje->message, 'M7:')) {
                $erroresM7[] = $mensaje->context['id_comercio'] ?? null;
            }
        });

        $this->aprobar(42);

        $this->assertSame(0, DB::table('cuentas_contables')->where('id_comercio', 42)->count());
        $this->assertSame([42], $erroresM7);
    }

    public function test_comando_crea_el_plan_solo_para_comercios_habilitados_en_m2(): void
    {
        $directorio = Mockery::mock(DirectorioComerciosM7GatewayInterface::class);
        $directorio->shouldReceive('obtenerNombres')->once()->with([34, 99])->andReturn([34 => 'Tienda La Ceiba']);
        $this->app->instance(DirectorioComerciosM7GatewayInterface::class, $directorio);

        $this->artisan('m7:crear-plan-cuentas', ['id_comercio' => ['34', '99']])
            ->expectsOutputToContain('Comercio 34 (Tienda La Ceiba)')
            ->expectsOutputToContain('Comercio 99: M2 no lo reporta como habilitado')
            ->assertExitCode(1);

        $this->assertSame(count(PlanCuentasBaseService::PLAN_BASE), DB::table('cuentas_contables')->where('id_comercio', 34)->count());
        $this->assertSame(0, DB::table('cuentas_contables')->where('id_comercio', 99)->count());
    }

    private function aprobar(int $idComercio): void
    {
        ComercioAprobado::dispatch((string) Str::uuid(), now(), $idComercio, 'Activo', 'Comercio de prueba');
    }
}
