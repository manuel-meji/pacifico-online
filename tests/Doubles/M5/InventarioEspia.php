<?php

namespace Tests\Doubles\M5;

use App\Services\M5_Pedidos\Dobles\InventarioStub;

/**
 * Doble de prueba de M4 que registra qué reservas se liberan y se confirman,
 * para verificar que M5 invoca a M4 (RN-M5-10).
 */
class InventarioEspia extends InventarioStub
{
    /** @var list<string> */
    public array $liberadas = [];

    /** @var list<array{id_reserva: string, id_pedido: int, id_subpedido: int}> */
    public array $confirmadas = [];

    public function liberarReserva(string $idReserva, ?string $motivo = null): array
    {
        $this->liberadas[] = $idReserva;

        return parent::liberarReserva($idReserva, $motivo);
    }

    public function confirmarReserva(string $idReserva, int $idPedido, int $idSubpedido): array
    {
        $this->confirmadas[] = [
            'id_reserva' => $idReserva,
            'id_pedido' => $idPedido,
            'id_subpedido' => $idSubpedido,
        ];

        return parent::confirmarReserva($idReserva, $idPedido, $idSubpedido);
    }
}
