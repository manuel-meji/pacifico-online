<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Events\M5_Pedidos\PedidoConfirmado;
use App\Listeners\M7_Contabilidad\ProcesarPedidoConfirmadoListener;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use ReflectionClass;
use Tests\TestCase;

class PedidoConfirmadoListenerTest extends TestCase
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
    }

    private function crearCuentas(int $comercioId): void
    {
        DB::table('cuentas_contables')->insert([
            [
                'id_comercio' => $comercioId,
                'codigo' => '1.1.02',
                'activa' => true,
            ],
            [
                'id_comercio' => $comercioId,
                'codigo' => '4.1.01',
                'activa' => true,
            ],
        ]);
    }

    public function test_pedido_confirmado_real_genera_asiento_de_venta(): void
    {
        $this->crearCuentas(1);

        $evento = $this->crearEventoPedidoConfirmado();

        app(ProcesarPedidoConfirmadoListener::class)
            ->handle($evento);

        $this->assertDatabaseCount('asientos_contables', 1);
        $this->assertDatabaseCount('detalle_asientos', 2);

        $this->assertDatabaseHas('asientos_contables', [
            'id_pedido' => 10,
            'id_subpedido' => 11,
            'id_comercio' => 1,
            'evento_id' => '11111111-1111-4111-8111-111111111111',
            'tipo_evento' => 'PedidoConfirmado',
            'tipo' => 'VENTA',
            'estado' => 'REGISTRADO',
        ]);

        $this->assertDatabaseHas('eventos_procesados', [
            'evento_id' => '11111111-1111-4111-8111-111111111111',
            'tipo_evento' => 'PedidoConfirmado',
            'estado' => 'PROCESADO',
        ]);

        $this->assertSame(
            100.25,
            (float) DB::table('detalle_asientos')->sum('debe')
        );

        $this->assertSame(
            100.25,
            (float) DB::table('detalle_asientos')->sum('haber')
        );
    }

    public function test_repetir_el_mismo_evento_no_duplica_el_asiento(): void
    {
        $this->crearCuentas(1);

        $evento = $this->crearEventoPedidoConfirmado(
            '22222222-2222-4222-8222-222222222222'
        );

        $listener = app(ProcesarPedidoConfirmadoListener::class);

        $listener->handle($evento);
        $listener->handle($evento);

        $this->assertDatabaseCount('asientos_contables', 1);
        $this->assertDatabaseCount('detalle_asientos', 2);
        $this->assertDatabaseCount('eventos_procesados', 1);
    }

    private function crearEventoPedidoConfirmado(
        string $eventoId = '11111111-1111-4111-8111-111111111111'
    ): PedidoConfirmado {
        $reflexion = new ReflectionClass(PedidoConfirmado::class);

        /** @var PedidoConfirmado $evento */
        $evento = $reflexion->newInstanceWithoutConstructor();

        $evento->id_evento = $eventoId;
        $evento->fecha_evento = '2026-11-10T12:00:00Z';
        $evento->id_pedido = 10;
        $evento->id_cliente = 20;
        $evento->id_comercio = 1;
        $evento->id_subpedido = 11;
        $evento->monto_total = 100.25;
        $evento->reservas = [];
        $evento->subpedidos = [
            [
                'id_subpedido' => 11,
                'id_comercio' => 1,
                'subtotal' => 100.25,
                'costo_envio' => 0.0,
                'impuesto' => 0.0,
                'reservas' => [],
            ],
        ];

        return $evento;
    }
}
