<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\CostosProductosVendidosGatewayInterface;
use App\Events\M5_Pedidos\PedidoConfirmado;
use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Listeners\M7_Contabilidad\ProcesarPedidoConfirmadoListener;
use App\Listeners\M7_Contabilidad\ProcesarSubpedidoDespachadoListener;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Mockery;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

class AislamientoListenersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new LogicException('Estas pruebas requieren SQLite en memoria.');
        }
    }

    public function test_pedido_confirmado_no_propaga_un_fallo_de_base_de_datos(): void
    {
        $this->assertFalse(Schema::hasTable('eventos_procesados'));
        Log::spy();
        $evento = (new ReflectionClass(PedidoConfirmado::class))->newInstanceWithoutConstructor();
        $evento->id_evento = '11111111-1111-4111-8111-111111111111';
        $evento->fecha_evento = '2026-11-10T12:00:00Z';
        $evento->id_pedido = 10;
        $evento->subpedidos = [['id_subpedido' => 11, 'id_comercio' => 1, 'subtotal' => 100.25]];

        app(ProcesarPedidoConfirmadoListener::class)->handle($evento);

        Log::shouldHaveReceived('error')->once()->with(
            'M7: no se pudo procesar el evento PedidoConfirmado.',
            Mockery::on(fn (array $contexto): bool => $contexto['id_evento'] === $evento->id_evento
                && $contexto['exception'] instanceof QueryException),
        );
    }

    public function test_subpedido_despachado_no_propaga_un_fallo_de_base_de_datos(): void
    {
        $this->assertFalse(Schema::hasTable('eventos_procesados'));
        Log::spy();
        $costos = $this->mock(CostosProductosVendidosGatewayInterface::class);
        $costos->shouldReceive('obtenerCostosProductosVendidos')->once()->with(11)->andReturn([
            'id_subpedido' => 11, 'id_comercio' => 1, 'costo_total' => 40.50, 'variaciones' => [],
        ]);
        $evento = $this->crearDespacho();

        app(ProcesarSubpedidoDespachadoListener::class)->handle($evento);

        Log::shouldHaveReceived('error')->once()->with(
            'M7: no se pudo procesar el evento SubpedidoDespachado.',
            Mockery::on(fn (array $contexto): bool => $contexto['id_evento'] === $evento->id_evento
                && $contexto['exception'] instanceof QueryException),
        );
    }

    public function test_subpedido_despachado_no_propaga_un_fallo_del_proveedor_de_costos(): void
    {
        Log::spy();
        $costos = $this->mock(CostosProductosVendidosGatewayInterface::class);
        $costos->shouldReceive('obtenerCostosProductosVendidos')->once()->with(11)->andThrow(new RuntimeException('Costo no disponible'));
        $evento = $this->crearDespacho();

        app(ProcesarSubpedidoDespachadoListener::class)->handle($evento);

        Log::shouldHaveReceived('error')->once()->with(
            'M7: no se pudo procesar el evento SubpedidoDespachado.',
            Mockery::on(fn (array $contexto): bool => $contexto['id_evento'] === $evento->id_evento
                && $contexto['exception'] instanceof RuntimeException
                && $contexto['exception']->getMessage() === 'Costo no disponible'),
        );
    }

    private function crearDespacho(): SubpedidoDespachado
    {
        $evento = (new ReflectionClass(SubpedidoDespachado::class))->newInstanceWithoutConstructor();
        $evento->id_evento = '22222222-2222-4222-8222-222222222222';
        $evento->fecha_evento = '2026-11-10T12:00:00Z';
        $evento->id_pedido = 10;
        $evento->id_subpedido = 11;
        $evento->id_comercio = 1;

        return $evento;
    }
}
