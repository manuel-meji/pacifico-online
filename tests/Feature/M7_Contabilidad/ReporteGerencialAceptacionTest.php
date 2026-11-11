<?php

namespace Tests\Feature\M7_Contabilidad;

use App\Models\Nucleo\Usuario;
use Database\Factories\UsuarioFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class ReporteGerencialAceptacionTest extends TestCase
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

        // Tabla mínima de comercios para satisfacer la clave foránea de usuarios.
        Schema::create('comercios', function (Blueprint $table) {
            $table->id('id_comercio');
        });

        DB::table('comercios')->insert([
            ['id_comercio' => 1],
            ['id_comercio' => 2],
        ]);

        Schema::create('cuentas_contables', function (Blueprint $table) {
            $table->id('id_cuenta_contable');
            $table->unsignedBigInteger('id_comercio');
            $table->string('codigo');
            $table->boolean('activa')->default(true);
        });

        Schema::create('asientos_contables', function (Blueprint $table) {
            $table->id('id_asiento');
            $table->unsignedBigInteger('id_comercio');
            $table->dateTime('fecha');
            $table->string('tipo');
            $table->string('estado');
        });

        Schema::create('detalle_asientos', function (Blueprint $table) {
            $table->id('id_detalle_asiento');
            $table->unsignedBigInteger('id_asiento');
            $table->unsignedBigInteger('id_cuenta_contable');
            $table->decimal('debe', 15, 2)->default(0);
            $table->decimal('haber', 15, 2)->default(0);
        });

        $this->crearCuenta(1, '4.1.01');
        $this->crearCuenta(1, '5.1.01');
        $this->crearCuenta(2, '4.1.01');
        $this->crearCuenta(2, '5.1.01');
    }

    private function crearCuenta(int $comercio, string $codigo): int
    {
        return DB::table('cuentas_contables')->insertGetId([
            'id_comercio' => $comercio,
            'codigo' => $codigo,
            'activa' => true,
        ], 'id_cuenta_contable');
    }

    private function crearAsiento(
        int $comercio,
        string $tipo,
        string $codigoCuenta,
        float $debe,
        float $haber
    ): void {
        $idCuenta = DB::table('cuentas_contables')
            ->where('id_comercio', $comercio)
            ->where('codigo', $codigoCuenta)
            ->value('id_cuenta_contable');

        $idAsiento = DB::table('asientos_contables')->insertGetId([
            'id_comercio' => $comercio,
            'fecha' => '2026-11-08 12:00:00',
            'tipo' => $tipo,
            'estado' => 'REGISTRADO',
        ], 'id_asiento');

        DB::table('detalle_asientos')->insert([
            'id_asiento' => $idAsiento,
            'id_cuenta_contable' => $idCuenta,
            'debe' => $debe,
            'haber' => $haber,
        ]);
    }

    private function usuarioComercio(int $comercio): Usuario
    {
        return UsuarioFactory::new()->create([
            'rol' => 'encargado_comercio',
            'id_comercio' => $comercio,
        ]);
    }

    public function test_genera_reporte_con_ventas_y_costos_del_periodo(): void
    {
        $this->crearAsiento(1, 'VENTA', '4.1.01', 0, 1000);
        $this->crearAsiento(1, 'COSTO_VENTA', '5.1.01', 400, 0);

        $usuario = $this->usuarioComercio(1);

        $respuesta = $this->actingAs($usuario, 'web')
            ->getJson('/api/m7/v1/reporte-gerencial?fecha_desde=2026-11-01&fecha_hasta=2026-11-30');

        $respuesta->assertOk()
            ->assertJsonPath('success', true);

        $respuesta->assertJsonPath('data.resumen.ingresos', '1000.00')
            ->assertJsonPath('data.resumen.costos', '400.00')
            ->assertJsonPath('data.resumen.utilidad_bruta', '600.00')
            ->assertJsonPath('data.mensual.0.periodo', '2026-11');
    }

    public function test_el_reporte_no_incluye_datos_de_otros_comercios(): void
    {
        $this->crearAsiento(1, 'VENTA', '4.1.01', 0, 1000);
        $this->crearAsiento(2, 'VENTA', '4.1.01', 0, 9000);

        $usuario = $this->usuarioComercio(1);

        $respuesta = $this->actingAs($usuario, 'web')
            ->getJson('/api/m7/v1/reporte-gerencial?fecha_desde=2026-11-01&fecha_hasta=2026-11-30');

        $respuesta->assertOk()
            ->assertJsonPath('success', true);

        $respuesta->assertJsonPath('data.resumen.ingresos', '1000.00')
            ->assertJsonPath('data.resumen.costos', '0.00')
            ->assertJsonPath('data.resumen.utilidad_bruta', '1000.00');
    }
}
