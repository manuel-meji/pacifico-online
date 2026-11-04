<?php

namespace App\Models\M5_Pedidos;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class PedidoMaestro extends Model
{
    protected $table = 'pedidos_maestros';

    protected $primaryKey = 'id_pedido';

    protected $fillable = [
        'id_cliente',
        'id_direccion_cliente',
        'fecha_creacion',
        'subtotal',
        'costo_envio',
        'impuesto',
        'descuento',
        'monto_total',
        'estado_pedido',
        'cupon_descuento',
        'tarjeta_regalo',
    ];

    protected $casts = [
        'id_cliente' => 'integer',
        'id_direccion_cliente' => 'integer',
        'fecha_creacion' => 'datetime',
        'subtotal' => 'decimal:2',
        'costo_envio' => 'decimal:2',
        'impuesto' => 'decimal:2',
        'descuento' => 'decimal:2',
        'monto_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Aislamiento multi-tenant por cliente (ADR-006)
        static::addGlobalScope('cliente', function (Builder $builder) {
            $idCliente = static::obtenerClienteActivoId();
            if ($idCliente !== null) {
                $builder->where($builder->getModel()->getTable().'.id_cliente', $idCliente);
            }
        });

        // Garantía de cuadratura contable y valores por defecto
        static::saving(function (PedidoMaestro $pedido) {
            $pedido->costo_envio = $pedido->costo_envio ?? 0.00;
            $pedido->impuesto = $pedido->impuesto ?? 0.00;
            $pedido->descuento = $pedido->descuento ?? 0.00;

            if ($pedido->subtotal === null && $pedido->monto_total !== null) {
                $pedido->subtotal = (float) ($pedido->monto_total - $pedido->costo_envio - $pedido->impuesto + $pedido->descuento);
            } elseif ($pedido->monto_total === null && $pedido->subtotal !== null) {
                $pedido->monto_total = (float) ($pedido->subtotal + $pedido->costo_envio + $pedido->impuesto - $pedido->descuento);
            } elseif ($pedido->subtotal === null && $pedido->monto_total === null) {
                $pedido->subtotal = 0.00;
                $pedido->monto_total = 0.00;
            } else {
                $pedido->monto_total = (float) ($pedido->subtotal + $pedido->costo_envio + $pedido->impuesto - $pedido->descuento);
            }
        });
    }

    /**
     * Resuelve el ID del cliente activo en la sesión, autenticación o encabezados.
     */
    public static function obtenerClienteActivoId(): ?int
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }

        try {
            if (session()->isStarted() && session()->has('id_cliente')) {
                return (int) session('id_cliente');
            }
        } catch (\Throwable) {
        }

        try {
            if (auth()->check() && ! isset(auth()->user()->id_comercio)) {
                return (int) auth()->id();
            }
        } catch (\Throwable) {
        }

        try {
            if (request()?->hasHeader('X-Cliente-Id')) {
                return (int) request()->header('X-Cliente-Id');
            }
        } catch (\Throwable) {
        }

        return null;
    }

    // Aplicación del Patrón Composite: Raíz Composite
    public function calcularTotal(): float
    {
        $subtotal = 0.0;
        $costoEnvio = 0.0;
        $impuesto = 0.0;
        $descuento = 0.0;

        foreach ($this->subpedidos as $subpedido) {
            $subpedido->calcularSubtotal();
            $subtotal += (float) $subpedido->subtotal;
            $costoEnvio += (float) $subpedido->costo_envio;
            $impuesto += (float) $subpedido->impuesto;
            $descuento += (float) ($subpedido->descuento ?? 0.0);
        }

        $this->subtotal = $subtotal;
        $this->costo_envio = $costoEnvio;
        $this->impuesto = $impuesto;
        $this->descuento = $descuento;
        $this->monto_total = (float) ($subtotal + $costoEnvio + $impuesto - $descuento);
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
        $this->costo_envio = 0.00;
        $this->impuesto = 0.00;
        $this->descuento = 0.00;
        $this->subtotal = $montoTotal;
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
