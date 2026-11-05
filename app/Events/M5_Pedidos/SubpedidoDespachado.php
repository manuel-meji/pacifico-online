<?php

declare(strict_types=1);

namespace App\Events\M5_Pedidos;

use App\Models\M5_Pedidos\Subpedido;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Evento canónico emitido cuando un comercio despacha un subpedido.
 * Conforme al esquema SubpedidoDespachadoEvento (contrato OpenAPI v1.1.0).
 * Consumido por M1 (seguimiento), M2 (comisión), M4 (kardex) y M7 (costo de ventas).
 */
class SubpedidoDespachado
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $id_evento;

    public string $fecha_evento;

    public string $fecha;

    public string $fecha_despacho;

    public string $evento = 'SubpedidoDespachado';

    public int $id_subpedido;

    public int $id_pedido;

    public int $id_comercio;

    public float $subtotal;

    public float $costo_envio;

    public string $estado_subpedido;

    public ?string $guia_transporte;

    /** @var list<string> */
    public array $reservas;

    public function __construct(Subpedido|int $subpedido, ?string $guia = null)
    {
        $this->id_evento = (string) Str::uuid();
        $ahora = now()->toRfc3339String();
        $this->fecha_evento = $ahora;
        $this->fecha = $ahora;
        $this->fecha_despacho = $ahora;
        $this->estado_subpedido = 'despachado';

        if ($subpedido instanceof Subpedido) {
            $subpedido->loadMissing('lineas');
            $this->id_subpedido = (int) $subpedido->id_subpedido;
            $this->id_pedido = (int) $subpedido->id_pedido;
            $this->id_comercio = (int) $subpedido->id_comercio;
            $this->subtotal = (float) $subpedido->subtotal;
            $this->costo_envio = (float) ($subpedido->costo_envio ?? 0.0);
            $this->guia_transporte = $guia ?? $subpedido->guia_seguimiento;

            $reservas = $subpedido->lineas->pluck('id_reserva')->filter()->values()->all();
            $this->reservas = ! empty($reservas) ? $reservas : [(string) Str::uuid()];
        } else {
            $this->id_subpedido = (int) $subpedido;
            $modelo = null;
            try {
                if (Schema::hasTable('pedidos_subpedidos')) {
                    $modelo = Subpedido::with('lineas')->find($this->id_subpedido);
                }
            } catch (\Throwable) {
                $modelo = null;
            }

            if ($modelo) {
                $this->id_pedido = (int) $modelo->id_pedido;
                $this->id_comercio = (int) $modelo->id_comercio;
                $this->subtotal = (float) $modelo->subtotal;
                $this->costo_envio = (float) ($modelo->costo_envio ?? 0.0);
                $this->guia_transporte = $guia ?? $modelo->guia_seguimiento;

                $reservas = $modelo->lineas->pluck('id_reserva')->filter()->values()->all();
                $this->reservas = ! empty($reservas) ? $reservas : [(string) Str::uuid()];
            } else {
                $this->id_pedido = 1;
                $this->id_comercio = 1;
                $this->subtotal = 0.0;
                $this->costo_envio = 0.0;
                $this->guia_transporte = $guia;
                $this->reservas = [(string) Str::uuid()];
            }
        }
    }
}
