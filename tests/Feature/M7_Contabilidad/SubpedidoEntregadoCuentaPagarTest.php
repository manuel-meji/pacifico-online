<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\ProcesadorCuentaPagarComercioInterface;
use App\Events\M5_Pedidos\SubpedidoEntregado;
use App\Models\M5_Pedidos\Subpedido;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

final class SubpedidoEntregadoCuentaPagarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create(
            'cuentas_pagar_comercio_pendientes',
            function (Blueprint $table): void {
                $table->id('id_cuenta_pagar_pendiente');

                $table->uuid('evento_id')->unique();
                $table->unsignedBigInteger('id_pedido');
                $table->unsignedBigInteger('id_subpedido')->unique();
                $table->unsignedBigInteger('id_comercio');

                $table->timestamp('fecha_evento');

                $table->string('estado', 20)
                    ->default('PENDIENTE');

                $table->unsignedInteger('intentos')
                    ->default(0);

                $table->text('ultimo_error')
                    ->nullable();

                $table->timestamp('fecha_procesamiento')
                    ->nullable();

                $table->timestamps();
            }
        );
    }

    public function test_subpedido_entregado_se_registra_y_procesa(): void
    {
        $procesador = Mockery::mock(
            ProcesadorCuentaPagarComercioInterface::class
        );

        $procesador
            ->shouldReceive('procesar')
            ->once()
            ->andReturn(true);

        $this->app->instance(
            ProcesadorCuentaPagarComercioInterface::class,
            $procesador
        );

        $evento = $this->evento();

        event($evento);

        $this->assertDatabaseHas(
            'cuentas_pagar_comercio_pendientes',
            [
                'evento_id' => $evento->id_evento,
                'id_pedido' => 8942,
                'id_subpedido' => 1001,
                'id_comercio' => 11,
                'estado' => 'PROCESADO',
            ]
        );
    }

    public function test_si_faltan_dependencias_permanece_pendiente(): void
    {
        $procesador = Mockery::mock(
            ProcesadorCuentaPagarComercioInterface::class
        );

        $procesador
            ->shouldReceive('procesar')
            ->once()
            ->andReturn(false);

        $this->app->instance(
            ProcesadorCuentaPagarComercioInterface::class,
            $procesador
        );

        event($this->evento());

        $registro = DB::table(
            'cuentas_pagar_comercio_pendientes'
        )->first();

        $this->assertNotNull($registro);

        $this->assertSame(
            'PENDIENTE',
            $registro->estado
        );

        $this->assertSame(
            1,
            (int) $registro->intentos
        );

        $this->assertNotNull(
            $registro->ultimo_error
        );
    }

    public function test_error_del_procesamiento_no_se_propaga_a_m5(): void
    {
        $procesador = Mockery::mock(
            ProcesadorCuentaPagarComercioInterface::class
        );

        $procesador
            ->shouldReceive('procesar')
            ->once()
            ->andThrow(
                new \LogicException(
                    'Falla contable de prueba.'
                )
            );

        $this->app->instance(
            ProcesadorCuentaPagarComercioInterface::class,
            $procesador
        );

        event($this->evento());

        $this->assertDatabaseHas(
            'cuentas_pagar_comercio_pendientes',
            [
                'id_subpedido' => 1001,
                'estado' => 'PENDIENTE',
                'intentos' => 1,
                'ultimo_error' => 'Falla contable de prueba.',
            ]
        );
    }

    public function test_repetir_mismo_evento_no_duplica_pendiente(): void
    {
        $procesador = Mockery::mock(
            ProcesadorCuentaPagarComercioInterface::class
        );

        $procesador
            ->shouldReceive('procesar')
            ->twice()
            ->andReturn(false);

        $this->app->instance(
            ProcesadorCuentaPagarComercioInterface::class,
            $procesador
        );

        $evento = $this->evento();

        event($evento);
        event($evento);

        $this->assertDatabaseCount(
            'cuentas_pagar_comercio_pendientes',
            1
        );

        $this->assertDatabaseHas(
            'cuentas_pagar_comercio_pendientes',
            [
                'evento_id' => $evento->id_evento,
                'id_subpedido' => 1001,
            ]
        );

        $registro = DB::table(
            'cuentas_pagar_comercio_pendientes'
        )
            ->where('id_subpedido', 1001)
            ->first();

        $this->assertNotNull($registro);

        $this->assertSame(
            2,
            (int) $registro->intentos
        );
    }

    public function test_comando_reintenta_y_marca_procesada(): void
    {
        DB::table(
            'cuentas_pagar_comercio_pendientes'
        )->insert([
            'evento_id' =>
                'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'id_pedido' => 8942,
            'id_subpedido' => 1001,
            'id_comercio' => 11,
            'fecha_evento' => '2026-11-14 10:00:00',
            'estado' => 'PENDIENTE',
            'intentos' => 0,
            'ultimo_error' => null,
            'fecha_procesamiento' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $procesador = Mockery::mock(
            ProcesadorCuentaPagarComercioInterface::class
        );

        $procesador
            ->shouldReceive('procesar')
            ->once()
            ->with(
                'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                8942,
                1001,
                11,
                '2026-11-14 10:00:00'
            )
            ->andReturn(true);

        $this->app->instance(
            ProcesadorCuentaPagarComercioInterface::class,
            $procesador
        );

        $this->artisan(
            'm7:reintentar-cuentas-pagar'
        )
            ->expectsOutputToContain(
                'Procesadas: 1'
            )
            ->assertExitCode(0);

        $this->assertDatabaseHas(
            'cuentas_pagar_comercio_pendientes',
            [
                'id_subpedido' => 1001,
                'estado' => 'PROCESADO',
            ]
        );

        $registro = DB::table(
            'cuentas_pagar_comercio_pendientes'
        )
            ->where('id_subpedido', 1001)
            ->first();

        $this->assertNotNull(
            $registro->fecha_procesamiento
        );
    }

    private function evento(): SubpedidoEntregado
    {
        $subpedido = new Subpedido;

        $subpedido->id_subpedido = 1001;
        $subpedido->id_pedido = 8942;
        $subpedido->id_comercio = 11;

        return new SubpedidoEntregado($subpedido);
    }
}