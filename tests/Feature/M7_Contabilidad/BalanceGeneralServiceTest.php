<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Services\M7_Contabilidad\BalanceGeneralService;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BalanceGeneralServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('cuentas_contables', function (Blueprint $table) {
            $table->id('id_cuenta_contable');
            $table->unsignedBigInteger('id_comercio');
            $table->string('codigo');
            $table->string('nombre');
            $table->string('tipo');
            $table->unsignedBigInteger('id_cuenta_padre')->nullable();
        });

        Schema::create('asientos_contables', function (Blueprint $table) {
            $table->id('id_asiento');
            $table->unsignedBigInteger('id_comercio');
            $table->string('estado');
            $table->dateTime('fecha');
        });

        Schema::create('detalle_asientos', function (Blueprint $table) {
            $table->id('id_detalle_asiento');
            $table->unsignedBigInteger('id_asiento');
            $table->unsignedBigInteger('id_cuenta_contable');
            $table->decimal('debe', 15, 2);
            $table->decimal('haber', 15, 2);
        });
    }

    private function cuenta(int $comercio, string $tipo): int
    {
        return (int) DB::table('cuentas_contables')->insertGetId([
            'id_comercio' => $comercio,
            'codigo' => $tipo . '-' . Str::random(6),
            'nombre' => $tipo,
            'tipo' => $tipo,
        ]);
    }

    private function movimiento(
        int $comercio,
        int $cuenta,
        string $fecha,
        float $debe,
        float $haber,
        string $estado = 'REGISTRADO'
    ): void {
        $asiento = DB::table('asientos_contables')->insertGetId([
            'id_comercio' => $comercio,
            'estado' => $estado,
            'fecha' => $fecha,
        ]);

        DB::table('detalle_asientos')->insert([
            'id_asiento' => $asiento,
            'id_cuenta_contable' => $cuenta,
            'debe' => $debe,
            'haber' => $haber,
        ]);
    }

    public function test_fecha_corte_aislamiento_y_saldos(): void
    {
        $activo = $this->cuenta(1, 'ACTIVO');
        $ingreso = $this->cuenta(1, 'INGRESO');
        $otroComercio = $this->cuenta(2, 'ACTIVO');

        $this->movimiento(1, $activo, '2025-12-15', 100, 0);
        $this->movimiento(1, $ingreso, '2025-12-15', 0, 100);

        $this->movimiento(1, $activo, '2026-06-15', 50, 0);
        $this->movimiento(1, $ingreso, '2026-06-15', 0, 50);

        $this->movimiento(1, $activo, '2026-12-15', 200, 0);
        $this->movimiento(1, $activo, '2026-05-15', 300, 0, 'ANULADO');

        $this->movimiento(2, $otroComercio, '2026-05-15', 999, 0);

        app(ContextoComercio::class)->establecer(1);

        $resultado = app(BalanceGeneralService::class)
            ->consultar(1, '2026-11-14');

        $this->assertSame(15000, $resultado['activos_centimos']);
        $this->assertSame(15000, $resultado['resultado_acumulado_centimos']);
        $this->assertSame(15000, $resultado['patrimonio_centimos']);
        $this->assertTrue($resultado['cuadra']);
        $this->assertCount(2, $resultado['cuentas']);
        $this->assertSame(1, $resultado['id_comercio']);
        $this->assertSame('2026-11-14', $resultado['fecha_corte']);
    }
}
