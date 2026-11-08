<?php

namespace Tests\Feature\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\PedidosDependencia;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Events\M5_Pedidos\PedidoCancelado;
use App\Events\M5_Pedidos\PedidoConfirmado;
use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Events\M6_Pagos\PagoRechazado;
use App\Services\M4_Inventario\Dobles\PedidosStub;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

/**
 * Listeners de los eventos de M5 y M6 (CP-M4-11): aplican el cambio a las reservas,
 * son idempotentes por id_evento y nunca interrumpen al módulo que publica.
 */
class ListenersPedidoM4Test extends TestCase
{
    use EsquemaInventarioPrueba;

    protected function setUp(): void
    {
        parent::setUp();
        config(['integraciones.m4.nucleo.modo' => 'real']);
        $this->crearEsquemaNucleo();
        $this->crearEsquemaInventario();
        $this->app->instance(PedidosDependencia::class, (new PedidosStub)->registrar(9101, 9001, 1, 'despachado'));
        $this->existencia(['id_variacion' => 10, 'id_ubicacion' => 1, 'cantidad_fisica' => 5, 'costo_promedio' => 1000]);
    }

    private function reservar(): string
    {
        return app(InventarioModuleInterface::class)->reservarExistencias(1, 10, 1)['id_reserva'];
    }

    private function estado(string $id): string
    {
        return app(InventarioModuleInterface::class)->obtenerReserva($id)['estado_reserva'];
    }

    /**
     * Evento de M5 armado con la carga útil del catálogo 4.3, sin pasar por su
     * constructor (que lee las tablas de M5, ajenas a esta prueba).
     *
     * @param  class-string  $clase
     * @param  array<string, mixed>  $datos
     */
    private function evento(string $clase, array $datos): object
    {
        $evento = (new ReflectionClass($clase))->newInstanceWithoutConstructor();

        foreach ($datos as $campo => $valor) {
            $evento->{$campo} = $valor;
        }

        return $evento;
    }

    public function test_pedido_confirmado_y_despacho_se_aplican_una_sola_vez_aunque_se_reenvien(): void
    {
        $id = $this->reservar();
        $confirmado = $this->evento(PedidoConfirmado::class, [
            'id_evento' => (string) Str::uuid(), 'id_pedido' => 9001,
            'subpedidos' => [['id_subpedido' => 9101, 'id_comercio' => 1, 'reservas' => [$id, (string) Str::uuid()]]],
        ]);

        event($confirmado);
        event($confirmado);
        $this->assertSame('confirmada', $this->estado($id));

        $despachado = $this->evento(SubpedidoDespachado::class, [
            'id_evento' => (string) Str::uuid(), 'id_pedido' => 9001, 'id_subpedido' => 9101, 'id_comercio' => 1, 'reservas' => [$id],
        ]);
        event($despachado);
        event($despachado);

        $this->assertSame('consumida', $this->estado($id));
        $this->assertSame(1, DB::table('movimientos_inventario')->where('tipo', 'salida')->count());
        $this->assertSame(1, DB::table('movimientos_inventario')->where('tipo', 'confirmacion_reserva')->count());
        $this->assertSame(2, DB::table('eventos_procesados_inventario')->count());
    }

    public function test_pago_rechazado_y_pedido_cancelado_liberan_las_reservas_pendientes(): void
    {
        $pagada = $this->reservar();
        $cancelada = $this->reservar();
        app(InventarioModuleInterface::class)->confirmarReserva($pagada, 9001, 9101);

        event(new PagoRechazado((string) Str::uuid(), 9001, 77, 'Fondos insuficientes', now()->toIso8601String()));
        event($this->evento(PedidoCancelado::class, ['id_evento' => (string) Str::uuid(), 'reservas' => [$cancelada]]));

        $this->assertSame('liberada', $this->estado($pagada));
        $this->assertSame('liberada', $this->estado($cancelada));
        $this->assertSame(0, (int) DB::table('existencias')->value('cantidad_reservada'));
    }
}
