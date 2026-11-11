<?php

namespace Tests\Doubles\M5;

use App\Services\M5_Pedidos\Dobles\PagosStub;

/**
 * Doble de prueba de M6 que registra las solicitudes de reembolso,
 * para verificar que M5 invoca a M6 al cancelar un pedido ya pagado (RN-M5-10).
 */
class PagosEspia extends PagosStub
{
    /** @var list<array{id_pedido: int, monto: float, motivo: string}> */
    public array $reembolsos = [];

    public function solicitarReembolso(int $pedidoId, float $monto, string $motivo = 'Cancelación de pedido'): array
    {
        $this->reembolsos[] = [
            'id_pedido' => $pedidoId,
            'monto' => $monto,
            'motivo' => $motivo,
        ];

        return parent::solicitarReembolso($pedidoId, $monto, $motivo);
    }
}
