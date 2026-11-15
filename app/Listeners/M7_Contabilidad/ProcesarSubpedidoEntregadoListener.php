<?php

declare(strict_types=1);

namespace App\Listeners\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\ProcesadorCuentaPagarComercioInterface;
use App\Events\M5_Pedidos\SubpedidoEntregado;
use App\Services\M7_Contabilidad\CuentaPagarPendienteRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcesarSubpedidoEntregadoListener
{
    public function __construct(
        private readonly CuentaPagarPendienteRepository $pendientes,
        private readonly ProcesadorCuentaPagarComercioInterface $procesador,
    ) {}

    public function handle(SubpedidoEntregado $evento): void
    {
        $registrado = false;

        try {
            $this->pendientes->registrar(
                eventoId: $evento->id_evento,
                pedidoId: $evento->id_pedido,
                subpedidoId: $evento->id_subpedido,
                comercioId: $evento->id_comercio,
                fechaEvento: $evento->fecha_entrega,
            );

            $registrado = true;

            $procesado = $this->procesador->procesar(
                eventoId: $evento->id_evento,
                pedidoId: $evento->id_pedido,
                subpedidoId: $evento->id_subpedido,
                comercioId: $evento->id_comercio,
                fecha: $evento->fecha_entrega,
            );

            if ($procesado) {
                $this->pendientes->marcarProcesada(
                    $evento->id_subpedido
                );

                return;
            }

            $this->registrarIntentoSeguro(
                $evento->id_subpedido,
                'Dependencias contables todavía no disponibles.'
            );
        } catch (Throwable $error) {
            if ($registrado) {
                $this->registrarIntentoSeguro(
                    $evento->id_subpedido,
                    $error->getMessage()
                );
            }

            Log::error(
                'M7: no se pudo procesar la cuenta por pagar del subpedido entregado.',
                [
                    'id_evento' => $evento->id_evento ?? null,
                    'id_pedido' => $evento->id_pedido ?? null,
                    'id_subpedido' => $evento->id_subpedido ?? null,
                    'id_comercio' => $evento->id_comercio ?? null,
                    'exception' => $error,
                ]
            );
        }
    }

    private function registrarIntentoSeguro(
        int $subpedidoId,
        string $error
    ): void {
        try {
            $this->pendientes->registrarIntento(
                $subpedidoId,
                $error
            );
        } catch (Throwable $errorRegistro) {
            Log::error(
                'M7: no se pudo actualizar el intento de cuenta por pagar pendiente.',
                [
                    'id_subpedido' => $subpedidoId,
                    'exception' => $errorRegistro,
                ]
            );
        }
    }
}