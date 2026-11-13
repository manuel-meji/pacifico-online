<?php

namespace App\Events\M5_Pedidos;

use App\Models\M5_Pedidos\PedidoMaestro;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use InvalidArgumentException;

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
            $this->hidratarDesdeModelo($pedido);

            return;
        }

        $idPedido = (int) $pedido;
        if ($idPedido <= 0) {
            throw new InvalidArgumentException('No se puede construir PedidoConfirmado sin un id_pedido válido.');
        }

        $modelo = PedidoMaestro::withoutGlobalScope('cliente')
            ->with(['subpedidos' => fn ($q) => $q->withoutGlobalScope('comercio')->with('lineas')])
            ->find($idPedido);

        if (! $modelo) {
            throw new InvalidArgumentException("No existe un pedido maestro real con id_pedido={$idPedido} para construir PedidoConfirmado.");
        }

        $this->hidratarDesdeModelo($modelo);
    }

    private function hidratarDesdeModelo(PedidoMaestro $pedido): void
    {
        if (! $pedido->relationLoaded('subpedidos')) {
            $pedido->load(['subpedidos' => fn ($q) => $q->withoutGlobalScope('comercio')->with('lineas')]);
        } else {
            $pedido->subpedidos->each(function ($sub): void {
                if (! $sub->relationLoaded('lineas')) {
                    $sub->loadMissing('lineas');
                }
            });
        }

        $idPedido = (int) $pedido->id_pedido;
        $idCliente = (int) $pedido->id_cliente;

        if ($idPedido <= 0 || $idCliente <= 0) {
            throw new InvalidArgumentException('No se puede construir PedidoConfirmado sin un id_pedido e id_cliente válidos.');
        }

        if ($pedido->subpedidos->isEmpty()) {
            throw new InvalidArgumentException("No se puede construir PedidoConfirmado para el pedido #{$idPedido} sin subpedidos válidos.");
        }

        $this->id_pedido = $idPedido;
        $this->id_cliente = $idCliente;
        $this->monto_total = round((float) $pedido->monto_total, 2);

        $primerSub = $pedido->subpedidos->first();
        $this->id_comercio = (int) $primerSub->id_comercio;
        $this->id_subpedido = (int) $primerSub->id_subpedido;

        $reservasCollect = [];
        $this->subpedidos = $pedido->subpedidos->map(function ($sub) use (&$reservasCollect, $idPedido): array {
            $idSubpedido = (int) $sub->id_subpedido;
            $idComercio = (int) $sub->id_comercio;

            if ($idSubpedido <= 0 || $idComercio <= 0) {
                throw new InvalidArgumentException("El pedido #{$idPedido} contiene un subpedido con identificadores inválidos.");
            }

            $subReservas = $sub->lineas->pluck('id_reserva')->filter()->values()->all();
            $reservasCollect = array_merge($reservasCollect, $subReservas);

            return [
                'id_subpedido' => $idSubpedido,
                'id_comercio' => $idComercio,
                'subtotal' => round((float) $sub->subtotal, 2),
                'costo_envio' => round((float) ($sub->costo_envio ?? 0.0), 2),
                'impuesto' => round((float) ($sub->impuesto ?? 0.0), 2),
                'reservas' => $subReservas,
            ];
        })->values()->all();

        $this->reservas = array_values(array_unique($reservasCollect));
    }
}
