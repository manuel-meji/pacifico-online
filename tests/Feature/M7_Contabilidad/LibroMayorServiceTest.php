<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Services\M7_Contabilidad\LibroMayorService;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class LibroMayorServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            DB::getDriverName() !== 'sqlite'
            || DB::connection()->getDatabaseName() !== ':memory:'
        ) {
            throw new LogicException(
                'Estas pruebas requieren SQLite en memoria.'
            );
        }

        Schema::create('cuentas_contables', function (Blueprint $tabla): void {
            $tabla->id('id_cuenta_contable');
            $tabla->unsignedBigInteger('id_comercio');
            $tabla->string('codigo');
            $tabla->string('nombre');
            $tabla->string('tipo')->nullable();
        });

        Schema::create('asientos_contables', function (Blueprint $tabla): void {
            $tabla->id('id_asiento');
            $tabla->unsignedBigInteger('id_comercio');
            $tabla->unsignedBigInteger('id_pedido')->nullable();
            $tabla->unsignedBigInteger('id_subpedido')->nullable();
            $tabla->unsignedBigInteger('id_pago')->nullable();
            $tabla->uuid('evento_id');
            $tabla->string('tipo_evento');
            $tabla->string('tipo');
            $tabla->timestamp('fecha');
            $tabla->text('descripcion')->nullable();
            $tabla->string('estado');
            $tabla->timestamps();
        });

        Schema::create('detalle_asientos', function (Blueprint $tabla): void {
            $tabla->id('id_detalle_asiento');
            $tabla->unsignedBigInteger('id_asiento');
            $tabla->unsignedBigInteger('id_cuenta_contable');
            $tabla->unsignedInteger('numero_linea');
            $tabla->decimal('debe', 15, 2);
            $tabla->decimal('haber', 15, 2);
            $tabla->text('descripcion')->nullable();
            $tabla->timestamps();
        });
    }

    private function crearCuenta(
        int $comercio,
        string $codigo = '1.1.01',
        string $nombre = 'Caja',
        string $tipo = 'ACTIVO'
    ): int {
        return (int) DB::table('cuentas_contables')->insertGetId([
            'id_comercio' => $comercio,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'tipo' => $tipo,
        ]);
    }

    private function crearAsiento(
        array $datos = [],
        array $lineas = []
    ): int {
        $idAsiento = (int) DB::table('asientos_contables')->insertGetId([
            'id_comercio' => $datos['id_comercio'] ?? 1,
            'id_pedido' => null,
            'id_subpedido' => null,
            'id_pago' => null,
            'evento_id' => (string) Str::uuid(),
            'tipo_evento' => 'PEDIDO_CONFIRMADO',
            'tipo' => $datos['tipo'] ?? 'VENTA',
            'fecha' => $datos['fecha'] ?? '2026-11-08 12:00:00',
            'descripcion' => $datos['descripcion'] ?? 'Asiento de prueba',
            'estado' => $datos['estado'] ?? 'REGISTRADO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($lineas as $linea) {
            DB::table('detalle_asientos')->insert([
                'id_asiento' => $idAsiento,
                'id_cuenta_contable' => $linea['cuenta'],
                'numero_linea' => $linea['numero'] ?? 1,
                'debe' => $linea['debe'] ?? '0.00',
                'haber' => $linea['haber'] ?? '0.00',
                'descripcion' => $linea['descripcion'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $idAsiento;
    }

    public function test_no_permite_consultar_sin_contexto_de_comercio_o_sistema(): void
    {
        $cuenta = $this->crearCuenta(1);

        $this->expectException(LogicException::class);

        app(LibroMayorService::class)->consultar([
            'id_cuenta_contable' => $cuenta,
        ]);
    }

    public function test_calcula_saldo_inicial_y_saldo_acumulado(): void
    {
        $cuenta = $this->crearCuenta(1);

        $this->crearAsiento(
            ['fecha' => '2026-11-01 10:00:00'],
            [[
                'cuenta' => $cuenta,
                'debe' => '100.00',
            ]]
        );

        $this->crearAsiento(
            ['fecha' => '2026-11-08 10:00:00'],
            [[
                'cuenta' => $cuenta,
                'debe' => '50.00',
            ]]
        );

        $this->crearAsiento(
            ['fecha' => '2026-11-09 10:00:00'],
            [[
                'cuenta' => $cuenta,
                'haber' => '20.00',
            ]]
        );

        app(ContextoComercio::class)->establecer(1);

        $resultado = app(LibroMayorService::class)->consultar([
            'id_cuenta_contable' => $cuenta,
            'fecha_desde' => '2026-11-08',
            'fecha_hasta' => '2026-11-09',
        ]);

        $this->assertSame('100.00', $resultado['saldo_inicial']);
        $this->assertSame('50.00', $resultado['total_debe']);
        $this->assertSame('20.00', $resultado['total_haber']);
        $this->assertSame('130.00', $resultado['saldo_final']);

        $this->assertCount(2, $resultado['movimientos']);
        $this->assertSame(
            '150.00',
            $resultado['movimientos'][0]['saldo']
        );
        $this->assertSame(
            '130.00',
            $resultado['movimientos'][1]['saldo']
        );
    }

    public function test_excluye_pendientes_y_anulados_por_defecto(): void
    {
        $cuenta = $this->crearCuenta(1);

        $this->crearAsiento(
            ['estado' => 'REGISTRADO'],
            [[
                'cuenta' => $cuenta,
                'debe' => '10.00',
            ]]
        );

        $this->crearAsiento(
            ['estado' => 'PENDIENTE'],
            [[
                'cuenta' => $cuenta,
                'debe' => '500.00',
            ]]
        );

        $this->crearAsiento(
            ['estado' => 'ANULADO'],
            [[
                'cuenta' => $cuenta,
                'debe' => '40.00',
            ]]
        );

        app(ContextoComercio::class)->establecer(1);

        $resultado = app(LibroMayorService::class)->consultar([
            'id_cuenta_contable' => $cuenta,
        ]);

        $this->assertCount(1, $resultado['movimientos']);
        $this->assertSame('10.00', $resultado['total_debe']);
        $this->assertSame('10.00', $resultado['saldo_final']);
    }

    public function test_incluye_anulados_cuando_se_activa_el_filtro(): void
    {
        $cuenta = $this->crearCuenta(1);

        $this->crearAsiento(
            ['estado' => 'REGISTRADO'],
            [[
                'cuenta' => $cuenta,
                'debe' => '10.00',
            ]]
        );

        $this->crearAsiento(
            ['estado' => 'ANULADO'],
            [[
                'cuenta' => $cuenta,
                'debe' => '40.00',
            ]]
        );

        $this->crearAsiento(
            ['estado' => 'PENDIENTE'],
            [[
                'cuenta' => $cuenta,
                'debe' => '500.00',
            ]]
        );

        app(ContextoComercio::class)->establecer(1);

        $resultado = app(LibroMayorService::class)->consultar([
            'id_cuenta_contable' => $cuenta,
            'incluirAnulados' => true,
        ]);

        $this->assertCount(2, $resultado['movimientos']);
        $this->assertSame('50.00', $resultado['total_debe']);
        $this->assertSame('50.00', $resultado['saldo_final']);
    }

    public function test_no_permite_consultar_cuenta_de_otro_comercio(): void
    {
        $cuentaComercioDos = $this->crearCuenta(
            2,
            '1.1.01',
            'Caja comercio 2'
        );

        app(ContextoComercio::class)->establecer(1);

        $this->expectException(ModelNotFoundException::class);

        app(LibroMayorService::class)->consultar([
            'id_cuenta_contable' => $cuentaComercioDos,
        ]);
    }

    public function test_calcula_correctamente_el_saldo_de_una_cuenta_acreedora(): void
    {
        $cuenta = $this->crearCuenta(
            1,
            '2.1.01',
            'Proveedores',
            'PASIVO'
        );

        $this->crearAsiento(
            ['fecha' => '2026-11-01 10:00:00'],
            [[
                'cuenta' => $cuenta,
                'haber' => '100.00',
            ]]
        );

        $this->crearAsiento(
            ['fecha' => '2026-11-08 10:00:00'],
            [[
                'cuenta' => $cuenta,
                'debe' => '20.00',
            ]]
        );

        app(ContextoComercio::class)->establecer(1);

        $resultado = app(LibroMayorService::class)->consultar([
            'id_cuenta_contable' => $cuenta,
            'fecha_desde' => '2026-11-08',
        ]);

        $this->assertSame('100.00', $resultado['saldo_inicial']);
        $this->assertSame('80.00', $resultado['saldo_final']);
        $this->assertSame('ACREEDORA', $resultado['cuenta']['naturaleza']);
    }
}
