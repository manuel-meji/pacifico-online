<?php

namespace App\Events\M5_Pedidos;

use App\Models\M5_Pedidos\PedidoMaestro;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class PedidoConfirmado
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $id_evento;

    public string $fecha_evento;

    public int $id_pedido;

    public int $id_cliente;

    public int $id_comercio;

    public int $id_subpedido;

    /** @var list<string> */
    public array $reservas;

    public float $monto_total;

    /** @var list<array<string, mixed>> */
    public array $subpedidos;

    public function __construct(PedidoMaestro|int $pedido, ?float $montoTotal = null)
    {
        $this->id_evento = (string) Str::uuid();
        $this->fecha_evento = now()->toRfc3339String();

        if ($pedido instanceof PedidoMaestro) {
            $pedido->loadMissing('subpedidos.lineas');
            $this->id_pedido = (int) $pedido->id_pedido;
            $this->id_cliente = (int) $pedido->id_cliente;
            $this->monto_total = round((float) $pedido->monto_total, 2);

            $primerSub = $pedido->subpedidos->first();
            $this->id_comercio = $primerSub ? (int) $primerSub->id_comercio : 1;
            $this->id_subpedido = $primerSub ? (int) $primerSub->id_subpedido : 1;

            $reservasCollect = [];
            $this->subpedidos = $pedido->subpedidos->map(function ($sub) use (&$reservasCollect) {
                $subReservas = $sub->lineas->pluck('id_reserva')->filter()->values()->all();
                $reservasCollect = array_merge($reservasCollect, $subReservas);

                return [
                    'id_subpedido' => (int) $sub->id_subpedido,
                    'id_comercio' => (int) $sub->id_comercio,
                    'subtotal' => round((float) $sub->subtotal, 2),
                    'costo_envio' => round((float) ($sub->costo_envio ?? 0.0), 2),
                    'impuesto' => round((float) ($sub->impuesto ?? 0.0), 2),
                    'reservas' => $subReservas,
                ];
            })->values()->all();

            $this->reservas = array_values(array_unique($reservasCollect));
            if (empty($this->reservas)) {
                $this->reservas = [(string) Str::uuid()];
            }
        } else {
            $this->id_pedido = (int) $pedido;
            $this->monto_total = round((float) ($montoTotal ?? 0.0), 2);
            $modelo = PedidoMaestro::withoutGlobalScope('cliente')
                ->with(['subpedidos' => fn ($q) => $q->withoutGlobalScope('comercio')->with('lineas')])
                ->find($this->id_pedido);

            if ($modelo) {
                $this->id_cliente = (int) $modelo->id_cliente;
                if ($montoTotal === null) {
                    $this->monto_total = round((float) $modelo->monto_total, 2);
                }
                $primerSub = $modelo->subpedidos->first();
                $this->id_comercio = $primerSub ? (int) $primerSub->id_comercio : 1;
                $this->id_subpedido = $primerSub ? (int) $primerSub->id_subpedido : 1;

                $reservasCollect = [];
                $this->subpedidos = $modelo->subpedidos->map(function ($sub) use (&$reservasCollect) {
                    $subReservas = $sub->lineas->pluck('id_reserva')->filter()->values()->all();
                    $reservasCollect = array_merge($reservasCollect, $subReservas);

                    return [
                        'id_subpedido' => (int) $sub->id_subpedido,
                        'id_comercio' => (int) $sub->id_comercio,
                        'subtotal' => round((float) $sub->subtotal, 2),
                        'costo_envio' => round((float) ($sub->costo_envio ?? 0.0), 2),
                        'impuesto' => round((float) ($sub->impuesto ?? 0.0), 2),
                        'reservas' => $subReservas,
                    ];
                })->values()->all();

                $this->reservas = array_values(array_unique($reservasCollect));
                if (empty($this->reservas)) {
                    $this->reservas = [(string) Str::uuid()];
                }
            } else {
                $this->id_cliente = 1;
                $this->id_comercio = 1;
                $this->id_subpedido = 1;
                $this->reservas = [(string) Str::uuid()];
                $this->subpedidos = [];
            }
        }
    }
}
