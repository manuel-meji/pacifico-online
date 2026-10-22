<?php

namespace App\Models\M5_Pedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class PedidoMaestro extends Model
{
    protected $table = 'pedidos_maestros';

    protected $primaryKey = 'id_pedido';

    protected $fillable = [
        'id_cliente',
        'fecha_creacion',
        'monto_total',
        'estado_pedido',
        'cupon_descuento',
        'tarjeta_regalo',
    ];

    protected $casts = [
        'id_cliente' => 'integer',
        'fecha_creacion' => 'datetime',
        'monto_total' => 'decimal:2',
    ];

    // Aplicación del Patrón Composite: Raíz Composite
    public function calcularTotal(): float
    {
        $total = 0.0;
        foreach ($this->subpedidos as $subpedido) {
            $total += $subpedido->calcularSubtotal();
        }
        $this->monto_total = $total;
        $this->save();

        return (float) $this->monto_total;
    }

    public function calcularMontoTotal(): float
    {
        return $this->calcularTotal();
    }

    public function agregarSubpedido(Subpedido $subpedido): void
    {
        $subpedido->id_pedido = $this->id_pedido;
        $subpedido->save();
    }

    public function crearPedidoMaestro(
        float $montoTotal,
        int $idCliente = 1,
        ?string $cupon = null,
        ?string $tarjetaRegalo = null
    ): int {
        $this->id_cliente = $idCliente;
        $this->fecha_creacion = now();
        $this->monto_total = $montoTotal;
        $this->estado_pedido = 'confirmado';
        $this->cupon_descuento = $cupon;
        $this->tarjeta_regalo = $tarjetaRegalo;
        $this->save();

        return (int) $this->id_pedido;
    }

    public function actualizarEstado(string $nuevoEstado): void
    {
        $this->estado_pedido = $nuevoEstado;
        $this->save();
    }

    public function cancelar(string $motivo = ''): void
    {
        $this->estado_pedido = 'cancelado';
        $this->save();

        foreach ($this->subpedidos as $subpedido) {
            $subpedido->cancelar($motivo);
        }
    }

    public function subpedidos(): HasMany
    {
        return $this->hasMany(Subpedido::class, 'id_pedido');
    }

    public function lineas(): HasManyThrough
    {
        return $this->hasManyThrough(
            PedidoLinea::class,
            Subpedido::class,
            'id_pedido',
            'id_subpedido',
            'id_pedido',
            'id_subpedido'
        );
    }
}
