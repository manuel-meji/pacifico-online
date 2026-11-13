<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Services\M7_Contabilidad\LibroDiarioService;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class LibroDiarioServiceTest extends TestCase
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
        string $codigo,
        string $nombre
    ): int {
        return (int) DB::table('cuentas_contables')->insertGetId([
            'id_comercio' => $comercio,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'tipo' => 'ACTIVO',
        ]);
    }

    private function crearAsiento(
        array $datos = [],
        array $lineas = []
    ): int {
        $idAsiento = (int) DB::table('asientos_contables')->insertGetId([
            'id_comercio' => $datos['id_comercio'] ?? 1,
            'id_pedido' => $datos['id_pedido'] ?? null,
            'id_subpedido' => $datos['id_subpedido'] ?? null,
            'id_pago' => $datos['id_pago'] ?? null,
            'evento_id' => (string) Str::uuid(),
            'tipo_evento' => $datos['tipo_evento'] ?? 'PEDIDO_CONFIRMADO',
            'tipo' => $datos['tipo'] ?? 'VENTA',
            'fecha' => $datos['fecha'] ?? '2026-11-08 12:00:00',
            'descripcion' => $datos['descripcion'] ?? 'Venta de prueba',
            'estado' => $datos['estado'] ?? 'REGISTRADO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($lineas as $linea) {
            DB::table('detalle_asientos')->insert([
                'id_asiento' => $idAsiento,
                'id_cuenta_contable' => $linea['cuenta'],
                'numero_linea' => $linea['numero'],
                'debe' => $linea['debe'],
                'haber' => $linea['haber'],
                'descripcion' => $linea['descripcion'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $idAsiento;
    }

    public function test_no_permite_consultar_sin_contexto_de_comercio_o_sistema(): void
    {
        $this->expectException(LogicException::class);

        app(LibroDiarioService::class)->listar();
    }

    public function test_consulta_solamente_los_asientos_del_comercio_activo(): void
    {
        $cuenta1 = $this->crearCuenta(1, '1.1.01', 'Caja comercio 1');
        $cuenta2 = $this->crearCuenta(2, '1.1.01', 'Caja comercio 2');

        $asiento1 = $this->crearAsiento(
            ['id_comercio' => 1],
            [[
                'cuenta' => $cuenta1,
                'numero' => 1,
                'debe' => 100,
                'haber' => 0,
            ]]
        );

        $this->crearAsiento(
            ['id_comercio' => 2],
            [[
                'cuenta' => $cuenta2,
                'numero' => 1,
                'debe' => 200,
                'haber' => 0,
            ]]
        );

        app(ContextoComercio::class)->establecer(1);

        $resultado = app(LibroDiarioService::class)->listar();

        $this->assertSame(1, $resultado['cantidad']);
        $this->assertSame($asiento1, $resultado['asientos'][0]['id']);
        $this->assertSame(100.0, $resultado['totalDebe']);
    }

    public function test_modo_sistema_consulta_asientos_de_todos_los_comercios(): void
    {
        $cuenta1 = $this->crearCuenta(1, '1.1.01', 'Caja comercio 1');
        $cuenta2 = $this->crearCuenta(2, '1.1.01', 'Caja comercio 2');

        $this->crearAsiento(
            ['id_comercio' => 1],
            [[
                'cuenta' => $cuenta1,
                'numero' => 1,
                'debe' => 100,
                'haber' => 0,
            ]]
        );

        $this->crearAsiento(
            ['id_comercio' => 2],
            [[
                'cuenta' => $cuenta2,
                'numero' => 1,
                'debe' => 200,
                'haber' => 0,
            ]]
        );

        $contexto = app(ContextoComercio::class);

        $resultado = $contexto->ejecutarComoSistema(
            fn () => app(LibroDiarioService::class)->listar()
        );

        $this->assertSame(2, $resultado['cantidad']);
        $this->assertSame(300.0, $resultado['totalDebe']);
    }

    public function test_aplica_filtros_de_fecha_tipo_y_estado(): void
    {
        $cuenta = $this->crearCuenta(1, '1.1.01', 'Caja');

        $asientoEsperado = $this->crearAsiento(
            [
                'tipo' => 'VENTA',
                'estado' => 'REGISTRADO',
                'fecha' => '2026-11-08 12:00:00',
            ],
            [[
                'cuenta' => $cuenta,
                'numero' => 1,
                'debe' => 150,
                'haber' => 0,
            ]]
        );

        $this->crearAsiento([
            'tipo' => 'COSTO_VENTA',
            'estado' => 'PENDIENTE',
            'fecha' => '2026-11-05 12:00:00',
        ]);

        app(ContextoComercio::class)->establecer(1);

        $resultado = app(LibroDiarioService::class)->listar([
            'fecha_desde' => '2026-11-08',
            'fecha_hasta' => '2026-11-08',
            'tipo' => 'VENTA',
            'estado' => 'REGISTRADO',
        ]);

        $this->assertSame(1, $resultado['cantidad']);
        $this->assertSame(
            $asientoEsperado,
            $resultado['asientos'][0]['id']
        );
    }

    public function test_busca_por_descripcion_cuenta_y_referencias_numericas(): void
    {
        $cuenta = $this->crearCuenta(1, '1.1.02', 'Caja principal');

        $asiento = $this->crearAsiento(
            [
                'id_pedido' => 123,
                'descripcion' => 'Venta especial',
            ],
            [[
                'cuenta' => $cuenta,
                'numero' => 1,
                'debe' => 100,
                'haber' => 0,
                'descripcion' => 'Ingreso de efectivo',
            ]]
        );

        app(ContextoComercio::class)->establecer(1);

        $servicio = app(LibroDiarioService::class);

        $porDescripcion = $servicio->listar([
            'buscar' => 'especial',
        ]);

        $porCuenta = $servicio->listar([
            'buscar' => 'Caja principal',
        ]);

        $porReferencia = $servicio->listar([
            'buscar' => '123',
        ]);

        $this->assertSame($asiento, $porDescripcion['asientos'][0]['id']);
        $this->assertSame($asiento, $porCuenta['asientos'][0]['id']);
        $this->assertSame($asiento, $porReferencia['asientos'][0]['id']);
    }

    public function test_agrupa_lineas_y_calcula_totales_del_libro_diario(): void
    {
        $cuentaCaja = $this->crearCuenta(1, '1.1.01', 'Caja');
        $cuentaVentas = $this->crearCuenta(1, '4.1.01', 'Ventas');

        $asiento = $this->crearAsiento(
            [
                'id_pedido' => 50,
                'id_subpedido' => 51,
                'descripcion' => 'Venta de productos',
            ],
            [
                [
                    'cuenta' => $cuentaCaja,
                    'numero' => 1,
                    'debe' => 250.50,
                    'haber' => 0,
                ],
                [
                    'cuenta' => $cuentaVentas,
                    'numero' => 2,
                    'debe' => 0,
                    'haber' => 250.50,
                ],
            ]
        );

        app(ContextoComercio::class)->establecer(1);

        $resultado = app(LibroDiarioService::class)->listar();

        $this->assertSame(1, $resultado['cantidad']);
        $this->assertSame($asiento, $resultado['asientos'][0]['id']);
        $this->assertCount(2, $resultado['asientos'][0]['lineas']);
        $this->assertSame('ASI-'.str_pad((string) $asiento, 4, '0', STR_PAD_LEFT), $resultado['asientos'][0]['numero']);
        $this->assertSame('Subpedido 51', $resultado['asientos'][0]['referencia']);
        $this->assertSame(250.5, $resultado['asientos'][0]['totalDebe']);
        $this->assertSame(250.5, $resultado['asientos'][0]['totalHaber']);
        $this->assertSame(250.5, $resultado['totalDebe']);
        $this->assertSame(250.5, $resultado['totalHaber']);
        $this->assertSame('Caja', $resultado['asientos'][0]['lineas'][0]['cuenta']);
        $this->assertSame('Ventas', $resultado['asientos'][0]['lineas'][1]['cuenta']);
    }

    public function test_muestra_asientos_sin_detalles(): void
    {
        $asiento = $this->crearAsiento([
            'tipo' => 'COSTO_VENTA',
            'descripcion' => 'Costo registrado',
        ]);

        app(ContextoComercio::class)->establecer(1);

        $resultado = app(LibroDiarioService::class)->listar();

        $this->assertSame(1, $resultado['cantidad']);
        $this->assertSame($asiento, $resultado['asientos'][0]['id']);
        $this->assertSame([], $resultado['asientos'][0]['lineas']);
        $this->assertSame(0.0, $resultado['totalDebe']);
        $this->assertSame(0.0, $resultado['totalHaber']);
    }
}
