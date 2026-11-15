<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\SubpedidosM7GatewayInterface;
use App\Services\M7_Contabilidad\ComisionPendienteRepository;
use App\Services\M7_Contabilidad\ProcesarCuentaPagarComercioService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

final class ProcesarCuentaPagarComercioServiceTest extends TestCase
{
    use PreparaIntegracionM6;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('cuentas_contables', function (Blueprint $table): void {
            $table->id('id_cuenta_contable');
            $table->unsignedBigInteger('id_comercio');
            $table->string('codigo');
            $table->boolean('activa');
        });

        Schema::create('asientos_contables', function (Blueprint $table): void {
            $table->id('id_asiento');
            $table->unsignedBigInteger('id_comercio');
            $table->unsignedBigInteger('id_pedido')->nullable();
            $table->unsignedBigInteger('id_subpedido')->nullable();
            $table->unsignedBigInteger('id_pago')->nullable();
            $table->unsignedBigInteger('id_asiento_original')->nullable();
            $table->uuid('evento_id');
            $table->string('tipo_evento');
            $table->string('tipo');
            $table->timestamp('fecha');
            $table->string('estado');
            $table->timestamps();
        });

        Schema::create('centros_costo', function (Blueprint $table): void {
            $table->id('id_centro_costo');
            $table->unsignedBigInteger('id_comercio');
            $table->string('codigo');
            $table->boolean('activo')->default(true);
        });

        Schema::create('detalle_asientos', function (Blueprint $table): void {
            $table->id('id_detalle_asiento');
            $table->unsignedBigInteger('id_asiento');
            $table->unsignedBigInteger('id_cuenta_contable');
            $table->unsignedBigInteger('id_centro_costo')->nullable();
            $table->unsignedInteger('numero_linea');
            $table->decimal('debe', 15, 2);
            $table->decimal('haber', 15, 2);
            $table->timestamps();
        });

        Schema::create('comisiones_contables_pendientes', function (Blueprint $table): void {
            $table->id('id_comision_pendiente');
            $table->uuid('evento_id')->unique();
            $table->unsignedBigInteger('id_pedido');
            $table->unsignedBigInteger('id_subpedido')->unique();
            $table->unsignedBigInteger('id_comercio');
            $table->timestamp('fecha_evento');
            $table->string('estado', 20)->default('PENDIENTE');
            $table->unsignedInteger('intentos')->default(0);
            $table->decimal('monto_comision', 15, 2)->nullable();
            $table->text('ultimo_error')->nullable();
            $table->timestamp('fecha_procesamiento')->nullable();
            $table->timestamps();
        });

        $this->prepararIntegracionM6();

        DB::table('cuentas_plataforma_m7')->insert([
            'codigo' => 'P-COMISION',
            'nombre' => 'Ingresos por comisiones',
            'tipo' => 'INGRESO',
            'activa' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['1.1.04', '5.2.01'] as $codigo) {
            DB::table('cuentas_contables')->insert([
                'id_comercio' => 11,
                'codigo' => $codigo,
                'activa' => true,
            ]);
        }

        $this->crearFacturaDistribuida();

        $gateway = Mockery::mock(SubpedidosM7GatewayInterface::class);

        $gateway->shouldReceive('obtenerSubpedido')
            ->with(1001)
            ->andReturn([
                'id_subpedido' => 1001,
                'id_pedido' => 8942,
                'id_comercio' => 11,
                'subtotal' => 30000.00,
                'impuesto' => 3900.00,
                'costo_envio' => 1500.00,
            ]);

        $this->app->instance(
            SubpedidosM7GatewayInterface::class,
            $gateway
        );
    }

