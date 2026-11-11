<?php

declare(strict_types=1);

namespace App\Events\M5_Pedidos;

use App\Models\M5_Pedidos\Subpedido;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Evento canónico emitido al confirmar la entrega de un subpedido al cliente.
 * Conforme al esquema SubpedidoEntregadoEvento (contrato OpenAPI v1.1.0).
 * Consumido por M1 (notificación/portal) y M7 (cierre contable).
 */
class SubpedidoEntregado
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $id_evento;

    public string $fecha;

    public string $fecha_entrega;

    public int $id_subpedido;

    public int $id_pedido;

    public int $id_comercio;

    public function __construct(Subpedido|int $subpedido)
    {
        $this->id_evento = (string) Str::uuid();
        $ahora = now()->toRfc3339String();
        $this->fecha = $ahora;
        $this->fecha_entrega = $ahora;

        if ($subpedido instanceof Subpedido) {
            $this->id_subpedido = (int) $subpedido->id_subpedido;
            $this->id_pedido = (int) $subpedido->id_pedido;
            $this->id_comercio = (int) $subpedido->id_comercio;
        } else {
            $this->id_subpedido = (int) $subpedido;
            $modelo = Subpedido::find($this->id_subpedido);
            if ($modelo) {
                $this->id_pedido = (int) $modelo->id_pedido;
                $this->id_comercio = (int) $modelo->id_comercio;
            } else {
                $this->id_pedido = 1;
                $this->id_comercio = 1;
            }
        }
    }
}
