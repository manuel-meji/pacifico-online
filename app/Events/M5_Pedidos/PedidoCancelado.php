<?php

declare(strict_types=1);

namespace App\Events\M5_Pedidos;

use App\Models\M5_Pedidos\PedidoMaestro;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Evento canónico emitido al cancelar la totalidad de un pedido maestro.
 * Conforme al esquema PedidoCanceladoEvento (contrato OpenAPI v1.1.0).
 * Consumido por M4 (liberación de reservas) y M6 (reembolsos).
 */
class PedidoCancelado
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $id_evento;

    public string $fecha_evento;

    public int $id_pedido;

    public int $id_comercio;

    public int $id_subpedido;

    public string $motivo;

    public bool $requiere_reembolso;

    /** @var list<string> */
    public array $reservas;

    public function __construct(PedidoMaestro|int $pedido, string $motivo = 'Cancelación total', bool $requiereReembolso = true)
    {
        $this->id_evento = (string) Str::uuid();
        $this->fecha_evento = now()->toRfc3339String();
        $this->motivo = $motivo;
        $this->requiere_reembolso = $requiereReembolso;

        if ($pedido instanceof PedidoMaestro) {
            $pedido->loadMissing('subpedidos.lineas');
            $this->id_pedido = (int) $pedido->id_pedido;
            $primerSub = $pedido->subpedidos->first();
            $this->id_comercio = $primerSub ? (int) $primerSub->id_comercio : 1;
            $this->id_subpedido = $primerSub ? (int) $primerSub->id_subpedido : 1;

            $reservasCollect = [];
            foreach ($pedido->subpedidos as $sub) {
                $subReservas = $sub->lineas->pluck('id_reserva')->filter()->values()->all();
                $reservasCollect = array_merge($reservasCollect, $subReservas);
            }
            $this->reservas = ! empty($reservasCollect) ? array_values(array_unique($reservasCollect)) : [(string) Str::uuid()];
        } else {
            $this->id_pedido = (int) $pedido;
            $modelo = PedidoMaestro::with('subpedidos.lineas')->find($this->id_pedido);
            if ($modelo) {
                $primerSub = $modelo->subpedidos->first();
                $this->id_comercio = $primerSub ? (int) $primerSub->id_comercio : 1;
                $this->id_subpedido = $primerSub ? (int) $primerSub->id_subpedido : 1;

                $reservasCollect = [];
                foreach ($modelo->subpedidos as $sub) {
                    $subReservas = $sub->lineas->pluck('id_reserva')->filter()->values()->all();
                    $reservasCollect = array_merge($reservasCollect, $subReservas);
                }
                $this->reservas = ! empty($reservasCollect) ? array_values(array_unique($reservasCollect)) : [(string) Str::uuid()];
            } else {
                $this->id_comercio = 1;
                $this->id_subpedido = 1;
                $this->reservas = [(string) Str::uuid()];
            }
        }
    }
}

