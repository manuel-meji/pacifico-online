<?php

namespace App\Events\M5_Pedidos;

use App\Models\M5_Pedidos\PedidoMaestro;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class PedidoPagado
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $id_evento;

    public string $timestamp;

    public string $tipo_evento = 'PedidoPagado';

    public string $version_contrato = '1.1.0';

    /**
     * @var array{
     *     pedido_id: int,
     *     cliente_id: int,
     *     monto_total: float,
     *     estado: string,
     *     subpedidos: list<array{
     *         subpedido_id: int,
     *         comercio_id: int,
     *         subtotal: float,
     *         iva: float,
     *         envio: float,
     *         lineas: list<array<string, mixed>>
     *     }>
     * }
     */
    public array $datos;

    /**
     * @param  array<string, mixed>|null  $datosExtra
     */
    public function __construct(PedidoMaestro|int $pedido, ?array $datosExtra = null)
    {
        $this->id_evento = (string) Str::uuid();
        $this->timestamp = now()->toRfc3339String();

        if ($pedido instanceof PedidoMaestro) {
            $pedido->loadMissing('subpedidos.lineas');
            $this->datos = [
                'pedido_id' => (int) $pedido->id_pedido,
                'cliente_id' => (int) $pedido->id_cliente,
                'monto_total' => (float) $pedido->monto_total,
                'estado' => strtoupper((string) ($pedido->estado_pedido ?? 'PAGADO')),
                'subpedidos' => $pedido->subpedidos->map(fn ($sub) => [
                    'subpedido_id' => (int) $sub->id_subpedido,
                    'comercio_id' => (int) $sub->id_comercio,
                    'subtotal' => (float) $sub->subtotal,
                    'iva' => (float) ($sub->impuesto ?? 0.0),
                    'envio' => (float) ($sub->costo_envio ?? 0.0),
                    'lineas' => $sub->lineas->map(fn ($l) => [
                        'id_linea' => (int) $l->id_linea,
                        'id_subpedido' => (int) $l->id_subpedido,
                        'id_variacion' => (int) $l->id_variacion,
                        'cantidad' => (int) $l->cantidad,
                        'precio_unitario' => (float) $l->precio_unitario,
                        'tarifa_impuesto' => (float) $l->tarifa_impuesto,
                        'id_reserva' => $l->id_reserva ? (string) $l->id_reserva : null,
                    ])->values()->all(),
                ])->values()->all(),
            ];
        } else {
            $pedidoId = (int) $pedido;
            $modelo = PedidoMaestro::with('subpedidos.lineas')->find($pedidoId);
            if ($modelo) {
                $this->datos = [
                    'pedido_id' => (int) $modelo->id_pedido,
                    'cliente_id' => (int) $modelo->id_cliente,
                    'monto_total' => (float) $modelo->monto_total,
                    'estado' => strtoupper((string) ($modelo->estado_pedido ?? 'PAGADO')),
                    'subpedidos' => $modelo->subpedidos->map(fn ($sub) => [
                        'subpedido_id' => (int) $sub->id_subpedido,
                        'comercio_id' => (int) $sub->id_comercio,
                        'subtotal' => (float) $sub->subtotal,
                        'iva' => (float) ($sub->impuesto ?? 0.0),
                        'envio' => (float) ($sub->costo_envio ?? 0.0),
                        'lineas' => $sub->lineas->map(fn ($l) => [
                            'id_linea' => (int) $l->id_linea,
                            'id_subpedido' => (int) $l->id_subpedido,
                            'id_variacion' => (int) $l->id_variacion,
                            'cantidad' => (int) $l->cantidad,
                            'precio_unitario' => (float) $l->precio_unitario,
                            'tarifa_impuesto' => (float) $l->tarifa_impuesto,
                            'id_reserva' => $l->id_reserva ? (string) $l->id_reserva : null,
                        ])->values()->all(),
                    ])->values()->all(),
                ];
            } else {
                $this->datos = array_merge([
                    'pedido_id' => $pedidoId,
                    'cliente_id' => (int) ($datosExtra['cliente_id'] ?? 1),
                    'monto_total' => (float) ($datosExtra['monto_total'] ?? 0.0),
                    'estado' => 'PAGADO',
                    'subpedidos' => $datosExtra['subpedidos'] ?? [],
                ], $datosExtra ?? []);
            }
        }
    }
}
