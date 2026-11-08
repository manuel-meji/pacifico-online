<?php

namespace Tests\Unit\M5_Pedidos\Concerns;

use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;

/**
 * Preparación común de las pruebas unitarias de M5 que necesitan las tablas del módulo.
 * Usa la base SQLite en memoria de phpunit.xml, nunca la base compartida de Supabase.
 */
trait PreparaBaseM5
{
    protected function migrarTablasM5(): void
    {
        $this->artisan('migrate', [
            '--path' => 'database/migrations/M5_Pedidos',
            '--realpath' => false,
        ]);
    }

    /**
     * Crea un Pedido Maestro con un único Subpedido, ambos en estado pendiente.
     *
     * @return array{0: PedidoMaestro, 1: Subpedido}
     */
    protected function crearPedidoConSubpedido(int $idCliente): array
    {
        $pedido = new PedidoMaestro;
        $pedido->crearPedidoMaestro(16950.0, $idCliente, null, null, 1, 15000.0, 1950.0, 0.0);

        $subpedido = (new Subpedido)->crearSubpedidos([
            ['id_comercio' => 1, 'subtotal' => 15000.0, 'costo_envio' => 0.0, 'impuesto' => 1950.0],
        ], $pedido->id_pedido)[0];

        return [$pedido, $subpedido];
    }
}
