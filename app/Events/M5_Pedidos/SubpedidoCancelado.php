<?php

declare(strict_types=1);

namespace App\Events\M5_Pedidos;

use App\Models\M5_Pedidos\Subpedido;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Evento canónico emitido al cancelar parcialmente un subpedido individual (SC-02 / INC-13).
 * Conforme al esquema SubpedidoCanceladoEvento (contrato OpenAPI v1.1.0).
 * Consumido por M4 (liberación de reservas del comercio) y M6 (reembolso proporcional).
 */
class SubpedidoCancelado
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $id_evento;

    public string $fecha_evento;

    public int $id_subpedido;

    public int $id_pedido;

    public int $id_comercio;

    public float $monto_subpedido;

    public string $motivo;

    public bool $requiere_reembolso;

    /** @var list<string> */
    public array $reservas;

    public function __construct(Subpedido|int $subpedido, string $motivo = 'Cancelación parcial SC-02', bool $requiereReembolso = true)
    {
        $this->id_evento = (string) Str::uuid();
        $this->fecha_evento = now()->toRfc3339String();
        $this->motivo = $motivo;
        $this->requiere_reembolso = $requiereReembolso;

        if ($subpedido instanceof Subpedido) {
            $subpedido->loadMissing('lineas');
            $this->id_subpedido = (int) $subpedido->id_subpedido;
            $this->id_pedido = (int) $subpedido->id_pedido;
            $this->id_comercio = (int) $subpedido->id_comercio;
            $this->monto_subpedido = (float) ($subpedido->total ?? $subpedido->subtotal);

            $reservas = $subpedido->lineas->pluck('id_reserva')->filter()->values()->all();
            $this->reservas = ! empty($reservas) ? $reservas : [(string) Str::uuid()];
        } else {
            $this->id_subpedido = (int) $subpedido;
            $modelo = Subpedido::with('lineas')->find($this->id_subpedido);
            if ($modelo) {
                $this->id_pedido = (int) $modelo->id_pedido;
                $this->id_comercio = (int) $modelo->id_comercio;
                $this->monto_subpedido = (float) ($modelo->total ?? $modelo->subtotal);

                $reservas = $modelo->lineas->pluck('id_reserva')->filter()->values()->all();
                $this->reservas = ! empty($reservas) ? $reservas : [(string) Str::uuid()];
            } else {
                $this->id_pedido = 1;
                $this->id_comercio = 1;
                $this->monto_subpedido = 0.0;
                $this->reservas = [(string) Str::uuid()];
            }
        }
    }
}