    public function test_comision_disponible_genera_asientos_en_ambos_libros(): void
    {
        $this->registrarComision(1500.50);

        $resultado = app(
            ProcesarCuentaPagarComercioService::class
        )->procesar(
            eventoId: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            pedidoId: 8942,
            subpedidoId: 1001,
            comercioId: 11,
            fecha: '2026-11-14T10:00:00-06:00',
        );

        $this->assertTrue($resultado);

        $this->assertDatabaseHas('asientos_contables', [
            'id_comercio' => 11,
            'id_subpedido' => 1001,
            'tipo_evento' => 'SubpedidoEntregado',
            'tipo' => 'COMISION',
            'estado' => 'REGISTRADO',
        ]);

        $this->assertDatabaseHas('asientos_plataforma_m7', [
            'id_comercio' => 11,
            'id_subpedido' => 1001,
            'tipo_evento' => 'SubpedidoEntregado',
            'tipo' => 'COMISION',
            'estado' => 'REGISTRADO',
        ]);

        $this->assertDatabaseHas('comisiones_contables_pendientes', [
            'id_subpedido' => 1001,
            'estado' => 'PROCESADO',
        ]);
    }

    public function test_el_asiento_del_comercio_descuenta_la_comision_de_fondos_por_cobrar(): void
    {
        $this->registrarComision(1500.50);

        app(ProcesarCuentaPagarComercioService::class)->procesar(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            8942,
            1001,
            11,
            '2026-11-14T10:00:00-06:00',
        );

        $asiento = DB::table('asientos_contables')
            ->where('tipo', 'COMISION')
            ->where('id_subpedido', 1001)
            ->first();

        $this->assertNotNull($asiento);

        $lineas = $this->lineasComercio(
            (int) $asiento->id_asiento
        );

        $this->assertSame(
            ['1500.50', '0.00'],
            $lineas['5.2.01']
        );

        $this->assertSame(
            ['0.00', '1500.50'],
            $lineas['1.1.04']
        );
    }

    public function test_el_libro_plataforma_reduce_la_obligacion_y_reconoce_el_ingreso(): void
    {
        $this->registrarComision(1500.50);

        app(ProcesarCuentaPagarComercioService::class)->procesar(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            8942,
            1001,
            11,
            '2026-11-14T10:00:00-06:00',
        );

        $asiento = DB::table('asientos_plataforma_m7')
            ->where('tipo', 'COMISION')
            ->where('id_subpedido', 1001)
            ->first();

        $this->assertNotNull($asiento);

        $lineas = $this->lineasPlataforma(
            (int) $asiento->id_asiento
        );

        $this->assertSame(
            ['1500.50', '0.00'],
            $lineas['P-COMERCIOS']
        );

        $this->assertSame(
            ['0.00', '1500.50'],
            $lineas['P-COMISION']
        );
    }

    public function test_comision_pendiente_no_genera_asiento(): void
    {
        $repositorio = app(ComisionPendienteRepository::class);

        $repositorio->registrar(
            eventoId: '11111111-1111-4111-8111-111111111111',
            pedidoId: 8942,
            subpedidoId: 1001,
            comercioId: 11,
            fechaEvento: '2026-11-13 12:00:00',
        );

        $resultado = app(
            ProcesarCuentaPagarComercioService::class
        )->procesar(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            8942,
            1001,
            11,
            '2026-11-14T10:00:00-06:00',
        );

        $this->assertFalse($resultado);

        $this->assertDatabaseMissing('asientos_contables', [
            'tipo' => 'COMISION',
        ]);

        $this->assertDatabaseMissing('asientos_plataforma_m7', [
            'tipo' => 'COMISION',
        ]);
    }

    public function test_sin_factura_distribuida_no_genera_asiento(): void
    {
        DB::table('registros_financieros_m6')->delete();

        $this->registrarComision(1500.50);

        $resultado = app(
            ProcesarCuentaPagarComercioService::class
        )->procesar(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            8942,
            1001,
            11,
            '2026-11-14T10:00:00-06:00',
        );

        $this->assertFalse($resultado);

        $this->assertDatabaseMissing('asientos_contables', [
            'tipo' => 'COMISION',
        ]);
    }

