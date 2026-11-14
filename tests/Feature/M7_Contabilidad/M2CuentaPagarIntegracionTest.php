<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M5_Pedidos\SubpedidosServiceInterface;
use App\Services\M2_Comercios\ComerciosService;
use App\Services\M7_Contabilidad\ComisionPendienteRepository;
use App\Services\M7_Contabilidad\ProcesarComisionPendienteService;
use App\Services\M7_Contabilidad\ProcesarCuentaPagarComercioService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

final class M2CuentaPagarIntegracionTest extends TestCase
{
    use PreparaM2;
    use PreparaIntegracionM6;

    private int $comercioId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTablasM7();

        $this->prepararIntegracionM6();

        DB::table('cuentas_plataforma_m7')->insert([
            'codigo' => 'P-COMISION',
            'nombre' => 'Ingresos por comisiones',
            'tipo' => 'INGRESO',
            'activa' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $comercio = $this->crearComercio([
            'nombre_comercial' => 'Comercio integración M2 M7',
        ]);

        $this->comercioId = (int) $comercio->id_comercio;

        $this->crearComision(
            $comercio,
            'porcentaje',
            8
        );

        foreach (['1.1.04', '5.2.01'] as $codigo) {
            DB::table('cuentas_contables')->insert([
                'id_comercio' => $this->comercioId,
                'codigo' => $codigo,
                'activa' => true,
            ]);
        }

        $this->mock(
            SubpedidosServiceInterface::class,
            function ($mock): void {
                $mock->shouldReceive('obtenerSubpedido')
                    ->with(7001)
                    ->andReturn([
                        'id_subpedido' => 7001,
                        'id_pedido' => 8942,
                        'id_comercio' => $this->comercioId,
                        'subtotal' => 24500.00,
                        'impuesto' => 3185.00,
                        'costo_envio' => 1500.00,
                        'estado_subpedido' => 'despachado',
                        'created_at' => null,
                        'updated_at' => null,
                        'lineas' => [],
                    ]);
            }
        );

        $this->prepararDistribucionM6();
    }

    public function test_m2_calcula_comision_y_m7_deja_cuenta_por_pagar_neta(): void
    {
        /*
         * Paso 1:
         * M2 procesa el despacho real y calcula 8 % sobre ₡24 500.
         *
         * Comisión esperada:
         * ₡24 500 × 8 % = ₡1 960.
         */
        app(ComerciosService::class)->registrarComisionDespacho(
            7001,
            '11111111-1111-4111-8111-111111111111'
        );

        $comisionM2 = app(ComerciosService::class)
            ->obtenerComisionSubpedido(7001);

        $this->assertSame(
            1960.0,
            $comisionM2['monto_comision']
        );

        $this->assertSame(
            24500.0,
            $comisionM2['base_utilizada']
        );

        /*
         * Paso 2:
         * M7 registra que debe consumir esa comisión.
         */
        $pendientes = app(
            ComisionPendienteRepository::class
        );

        $pendientes->registrar(
            eventoId: '11111111-1111-4111-8111-111111111111',
            pedidoId: 8942,
            subpedidoId: 7001,
            comercioId: $this->comercioId,
            fechaEvento: '2026-11-14 10:00:00',
        );

        /*
         * Paso 3:
         * M7 consulta M2 mediante su gateway real.
         */
        $resultadoComision = app(
            ProcesarComisionPendienteService::class
        )->intentar(7001);

        $this->assertTrue($resultadoComision);

        $this->assertDatabaseHas(
            'comisiones_contables_pendientes',
            [
                'id_subpedido' => 7001,
                'id_comercio' => $this->comercioId,
                'estado' => 'DISPONIBLE',
                'monto_comision' => 1960.00,
            ]
        );

        /*
         * Paso 4:
         * Se procesa la entrega del subpedido.
         */
        $resultado = app(
            ProcesarCuentaPagarComercioService::class
        )->procesar(
            eventoId: '22222222-2222-4222-8222-222222222222',
            pedidoId: 8942,
            subpedidoId: 7001,
            comercioId: $this->comercioId,
            fecha: '2026-11-14T11:00:00-06:00',
        );

        $this->assertTrue($resultado);

        /*
         * Total:
         *
         * subtotal      ₡24 500
         * IVA            ₡3 185
         * envío          ₡1 500
         * ----------------------
         * total         ₡29 185
         *
         * comisión       ₡1 960
         *
         * cuenta por pagar neta:
         * ₡29 185 - ₡1 960 = ₡27 225
         */
        $this->assertSame(
            2722500,
            $this->saldoPlataforma(
                'P-COMERCIOS'
            )
        );

        $this->assertSame(
            2722500,
            $this->saldoFondosComercio()
        );

        /*
         * El ingreso de la plataforma debe ser exactamente
         * la comisión calculada por M2.
         */
        $this->assertSame(
            196000,
            $this->saldoPlataforma(
                'P-COMISION'
            )
        );

        /*
         * La comisión queda cerrada.
         */
        $this->assertDatabaseHas(
            'comisiones_contables_pendientes',
            [
                'id_subpedido' => 7001,
                'estado' => 'PROCESADO',
                'monto_comision' => 1960.00,
            ]
        );

        /*
         * Existe un único asiento de comisión por comercio.
         */
        $this->assertSame(
            1,
            DB::table('asientos_contables')
                ->where('id_subpedido', 7001)
                ->where('tipo', 'COMISION')
                ->count()
        );

        /*
         * Y uno en plataforma.
         */
        $this->assertSame(
            1,
            DB::table('asientos_plataforma_m7')
                ->where('id_subpedido', 7001)
                ->where('tipo', 'COMISION')
                ->count()
        );
    }

    private function crearTablasM7(): void
    {
        Schema::create(
            'cuentas_contables',
            function (Blueprint $table): void {
                $table->id('id_cuenta_contable');
                $table->unsignedBigInteger('id_comercio');
                $table->string('codigo');
                $table->boolean('activa');
            }
        );

        Schema::create(
            'asientos_contables',
            function (Blueprint $table): void {
                $table->id('id_asiento');

                $table->unsignedBigInteger('id_comercio');
                $table->unsignedBigInteger('id_pedido')
                    ->nullable();

                $table->unsignedBigInteger('id_subpedido')
                    ->nullable();

                $table->unsignedBigInteger('id_pago')
                    ->nullable();

                $table->unsignedBigInteger('id_asiento_original')
                    ->nullable();

                $table->uuid('evento_id');
                $table->string('tipo_evento');
                $table->string('tipo');
                $table->timestamp('fecha');
                $table->string('estado');

                $table->timestamps();
            }
        );

        Schema::create(
            'detalle_asientos',
            function (Blueprint $table): void {
                $table->id('id_detalle_asiento');

                $table->unsignedBigInteger('id_asiento');

                $table->unsignedBigInteger(
                    'id_cuenta_contable'
                );

                $table->unsignedInteger('numero_linea');

                $table->decimal(
                    'debe',
                    15,
                    2
                );

                $table->decimal(
                    'haber',
                    15,
                    2
                );

                $table->timestamps();
            }
        );

        Schema::create(
            'comisiones_contables_pendientes',
            function (Blueprint $table): void {
                $table->id(
                    'id_comision_pendiente'
                );

                $table->uuid('evento_id')
                    ->unique();

                $table->unsignedBigInteger(
                    'id_pedido'
                );

                $table->unsignedBigInteger(
                    'id_subpedido'
                )->unique();

                $table->unsignedBigInteger(
                    'id_comercio'
                );

                $table->timestamp(
                    'fecha_evento'
                );

                $table->string(
                    'estado',
                    20
                )->default('PENDIENTE');

                $table->unsignedInteger(
                    'intentos'
                )->default(0);

                $table->decimal(
                    'monto_comision',
                    15,
                    2
                )->nullable();

                $table->text(
                    'ultimo_error'
                )->nullable();

                $table->timestamp(
                    'fecha_procesamiento'
                )->nullable();

                $table->timestamps();
            }
        );
    }

    private function prepararDistribucionM6(): void
    {
        /*
         * Distribución original del comercio:
         *
         * Debe 1.1.04 = ₡29 185.
         */
        $idAsiento = DB::table(
            'asientos_contables'
        )->insertGetId([
            'id_comercio' => $this->comercioId,
            'id_pedido' => 8942,
            'id_subpedido' => 7001,
            'id_pago' => 7,
            'id_asiento_original' => null,
            'evento_id' =>
                '33333333-3333-4333-8333-333333333333',
            'tipo_evento' => 'FacturaGenerada',
            'tipo' => 'DISTRIBUCION',
            'fecha' => '2026-11-14 09:00:00',
            'estado' => 'REGISTRADO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cuentaFondos = DB::table(
            'cuentas_contables'
        )
            ->where(
                'id_comercio',
                $this->comercioId
            )
            ->where(
                'codigo',
                '1.1.04'
            )
            ->value(
                'id_cuenta_contable'
            );

        DB::table(
            'detalle_asientos'
        )->insert([
            'id_asiento' => $idAsiento,
            'id_cuenta_contable' => $cuentaFondos,
            'numero_linea' => 1,
            'debe' => 29185.00,
            'haber' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(
            'registros_financieros_m6'
        )->insert([
            'referencia' => 'factura:31',
            'tipo' => 'FACTURA',
            'id_operacion' => 31,
            'id_pago' => 7,
            'id_pedido' => 8942,
            'id_subpedido' => 7001,
            'id_comercio' => $this->comercioId,
            'id_asiento' => $idAsiento,
            'subtotal_centimos' => 2450000,
            'impuesto_centimos' => 318500,
            'envio_centimos' => 150000,
            'huella' => str_repeat(
                'a',
                64
            ),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Plataforma antes de comisión:
         *
         * Haber P-COMERCIOS = ₡29 185.
         */
        $idPlataforma = DB::table(
            'asientos_plataforma_m7'
        )->insertGetId([
            'id_pago' => 7,
            'id_pedido' => 8942,
            'id_comercio' => $this->comercioId,
            'id_subpedido' => 7001,
            'evento_id' =>
                '33333333-3333-4333-8333-333333333333',
            'tipo_evento' => 'FacturaGenerada',
            'tipo' => 'DISTRIBUCION',
            'fecha' => '2026-11-14 09:00:00',
            'estado' => 'REGISTRADO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cuentaComercios = DB::table(
            'cuentas_plataforma_m7'
        )
            ->where(
                'codigo',
                'P-COMERCIOS'
            )
            ->value(
                'id_cuenta'
            );

        DB::table(
            'detalles_plataforma_m7'
        )->insert([
            'id_asiento' => $idPlataforma,
            'id_cuenta' => $cuentaComercios,
            'numero_linea' => 1,
            'debe' => 0,
            'haber' => 29185.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function saldoPlataforma(
        string $codigo
    ): int {
        $movimiento = DB::table(
            'detalles_plataforma_m7 as d'
        )
            ->join(
                'cuentas_plataforma_m7 as c',
                'c.id_cuenta',
                '=',
                'd.id_cuenta'
            )
            ->where(
                'c.codigo',
                $codigo
            )
            ->selectRaw(
                'COALESCE(SUM(haber - debe), 0) AS saldo'
            )
            ->value(
                'saldo'
            );

        return (int) round(
            (float) $movimiento * 100
        );
    }

    private function saldoFondosComercio(): int
    {
        $movimiento = DB::table(
            'detalle_asientos as d'
        )
            ->join(
                'cuentas_contables as c',
                'c.id_cuenta_contable',
                '=',
                'd.id_cuenta_contable'
            )
            ->where(
                'c.id_comercio',
                $this->comercioId
            )
            ->where(
                'c.codigo',
                '1.1.04'
            )
            ->selectRaw(
                'COALESCE(SUM(debe - haber), 0) AS saldo'
            )
            ->value(
                'saldo'
            );

        return (int) round(
            (float) $movimiento * 100
        );
    }
}