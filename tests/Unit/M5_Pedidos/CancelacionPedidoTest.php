<?php

namespace Tests\Unit\M5_Pedidos;

use App\Models\M5_Pedidos\CancelacionPedido;
use App\Services\M5_Pedidos\Dobles\InventarioStub;
use App\Services\M5_Pedidos\PedidosService;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Doubles\M5\PagosEspia;
use Tests\TestCase;
use Tests\Unit\M5_Pedidos\Concerns\PreparaBaseM5;

/**
 * CP-009 y CP-010: cancelación permitida y denegada (RN-M5-05, RN-M5-10, RF-014).
 */
class CancelacionPedidoTest extends TestCase
{
    use PreparaBaseM5;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrarTablasM5();
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public static function estadosCancelables(): array
    {
        // El valor indica si el subpedido ya estaba pagado (y por tanto requiere reembolso).
        return [
            'pendiente' => [false],
            'pagado' => [true],
        ];
    }

    #[DataProvider('estadosCancelables')]
    public function test_cp009_un_subpedido_pendiente_o_pagado_acepta_la_cancelacion(bool $estabaPagado): void
    {
        [$pedido, $subpedido] = $this->crearPedidoConSubpedido(61);
        if ($estabaPagado) {
            $subpedido->cambiarEstado('pagado', 'Pago M6');
        }
        $pagos = new PagosEspia;
        $servicio = new PedidosService(null, null, new InventarioStub, $pagos);

        $servicio->cancelarSubpedidoParcial($subpedido->id_subpedido, ['motivo' => 'Cliente desistió de la compra']);

        $this->assertSame('cancelado', $subpedido->fresh()->estado_subpedido);

        $cancelacion = CancelacionPedido::where('id_subpedido', $subpedido->id_subpedido)->first();
        $this->assertNotNull($cancelacion);
        $this->assertSame('Cliente desistió de la compra', $cancelacion->motivo);
        $this->assertSame($estabaPagado, $cancelacion->requiere_reembolso);

        // RN-M5-10: solo un subpedido ya pagado dispara la solicitud de reembolso a M6.
        $this->assertCount($estabaPagado ? 1 : 0, $pagos->reembolsos);
        if ($estabaPagado) {
            $this->assertEqualsWithDelta(16950.0, $pagos->reembolsos[0]['monto'], 0.001);
        }
    }

    public function test_cp010_un_subpedido_despachado_rechaza_la_cancelacion_y_conserva_su_estado(): void
    {
        [$pedido, $subpedido] = $this->crearPedidoConSubpedido(62);
        $subpedido->cambiarEstado('pagado', 'Pago M6');
        $subpedido->cambiarEstado('despachado', 'En camino');
        $pagos = new PagosEspia;
        $servicio = new PedidosService(null, null, new InventarioStub, $pagos);

        try {
            $servicio->cancelarSubpedidoParcial($subpedido->id_subpedido, ['motivo' => 'Intento tardío']);
            $this->fail('Se esperaba DomainException por cancelar un subpedido despachado.');
        } catch (DomainException $excepcion) {
            $this->assertSame(409, $excepcion->getCode());
        }

        try {
            $servicio->cancelarPedidoTotal($pedido->id_pedido, ['motivo' => 'Intento tardío']);
            $this->fail('Se esperaba DomainException por cancelar un pedido con un subpedido despachado.');
        } catch (DomainException $excepcion) {
            $this->assertSame(409, $excepcion->getCode());
        }

        $this->assertSame('despachado', $subpedido->fresh()->estado_subpedido);
        $this->assertSame('pendiente', $pedido->fresh()->estado_pedido);
        $this->assertDatabaseCount('cancelaciones_pedido', 0);
        $this->assertCount(0, $pagos->reembolsos);
    }
}
