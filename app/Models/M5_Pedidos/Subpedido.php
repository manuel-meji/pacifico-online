<?php

namespace App\Models\M5_Pedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subpedido extends Model
{
    protected $table = 'pedidos_subpedidos';

    protected $primaryKey = 'id_subpedido';

    protected $fillable = [
        'id_pedido',
        'id_comercio',
        'subtotal',
        'costo_envio',
        'impuesto',
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
        'fecha_despacho' => 'datetime',
    ];

    // Aplicación del Patrón Composite: Nodo intermedio
    public function calcularSubtotal(): float
    {
        $subtotalLineas = 0.0;
        $totalImpuestos = 0.0;

        foreach ($this->lineas as $linea) {
            $subtotalLineas += $linea->calcularSubtotalLinea();
            $totalImpuestos += $linea->obtenerMontoImpuesto();
        }

        $this->subtotal = $subtotalLineas;
        $this->impuesto = $totalImpuestos;
        $this->save();

        return (float) ($this->subtotal + $this->impuesto + ($this->costo_envio ?? 0.0));
    }

    public function cambiarEstado(string $nuevoEstado): void
    {
        $this->estado_subpedido = $nuevoEstado;
        $this->save();
    }

    public function cancelar(string $motivo = ''): void
    {
        $this->estado_subpedido = 'cancelado';
        $this->save();
    }

    public function despachar(?string $guiaSeguimiento = null): void
    {
        $this->estado_subpedido = 'despachado';
        if ($guiaSeguimiento !== null) {
            $this->guia_seguimiento = $guiaSeguimiento;
        }
        $this->fecha_despacho = now();
        $this->save();
    }

    public function entregar(): void
    {
        $this->estado_subpedido = 'entregado';
        $this->save();
    }

    public function crearSubpedidos(array $comerciosInvolucrados, ?int $idPedido = null): array
    {
        $pedidoId = $idPedido ?? $this->id_pedido;
        $creados = [];

        foreach ($comerciosInvolucrados as $comercio) {
            $comercioId = is_array($comercio) ? ($comercio['id_comercio'] ?? $comercio['id'] ?? 1) : $comercio;
            $subtotal = is_array($comercio) ? ($comercio['subtotal'] ?? 0.00) : 0.00;
            $costoEnvio = is_array($comercio) ? ($comercio['costo_envio'] ?? 0.00) : 0.00;
            $impuesto = is_array($comercio) ? ($comercio['impuesto'] ?? 0.00) : 0.00;

            $creados[] = self::create([
                'id_pedido' => $pedidoId,
                'id_comercio' => $comercioId,
                'subtotal' => $subtotal,
                'costo_envio' => $costoEnvio,
                'impuesto' => $impuesto,
                'estado_subpedido' => 'pendiente',
            ]);
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
}
