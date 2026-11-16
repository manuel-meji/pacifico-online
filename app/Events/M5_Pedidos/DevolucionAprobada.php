<?php

declare(strict_types=1);

namespace App\Events\M5_Pedidos;

use App\Models\M5_Pedidos\DevolucionSubpedido;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Evento canónico emitido cuando el comercio aprueba una devolución (RF-020 → RF-021).
 *
 * Es la única vía por la que M5 avisa a los demás módulos: M4 reingresa el inventario al
 * kardex y M2 revierte la comisión. El reembolso de M6 lo solicita M5 directamente
 * (necesita el resultado para informar al cliente), por lo que no depende de este evento.
 *
 * Carga útil primitiva (sin modelos) para que los consumidores no importen modelos de M5.
 */
class DevolucionAprobada
{
    use Dispatchable, SerializesModels;

    public string $id_evento;

    public string $fecha_evento;

    public int $id_devolucion;

    public int $id_pedido;

    public int $id_subpedido;

    public int $id_comercio;

    public string $tipo;

    public string $motivo;

    /** Monto que el cliente recupera (incluye envío e impuestos si la devolución es total). */
    public float $monto_reembolso;

    /** Suma de las líneas devueltas antes de impuestos (base para revertir la comisión). */
    public float $subtotal_devuelto;

    /** Subtotal del subpedido sobre el que M2 calculó la comisión. */
    public float $subtotal_subpedido;

    /** @var list<array{id_linea: int, id_variacion: int, id_reserva: string|null, cantidad: int, monto: float}> */
    public array $lineas;

    /**
     * @param  list<array{id_linea: int, id_variacion: int, id_reserva: string|null, cantidad: int, monto: float}>  $lineas
     */
    public function __construct(
        DevolucionSubpedido $devolucion,
        array $lineas,
        float $subtotalDevuelto,
        float $subtotalSubpedido,
    ) {
        $this->id_evento = (string) Str::uuid();
        $this->fecha_evento = now()->toRfc3339String();
        $this->id_devolucion = (int) $devolucion->id_devolucion;
        $this->id_pedido = (int) $devolucion->id_pedido;
        $this->id_subpedido = (int) $devolucion->id_subpedido;
        $this->id_comercio = (int) $devolucion->id_comercio;
        $this->tipo = (string) $devolucion->tipo;
        $this->motivo = (string) $devolucion->motivo;
        $this->monto_reembolso = round((float) $devolucion->monto_solicitado, 2);
        $this->subtotal_devuelto = round($subtotalDevuelto, 2);
        $this->subtotal_subpedido = round($subtotalSubpedido, 2);
        $this->lineas = $lineas;
    }
}
