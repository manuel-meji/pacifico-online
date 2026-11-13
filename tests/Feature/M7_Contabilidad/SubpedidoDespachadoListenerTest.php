<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\CostosProductosVendidosGatewayInterface;
use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Listeners\M7_Contabilidad\ProcesarSubpedidoDespachadoListener;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use ReflectionClass;
use Tests\TestCase;

class SubpedidoDespachadoListenerTest extends TestCase
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
            $tabla->boolean('activa');
        });

        Schema::create('eventos_procesados', function (Blueprint $tabla): void {
            $tabla->id('id_evento_procesado');
            $tabla->uuid('evento_id')->unique();
            $tabla->string('tipo_evento');
            $tabla->string('estado');
            $tabla->timestamp('fecha_recepcion');
            $tabla->timestamp('fecha_procesamiento')->nullable();
            $tabla->text('ultimo_error')->nullable();
            $tabla->timestamps();
        });

        Schema::create('asientos_contables', function (Blueprint $tabla): void {
            $tabla->id('id_asiento');
            $tabla->unsignedBigInteger('id_comercio');
            $tabla->unsignedBigInteger('id_pedido')->nullable();
            $tabla->unsignedBigInteger('id_subpedido')->nullable();
            $tabla->uuid('evento_id');
            $tabla->string('tipo_evento');
            $tabla->string('tipo');
            $tabla->timestamp('fecha');
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
            $tabla->timestamps();
        });

        $this->crearCuentas(1);

        $this->app->instance(
            CostosProductosVendidosGatewayInterface::class,
            new class implements CostosProductosVendidosGatewayInterface {
            public function obtenerCostosProductosVendidos(
                int $subpedidoId
            ): array {
                return [
                    'id_subpedido' => $subpedidoId,
                    'id_comercio' => 1,
                    'variaciones' => [
                        [
                            'id_variacion' => 100,
                            'cantidad' => 2,
                            'costo_unitario' => 20.25,
                            'costo_total' => 40.50,
                        ],
                    ],
                    'costo_total' => 40.50,
                ];
            }
            }
        );
    }

    private function crearCuentas(int $comercioId): void
    {
        DB::table('cuentas_contables')->insert([
            [
                'id_comercio' => $comercioId,
                'codigo' => '5.1.01',
                'activa' => true,
            ],
            [
                'id_comercio' => $comercioId,
                'codigo' => '1.1.03',
                'activa' => true,
            ],
        ]);
    }

    private function crearEvento(
        string $eventoId = '33333333-3333-4333-8333-333333333333'
    ): SubpedidoDespachado {
        $reflexion = new ReflectionClass(SubpedidoDespachado::class);

        /** @var SubpedidoDespachado $evento */
        $evento = $reflexion->newInstanceWithoutConstructor();

        $evento->id_evento = $eventoId;
        $evento->fecha_evento = '2026-11-10T12:00:00Z';
        $evento->fecha = '2026-11-10T12:00:00Z';
        $evento->fecha_despacho = '2026-11-10T12:00:00Z';
        $evento->evento = 'SubpedidoDespachado';
        $evento->id_subpedido = 11;
        $evento->id_pedido = 10;
        $evento->id_comercio = 1;
        $evento->subtotal = 100.25;
        $evento->costo_envio = 0.0;
        $evento->estado_subpedido = 'despachado';
        $evento->guia_transporte = null;
        $evento->reservas = [];

        return $evento;
    }

    public function test_subpedido_despachado_genera_asiento_de_costo(): void
    {
        $evento = $this->crearEvento();

        app(ProcesarSubpedidoDespachadoListener::class)
            ->handle($evento);

        $this->assertDatabaseCount('asientos_contables', 1);
        $this->assertDatabaseCount('detalle_asientos', 2);

        $this->assertDatabaseHas('asientos_contables', [
            'id_pedido' => 10,
            'id_subpedido' => 11,
            'id_comercio' => 1,
            'evento_id' => '33333333-3333-4333-8333-333333333333',
            'tipo_evento' => 'SubpedidoDespachado',
            'tipo' => 'COSTO_VENTA',
            'estado' => 'REGISTRADO',
        ]);

        $this->assertDatabaseHas('eventos_procesados', [
            'evento_id' => '33333333-3333-4333-8333-333333333333',
            'tipo_evento' => 'SubpedidoDespachado',
            'estado' => 'PROCESADO',
        ]);

        $this->assertSame(
            40.50,
            (float) DB::table('detalle_asientos')->sum('debe')
        );

        $this->assertSame(
            40.50,
            (float) DB::table('detalle_asientos')->sum('haber')
        );
    }

    public function test_repetir_subpedido_despachado_no_duplica_el_asiento(): void
    {
        $evento = $this->crearEvento(
            '44444444-4444-4444-8444-444444444444'
        );

        $listener = app(ProcesarSubpedidoDespachadoListener::class);

        $listener->handle($evento);
        $listener->handle($evento);

        $this->assertDatabaseCount('asientos_contables', 1);
        $this->assertDatabaseCount('detalle_asientos', 2);
        $this->assertDatabaseCount('eventos_procesados', 1);
    }

    public function test_uuid_diferente_para_el_mismo_subpedido_no_duplica_el_asiento(): void
    {
        $primerEvento = $this->crearEvento(
            '55555555-5555-4555-8555-555555555555'
        );

        $segundoEvento = $this->crearEvento(
            '66666666-6666-4666-8666-666666666666'
        );

        $listener = app(ProcesarSubpedidoDespachadoListener::class);

        $listener->handle($primerEvento);
        $listener->handle($segundoEvento);

        // No debe generarse un segundo asiento de costo.
        $this->assertDatabaseCount('asientos_contables', 1);
        $this->assertDatabaseCount('detalle_asientos', 2);

        // El primer evento debe quedar procesado.
        $this->assertDatabaseHas('eventos_procesados', [
            'evento_id' => '55555555-5555-4555-8555-555555555555',
            'estado' => 'PROCESADO',
        ]);

        // El segundo evento también debe quedar procesado.
        $this->assertDatabaseHas('eventos_procesados', [
            'evento_id' => '66666666-6666-4666-8666-666666666666',
            'estado' => 'PROCESADO',
        ]);
    }

}