    public function test_no_duplica_el_asiento_si_se_procesa_dos_veces(): void
    {
        $this->registrarComision(1500.50);

        $servicio = app(
            ProcesarCuentaPagarComercioService::class
        );

        $this->assertTrue(
            $servicio->procesar(
                'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                8942,
                1001,
                11,
                '2026-11-14T10:00:00-06:00',
            )
        );

        $this->assertTrue(
            $servicio->procesar(
                'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                8942,
                1001,
                11,
                '2026-11-14T10:00:00-06:00',
            )
        );

        $this->assertSame(
            1,
            DB::table('asientos_contables')
                ->where('tipo', 'COMISION')
                ->count()
        );

        $this->assertSame(
            1,
            DB::table('asientos_plataforma_m7')
                ->where('tipo', 'COMISION')
                ->count()
        );
    }

    public function test_rechaza_comision_mayor_al_total_del_subpedido(): void
    {
        $this->registrarComision(40000);

        $this->expectException(\LogicException::class);

        app(
            ProcesarCuentaPagarComercioService::class
        )->procesar(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            8942,
            1001,
            11,
            '2026-11-14T10:00:00-06:00',
        );
    }

    private function registrarComision(float $monto): void
    {
        $repositorio = app(
            ComisionPendienteRepository::class
        );

        $repositorio->registrar(
            eventoId: '11111111-1111-4111-8111-111111111111',
            pedidoId: 8942,
            subpedidoId: 1001,
            comercioId: 11,
            fechaEvento: '2026-11-13 12:00:00',
        );

        $repositorio->marcarDisponible(
            1001,
            $monto
        );
    }

    private function crearFacturaDistribuida(): void
    {
        $idAsiento = DB::table('asientos_contables')
            ->insertGetId([
                'id_comercio' => 11,
                'id_pedido' => 8942,
                'id_subpedido' => 1001,
                'id_pago' => 7,
                'evento_id' => '22222222-2222-4222-8222-222222222222',
                'tipo_evento' => 'FacturaGenerada',
                'tipo' => 'DISTRIBUCION',
                'fecha' => '2026-11-13 12:00:00',
                'estado' => 'REGISTRADO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('registros_financieros_m6')->insert([
            'referencia' => 'factura:31',
            'tipo' => 'FACTURA',
            'id_operacion' => 31,
            'id_pago' => 7,
            'id_pedido' => 8942,
            'id_subpedido' => 1001,
            'id_comercio' => 11,
            'id_asiento' => $idAsiento,
            'subtotal_centimos' => 3000000,
            'impuesto_centimos' => 390000,
            'envio_centimos' => 150000,
            'huella' => str_repeat('a', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function lineasComercio(int $asientoId): array
    {
        $resultado = [];

        $lineas = DB::table('detalle_asientos as d')
            ->join(
                'cuentas_contables as c',
                'c.id_cuenta_contable',
                '=',
                'd.id_cuenta_contable'
            )
            ->where('d.id_asiento', $asientoId)
            ->orderBy('d.numero_linea')
            ->get([
                'c.codigo',
                'd.debe',
                'd.haber',
            ]);

        foreach ($lineas as $linea) {
            $resultado[$linea->codigo] = [
                number_format((float) $linea->debe, 2, '.', ''),
                number_format((float) $linea->haber, 2, '.', ''),
            ];
        }

        return $resultado;
    }

    private function lineasPlataforma(int $asientoId): array
    {
        $resultado = [];

        $lineas = DB::table('detalles_plataforma_m7 as d')
            ->join(
                'cuentas_plataforma_m7 as c',
                'c.id_cuenta',
                '=',
                'd.id_cuenta'
            )
            ->where('d.id_asiento', $asientoId)
            ->orderBy('d.numero_linea')
            ->get([
                'c.codigo',
                'd.debe',
                'd.haber',
            ]);

        foreach ($lineas as $linea) {
            $resultado[$linea->codigo] = [
                number_format((float) $linea->debe, 2, '.', ''),
                number_format((float) $linea->haber, 2, '.', ''),
            ];
        }

        return $resultado;
    }
}
