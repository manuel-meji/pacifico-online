<?php

declare(strict_types=1);

namespace App\Listeners\M7_Contabilidad;

use App\Events\M5_Pedidos\PedidoConfirmado;
use App\Services\M7_Contabilidad\DTOs\VentaDTO;
use App\Services\M7_Contabilidad\ProcesarPedidoConfirmadoService;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcesarPedidoConfirmadoListener
{
    public function __construct(
        private readonly ProcesarPedidoConfirmadoService $procesador,
        private readonly ContextoComercio $contexto,
    ) {}

    public function handle(PedidoConfirmado $evento): void
    {
        try {
            $this->procesar($evento);
        } catch (Throwable $error) {
            Log::error('M7: no se pudo procesar el evento PedidoConfirmado.', [
                'id_evento' => $evento->id_evento ?? null,
                'exception' => $error,
            ]);
        }
    }

    private function procesar(PedidoConfirmado $evento): void
    {
        $ventas = [];

        foreach ($evento->subpedidos as $subpedido) {
            $ventas[] = new VentaDTO(
                eventoId: $evento->id_evento,
                fecha: $evento->fecha_evento,
                pedidoId: $evento->id_pedido,
                subpedidoId: (int) $subpedido['id_subpedido'],
                comercioId: (int) $subpedido['id_comercio'],
                subtotal: number_format(
                    (float) $subpedido['subtotal'],
                    2,
                    '.',
                    ''
                ),
            );
        }

        $this->contexto->ejecutarComoSistema(
            fn () => $this->procesador->procesar(
                $evento->id_evento,
                $ventas
            )
        );
    }
}
