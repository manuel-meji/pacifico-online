<?php

namespace App\Models\M5_Pedidos;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
                $pedido->subtotal = round((float) ($pedido->monto_total - $pedido->costo_envio - $pedido->impuesto + $pedido->descuento), 2);
            } elseif ($pedido->monto_total === null && $pedido->subtotal !== null) {
                $pedido->monto_total = round((float) ($pedido->subtotal + $pedido->costo_envio + $pedido->impuesto - $pedido->descuento), 2);
            } elseif ($pedido->subtotal === null && $pedido->monto_total === null) {
                $pedido->subtotal = 0.00;
                $pedido->monto_total = 0.00;
            } else {
                $pedido->monto_total = round((float) ($pedido->subtotal + $pedido->costo_envio + $pedido->impuesto - $pedido->descuento), 2);
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
                return (int) (auth()->user()?->id_usuario ?? auth()->id());
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

        $this->subtotal = round($subtotal, 2);
        $this->costo_envio = round($costoEnvio, 2);
        $this->impuesto = round($impuesto, 2);
        $this->descuento = round($descuento, 2);
        $this->monto_total = round((float) ($this->subtotal + $this->costo_envio + $this->impuesto - $this->descuento), 2);
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
        ?string $tarjetaRegalo = null,
        ?int $idDireccionCliente = null,
        ?float $subtotal = null,
        float $impuesto = 0.00,
        float $costoEnvio = 0.00,
        float $descuento = 0.00,
        string $estadoInicial = 'pendiente'
    ): int {
        $this->id_cliente = $idCliente;
        if ($idDireccionCliente !== null && Schema::hasColumn('pedidos_maestros', 'id_direccion_cliente')) {
            $this->id_direccion_cliente = $idDireccionCliente;
        }
        $this->fecha_creacion = now();
        $this->costo_envio = round($costoEnvio, 2);
        $this->impuesto = round($impuesto, 2);
        $this->descuento = round($descuento, 2);

        if ($subtotal !== null) {
            $this->subtotal = round($subtotal, 2);
            $this->monto_total = round($this->subtotal + $this->costo_envio + $this->impuesto - $this->descuento, 2);
        } else {
            $this->subtotal = round($montoTotal - $this->costo_envio - $this->impuesto + $this->descuento, 2);
            $this->monto_total = round($montoTotal, 2);
        }

        $this->estado_pedido = $estadoInicial;
        $this->cupon_descuento = $cupon;
        $this->tarjeta_regalo = $tarjetaRegalo;
        $this->save();

        $this->registrarHistorialEstado(null, $this->estado_pedido, 'Creación de pedido maestro');

        return (int) $this->id_pedido;
    }

    public function actualizarEstado(string $nuevoEstado, ?string $motivo = null, ?int $usuarioId = null): void
    {
        $estadoAnterior = $this->estado_pedido ? strtolower((string) $this->estado_pedido) : null;
        $destino = strtolower(trim($nuevoEstado));

        if ($estadoAnterior === $destino) {
            return;
        }

        $this->estado_pedido = $destino;
        $this->save();

        $this->registrarHistorialEstado($estadoAnterior, $destino, $motivo, $usuarioId);
    }

    public function cancelar(string $motivo = ''): void
    {
        $this->loadMissing('subpedidos');

        $noCancelables = ['en_preparacion', 'despachado', 'entregado'];
        foreach ($this->subpedidos as $sub) {
            if (in_array(strtolower((string) $sub->estado_subpedido), $noCancelables, true)) {
                throw new DomainException(
                    "No se puede cancelar el pedido porque el subpedido #{$sub->id_subpedido} ya está en estado '{$sub->estado_subpedido}'.",
                    409
                );
            }
        }

        $estadoPrevio = strtolower((string) $this->estado_pedido);
        $requiereReembolso = ($estadoPrevio === 'pagado');
        $motivoFinal = $motivo !== '' ? $motivo : 'Cancelación total solicitada';

        $this->actualizarEstado('cancelado', $motivoFinal);

        if (Schema::hasTable('cancelaciones_pedido')) {
            CancelacionPedido::create([
                'id_pedido' => $this->id_pedido,
                'id_subpedido' => null,
                'motivo' => $motivoFinal,
                'requiere_reembolso' => $requiereReembolso,
                'fecha_cancelacion' => now(),
            ]);
        }

        foreach ($this->subpedidos as $subpedido) {
            if (strtolower((string) $subpedido->estado_subpedido) !== 'cancelado') {
                $subpedido->cancelar($motivoFinal, false);
            }
        }
    }

    public function registrarHistorialEstado(
        ?string $estadoAnterior,
        string $nuevoEstado,
        ?string $motivo = null,
        ?int $usuarioId = null
    ): void {
        if (! $this->id_pedido || ! Schema::hasTable('estados_pedido')) {
            return;
        }

        EstadoPedido::create([
            'id_pedido' => $this->id_pedido,
            'id_subpedido' => null,
            'id_evento' => (string) Str::uuid(),
            'estado_anterior' => $estadoAnterior,
            'estado' => $nuevoEstado,
            'usuario_id' => $usuarioId,
            'motivo' => $motivo,
            'fecha_estado' => now(),
        ]);
    }

    public function subpedidos(): HasMany
    {
        return $this->hasMany(Subpedido::class, 'id_pedido');
    }

    public function estados(): HasMany
    {
        return $this->hasMany(EstadoPedido::class, 'id_pedido');
    }

    public function cancelaciones(): HasMany
    {
        return $this->hasMany(CancelacionPedido::class, 'id_pedido');
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
