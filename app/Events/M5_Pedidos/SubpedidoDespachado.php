<?php

declare(strict_types=1);

namespace App\Events\M5_Pedidos;

use App\Models\M5_Pedidos\Subpedido;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use InvalidArgumentException;

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

    public float $impuesto = 0.0;

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
            $this->hidratarDesdeModelo($subpedido, $guia);

            return;
        }

        $idSubpedido = (int) $subpedido;
        if ($idSubpedido <= 0) {
            throw new InvalidArgumentException('No se puede construir SubpedidoDespachado sin un id_subpedido válido.');
        }

        $modelo = Subpedido::withoutGlobalScope('comercio')->with('lineas')->find($idSubpedido);
        if (! $modelo) {
            throw new InvalidArgumentException("No existe un subpedido real con id_subpedido={$idSubpedido} para construir SubpedidoDespachado.");
        }

        $this->hidratarDesdeModelo($modelo, $guia);
    }

    private function hidratarDesdeModelo(Subpedido $subpedido, ?string $guia = null): void
    {
        if (! $subpedido->relationLoaded('lineas')) {
            $subpedido->loadMissing('lineas');
        }

        $idSubpedido = (int) $subpedido->id_subpedido;
        $idPedido = (int) $subpedido->id_pedido;
        $idComercio = (int) $subpedido->id_comercio;

        if ($idSubpedido <= 0 || $idPedido <= 0 || $idComercio <= 0) {
            throw new InvalidArgumentException('No se puede construir SubpedidoDespachado sin identificadores reales de subpedido, pedido y comercio.');
        }

        $this->id_subpedido = $idSubpedido;
        $this->id_pedido = $idPedido;
        $this->id_comercio = $idComercio;
        $this->subtotal = round((float) $subpedido->subtotal, 2);
        $this->impuesto = round((float) ($subpedido->impuesto ?? 0.0), 2);
        $this->costo_envio = round((float) ($subpedido->costo_envio ?? 0.0), 2);
        $this->guia_transporte = $guia ?? $subpedido->guia_seguimiento;
        $this->reservas = $subpedido->lineas->pluck('id_reserva')->filter()->values()->all();
    }
}
