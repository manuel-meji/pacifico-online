<?php

namespace App\Models\M5_Pedidos;

use App\Models\M5_Pedidos\Concerns\BelongsToComercio;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Subpedido extends Model
{
    use BelongsToComercio;

    /**
     * Tabla de transiciones permitidas para subpedidos (RN-M5-13 / Decisión D2).
     *
     * @var array<string, list<string>>
     */
    public const TRANSICIONES_PERMITIDAS = [
        'pendiente' => ['pagado', 'cancelado'],
        'confirmado' => ['pagado', 'cancelado'],
        'pagado' => ['en_preparacion', 'despachado', 'cancelado'],
        'en_preparacion' => ['despachado'],
        'despachado' => ['entregado'],
        'entregado' => [],
        'cancelado' => [],
    ];

    protected $table = 'pedidos_subpedidos';

    protected $primaryKey = 'id_subpedido';

    protected $fillable = [
        'id_pedido',
        'id_comercio',
        'subtotal',
        'costo_envio',
        'impuesto',
        'descuento',
        'total',
        'estado_subpedido',
        'guia_seguimiento',
        'fecha_despacho',
    ];

    protected $casts = [
        'id_pedido' => 'integer',
        'id_comercio' => 'integer',
        'subtotal' => 'decimal:2',
        'costo_envio' => 'decimal:2',
        'impuesto' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
        'fecha_despacho' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Subpedido $subpedido) {
            $subpedido->subtotal = $subpedido->subtotal ?? 0.00;
            $subpedido->costo_envio = $subpedido->costo_envio ?? 0.00;
            $subpedido->impuesto = $subpedido->impuesto ?? 0.00;
            $subpedido->descuento = $subpedido->descuento ?? 0.00;
            $subpedido->total = round((float) ($subpedido->subtotal + $subpedido->costo_envio + $subpedido->impuesto - $subpedido->descuento), 2);
        });
    }

    // Aplicación del Patrón Composite: Nodo intermedio
    public function calcularSubtotal(): float
    {
        $subtotalLineas = 0.0;
        $totalImpuestos = 0.0;

        foreach ($this->lineas as $linea) {
            $subtotalLineas += $linea->calcularSubtotalLinea();
            $totalImpuestos += $linea->obtenerMontoImpuesto();
        }

        $this->subtotal = round($subtotalLineas, 2);
        $this->impuesto = round($totalImpuestos, 2);
        $this->save();

        return (float) ($this->subtotal + $this->impuesto + ($this->costo_envio ?? 0.0));
    }

    public static function esTransicionPermitida(string $estadoActual, string $nuevoEstado): bool
    {
        $actual = strtolower(trim($estadoActual));
        $destino = strtolower(trim($nuevoEstado));

        if ($actual === $destino) {
            return true;
        }

        $permitidos = self::TRANSICIONES_PERMITIDAS[$actual] ?? [];

        return in_array($destino, $permitidos, true);
    }

    public function cambiarEstado(string $nuevoEstado, ?string $motivo = null, ?int $usuarioId = null): void
    {
        $estadoActual = strtolower((string) ($this->estado_subpedido ?: 'pendiente'));
        $destino = strtolower(trim($nuevoEstado));

        if ($estadoActual === $destino) {
            return;
        }

        if (! self::esTransicionPermitida($estadoActual, $destino)) {
            throw new DomainException(
                "Transición de estado no permitida de '{$estadoActual}' a '{$destino}' (ERR-002).",
                409
            );
        }

        $this->estado_subpedido = $destino;
        $this->save();

        $this->registrarHistorialEstado($estadoActual, $destino, $motivo, $usuarioId);

        if ($destino === 'entregado') {
            $this->sincronizarEntregaMaestro($usuarioId);
        }
    }

    public function cancelar(string $motivo = '', bool $registrarCancelacionPropia = true): void
    {
        $estadoActual = strtolower((string) ($this->estado_subpedido ?: 'pendiente'));

        if (in_array($estadoActual, ['en_preparacion', 'despachado', 'entregado'], true)) {
            throw new DomainException(
                "No se puede cancelar un subpedido en estado '{$estadoActual}' (RN-M5-05).",
                409
            );
        }

        if ($estadoActual === 'cancelado') {
            return;
        }

        $requiereReembolso = ($estadoActual === 'pagado');
        $motivoFinal = $motivo !== '' ? $motivo : 'Cancelación de subpedido';

        $this->cambiarEstado('cancelado', $motivoFinal);

        if ($registrarCancelacionPropia && $this->id_pedido && Schema::hasTable('cancelaciones_pedido')) {
            CancelacionPedido::create([
                'id_pedido' => $this->id_pedido,
                'id_subpedido' => $this->id_subpedido,
                'motivo' => $motivoFinal,
                'requiere_reembolso' => $requiereReembolso,
                'fecha_cancelacion' => now(),
            ]);
        }
    }

    public function despachar(?string $guiaSeguimiento = null): void
    {
        $estadoActual = strtolower((string) ($this->estado_subpedido ?: 'pendiente'));

        if (in_array($estadoActual, ['despachado', 'entregado', 'cancelado'], true)) {
            throw new DomainException(
                "No se puede despachar un subpedido en estado '{$estadoActual}'.",
                409
            );
        }

        $this->estado_subpedido = 'despachado';
        if ($guiaSeguimiento !== null) {
            $this->guia_seguimiento = $guiaSeguimiento;
        }
        $this->fecha_despacho = now();
        $this->save();

        $motivo = $guiaSeguimiento ? "Despachado con guía {$guiaSeguimiento}" : 'Subpedido despachado';
        $this->registrarHistorialEstado($estadoActual, 'despachado', $motivo);
    }

    public function entregar(): void
    {
        $estadoActual = strtolower((string) ($this->estado_subpedido ?: 'pendiente'));

        if (in_array($estadoActual, ['entregado', 'cancelado'], true)) {
            throw new DomainException(
                "No se puede entregar un subpedido en estado '{$estadoActual}'.",
                409
            );
        }

        if ($estadoActual === 'despachado') {
            $this->cambiarEstado('entregado', 'Entrega confirmada');

            return;
        }

        $this->estado_subpedido = 'entregado';
        $this->save();

        $this->registrarHistorialEstado($estadoActual, 'entregado', 'Entrega confirmada');
        $this->sincronizarEntregaMaestro();
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
            'id_subpedido' => $this->id_subpedido,
            'id_evento' => (string) Str::uuid(),
            'estado_anterior' => $estadoAnterior,
            'estado' => $nuevoEstado,
            'usuario_id' => $usuarioId,
            'motivo' => $motivo,
            'fecha_estado' => now(),
        ]);
    }

    protected function sincronizarEntregaMaestro(?int $usuarioId = null): void
    {
        if (! $this->id_pedido) {
            return;
        }

        $maestro = $this->pedidoMaestro()
            ->withoutGlobalScope('cliente')
            ->with(['subpedidos' => fn ($q) => $q->withoutGlobalScope('comercio')])
            ->first();
        if (! $maestro || $maestro->subpedidos->isEmpty()) {
            return;
        }

        $todosEntregados = $maestro->subpedidos->every(
            fn (Subpedido $sub) => strtolower((string) $sub->estado_subpedido) === 'entregado'
        );

        if ($todosEntregados && strtolower((string) $maestro->estado_pedido) !== 'entregado') {
            $maestro->actualizarEstado('entregado', 'Todos los subpedidos han sido entregados', $usuarioId);
        }
    }

    public function crearSubpedidos(array $comerciosInvolucrados, ?int $idPedido = null): array
    {
        $pedidoId = $idPedido ?? $this->id_pedido;
        $creados = [];

        foreach ($comerciosInvolucrados as $comercio) {
            $comercioId = is_array($comercio) ? ($comercio['id_comercio'] ?? $comercio['id'] ?? 1) : $comercio;
            $subtotal = is_array($comercio) ? (float) ($comercio['subtotal'] ?? 0.00) : 0.00;
            $costoEnvio = is_array($comercio) ? (float) ($comercio['costo_envio'] ?? 0.00) : 0.00;
            $impuesto = is_array($comercio) ? (float) ($comercio['impuesto'] ?? 0.00) : 0.00;
            $descuento = is_array($comercio) ? (float) ($comercio['descuento'] ?? 0.00) : 0.00;
            $total = round((float) ($subtotal + $costoEnvio + $impuesto - $descuento), 2);

            $subpedido = self::create([
                'id_pedido' => $pedidoId,
                'id_comercio' => $comercioId,
                'subtotal' => $subtotal,
                'costo_envio' => $costoEnvio,
                'impuesto' => $impuesto,
                'descuento' => $descuento,
                'total' => $total,
                'estado_subpedido' => 'pendiente',
            ]);

            $subpedido->registrarHistorialEstado(null, 'pendiente', 'Creación de subpedido');
            $creados[] = $subpedido;
        }

        return $creados;
    }

    public function crearSubpedido(array $comerciosInvolucrados, ?int $idPedido = null): array
    {
        return $this->crearSubpedidos($comerciosInvolucrados, $idPedido);
    }

    public function pedidoMaestro(): BelongsTo
    {
        return $this->belongsTo(PedidoMaestro::class, 'id_pedido');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(PedidoLinea::class, 'id_subpedido');
    }

    public function estados(): HasMany
    {
        return $this->hasMany(EstadoPedido::class, 'id_subpedido');
    }

    public function cancelaciones(): HasMany
    {
        return $this->hasMany(CancelacionPedido::class, 'id_subpedido');
    }
}
